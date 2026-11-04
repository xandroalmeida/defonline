<?php

declare(strict_types=1);

namespace App\Domain\Motor\Indicadores;

use App\Domain\Motor\Calculos\DreAdaptada;
use App\Domain\Motor\Farol;
use App\Domain\Motor\IndicadorResultado;
use App\Domain\Motor\MotivosIndisponibilidade;

/**
 * Indicador #9 — NCG absoluto = `Estoques + Clientes − Fornecedores` (Anexo D; espec §4.5).
 *   - Clientes = Q03; Estoques = Q04; Fornecedores = Q07.
 *
 * **Informativo — sem farol.** Decisão registrada na espec §4.5 (fechada em
 * 17/05/2026 pelo item §6.3): farol = `Farol::NENHUM` sempre que calculado.
 * A interpretação semântica entra via 3 faixas de mensagem:
 *
 *   - Faixa 1 (`NCG ≤ 0`): ciclo financiado pelo fornecedor.
 *   - Faixa 2 (NCG/Vendas até 0,10): patamar moderado, atenção a prazos.
 *   - Faixa 3 (NCG/Vendas acima de 0,10): pressão sobre o caixa.
 *
 * O limite é o mesmo do farol do NCG/Vendas (#10), lido da config, para os dois
 * indicadores não se contradizerem no relatório (PDR-014).
 *
 * **Por que a faixa 1 não fala em "folga" (revisão do PO, 06/08/2026).** A
 * redação original da STORY-060 dizia "folga operacional", e a palavra foi lida
 * — corretamente — como "sobra". Não é: não há dinheiro disponível, há dívida
 * com fornecedor maior do que o que o ciclo devolve. Dois problemas somados:
 *
 *   1. **O indicador não distingue prazo negociado de atraso.** Quem combinou
 *      60 dias com o fornecedor e quem está atrasado com ele produzem o mesmo
 *      número. A primeira situação é vantagem competitiva; a segunda é
 *      problema. Afirmar a leitura favorável escolhe uma das duas sem saber.
 *   2. **A própria EBC é mais cautelosa.** O texto do Anexo F para `ncg_vendas`
 *      verde — o mesmo cenário, no mesmo relatório — diz "**pode indicar** que
 *      a empresa não necessita de recursos para o giro". Afirmar aqui o que a
 *      fonte apenas sugere é inconsistência editorial dentro da mesma página.
 *
 * O texto atual nomeia o que o número é (financiamento do fornecedor), evita a
 * palavra "folga" e diz a dependência que ele cria. Vale para `NCG ≤ 0`
 * inclusive no zero exato — daí "não supera", e não "é menor que".
 *
 * **Quando Vendas é null ou zero** mas o NCG é calculável e positivo,
 * a referência de vendas não existe — usamos a Faixa 2 como fallback
 * conservador (mensagem moderada, não alarmista).
 *
 * **Não consome a matriz `dez-2025`.** A STORY-032 (motor 1.3.0) migrou os 13
 * indicadores com farol para `MatrizRecomendacoes::texto()`. Este indicador
 * **não** entrou na migração: o Anexo F só tem 2 cenários (positiva/negativa)
 * e a granularidade FOLGA/MODERADO/ALTO entregue pela STORY-028 é mais útil
 * ao usuário. Decisão registrada no briefing da STORY-032, reafirmada pelo
 * `PDR-006` §2 e **não reaberta** pela STORY-060.
 *
 * **De onde vem o texto das faixas (STORY-060, motor 1.3.2).** Como o indicador
 * não lê a matriz, a intenção editorial da EBC para o cenário "NCG positiva"
 * (Anexo F §F.13, coluna Indústria) não chegava ao usuário: o texto existia na
 * matriz, mas nenhuma superfície o renderizava. A STORY-060 trouxe essa
 * intenção — elevação acompanhada, **na mesma proporção**, do aumento das
 * **fontes de financiamento de longo prazo, preferencialmente de fornecedores**
 * — para as constantes MODERADO e ALTO abaixo, que são o que o produto de fato
 * exibe. A faixa FOLGA foi revisitada contra o cenário "NCG negativa" do mesmo
 * §F.13. Estas constantes ficam fora da tabela `recomendacoes` e da tela de edição:
 * o NCG absoluto não tem farol (PDR-016).
 *
 * Casos extremos (catálogo §9):
 *   - Q03 ∨ Q04 ∨ Q07 ausente → `indisponivel:ncg_componente_faltante`.
 *   - Vendas faltante NÃO bloqueia — NCG é calculado de qualquer forma.
 */
final class NcgAbsoluto implements Indicador
{
    public const MSG_FOLGA = 'Seu ciclo operacional é financiado pelos fornecedores: o que você tem a receber e em estoque não supera o que deve a eles. Enquanto os prazos combinados se mantiverem, o giro não consome dinheiro seu. Atenção ao outro lado: quanto maior essa diferença, maior a sua dependência do crédito do fornecedor — se ele encurtar o prazo, a pressão aparece de uma vez.';

    public const MSG_MODERADO = 'NCG positivo moderado: patamar gerenciável. Se ele crescer — por aumento das vendas ou do prazo concedido a clientes —, o crescimento precisa vir acompanhado, na mesma proporção, do aumento das fontes de financiamento de longo prazo, preferencialmente o prazo de pagamento a fornecedores. Acompanhe seus prazos médios (PMC, PME e PMR).';

    public const MSG_ALTO = 'NCG positivo alto: pressão sobre o caixa. Antes de ampliar vendas a prazo ou estoques, aumente na mesma proporção as fontes de financiamento de longo prazo, preferencialmente o prazo de pagamento a fornecedores. Sem isso, reveja seus prazos médios ou estruture captação de longo prazo.';

    public function chave(): string
    {
        return 'ncg_absoluto';
    }

    public function calcular(array $payload, DreAdaptada $dre): IndicadorResultado
    {
        $clientes = $dre->clientes();
        $estoques = $dre->estoques();
        $fornecedores = $dre->fornecedores();

        if ($clientes === null || $estoques === null || $fornecedores === null) {
            return IndicadorResultado::indisponivel(
                MotivosIndisponibilidade::NCG_COMPONENTE_FALTANTE,
                MotivosIndisponibilidade::mensagem(MotivosIndisponibilidade::NCG_COMPONENTE_FALTANTE),
            );
        }

        $ncg = bcsub(bcadd($clientes, $estoques, DreAdaptada::ESCALA), $fornecedores, DreAdaptada::ESCALA);
        $valor = (float) $ncg;

        $mensagem = $this->mensagemPorFaixa($ncg, $dre->vendasMensais());

        return new IndicadorResultado(
            valor: $valor,
            farol: Farol::NENHUM,
            motivo: null,
            mensagem: $mensagem,
        );
    }

    private function mensagemPorFaixa(string $ncg, ?string $vendasMensais): string
    {
        // Faixa 1: NCG ≤ 0.
        if (bccomp($ncg, '0', DreAdaptada::ESCALA) <= 0) {
            return self::MSG_FOLGA;
        }

        // Sem referência de vendas → fallback moderado.
        if ($vendasMensais === null || bccomp($vendasMensais, '0', DreAdaptada::ESCALA) <= 0) {
            return self::MSG_MODERADO;
        }

        // Faixa 3: NCG/Vendas acima do limite do farol do #10.
        $razaoLimite = (string) config('motor.faroes-industria.ncg_vendas.vermelho.valor');
        $limite = bcmul($vendasMensais, $razaoLimite, DreAdaptada::ESCALA);
        if (bccomp($ncg, $limite, DreAdaptada::ESCALA) > 0) {
            return self::MSG_ALTO;
        }

        // Faixa 2: NCG positivo até o limite.
        return self::MSG_MODERADO;
    }
}

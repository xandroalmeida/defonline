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
 *   - Faixa 1 (`NCG ≤ 0`): folga operacional — o giro não exige recursos.
 *   - Faixa 2 (`0 < NCG ≤ 10% Vendas anualizadas`): patamar moderado, atenção a prazos.
 *   - Faixa 3 (`NCG > 10% Vendas anualizadas`): pressão sobre o caixa.
 *
 * **Quando Vendas anuais é null ou zero** mas o NCG é calculável e positivo,
 * a referência "10% das Vendas" não existe — usamos a Faixa 2 como fallback
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
 * §F.13. A entrada correspondente da matriz permanece em
 * `config/motor/matriz-dez-2025-industria.php`, agora sob a chave
 * `_referencia_editorial`, explicitamente fora do que o runtime consome.
 *
 * Casos extremos (catálogo §9):
 *   - Q03 ∨ Q04 ∨ Q07 ausente → `indisponivel:ncg_componente_faltante`.
 *   - Vendas faltante NÃO bloqueia — NCG é calculado de qualquer forma.
 */
final class NcgAbsoluto implements Indicador
{
    public const MSG_FOLGA = 'Folga operacional: o prazo que você tem para pagar fornecedores cobre o ciclo de estoques e de recebimento dos clientes. Na prática, o giro do negócio não exige dinheiro seu para funcionar — preserve os prazos que produzem essa folga.';

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

        $mensagem = $this->mensagemPorFaixa($ncg, $dre->vendasAnuais());

        return new IndicadorResultado(
            valor: $valor,
            farol: Farol::NENHUM,
            motivo: null,
            mensagem: $mensagem,
        );
    }

    private function mensagemPorFaixa(string $ncg, ?string $vendasAnuais): string
    {
        // Faixa 1: NCG ≤ 0.
        if (bccomp($ncg, '0', DreAdaptada::ESCALA) <= 0) {
            return self::MSG_FOLGA;
        }

        // Sem referência de vendas → fallback moderado.
        if ($vendasAnuais === null || bccomp($vendasAnuais, '0', DreAdaptada::ESCALA) <= 0) {
            return self::MSG_MODERADO;
        }

        // Faixa 3: NCG > 10% Vendas.
        $dezPctVendas = bcmul($vendasAnuais, '0.10', DreAdaptada::ESCALA);
        if (bccomp($ncg, $dezPctVendas, DreAdaptada::ESCALA) > 0) {
            return self::MSG_ALTO;
        }

        // Faixa 2: 0 < NCG ≤ 10% Vendas.
        return self::MSG_MODERADO;
    }
}

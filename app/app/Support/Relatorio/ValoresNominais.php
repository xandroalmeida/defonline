<?php

declare(strict_types=1);

namespace App\Support\Relatorio;

use App\Actions\CalcularDiagnostico;
use App\Domain\Motor\Calculos\DreAdaptada;
use App\Domain\Motor\IndicadorResultado;

/**
 * Valores nominais (em R$) que compõem cada indicador do relatório — STORY-044.
 *
 * **Camada de apresentação.** Não calcula indicador, não classifica farol, não
 * escreve nada. Lê a coluna `diagnosticos.quiz_payload` — a outra metade do mesmo
 * snapshot imutável de IDR-010 — e devolve os termos da fórmula do Anexo D já
 * formatados em padrão BR, prontos para o Blade exibir cru.
 *
 * **Por que ler o `quiz_payload` e não o `indicadores_calculados`:** o bloco
 * `indicadores_calculados` guarda só `{valor, farol, motivo, mensagem}`
 * ({@see IndicadorResultado}) — os agregados que produziram o
 * indicador nunca foram carimbados lá. Carimbá-los agora exigiria mudar a saída
 * do motor e bumpar `motor_version`, o que a STORY-044 proíbe explicitamente
 * (CA-2 + "Fora de escopo"). O `quiz_payload` está persistido, já canonicalizado
 * no INSERT ({@see CalcularDiagnostico::execute()}) e é imutável —
 * derivar dele é leitura de snapshot, não recálculo. Registrado em IDR-013.
 *
 * **A {@see DreAdaptada} é reusada em modo leitura**, não reimplementada: ela é
 * pura (sem `now()`, sem banco, sem Auth), já faz a aritmética em `bcmath` e já
 * devolve `null` quando falta insumo. Duplicar as fórmulas aqui criaria uma
 * segunda fonte de verdade para "quanto é o EBITDA desta empresa" — que é
 * exatamente o defeito que o Key User apontou no relatório. O `Motor` **não** é
 * instanciado em nenhum ponto.
 *
 * **Resiliência (CA-8).** Todo termo faltante zera o indicador inteiro: um par
 * pela metade ("R$ 600.000,00 sobre —") mente mais do que não mostrar nada. A
 * granularidade é por indicador, então snapshot antigo incompleto perde só os
 * indicadores afetados.
 *
 * **Seis indicadores não têm valor nominal derivável** e devolvem `[]` por
 * decisão, não por lacuna de implementação: `pmc`/`pme`/`pmr` são declarados em
 * dias (Q10/Q11/Q12), `ciclo_financeiro`/`ciclo_operacional` são somas desses
 * dias, e `inadimplencia` é um percentual declarado (Q13) cuja base — vendas a
 * prazo — o quiz não captura em reais. Nenhum deles ganharia nominal com
 * mudança de motor; exigiria pergunta nova no quiz. Ver "Notas do agente" da
 * STORY-044.
 */
final class ValoresNominais
{
    /**
     * Indicadores que expõem valores nominais. Lista explícita (não derivada)
     * para que um indicador novo entre aqui por decisão consciente, e não
     * silenciosamente sem nominal — travado por teste de contrato.
     *
     * @var list<string>
     */
    public const CODIGOS_COM_NOMINAL = [
        'margem_bruta',
        'margem_ebitda',
        'margem_liquida',
        'divida_liquida_ebitda',
        'despesas_fin_ebitda',
        'fontes_recursos',
        'giro_ativo',
        'ncg_vendas',
        'ncg_absoluto',
    ];

    private function __construct(private readonly DreAdaptada $dre) {}

    /**
     * @param  array<string, mixed>  $quizPayload  `diagnosticos.quiz_payload` como persistido
     *                                             (já canonicalizado — nada é recanonicalizado aqui).
     */
    public static function doSnapshot(array $quizPayload): self
    {
        return new self(new DreAdaptada($quizPayload));
    }

    /**
     * Termos nominais do indicador, na ordem numerador → denominador (a mesma
     * da fórmula do Anexo D), com o valor já em padrão monetário BR.
     *
     * Lista vazia quando o indicador não tem nominal aplicável ou quando algum
     * termo não é derivável do snapshot.
     *
     * @return list<array{rotulo: string, valor: string}>
     */
    public function paraIndicador(string $codigo): array
    {
        $termos = match ($codigo) {
            'margem_bruta' => [
                'Lucro bruto (12 meses)' => $this->dre->lucroBruto(),
                'Vendas (12 meses)' => $this->dre->vendasAnuais(),
            ],
            'margem_ebitda' => [
                'EBITDA (12 meses)' => $this->dre->ebitda(),
                'Vendas (12 meses)' => $this->dre->vendasAnuais(),
            ],
            'margem_liquida' => [
                'Resultado líquido (12 meses)' => $this->dre->lucroOperacionalLiquido(),
                'Vendas (12 meses)' => $this->dre->vendasAnuais(),
            ],
            'divida_liquida_ebitda' => [
                'Dívida líquida' => $this->dividaLiquida(),
                'EBITDA (12 meses)' => $this->dre->ebitda(),
            ],
            'despesas_fin_ebitda' => [
                'Despesas financeiras (12 meses)' => $this->dre->despesasFinanceirasAnuais(),
                'EBITDA (12 meses)' => $this->dre->ebitda(),
            ],
            'fontes_recursos' => [
                'Passivo circulante' => $this->dre->passivoCirculante(),
                'Patrimônio líquido' => $this->dre->patrimonioLiquido(),
            ],
            'giro_ativo' => [
                'Vendas (12 meses)' => $this->dre->vendasAnuais(),
                'Ativo total' => $this->dre->ativoTotal(),
            ],
            'ncg_vendas' => [
                'NCG' => $this->ncg(),
                'Vendas (12 meses)' => $this->dre->vendasAnuais(),
            ],
            'ncg_absoluto' => [
                'Contas a receber' => $this->dre->clientes(),
                'Estoque' => $this->dre->estoques(),
                'Fornecedores a pagar' => $this->dre->fornecedores(),
            ],
            default => [],
        };

        $formatados = [];
        foreach ($termos as $rotulo => $valor) {
            if ($valor === null) {
                // Par incompleto não é exibido pela metade (CA-3 / CA-8).
                return [];
            }
            $formatados[] = ['rotulo' => $rotulo, 'valor' => IndicadorFormatter::moeda((float) $valor)];
        }

        return $formatados;
    }

    /** Dívida líquida = dívidas financeiras (Q06) − disponibilidades (Q02). */
    private function dividaLiquida(): ?string
    {
        $pcf = $this->dre->dividasFinanceiras();
        $acf = $this->dre->disponibilidades();
        if ($pcf === null || $acf === null) {
            return null;
        }

        return bcsub($pcf, $acf, DreAdaptada::ESCALA);
    }

    /** NCG = clientes (Q03) + estoques (Q04) − fornecedores (Q07). */
    private function ncg(): ?string
    {
        $clientes = $this->dre->clientes();
        $estoques = $this->dre->estoques();
        $fornecedores = $this->dre->fornecedores();
        if ($clientes === null || $estoques === null || $fornecedores === null) {
            return null;
        }

        return bcsub(bcadd($clientes, $estoques, DreAdaptada::ESCALA), $fornecedores, DreAdaptada::ESCALA);
    }
}

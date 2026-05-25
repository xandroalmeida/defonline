<?php

declare(strict_types=1);

namespace App\Domain\Motor;

use App\Domain\Motor\Calculos\DreAdaptada;
use App\Domain\Motor\Indicadores\CicloFinanceiro;
use App\Domain\Motor\Indicadores\CicloOperacional;
use App\Domain\Motor\Indicadores\DespesasFinEbitda;
use App\Domain\Motor\Indicadores\DividaLiquidaEbitda;
use App\Domain\Motor\Indicadores\FontesRecursos;
use App\Domain\Motor\Indicadores\GiroAtivo;
use App\Domain\Motor\Indicadores\Inadimplencia;
use App\Domain\Motor\Indicadores\Indicador;
use App\Domain\Motor\Indicadores\MargemBruta;
use App\Domain\Motor\Indicadores\MargemEbitda;
use App\Domain\Motor\Indicadores\MargemLiquida;
use App\Domain\Motor\Indicadores\NcgAbsoluto;
use App\Domain\Motor\Indicadores\NcgVendas;
use App\Domain\Motor\Indicadores\Pmc;
use App\Domain\Motor\Indicadores\Pme;
use App\Domain\Motor\Indicadores\Pmr;

/**
 * Orquestrador do motor de cálculo (STORY-031 — `motor_version = 1.2.0`).
 *
 * Responsabilidades:
 *   1. Canonicaliza o `quiz_payload` (IDR-010 §sub-decisão 3).
 *   2. Constrói a {@see DreAdaptada} a partir do payload canonicalizado.
 *   3. Itera a **lista explícita** de indicadores na ordem fixa abaixo e
 *      monta `indicadores_calculados` preservando ordem (importante para hash).
 *   4. Estampa `motor_version` e `matrix_version` no resultado, a partir do
 *      `config('motor.*')`.
 *   5. Gera o `resumo_executivo` determinístico via {@see ResumoExecutivo}.
 *
 * **Pureza.** Sem `now()`, sem leitura de banco, sem Auth (IDR-010 §sub-decisão 4
 * — fontes de não-determinismo proibidas). O `gerado_em` é definido pelo Action
 * `CalcularDiagnostico`, não aqui.
 *
 * **Ordem dos indicadores** = ordem do Anexo D §4.5 (#1..#14) + Ciclo Operacional
 * informativo no final. Esta ordem entra no hash de saída — mudança requer bump.
 */
final class Motor
{
    /**
     * @param  array<string, mixed>  $payload  quiz_payload bruto (será canonicalizado aqui).
     * @param  string  $setor  V1/V2 aceita SOMENTE 'industria'. Outros setores entram em estória futura
     *                         do EPIC-002+ (cada um traz seu classificador de farol próprio).
     * @return array{
     *     motor_version: string,
     *     matrix_version: string,
     *     setor: string,
     *     indicadores_calculados: array<string, array{valor: float|int|null, farol: string, motivo: ?string, mensagem: string}>,
     *     resumo_executivo: array<string, mixed>
     * }
     *
     * @throws \InvalidArgumentException se `$setor` não é suportado nesta versão do motor.
     */
    public function calcular(array $payload, string $setor): array
    {
        if ($setor !== 'industria') {
            throw new \InvalidArgumentException(
                "Setor '{$setor}' não suportado pelo motor (1.2.0). Esta versão atende apenas Indústria; ".
                'Comércio/Serviços entram em estória posterior do EPIC-002.',
            );
        }

        $canonical = QuizPayloadCanonicalizer::canonicalize($payload);
        $dre = new DreAdaptada($canonical);

        $indicadoresCalculados = [];
        foreach (self::indicadores() as $indicador) {
            $indicadoresCalculados[$indicador->chave()] = $indicador->calcular($canonical, $dre)->toArray();
        }

        $motorVersion = (string) config('motor.version');
        $resumoExecutivo = (new ResumoExecutivo)->gerar($indicadoresCalculados, $motorVersion);

        return [
            'motor_version' => $motorVersion,
            'matrix_version' => (string) config('motor.matrix_version'),
            'setor' => $setor,
            'indicadores_calculados' => $indicadoresCalculados,
            'resumo_executivo' => $resumoExecutivo,
        ];
    }

    /**
     * Lista canônica dos indicadores, na ordem do Anexo D §4.5 (#1..#14)
     * com Ciclo Operacional informativo ao final.
     *
     * @return list<Indicador>
     */
    private static function indicadores(): array
    {
        return [
            new MargemBruta,           // #1
            new MargemEbitda,          // #2  (STORY-030)
            new MargemLiquida,         // #3
            new DividaLiquidaEbitda,   // #4
            new DespesasFinEbitda,     // #5  (STORY-030)
            new FontesRecursos,        // #6  (STORY-030)
            new GiroAtivo,             // #7  (STORY-030)
            new CicloFinanceiro,       // #8
            new NcgAbsoluto,           // #9  (informativo, sem farol)
            new NcgVendas,             // #10
            new Pmc,                   // #11
            new Pme,                   // #12 (STORY-030)
            new Pmr,                   // #13
            new Inadimplencia,         // #14 (STORY-030)
            new CicloOperacional,      // (+) informativo, sem farol (STORY-030)
        ];
    }
}

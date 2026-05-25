<?php

declare(strict_types=1);

namespace App\Domain\Motor;

use App\Domain\Motor\Calculos\DreAdaptada;
use App\Domain\Motor\Indicadores\CicloFinanceiro;
use App\Domain\Motor\Indicadores\DividaLiquidaEbitda;
use App\Domain\Motor\Indicadores\Indicador;
use App\Domain\Motor\Indicadores\MargemBruta;
use App\Domain\Motor\Indicadores\MargemLiquida;
use App\Domain\Motor\Indicadores\NcgAbsoluto;
use App\Domain\Motor\Indicadores\NcgVendas;
use App\Domain\Motor\Indicadores\Pmc;
use App\Domain\Motor\Indicadores\Pmr;

/**
 * Orquestrador do motor de cálculo V1 (STORY-028 — `motor_version = 1.0.0`).
 *
 * Responsabilidades:
 *   1. Canonicaliza o `quiz_payload` (IDR-010 §sub-decisão 3).
 *   2. Constrói a {@see DreAdaptada} a partir do payload canonicalizado.
 *   3. Itera a **lista explícita** de indicadores na ordem fixa abaixo e
 *      monta `indicadores_calculados` preservando ordem (importante para hash).
 *   4. Estampa `motor_version` e `matrix_version` no resultado, a partir do
 *      `config('motor.*')`.
 *
 * **Pureza.** Sem `now()`, sem leitura de banco, sem Auth (IDR-010 §sub-decisão 4
 * — fontes de não-determinismo proibidas). O `gerado_em` é definido pelo Action
 * `CalcularDiagnostico`, não aqui.
 *
 * **Ordem dos indicadores** = ordem de definição dos essenciais na STORY-028 +
 * NCG absoluto por último (informativo). Esta ordem entra no hash de saída.
 *
 * **Resumo Executivo** é placeholder nesta V1 — STORY-031 substitui pelo
 * algoritmo determinístico §4.7.1. O placeholder mantém o snapshot
 * sintaticamente válido (campo NOT NULL no banco).
 */
final class Motor
{
    /**
     * @param  array<string, mixed>  $payload  quiz_payload bruto (será canonicalizado aqui).
     * @param  string  $setor  V1 aceita SOMENTE 'industria'. Outros setores entram em estória futura
     *                         do EPIC-002+ (cada um traz seu classificador de farol próprio).
     * @return array{
     *     motor_version: string,
     *     matrix_version: string,
     *     setor: string,
     *     indicadores_calculados: array<string, array{valor: float|int|null, farol: string, motivo: ?string, mensagem: string}>,
     *     resumo_executivo: array{pendente_story: string, fallback_acionado: bool}
     * }
     *
     * @throws \InvalidArgumentException se `$setor` não é suportado nesta versão do motor.
     */
    public function calcular(array $payload, string $setor): array
    {
        if ($setor !== 'industria') {
            throw new \InvalidArgumentException(
                "Setor '{$setor}' não suportado pelo motor V1 (1.0.0). Esta versão atende apenas Indústria; ".
                'Comércio/Serviços entram em estória posterior do EPIC-002.',
            );
        }

        $canonical = QuizPayloadCanonicalizer::canonicalize($payload);
        $dre = new DreAdaptada($canonical);

        $indicadoresCalculados = [];
        foreach (self::indicadores() as $indicador) {
            $indicadoresCalculados[$indicador->chave()] = $indicador->calcular($canonical, $dre)->toArray();
        }

        return [
            'motor_version' => (string) config('motor.version'),
            'matrix_version' => (string) config('motor.matrix_version'),
            'setor' => $setor,
            'indicadores_calculados' => $indicadoresCalculados,
            'resumo_executivo' => [
                'pendente_story' => 'STORY-031',
                'fallback_acionado' => false,
            ],
        ];
    }

    /**
     * Lista canônica dos indicadores V1, na ordem que entra no snapshot.
     *
     * @return list<Indicador>
     */
    private static function indicadores(): array
    {
        return [
            new MargemBruta,
            new MargemLiquida,
            new DividaLiquidaEbitda,
            new NcgVendas,
            new Pmr,
            new Pmc,
            new CicloFinanceiro,
            new NcgAbsoluto,
        ];
    }
}

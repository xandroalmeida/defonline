<?php

declare(strict_types=1);

namespace App\Domain\Cobranca;

use Carbon\CarbonInterface;

/**
 * STORY-091 CA-3 — a regra do upgrade para a cota do ciclo em curso. **Decisão do PO em 2026-11-05 (D-05)**
 * (antes provisória). Este é o ÚNICO ponto que a implementa; trocar a regra é trocar este método.
 *
 * No upgrade, a cota extra do ciclo em curso é proporcional ao tempo que falta para a cota voltar cheia,
 * com arredondamento para baixo. Nunca é negativa, então a cota já disponível no plano antigo nunca diminui.
 * A cota cheia do plano novo vale a partir da próxima renovação (`cota_snapshot`).
 */
final class CotaExtraNoUpgrade
{
    /**
     * @param  int  $cotaAntiga  a cota contratada hoje (retrato)
     * @param  int  $cotaNova  a cota do plano de destino
     * @param  CarbonInterface  $inicioDaJanela  quando a cota em curso começou a valer
     * @param  CarbonInterface  $fimDaJanela  quando ela volta cheia (renovação do mês, ou aniversário no anual)
     */
    public static function calcular(int $cotaAntiga, int $cotaNova, CarbonInterface $inicioDaJanela, CarbonInterface $fimDaJanela, CarbonInterface $agora): int
    {
        $diferenca = max(0, $cotaNova - $cotaAntiga);
        $total = max(1, $fimDaJanela->getTimestamp() - $inicioDaJanela->getTimestamp());
        $restante = max(0, min($total, $fimDaJanela->getTimestamp() - $agora->getTimestamp()));

        return intdiv($diferenca * $restante, $total);
    }
}

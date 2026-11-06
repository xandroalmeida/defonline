<?php

declare(strict_types=1);

namespace App\Domain\Cobranca;

use App\Support\DataExibicao;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * STORY-077 CA-6 — dia de aniversário da cota. A cota volta cheia todo mês no dia em que a
 * assinatura começou; num mês sem esse dia, vale o último dia do mês. Cada aniversário é calculado
 * a partir do início (`inicio + n meses`), então o dia 31 volta ao 31 nos meses que o têm.
 */
final class CotaMensal
{
    /** O próximo aniversário depois de `$agora`, ou nulo se cair no fim (ou depois) do período pago. */
    public static function proxima(CarbonInterface $inicio, CarbonInterface $agora, CarbonInterface $fimDoPeriodo): ?CarbonImmutable
    {
        $n = 1;

        while (CarbonImmutable::instance($inicio)->addMonthsNoOverflow($n)->lessThanOrEqualTo($agora)) {
            $n++;
        }

        $proxima = CarbonImmutable::instance($inicio)->addMonthsNoOverflow($n);

        // O período vai até o último segundo do dia do vencimento; o aniversário nesse dia já é do ciclo seguinte.
        return $proxima->lessThan(DataExibicao::fimDoDia($fimDoPeriodo)->startOfDay()) ? $proxima : null;
    }
}

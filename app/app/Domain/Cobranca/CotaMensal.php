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

    /**
     * A janela da cota em curso: do último aniversário (ou do início) até o próximo, limitado ao fim do
     * período pago. É dela que o upgrade tira a proporção da cota extra (STORY-091).
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public static function janela(CarbonInterface $inicio, CarbonInterface $agora, CarbonInterface $fimDoPeriodo): array
    {
        // Como em `proxima()`: o aniversário que cai no ÚLTIMO dia do período já é da renovação, não de uma janela de
        // horas. Sem isto, a data só com o dia do provedor (meia-noite de Brasília) fazia o upgrade do último dia
        // ratear a cota por 24 h em vez de por mês (STORY-093).
        $ultimoDia = DataExibicao::fimDoDia($fimDoPeriodo)->startOfDay();
        $aniversario = fn (int $n): CarbonImmutable => CarbonImmutable::instance($inicio)->addMonthsNoOverflow($n);
        $n = 0;

        while ($aniversario($n + 1)->lessThanOrEqualTo($agora) && $aniversario($n + 1)->lessThan($ultimoDia)) {
            $n++;
        }

        $fim = $aniversario($n + 1);
        $limite = CarbonImmutable::instance($fimDoPeriodo);

        // O fim do período entra como o NOMINAL (como o preço proporcional, ver `fimNominal()`), não com as horas a mais.
        return [$aniversario($n), $fim->greaterThanOrEqualTo($ultimoDia) || $fim->greaterThan($limite) ? self::fimNominal($limite, $inicio) : $fim];
    }

    /**
     * O vencimento gravado vai até o último segundo do dia (STORY-089), mas o ciclo pago tem o tamanho de
     * "início + n meses": a proporção do preço e da cota usa esse fim nominal, o mesmo dia no mesmo horário do
     * início, e não ganha horas só porque o acesso dura o dia inteiro.
     */
    public static function fimNominal(CarbonImmutable $fim, CarbonInterface $inicio): CarbonImmutable
    {
        if (! $fim->equalTo(DataExibicao::fimDoDia($fim))) {
            return $fim;
        }

        $horaDoInicio = DataExibicao::converter($inicio);

        return DataExibicao::converter($fim)->setTime($horaDoInicio->hour, $horaDoInicio->minute, $horaDoInicio->second);
    }
}

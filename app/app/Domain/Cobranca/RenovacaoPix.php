<?php

declare(strict_types=1);

namespace App\Domain\Cobranca;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * STORY-080 — regras de tempo do plano anual pago com Pix, que não renova sozinho. A renovação é um novo
 * Checkout Pix pago pelo usuário; ela estende o plano A PARTIR DO FIM do ciclo atual (sem perder dias),
 * e só é oferecida nos últimos {@see AVISO_DIAS} dias. Sem pagamento até o fim, o acesso acaba (sem
 * retentativa: nada falhou).
 */
final class RenovacaoPix
{
    public const AVISO_DIAS = 7;

    /** Início da janela em que o aviso sai e a renovação pode ser paga. */
    public static function abreEm(CarbonInterface $vencimento): CarbonImmutable
    {
        return CarbonImmutable::instance($vencimento)->subDays(self::AVISO_DIAS);
    }

    /** Dentro dos últimos 7 dias do ciclo e antes do fim. */
    public static function janelaAberta(CarbonInterface $vencimento, CarbonInterface $agora): bool
    {
        return $agora->greaterThanOrEqualTo(self::abreEm($vencimento)) && $agora->lessThan($vencimento);
    }

    /** O novo fim: mais um ano contado do INÍCIO da assinatura (sem deriva nem perda de dias). */
    public static function novoVencimento(CarbonInterface $inicio, int $periodosPagos): CarbonImmutable
    {
        return CicloCobranca::Anual->vencimento($inicio, $periodosPagos + 1);
    }

    /** O aviso deste ciclo ainda não saiu e a janela já abriu? */
    public static function avisoDevido(CarbonInterface $vencimento, ?CarbonInterface $avisadoPara, CarbonInterface $agora): bool
    {
        return self::janelaAberta($vencimento, $agora)
            && ($avisadoPara === null || ! $avisadoPara->equalTo($vencimento));
    }
}

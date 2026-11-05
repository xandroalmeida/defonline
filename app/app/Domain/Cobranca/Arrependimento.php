<?php

declare(strict_types=1);

namespace App\Domain\Cobranca;

use Carbon\CarbonImmutable;

/**
 * STORY-081 CA-6 / espec §2.4 — direito de arrependimento: 7 dias corridos da contratação e nenhuma
 * análise feita com a cota da assinatura. O pedido é por e-mail ({@see CANAL}); a tela do admin que reembolsa é da STORY-085.
 */
final class Arrependimento
{
    public const DIAS = 7;

    /** Canal provisório definido pelo PO (STORY-081): não existe botão de reembolso. */
    public const CANAL = 'eb@ebparcerias.com';

    /** Os 7 dias corridos ainda não acabaram (STORY-085 CA-3: a tela mostra isto separado de "fez análise"). */
    public static function noPrazo(CarbonImmutable $contratadaEm, CarbonImmutable $agora): bool
    {
        return ! $agora->lessThan($contratadaEm) && ! $agora->greaterThan($contratadaEm->addDays(self::DIAS));
    }

    /** Quantos dias se passaram desde a contratação, se ainda cabe o arrependimento; senão `null`. */
    public static function diasDesde(CarbonImmutable $contratadaEm, CarbonImmutable $agora, bool $fezAnalise): ?int
    {
        if ($fezAnalise || ! self::noPrazo($contratadaEm, $agora)) {
            return null;
        }

        return (int) $contratadaEm->diffInDays($agora, true);
    }
}

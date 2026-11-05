<?php

declare(strict_types=1);

namespace App\Domain\Cobranca;

use Carbon\CarbonImmutable;

/**
 * STORY-081 CA-6 / espec §2.4 — direito de arrependimento: 7 dias corridos da contratação e nenhuma
 * análise feita com a cota da assinatura. O pedido é por e-mail ({@see CANAL}); reembolsar é da STORY-085.
 */
final class Arrependimento
{
    public const DIAS = 7;

    /** Canal provisório definido pelo PO (STORY-081): não existe botão de reembolso. */
    public const CANAL = 'eb@ebparcerias.com';

    /** Quantos dias se passaram desde a contratação, se ainda cabe o arrependimento; senão `null`. */
    public static function diasDesde(CarbonImmutable $contratadaEm, CarbonImmutable $agora, bool $fezAnalise): ?int
    {
        if ($fezAnalise || $agora->lessThan($contratadaEm) || $agora->greaterThan($contratadaEm->addDays(self::DIAS))) {
            return null;
        }

        return (int) $contratadaEm->diffInDays($agora, true);
    }
}

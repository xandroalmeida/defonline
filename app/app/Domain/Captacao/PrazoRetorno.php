<?php

declare(strict_types=1);

namespace App\Domain\Captacao;

use Carbon\CarbonImmutable;

/**
 * STORY-102 / PDR-021 — a equipe retorna a análise da demanda de captação em até 5 dias úteis.
 * Dia útil aqui é segunda a sexta, no fuso de exibição; feriados não entram (o prazo serve para a
 * equipe se organizar no backoffice, o cliente só vê "5 dias úteis").
 */
final class PrazoRetorno
{
    public const DIAS_UTEIS = 5;

    public static function aPartirDe(CarbonImmutable $registro): CarbonImmutable
    {
        return $registro->setTimezone((string) config('app.display_timezone'))
            ->startOfDay()
            ->addWeekdays(self::DIAS_UTEIS);
    }
}

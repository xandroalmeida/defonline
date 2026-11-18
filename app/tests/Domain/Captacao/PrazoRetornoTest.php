<?php

declare(strict_types=1);

use App\Domain\Captacao\PrazoRetorno;
use Carbon\CarbonImmutable;

/** STORY-102 / PDR-021 — o retorno da demanda de captação é em até 5 dias úteis (segunda a sexta). */
it('conta cinco dias úteis a partir do dia do registro, pulando o fim de semana', function (string $registroUtc, string $esperado) {
    expect(PrazoRetorno::aPartirDe(CarbonImmutable::parse($registroUtc, 'UTC'))->format('Y-m-d'))->toBe($esperado);
})->with([
    'segunda → segunda seguinte' => ['2026-11-16 15:00:00', '2026-11-23'],
    'sexta → sexta seguinte' => ['2026-11-20 15:00:00', '2026-11-27'],
    'sábado → sexta seguinte' => ['2026-11-21 15:00:00', '2026-11-27'],
    'domingo → sexta seguinte' => ['2026-11-22 15:00:00', '2026-11-27'],
]);

it('usa o dia de Brasília: 23h de segunda em Brasília ainda é segunda', function () {
    // 02:00 UTC de terça = 23:00 de segunda em Brasília.
    expect(PrazoRetorno::aPartirDe(CarbonImmutable::parse('2026-11-17 02:00:00', 'UTC'))->format('Y-m-d'))->toBe('2026-11-23');
});

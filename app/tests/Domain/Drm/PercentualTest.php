<?php

declare(strict_types=1);

use App\Domain\Drm\Percentual;

/*
|-------------------------------------------------------------------------
| STORY-048 CA-2 e CA-8 — percentual em aritmética inteira.
|-------------------------------------------------------------------------
*/

it('calcula parte / base × 100, truncado em quatro casas', function (int $parte, int $base, ?string $esperado) {
    expect(Percentual::deCentavos($parte, $base))->toBe($esperado);
})->with([
    'um terço' => [1, 3, '33.3333'],
    'dois terços truncam, não arredondam' => [2, 3, '66.6666'],
    'inteiro' => [50, 100, '50.0000'],
    'cem por cento' => [100, 100, '100.0000'],
    'acima da base' => [250, 100, '250.0000'],
    'zero' => [0, 100, '0.0000'],
    'negativo' => [-1, 3, '-33.3333'],
    'negativo exato' => [-25, 100, '-25.0000'],
    'menor que a casa mínima vira zero sem sinal' => [-1, 100_000_000, '0.0000'],
    'base zero é indisponível' => [10, 0, null],
    'base negativa é indisponível' => [10, -5, null],
]);

it('não estoura perto do teto de 64 bits', function () {
    $teto = 3_600_000_000_000; // 36 campos no máximo de 999.999.999,99

    expect(Percentual::deCentavos($teto, $teto))->toBe('100.0000')
        ->and(Percentual::deCentavos(-$teto, 99_999_999_999))->toBe('-3600.0000');
});

it('formata com uma casa, meio para cima em módulo, vírgula decimal', function (string $entrada, string $esperado) {
    expect(Percentual::formatar($entrada))->toBe($esperado);
})->with([
    'abaixo do meio' => ['13.3499', '13,3'],
    'no meio sobe' => ['13.3500', '13,4'],
    'acima do meio' => ['13.3501', '13,4'],
    'cem' => ['100.0000', '100,0'],
    'virada de dezena' => ['9.9500', '10,0'],
    'zero' => ['0.0000', '0,0'],
    'negativo' => ['-2.5500', '-2,6'],
    'negativo abaixo do meio' => ['-2.5499', '-2,5'],
    'negativo que arredonda para zero perde o sinal' => ['-0.0400', '0,0'],
    'milhar' => ['1234.5678', '1234,6'],
]);

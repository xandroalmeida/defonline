<?php

declare(strict_types=1);

use App\Support\Drm\PlanoDeFormulas;

/*
 * STORY-055 CA-4 — o plano de fórmulas lê só nível e tipo. Uma fórmula só vale se reproduz o snapshot.
 */

function linhaDoPlano(string $codigo, int $nivel, string $tipo): object
{
    return (object) ['codigo' => $codigo, 'nivel' => $nivel, 'tipo' => $tipo];
}

function estruturaFora(): array
{
    return [
        linhaDoPlano('L1', 1, 'item'), linhaDoPlano('L2', 1, 'deducao'), linhaDoPlano('L3', 1, 'total'),
        linhaDoPlano('L4', 1, 'deducao'), linhaDoPlano('L5', 1, 'total'),
        linhaDoPlano('L6', 1, 'deducao'), linhaDoPlano('L6a', 2, 'subtotal'), linhaDoPlano('L6b', 2, 'subtotal'),
        linhaDoPlano('L7', 1, 'total'), linhaDoPlano('L7b', 1, 'deducao'), linhaDoPlano('L8', 1, 'deducao'),
        linhaDoPlano('L9', 1, 'total'), linhaDoPlano('L10', 1, 'deducao'), linhaDoPlano('L11', 1, 'total'),
    ];
}

it('monta as fórmulas dos totais a partir de nível e tipo, com a linha aditiva somando', function () {
    $plano = PlanoDeFormulas::de(estruturaFora(), ['L7b']);

    expect($plano[2])->toBe([[1, 0], [-1, 1]])                 // L3 = L1 − L2
        ->and($plano[4])->toBe([[1, 2], [-1, 3]])               // L5 = L3 − L4
        ->and($plano[5])->toBe([[1, 6], [1, 7]])                // L6 = L6a + L6b
        ->and($plano[8])->toBe([[1, 4], [-1, 5]])               // L7 = L5 − L6
        ->and($plano[11])->toBe([[1, 8], [1, 9], [-1, 10]])     // L9 = L7 + L7b − L8
        ->and($plano[13])->toBe([[1, 11], [-1, 12]])            // L11 = L9 − L10
        ->and($plano)->not->toHaveKeys([0, 1, 3, 6, 7, 9, 10, 12]);
});

it('sem linha-base antes do total, não há fórmula; dedução sem filhas também não', function () {
    expect(PlanoDeFormulas::de([linhaDoPlano('X', 1, 'total'), linhaDoPlano('Y', 1, 'deducao')], []))->toBe([]);
});

it('confere: soma exata, e operando nulo nunca vira zero', function () {
    $termos = [[1, 0], [-1, 1]];

    expect(PlanoDeFormulas::confere($termos, 2, [0 => 10000, 1 => 600, 2 => 9400]))->toBeTrue()
        ->and(PlanoDeFormulas::confere($termos, 2, [0 => 10000, 1 => 600, 2 => 9000]))->toBeFalse()
        ->and(PlanoDeFormulas::confere($termos, 2, [0 => 10000, 1 => null, 2 => 10000]))->toBeFalse()
        ->and(PlanoDeFormulas::confere($termos, 2, [0 => 10000, 1 => 600, 2 => null]))->toBeFalse()
        ->and(PlanoDeFormulas::confere($termos, 2, [0 => 10000, 2 => 9400]))->toBeFalse();
});

<?php

declare(strict_types=1);

use App\Domain\Motor\Calculos\DreAdaptada;
use App\Domain\Motor\Indicadores\FontesRecursos;

function calcFontesRecursos(array $payload): array
{
    return (new FontesRecursos)->calcular($payload, new DreAdaptada($payload))->toArray();
}

function pBaseFr(array $overrides = []): array
{
    // AT = 1.5M (Q02 100k + Q03 100k + Q04 300k + Q05 1M).
    // PC = Q06 + Q07. Q07 fixo em 100k.
    return array_merge([
        'Q02' => '100000',
        'Q03' => '100000',
        'Q04' => '300000',
        'Q05' => '1000000',
        'Q06' => '200000',  // dívidas → PC = 300k → PL = 1.2M → Fontes = 0.25 (verde)
        'Q07' => '100000',  // fornecedores
    ], $overrides);
}

// Anexo E (Indústria) Fontes de Recursos: ≤ 0.5 verde · 0.501–1 amarelo · > 1 vermelho.

it('verde — Fontes 0.25 (típico saudável)', function () {
    $r = calcFontesRecursos(pBaseFr());
    expect($r['valor'])->toBe(0.25)->and($r['farol'])->toBe('verde');
});

it('verde — fronteira 0.5 (≤ 0.5)', function () {
    // PC=500k → PL=1M → 0.5. Q06=400k.
    $r = calcFontesRecursos(pBaseFr(['Q06' => '400000']));
    expect($r['valor'])->toBe(0.5)->and($r['farol'])->toBe('verde');
});

it('amarelo — fronteira logo acima de 0.5 (> 0.5)', function () {
    // Delta precisa survival após truncamento bcmath escala 4.
    // PC=500100 → PL=999900 → 0.500150... → 0.5001 (amarelo).
    $r = calcFontesRecursos(pBaseFr(['Q06' => '400100']));
    expect($r['valor'])->toBeGreaterThan(0.5);
    expect($r['farol'])->toBe('amarelo');
});

it('amarelo — típico 0.6667 (PC = 600k, PL = 900k)', function () {
    // Q06 = 500k → PC = 600k → PL = 900k → 0.6666...
    $r = calcFontesRecursos(pBaseFr(['Q06' => '500000']));
    expect($r['valor'])->toBeGreaterThan(0.6);
    expect($r['valor'])->toBeLessThan(0.7);
    expect($r['farol'])->toBe('amarelo');
});

it('amarelo — fronteira superior 1.0', function () {
    // Q06 = 650k → PC = 750k → PL = 750k → 1.0.
    $r = calcFontesRecursos(pBaseFr(['Q06' => '650000']));
    expect($r['valor'])->toBe(1.0)->and($r['farol'])->toBe('amarelo');
});

it('vermelho — fronteira logo acima de 1.0', function () {
    // Delta precisa survival após truncamento bcmath escala 4.
    // Q06=650100 → PC=750100 → PL=749900 → ~1.000267 → 1.0002 (vermelho).
    $r = calcFontesRecursos(pBaseFr(['Q06' => '650100']));
    expect($r['valor'])->toBeGreaterThan(1.0);
    expect($r['farol'])->toBe('vermelho');
});

it('vermelho — típico 2.0 (PC = 1M, PL = 500k)', function () {
    // Q06 = 900k → PC = 1M → PL = 500k → 2.0.
    $r = calcFontesRecursos(pBaseFr(['Q06' => '900000']));
    expect($r['valor'])->toBe(2.0)->and($r['farol'])->toBe('vermelho');
});

it('vermelho — extremo 5.0 (PC = 1.25M, PL = 250k)', function () {
    // Q06 = 1.15M → PC = 1.25M → PL = 250k → 5.0.
    $r = calcFontesRecursos(pBaseFr(['Q06' => '1150000']));
    expect($r['valor'])->toBe(5.0)->and($r['farol'])->toBe('vermelho');
});

// ---------- casos extremos ----------

it('indisponível — PL = 0 (PC = AT)', function () {
    // Q06 = 1.4M → PC = 1.5M = AT → PL = 0.
    $r = calcFontesRecursos(pBaseFr(['Q06' => '1400000']));
    expect($r['valor'])->toBeNull()->and($r['motivo'])->toBe('indisponivel:pl_nao_positivo');
});

it('indisponível — PL < 0 (PC > AT, passivo maior que ativo)', function () {
    // Q06 = 1.5M → PC = 1.6M → PL = −100k.
    $r = calcFontesRecursos(pBaseFr(['Q06' => '1500000']));
    expect($r['valor'])->toBeNull()->and($r['motivo'])->toBe('indisponivel:pl_nao_positivo');
});

it('indisponível — Q02 ausente (componente do AT)', function () {
    $r = calcFontesRecursos(pBaseFr(['Q02' => null]));
    expect($r['valor'])->toBeNull()->and($r['motivo'])->toBe('indisponivel:ativo_componente_faltante');
});

it('indisponível — Q05 ausente (componente do AT)', function () {
    $r = calcFontesRecursos(pBaseFr(['Q05' => null]));
    expect($r['valor'])->toBeNull()->and($r['motivo'])->toBe('indisponivel:ativo_componente_faltante');
});

it('indisponível — Q06 ausente (componente do PC)', function () {
    $r = calcFontesRecursos(pBaseFr(['Q06' => null]));
    expect($r['valor'])->toBeNull()->and($r['motivo'])->toBe('indisponivel:divida_componente_faltante');
});

it('indisponível — Q07 ausente (componente do PC)', function () {
    $r = calcFontesRecursos(pBaseFr(['Q07' => null]));
    expect($r['valor'])->toBeNull()->and($r['motivo'])->toBe('indisponivel:divida_componente_faltante');
});

it('indisponível — Q03 não-numérico (componente do AT via defensivo)', function () {
    $r = calcFontesRecursos(pBaseFr(['Q03' => 'NaN']));
    expect($r['valor'])->toBeNull()->and($r['motivo'])->toBe('indisponivel:ativo_componente_faltante');
});

it('indisponível — Q06 não-numérico (componente do PC via defensivo)', function () {
    $r = calcFontesRecursos(pBaseFr(['Q06' => 'NaN']));
    expect($r['valor'])->toBeNull()->and($r['motivo'])->toBe('indisponivel:divida_componente_faltante');
});

<?php

declare(strict_types=1);

use App\Domain\Motor\Calculos\DreAdaptada;
use App\Domain\Motor\Indicadores\NcgVendas;

function calcNcgVendas(array $payload): array
{
    return (new NcgVendas)->calcular($payload, new DreAdaptada($payload))->toArray();
}

function pBaseNv(array $overrides = []): array
{
    // NCG = Q03 + Q04 − Q07 = 80k + 60k − 30k = 110k.
    // Vendas anuais = Q09 × 12 = 1_200_000.
    // NCG/Vendas = 110000 / 1200000 ≈ 0.0917 (9.17%).
    return array_merge([
        'Q03' => '80000',
        'Q04' => '60000',
        'Q07' => '30000',
        'Q09' => '100000',
    ], $overrides);
}

it('verde — NCG negativo (folga operacional, fornecedores > clientes+estoques)', function () {
    $r = calcNcgVendas(pBaseNv(['Q03' => '20000', 'Q04' => '10000', 'Q07' => '50000']));
    expect($r['valor'])->toBeLessThan(0.0);
    expect($r['farol'])->toBe('verde');
});

it('verde — fronteira 0.0 exato (≤ 0 → verde)', function () {
    // NCG = 0 → ACC = PCC. Q03 + Q04 = Q07. 20+10=30.
    $r = calcNcgVendas(pBaseNv(['Q03' => '20000', 'Q04' => '10000', 'Q07' => '30000']));
    expect($r['valor'])->toBe(0.0)->and($r['farol'])->toBe('verde');
});

it('amarelo — fronteira 0.01 (NCG positivo pequeno)', function () {
    // NCG = 12001 → 1.0001% das vendas 1.2M. Quero ratio > 0. Q03+Q04−Q07 = 12001.
    // 20+10−Q07/1000 = 12.001 → Q07 = 17999.
    $r = calcNcgVendas(pBaseNv(['Q03' => '20000', 'Q04' => '10000', 'Q07' => '17999']));
    expect($r['valor'])->toBeGreaterThan(0.0);
    expect($r['farol'])->toBe('amarelo');
});

it('amarelo — típico 5% das vendas', function () {
    // NCG = 60000 → 5% das 1.2M. Q03+Q04−Q07 = 60000. 80+60−Q07 = 60 → Q07=80k.
    $r = calcNcgVendas(pBaseNv(['Q07' => '80000']));
    expect($r['valor'])->toBe(0.05)->and($r['farol'])->toBe('amarelo');
});

it('amarelo — fronteira superior 10% exato', function () {
    // NCG = 120000 → 10%. Q03+Q04−Q07 = 120000. 80+60−Q07 = 120 → Q07=20k.
    $r = calcNcgVendas(pBaseNv(['Q07' => '20000']));
    expect($r['valor'])->toBe(0.10)->and($r['farol'])->toBe('amarelo');
});

it('vermelho — fronteira 10.01% (> 0.10)', function () {
    // NCG = 120120 → 10.01%. Q03+Q04−Q07 = 120120. 80+60−Q07 = 120.12 → Q07=19880.
    $r = calcNcgVendas(pBaseNv(['Q07' => '19880']));
    expect($r['valor'])->toBeGreaterThan(0.10);
    expect($r['farol'])->toBe('vermelho');
});

it('vermelho — 20% das vendas (NCG alto)', function () {
    // NCG = 240000. Q07 = (80+60) − 240 = −100k? Não. Aumentar clientes: Q03 = 250k, Q04=20k, Q07=30k → NCG = 240k.
    $r = calcNcgVendas(pBaseNv(['Q03' => '250000', 'Q04' => '20000', 'Q07' => '30000']));
    expect($r['valor'])->toBe(0.20)->and($r['farol'])->toBe('vermelho');
});

// ---------- casos extremos ----------

it('indisponível — Q03 ausente', function () {
    $r = calcNcgVendas(pBaseNv(['Q03' => null]));
    expect($r['valor'])->toBeNull()->and($r['motivo'])->toBe('indisponivel:ncg_componente_faltante');
});

it('indisponível — Q04 ausente', function () {
    $r = calcNcgVendas(pBaseNv(['Q04' => null]));
    expect($r['valor'])->toBeNull()->and($r['motivo'])->toBe('indisponivel:ncg_componente_faltante');
});

it('indisponível — Q07 ausente', function () {
    $r = calcNcgVendas(pBaseNv(['Q07' => null]));
    expect($r['valor'])->toBeNull()->and($r['motivo'])->toBe('indisponivel:ncg_componente_faltante');
});

it('indisponível — Q09 ausente (vendas faltante)', function () {
    $r = calcNcgVendas(pBaseNv(['Q09' => null]));
    expect($r['valor'])->toBeNull()->and($r['motivo'])->toBe('indisponivel:vendas_faltante');
});

it('indisponível — vendas zero', function () {
    $r = calcNcgVendas(pBaseNv(['Q09' => '0']));
    expect($r['valor'])->toBeNull()->and($r['motivo'])->toBe('indisponivel:vendas_zero');
});

it('indisponível — Q09 não-numérico', function () {
    $r = calcNcgVendas(pBaseNv(['Q09' => 'NaN']));
    expect($r['valor'])->toBeNull()->and($r['motivo'])->toBe('indisponivel:vendas_faltante');
});

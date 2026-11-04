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
    // Venda média mensal = Q09 = 100k. NCG/Vendas = 1,10.
    return array_merge([
        'Q03' => '80000',
        'Q04' => '60000',
        'Q07' => '30000',
        'Q09' => '100000',
    ], $overrides);
}

it('divide a NCG pela venda mensal: caso da Drogaria apontado pela EB dá 0,76 (PDR-014)', function () {
    $r = calcNcgVendas(['Q03' => '1500', 'Q04' => '120000', 'Q07' => '30000', 'Q09' => '120000']);
    expect($r['valor'])->toBe(0.7625)->and($r['farol'])->toBe('vermelho');
});

it('verde — NCG negativo', function () {
    $r = calcNcgVendas(pBaseNv(['Q03' => '20000', 'Q04' => '10000', 'Q07' => '50000']));
    expect($r['valor'])->toBe(-0.2)->and($r['farol'])->toBe('verde');
});

it('verde — fronteira 0 exato (≤ 0 → verde)', function () {
    $r = calcNcgVendas(pBaseNv(['Q03' => '20000', 'Q04' => '10000', 'Q07' => '30000']));
    expect($r['valor'])->toBe(0.0)->and($r['farol'])->toBe('verde');
});

it('amarelo — logo acima de zero', function () {
    $r = calcNcgVendas(pBaseNv(['Q03' => '20000', 'Q04' => '10000', 'Q07' => '29000']));
    expect($r['valor'])->toBe(0.01)->and($r['farol'])->toBe('amarelo');
});

it('amarelo — fronteira superior 0,10 exato', function () {
    $r = calcNcgVendas(pBaseNv(['Q03' => '10000', 'Q04' => '0', 'Q07' => '0']));
    expect($r['valor'])->toBe(0.1)->and($r['farol'])->toBe('amarelo');
});

it('vermelho — logo acima de 0,10', function () {
    $r = calcNcgVendas(pBaseNv(['Q03' => '10010', 'Q04' => '0', 'Q07' => '0']));
    expect($r['valor'])->toBe(0.1001)->and($r['farol'])->toBe('vermelho');
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

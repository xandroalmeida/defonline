<?php

declare(strict_types=1);

use App\Domain\Motor\Calculos\DreAdaptada;
use App\Domain\Motor\Indicadores\DespesasFinEbitda;

function calcDespFinEbitda(array $payload): array
{
    return (new DespesasFinEbitda)->calcular($payload, new DreAdaptada($payload))->toArray();
}

function pBaseDfe(array $overrides = []): array
{
    // EBITDA alvo = 240k (Vendas 1.2M − Compras 624k − DF 240k − DV 96k).
    // Q08 = 52k → Compras anuais 624k → LB 576k → EBITDA 240k.
    return array_merge([
        'Q08' => '52000',   // compras
        'Q09' => '100000',  // vendas → 1.2M anual
        'Q14' => '20000',   // desp fixas → 240k anual
        'Q15' => '8000',    // desp variáveis → 96k anual
        'Q16' => '6000',    // desp financeiras → 72k anual → 72k/240k = 30% (verde)
    ], $overrides);
}

// Anexo E (Indústria) Desp.Fin/EBITDA: ≤ 35% verde · 35.01–50% amarelo · > 50% vermelho.

it('verde — 30% (típico saudável)', function () {
    $r = calcDespFinEbitda(pBaseDfe());
    expect($r['valor'])->toBe(30.0)->and($r['farol'])->toBe('verde');
});

it('verde — fronteira 35.0% (≤ 35)', function () {
    // Q16 = 7000 → 84k anual → 84/240 = 35%.
    $r = calcDespFinEbitda(pBaseDfe(['Q16' => '7000']));
    expect($r['valor'])->toBe(35.0)->and($r['farol'])->toBe('verde');
});

it('amarelo — fronteira 35.01% (> 35)', function () {
    // Q16 = 7002 → 84024 anual → 35.01%.
    $r = calcDespFinEbitda(pBaseDfe(['Q16' => '7002']));
    expect($r['valor'])->toBeGreaterThan(35.0);
    expect($r['farol'])->toBe('amarelo');
});

it('amarelo — típico 40%', function () {
    // Q16 = 8000 → 96k/240k = 40%.
    $r = calcDespFinEbitda(pBaseDfe(['Q16' => '8000']));
    expect($r['valor'])->toBe(40.0)->and($r['farol'])->toBe('amarelo');
});

it('amarelo — fronteira superior 50%', function () {
    // Q16 = 10000 → 120k/240k = 50%.
    $r = calcDespFinEbitda(pBaseDfe(['Q16' => '10000']));
    expect($r['valor'])->toBe(50.0)->and($r['farol'])->toBe('amarelo');
});

it('vermelho — fronteira 50.01% (> 50)', function () {
    // Q16 = 10002 → 120024/240k = 50.01%.
    $r = calcDespFinEbitda(pBaseDfe(['Q16' => '10002']));
    expect($r['valor'])->toBeGreaterThan(50.0);
    expect($r['farol'])->toBe('vermelho');
});

it('vermelho — típico 60%', function () {
    // Q16 = 12000 → 144k/240k = 60%.
    $r = calcDespFinEbitda(pBaseDfe(['Q16' => '12000']));
    expect($r['valor'])->toBe(60.0)->and($r['farol'])->toBe('vermelho');
});

it('vermelho — 100% (despesas financeiras consomem todo o EBITDA)', function () {
    // Q16 = 20000 → 240k/240k = 100%.
    $r = calcDespFinEbitda(pBaseDfe(['Q16' => '20000']));
    expect($r['valor'])->toBe(100.0)->and($r['farol'])->toBe('vermelho');
});

// ---------- casos extremos ----------

it('indisponível — Q16 ausente', function () {
    $r = calcDespFinEbitda(pBaseDfe(['Q16' => null]));
    expect($r['valor'])->toBeNull()->and($r['motivo'])->toBe('indisponivel:despesas_financeiras_faltante');
});

it('indisponível — Q16 não-numérico', function () {
    $r = calcDespFinEbitda(pBaseDfe(['Q16' => 'abc']));
    expect($r['valor'])->toBeNull()->and($r['motivo'])->toBe('indisponivel:despesas_financeiras_faltante');
});

it('indisponível — EBITDA zero', function () {
    // Compras 72k mensais → 864k anual → LB 336k → EBITDA 0.
    $r = calcDespFinEbitda(pBaseDfe(['Q08' => '72000']));
    expect($r['valor'])->toBeNull()->and($r['motivo'])->toBe('indisponivel:ebitda_zero');
});

it('indisponível — EBITDA negativo', function () {
    // Compras 90k mensais → 1.08M anual → LB 120k → EBITDA = 120 − 240 − 96 = −216k.
    $r = calcDespFinEbitda(pBaseDfe(['Q08' => '90000']));
    expect($r['valor'])->toBeNull()->and($r['motivo'])->toBe('indisponivel:ebitda_negativo');
});

it('indisponível — Q14 ausente (despesas propagam)', function () {
    $r = calcDespFinEbitda(pBaseDfe(['Q14' => null]));
    expect($r['valor'])->toBeNull()->and($r['motivo'])->toBe('indisponivel:despesas_faltante');
});

it('indisponível — Q09 ausente (vendas propagam)', function () {
    $r = calcDespFinEbitda(pBaseDfe(['Q09' => null]));
    expect($r['valor'])->toBeNull()->and($r['motivo'])->toBe('indisponivel:vendas_faltante');
});

it('indisponível — Q08 ausente (LB null → EBITDA null → despesas_faltante)', function () {
    $r = calcDespFinEbitda(pBaseDfe(['Q08' => null]));
    expect($r['valor'])->toBeNull()->and($r['motivo'])->toBe('indisponivel:despesas_faltante');
});

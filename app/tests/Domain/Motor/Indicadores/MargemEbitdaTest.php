<?php

declare(strict_types=1);

use App\Domain\Motor\Calculos\DreAdaptada;
use App\Domain\Motor\Indicadores\MargemEbitda;

function calcMargemEbitda(array $payload): array
{
    return (new MargemEbitda)->calcular($payload, new DreAdaptada($payload))->toArray();
}

function pBaseMe(array $overrides = []): array
{
    // Vendas 100k/mês = 1.2M/ano.
    // LB = (100k − Q08) × 12. EBITDA = LB − Q14×12 − Q15×12.
    return array_merge([
        'Q08' => '50000',   // compras
        'Q09' => '100000',  // vendas
        'Q14' => '20000',   // desp fixas
        'Q15' => '8000',    // desp variáveis
    ], $overrides);
}

// Anexo E (Indústria) Margem EBITDA: > 20% verde · 15.01–20% amarelo · ≤ 15% vermelho.

it('verde — margem EBITDA 25% (saudável)', function () {
    // EBITDA = 300k → 25%. LB = 636k → Compras anuais 564k → Compras mensais 47k.
    $r = calcMargemEbitda(pBaseMe(['Q08' => '47000']));
    expect($r['valor'])->toBe(25.0)->and($r['farol'])->toBe('verde');
});

it('verde — fronteira 20.01% (> 20)', function () {
    // EBITDA = 240120 → 20.01%. LB = 576120 → Compras anuais 623880 → Compras mensais 51990.
    $r = calcMargemEbitda(pBaseMe(['Q08' => '51990']));
    expect($r['valor'])->toBeGreaterThan(20.0);
    expect($r['farol'])->toBe('verde');
});

it('amarelo — fronteira superior 20.0%', function () {
    // EBITDA = 240k → 20%. LB = 576k → Compras anuais 624k → Compras mensais 52k.
    $r = calcMargemEbitda(pBaseMe(['Q08' => '52000']));
    expect($r['valor'])->toBe(20.0)->and($r['farol'])->toBe('amarelo');
});

it('amarelo — típico 17.5%', function () {
    // EBITDA = 210k → 17.5%. LB = 546k → Compras anuais 654k → Compras mensais 54500.
    $r = calcMargemEbitda(pBaseMe(['Q08' => '54500']));
    expect($r['valor'])->toBe(17.5)->and($r['farol'])->toBe('amarelo');
});

it('amarelo — fronteira inferior 15.01%', function () {
    // EBITDA = 180120 → 15.01%. LB = 516120 → Compras anuais 683880 → Compras mensais 56990.
    $r = calcMargemEbitda(pBaseMe(['Q08' => '56990']));
    expect($r['valor'])->toBeGreaterThan(15.0);
    expect($r['farol'])->toBe('amarelo');
});

it('vermelho — fronteira superior 15.0%', function () {
    // EBITDA = 180k → 15%. LB = 516k → Compras anuais 684k → Compras mensais 57k.
    $r = calcMargemEbitda(pBaseMe(['Q08' => '57000']));
    expect($r['valor'])->toBe(15.0)->and($r['farol'])->toBe('vermelho');
});

it('vermelho — típico 10%', function () {
    // EBITDA = 120k → 10%. LB = 456k → Compras anuais 744k → Compras mensais 62k.
    $r = calcMargemEbitda(pBaseMe(['Q08' => '62000']));
    expect($r['valor'])->toBe(10.0)->and($r['farol'])->toBe('vermelho');
});

it('vermelho — EBITDA zero (margem 0%)', function () {
    // EBITDA = 0. LB = 336k. Compras anuais = 864k. Compras mensais = 72k.
    $r = calcMargemEbitda(pBaseMe(['Q08' => '72000']));
    expect($r['valor'])->toBe(0.0)->and($r['farol'])->toBe('vermelho');
});

it('vermelho — EBITDA negativo dentro do limite (margem −50%)', function () {
    // EBITDA = −600k → −50%. LB = −264k. Compras anuais = 1464k. Compras mensais = 122k.
    $r = calcMargemEbitda(pBaseMe(['Q08' => '122000']));
    expect($r['valor'])->toBe(-50.0)->and($r['farol'])->toBe('vermelho');
});

it('vermelho — fronteira EBITDA = −Vendas (margem exatamente −100%, ainda exibível)', function () {
    // EBITDA = −1.2M → −100%. LB = −864k. Compras anuais = 2064k. Compras mensais = 172k.
    $r = calcMargemEbitda(pBaseMe(['Q08' => '172000']));
    expect($r['valor'])->toBe(-100.0)->and($r['farol'])->toBe('vermelho');
});

// ---------- casos extremos ----------

it('indisponível — Q09 ausente (vendas)', function () {
    $r = calcMargemEbitda(pBaseMe(['Q09' => null]));
    expect($r['valor'])->toBeNull()->and($r['motivo'])->toBe('indisponivel:vendas_faltante');
});

it('indisponível — Q09 = 0 (vendas zero)', function () {
    $r = calcMargemEbitda(pBaseMe(['Q09' => '0']));
    expect($r['valor'])->toBeNull()->and($r['motivo'])->toBe('indisponivel:vendas_zero');
});

it('indisponível — Q14 ausente (despesas fixas)', function () {
    $r = calcMargemEbitda(pBaseMe(['Q14' => null]));
    expect($r['valor'])->toBeNull()->and($r['motivo'])->toBe('indisponivel:despesas_faltante');
});

it('indisponível — Q15 ausente (despesas variáveis)', function () {
    $r = calcMargemEbitda(pBaseMe(['Q15' => null]));
    expect($r['valor'])->toBeNull()->and($r['motivo'])->toBe('indisponivel:despesas_faltante');
});

it('indisponível — Q08 ausente (compras, propaga via LB null)', function () {
    $r = calcMargemEbitda(pBaseMe(['Q08' => null]));
    expect($r['valor'])->toBeNull()->and($r['motivo'])->toBe('indisponivel:despesas_faltante');
});

it('indisponível — Q09 não-numérico (defensivo via DRE null)', function () {
    $r = calcMargemEbitda(pBaseMe(['Q09' => 'NaN']));
    expect($r['valor'])->toBeNull()->and($r['motivo'])->toBe('indisponivel:vendas_faltante');
});

it('indisponível — EBITDA extremo (< −Vendas / margem < −100%)', function () {
    // EBITDA = −1.5M → −125%. LB = −1.164M. Compras anuais = 2.364M. Compras mensais = 197k.
    $r = calcMargemEbitda(pBaseMe(['Q08' => '197000']));
    expect($r['valor'])->toBeNull()->and($r['motivo'])->toBe('indisponivel:ebitda_extremo');
});

<?php

declare(strict_types=1);

use App\Domain\Motor\Calculos\DreAdaptada;
use App\Domain\Motor\Indicadores\MargemLiquida;

function calcMargemLiquida(array $payload): array
{
    return (new MargemLiquida)->calcular($payload, new DreAdaptada($payload))->toArray();
}

function pBaseMl(array $overrides = []): array
{
    // Vendas 100k/mês = 1.2M/ano.
    // LB = (100k − Q08) × 12.
    // EBITDA = LB − Q14×12 − Q15×12.
    // LOL = EBITDA − Q16×12.
    return array_merge([
        'Q08' => '50000',   // compras
        'Q09' => '100000',  // vendas
        'Q14' => '20000',   // desp fixas
        'Q15' => '8000',    // desp variáveis
        'Q16' => '2000',    // desp financeiras
    ], $overrides);
}

it('verde — margem líquida 20% (saudável)', function () {
    // Vendas 1.2M; LB 600k; EBITDA = 600k − 240k − 96k = 264k; LOL = 264k − 24k = 240k; margem = 20%.
    $r = calcMargemLiquida(pBaseMl());
    expect($r['valor'])->toBe(20.0)->and($r['farol'])->toBe('verde');
});

it('verde — fronteira 15.01% (LOL = 180120 com Vendas 1.2M)', function () {
    // Para 15.01%: LOL = 180120. EBITDA = LOL + DF anuais. DF=24k. EBITDA = 204120.
    // Despesas fixas anuais = 240k; variáveis = 96k. LB = EBITDA + 336k = 540120. Compras anuais = 1.2M − 540120 = 659880. Compras mensais = 54990.
    $r = calcMargemLiquida(pBaseMl(['Q08' => '54990']));
    expect($r['valor'])->toBeGreaterThan(15.0);
    expect($r['farol'])->toBe('verde');
});

it('amarelo — fronteira superior 15.0% (LOL = 180000)', function () {
    // Para 15%: LOL = 180000. EBITDA = 204000. LB = 540000. Compras anuais = 660000. Compras mensais = 55000.
    $r = calcMargemLiquida(pBaseMl(['Q08' => '55000']));
    expect($r['valor'])->toBe(15.0)->and($r['farol'])->toBe('amarelo');
});

it('amarelo — típico 10% (LOL = 120k)', function () {
    // LOL = 120k. EBITDA = 144k. LB = 480k. Compras anuais = 720k. Compras mensais = 60k.
    $r = calcMargemLiquida(pBaseMl(['Q08' => '60000']));
    expect($r['valor'])->toBe(10.0)->and($r['farol'])->toBe('amarelo');
});

it('amarelo — fronteira inferior 8.01%', function () {
    // LOL = 96120. EBITDA = 120120. LB = 456120. Compras anuais = 743880. Compras mensais = 61990.
    $r = calcMargemLiquida(pBaseMl(['Q08' => '61990']));
    expect($r['valor'])->toBeGreaterThan(8.0);
    expect($r['farol'])->toBe('amarelo');
});

it('vermelho — fronteira superior 8.0%', function () {
    // LOL = 96000. EBITDA = 120000. LB = 456000. Compras anuais = 744000. Compras mensais = 62000.
    $r = calcMargemLiquida(pBaseMl(['Q08' => '62000']));
    expect($r['valor'])->toBe(8.0)->and($r['farol'])->toBe('vermelho');
});

it('vermelho — margem 0%', function () {
    // LOL = 0. EBITDA = 24000. LB = 360000. Compras anuais = 840000. Compras mensais = 70000.
    $r = calcMargemLiquida(pBaseMl(['Q08' => '70000']));
    expect($r['valor'])->toBe(0.0)->and($r['farol'])->toBe('vermelho');
});

it('vermelho — margem negativa (prejuízo)', function () {
    $r = calcMargemLiquida(pBaseMl(['Q08' => '90000']));
    expect($r['valor'])->toBeLessThan(0.0);
    expect($r['farol'])->toBe('vermelho');
});

// ---------- casos extremos ----------

it('indisponível — Q09 ausente (vendas)', function () {
    $r = calcMargemLiquida(pBaseMl(['Q09' => null]));
    expect($r['valor'])->toBeNull()->and($r['motivo'])->toBe('indisponivel:vendas_faltante');
});

it('indisponível — Q09 = 0 (vendas zero)', function () {
    $r = calcMargemLiquida(pBaseMl(['Q09' => '0']));
    expect($r['valor'])->toBeNull()->and($r['motivo'])->toBe('indisponivel:vendas_zero');
});

it('indisponível — Q16 ausente (despesas financeiras)', function () {
    $r = calcMargemLiquida(pBaseMl(['Q16' => null]));
    expect($r['valor'])->toBeNull()->and($r['motivo'])->toBe('indisponivel:despesas_financeiras_faltante');
});

it('indisponível — Q14 ausente (despesas fixas)', function () {
    $r = calcMargemLiquida(pBaseMl(['Q14' => null]));
    expect($r['valor'])->toBeNull()->and($r['motivo'])->toBe('indisponivel:despesas_faltante');
});

it('indisponível — Q15 ausente (despesas variáveis)', function () {
    $r = calcMargemLiquida(pBaseMl(['Q15' => null]));
    expect($r['valor'])->toBeNull()->and($r['motivo'])->toBe('indisponivel:despesas_faltante');
});

it('indisponível — Q08 não-numérico', function () {
    $r = calcMargemLiquida(pBaseMl(['Q08' => 'abc']));
    expect($r['valor'])->toBeNull()->and($r['motivo'])->toBe('indisponivel:despesas_faltante');
});

it('indisponível — Q09 não-numérico (vendas faltante via DRE null defensivo)', function () {
    // Q09 não-null mas inválido — passa o primeiro check e cai no defensivo (linhas 44-46).
    $r = calcMargemLiquida(pBaseMl(['Q09' => 'NaN']));
    expect($r['valor'])->toBeNull()->and($r['motivo'])->toBe('indisponivel:vendas_faltante');
});

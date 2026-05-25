<?php

declare(strict_types=1);

use App\Domain\Motor\Calculos\DreAdaptada;
use App\Domain\Motor\Indicadores\DividaLiquidaEbitda;

function calcDivLqEbitda(array $payload): array
{
    return (new DividaLiquidaEbitda)->calcular($payload, new DreAdaptada($payload))->toArray();
}

function pBaseDl(array $overrides = []): array
{
    // EBITDA padrão 264_000 anuais (mesmo cálculo de MargemLiquidaTest).
    return array_merge([
        'Q02' => '50000',   // disponibilidades (ACF)
        'Q06' => '40000',   // dívidas financeiras (PCF)
        'Q08' => '50000',
        'Q09' => '100000',
        'Q14' => '20000',
        'Q15' => '8000',
    ], $overrides);
}

it('verde — dívida líquida negativa (caixa > dívida)', function () {
    // PCF=40k − ACF=50k = −10k. EBITDA=264k. Ratio = −0.0379. Verde.
    $r = calcDivLqEbitda(pBaseDl());
    expect($r['valor'])->toBeLessThan(0.0);
    expect($r['farol'])->toBe('verde');
});

it('verde — ratio típico 1.0', function () {
    // Ratio = 1 → dívida líquida = EBITDA = 264k. ACF=0 (Q02=0), PCF=264k.
    $r = calcDivLqEbitda(pBaseDl(['Q02' => '0', 'Q06' => '264000']));
    expect($r['valor'])->toBe(1.0)->and($r['farol'])->toBe('verde');
});

it('verde — fronteira 2.0 exato (≤ 2 → verde)', function () {
    // Ratio = 2 → dívida líquida = 2 × 264k = 528k. Q02=0, Q06=528k.
    $r = calcDivLqEbitda(pBaseDl(['Q02' => '0', 'Q06' => '528000']));
    expect($r['valor'])->toBe(2.0)->and($r['farol'])->toBe('verde');
});

it('amarelo — fronteira 2.01', function () {
    // Q06 = 530640 (2.01 × 264000).
    $r = calcDivLqEbitda(pBaseDl(['Q02' => '0', 'Q06' => '530640']));
    expect($r['valor'])->toBeGreaterThan(2.0);
    expect($r['farol'])->toBe('amarelo');
});

it('amarelo — fronteira 3.0 exato (≤ 3 → amarelo)', function () {
    $r = calcDivLqEbitda(pBaseDl(['Q02' => '0', 'Q06' => '792000']));
    expect($r['valor'])->toBe(3.0)->and($r['farol'])->toBe('amarelo');
});

it('vermelho — fronteira 3.01', function () {
    $r = calcDivLqEbitda(pBaseDl(['Q02' => '0', 'Q06' => '794640']));
    expect($r['valor'])->toBeGreaterThan(3.0);
    expect($r['farol'])->toBe('vermelho');
});

it('vermelho — ratio 5.0 (alavancagem alta)', function () {
    $r = calcDivLqEbitda(pBaseDl(['Q02' => '0', 'Q06' => '1320000']));
    expect($r['valor'])->toBe(5.0)->and($r['farol'])->toBe('vermelho');
});

// ---------- casos extremos ----------

it('indisponível — EBITDA = 0', function () {
    // Despesas totais = LB → EBITDA zero. LB = 600k. Q14×12+Q15×12 = 600k. Q14=30k, Q15=20k.
    $r = calcDivLqEbitda(pBaseDl(['Q14' => '30000', 'Q15' => '20000']));
    expect($r['valor'])->toBeNull()->and($r['motivo'])->toBe('indisponivel:ebitda_zero');
});

it('indisponível — EBITDA negativo (Q14 alto)', function () {
    $r = calcDivLqEbitda(pBaseDl(['Q14' => '100000']));
    expect($r['valor'])->toBeNull()->and($r['motivo'])->toBe('indisponivel:ebitda_negativo');
});

it('indisponível — Q02 ausente', function () {
    $r = calcDivLqEbitda(pBaseDl(['Q02' => null]));
    expect($r['valor'])->toBeNull()->and($r['motivo'])->toBe('indisponivel:divida_componente_faltante');
});

it('indisponível — Q06 ausente', function () {
    $r = calcDivLqEbitda(pBaseDl(['Q06' => null]));
    expect($r['valor'])->toBeNull()->and($r['motivo'])->toBe('indisponivel:divida_componente_faltante');
});

it('indisponível — Q14 ausente (propaga EBITDA null → despesas_faltante)', function () {
    $r = calcDivLqEbitda(pBaseDl(['Q14' => null]));
    expect($r['valor'])->toBeNull()->and($r['motivo'])->toBe('indisponivel:despesas_faltante');
});

it('indisponível — Q02 não-numérico (defensa pós-check de null)', function () {
    // Q02 não-null mas inválido — passa o check de null, mas DreAdaptada::disponibilidades()
    // retorna null pelo asBc → cai no branch defensivo (linhas 66-70).
    $r = calcDivLqEbitda(pBaseDl(['Q02' => 'NaN']));
    expect($r['valor'])->toBeNull()->and($r['motivo'])->toBe('indisponivel:divida_componente_faltante');
});

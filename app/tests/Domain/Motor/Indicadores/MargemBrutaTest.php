<?php

declare(strict_types=1);

use App\Domain\Motor\Calculos\DreAdaptada;
use App\Domain\Motor\Indicadores\MargemBruta;

function calcMargemBruta(array $payload): array
{
    return (new MargemBruta)->calcular($payload, new DreAdaptada($payload))->toArray();
}

function pBaseMb(array $overrides = []): array
{
    return array_merge([
        'Q08' => '50000',   // compras (mensal)
        'Q09' => '100000',  // vendas (mensal)
    ], $overrides);
}

it('verde — margem 50% (vendas 100k, compras 50k)', function () {
    $r = calcMargemBruta(pBaseMb());
    // Vendas anuais 1_200_000; LB = 600_000; Margem = 50%.
    expect($r['valor'])->toBe(50.0)->and($r['farol'])->toBe('verde');
});

it('verde — margem limítrofe 25.01% (compras = 74.99% das vendas)', function () {
    // Para 25.01%, compras = 74.99% das vendas = 74990. Vendas 100k.
    $r = calcMargemBruta(pBaseMb(['Q08' => '74990']));
    expect($r['valor'])->toBeGreaterThan(25.0);
    expect($r['farol'])->toBe('verde');
});

it('amarelo — margem na fronteira superior 25.0% (compras = 75% das vendas)', function () {
    $r = calcMargemBruta(pBaseMb(['Q08' => '75000']));
    expect($r['valor'])->toBe(25.0)->and($r['farol'])->toBe('amarelo');
});

it('amarelo — margem típica 22.5%', function () {
    // 22.5% = compras 77.5% = 77500.
    $r = calcMargemBruta(pBaseMb(['Q08' => '77500']));
    expect($r['valor'])->toBe(22.5)->and($r['farol'])->toBe('amarelo');
});

it('amarelo — fronteira inferior 20.01%', function () {
    $r = calcMargemBruta(pBaseMb(['Q08' => '79990']));
    expect($r['valor'])->toBeGreaterThan(20.0);
    expect($r['farol'])->toBe('amarelo');
});

it('vermelho — fronteira superior 20.0%', function () {
    $r = calcMargemBruta(pBaseMb(['Q08' => '80000']));
    expect($r['valor'])->toBe(20.0)->and($r['farol'])->toBe('vermelho');
});

it('vermelho — margem 10%', function () {
    $r = calcMargemBruta(pBaseMb(['Q08' => '90000']));
    expect($r['valor'])->toBe(10.0)->and($r['farol'])->toBe('vermelho');
});

it('vermelho — margem negativa (compras > vendas)', function () {
    $r = calcMargemBruta(pBaseMb(['Q08' => '120000']));
    expect($r['valor'])->toBe(-20.0)->and($r['farol'])->toBe('vermelho');
});

// ---------- casos extremos ----------

it('indisponível — Q09 ausente (vendas faltante)', function () {
    $r = calcMargemBruta(pBaseMb(['Q09' => null]));
    expect($r['valor'])->toBeNull()
        ->and($r['farol'])->toBe('nenhum')
        ->and($r['motivo'])->toBe('indisponivel:vendas_faltante');
});

it('indisponível — vendas zero (Q09 = 0)', function () {
    $r = calcMargemBruta(pBaseMb(['Q09' => '0']));
    expect($r['valor'])->toBeNull()
        ->and($r['farol'])->toBe('nenhum')
        ->and($r['motivo'])->toBe('indisponivel:vendas_zero');
});

it('indisponível — Q08 ausente (compras faltante propaga via LB)', function () {
    $r = calcMargemBruta(pBaseMb(['Q08' => null]));
    expect($r['valor'])->toBeNull()
        ->and($r['farol'])->toBe('nenhum');
});

it('indisponível — Q09 não-numérico (defensa)', function () {
    $r = calcMargemBruta(pBaseMb(['Q09' => 'abc']));
    expect($r['valor'])->toBeNull()
        ->and($r['farol'])->toBe('nenhum');
});

it('mensagem placeholder PT-BR para faixa verde', function () {
    $r = calcMargemBruta(pBaseMb());
    expect($r['mensagem'])->toBe('Faixa verde.');
});

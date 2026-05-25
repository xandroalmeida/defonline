<?php

declare(strict_types=1);

use App\Domain\Motor\Calculos\DreAdaptada;
use App\Domain\Motor\Indicadores\GiroAtivo;

function calcGiroAtivo(array $payload): array
{
    return (new GiroAtivo)->calcular($payload, new DreAdaptada($payload))->toArray();
}

function pBaseGa(array $overrides = []): array
{
    // Vendas 100k/mês = 1.2M/ano.
    // AT = Q02 + Q03 + Q04 + Q05.
    return array_merge([
        'Q02' => '50000',   // disponibilidades
        'Q03' => '50000',   // clientes
        'Q04' => '100000',  // estoques
        'Q05' => '200000',  // imobilizado
        'Q09' => '100000',  // vendas
    ], $overrides);
    // AT = 400k → Giro = 1.2M / 400k = 3.0 (verde)
}

// Anexo E (Indústria) Giro do Ativo: > 2 verde · 1.01–2 amarelo · ≤ 1 vermelho.

it('verde — Giro 3.0 (típico saudável)', function () {
    $r = calcGiroAtivo(pBaseGa());
    expect($r['valor'])->toBe(3.0)->and($r['farol'])->toBe('verde');
});

it('verde — fronteira 2.01 (> 2)', function () {
    // AT alvo: 1.2M / 2.01 = ~597014.9. Use Q05 = 397015.
    $r = calcGiroAtivo(pBaseGa(['Q05' => '397015']));
    expect($r['valor'])->toBeGreaterThan(2.0);
    expect($r['farol'])->toBe('verde');
});

it('amarelo — fronteira superior 2.0', function () {
    // AT = 600k → Giro = 2.0. Q05 = 400k.
    $r = calcGiroAtivo(pBaseGa(['Q05' => '400000']));
    expect($r['valor'])->toBe(2.0)->and($r['farol'])->toBe('amarelo');
});

it('amarelo — típico 1.5', function () {
    // AT = 800k → Giro = 1.5. Q05 = 600k.
    $r = calcGiroAtivo(pBaseGa(['Q05' => '600000']));
    expect($r['valor'])->toBe(1.5)->and($r['farol'])->toBe('amarelo');
});

it('amarelo — fronteira inferior 1.01', function () {
    // AT alvo: 1.2M / 1.01 = ~1188119. Q05 = 988119.
    $r = calcGiroAtivo(pBaseGa(['Q05' => '988119']));
    expect($r['valor'])->toBeGreaterThan(1.0);
    expect($r['farol'])->toBe('amarelo');
});

it('vermelho — fronteira superior 1.0', function () {
    // AT = 1.2M → Giro = 1.0. Q05 = 1M.
    $r = calcGiroAtivo(pBaseGa(['Q05' => '1000000']));
    expect($r['valor'])->toBe(1.0)->and($r['farol'])->toBe('vermelho');
});

it('vermelho — típico 0.5', function () {
    // AT = 2.4M → Giro = 0.5. Q05 = 2.2M.
    $r = calcGiroAtivo(pBaseGa(['Q05' => '2200000']));
    expect($r['valor'])->toBe(0.5)->and($r['farol'])->toBe('vermelho');
});

it('vermelho — vendas zero (Giro = 0)', function () {
    // Q09 = 0 → vendas = 0 → Giro = 0 → vermelho. (Não é indisponibilidade.)
    $r = calcGiroAtivo(pBaseGa(['Q09' => '0']));
    expect($r['valor'])->toBe(0.0)->and($r['farol'])->toBe('vermelho');
});

// ---------- casos extremos ----------

it('indisponível — Q02 ausente (componente do AT)', function () {
    $r = calcGiroAtivo(pBaseGa(['Q02' => null]));
    expect($r['valor'])->toBeNull()->and($r['motivo'])->toBe('indisponivel:ativo_componente_faltante');
});

it('indisponível — Q05 ausente (componente do AT)', function () {
    $r = calcGiroAtivo(pBaseGa(['Q05' => null]));
    expect($r['valor'])->toBeNull()->and($r['motivo'])->toBe('indisponivel:ativo_componente_faltante');
});

it('indisponível — AT = 0 (todos os componentes zerados)', function () {
    $r = calcGiroAtivo(['Q02' => '0', 'Q03' => '0', 'Q04' => '0', 'Q05' => '0', 'Q09' => '100000']);
    expect($r['valor'])->toBeNull()->and($r['motivo'])->toBe('indisponivel:ativo_zero');
});

it('indisponível — Q09 ausente (vendas faltante)', function () {
    $r = calcGiroAtivo(pBaseGa(['Q09' => null]));
    expect($r['valor'])->toBeNull()->and($r['motivo'])->toBe('indisponivel:vendas_faltante');
});

it('indisponível — Q03 não-numérico (componente do AT)', function () {
    $r = calcGiroAtivo(pBaseGa(['Q03' => 'NaN']));
    expect($r['valor'])->toBeNull()->and($r['motivo'])->toBe('indisponivel:ativo_componente_faltante');
});

it('indisponível — Q09 não-numérico (vendas)', function () {
    $r = calcGiroAtivo(pBaseGa(['Q09' => 'abc']));
    expect($r['valor'])->toBeNull()->and($r['motivo'])->toBe('indisponivel:vendas_faltante');
});

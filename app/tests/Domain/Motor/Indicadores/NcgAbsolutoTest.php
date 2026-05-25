<?php

declare(strict_types=1);

use App\Domain\Motor\Calculos\DreAdaptada;
use App\Domain\Motor\Indicadores\NcgAbsoluto;

function calcNcgAbs(array $payload): array
{
    return (new NcgAbsoluto)->calcular($payload, new DreAdaptada($payload))->toArray();
}

function pBaseNa(array $overrides = []): array
{
    return array_merge([
        'Q03' => '80000',
        'Q04' => '60000',
        'Q07' => '30000',
        'Q09' => '100000',
    ], $overrides);
}

// ---------- valor calculado ----------

it('calcula NCG = Q03 + Q04 − Q07 (padrão 80+60−30 = 110k)', function () {
    $r = calcNcgAbs(pBaseNa());
    expect($r['valor'])->toBe(110000.0);
});

it('NCG sempre tem farol nenhum (informativo)', function () {
    $r = calcNcgAbs(pBaseNa());
    expect($r['farol'])->toBe('nenhum');
});

it('motivo é null quando indicador calculável (não é "indisponivel:*")', function () {
    $r = calcNcgAbs(pBaseNa());
    expect($r['motivo'])->toBeNull();
});

// ---------- Faixa 1: NCG ≤ 0 ----------

it('Faixa 1 — NCG negativo (folga operacional)', function () {
    $r = calcNcgAbs(pBaseNa(['Q03' => '20000', 'Q04' => '10000', 'Q07' => '50000']));
    expect($r['valor'])->toBe(-20000.0)
        ->and($r['mensagem'])->toContain('Folga operacional');
});

it('Faixa 1 — NCG = 0 exato (fronteira inclusiva)', function () {
    $r = calcNcgAbs(pBaseNa(['Q03' => '20000', 'Q04' => '10000', 'Q07' => '30000']));
    expect($r['valor'])->toBe(0.0)
        ->and($r['mensagem'])->toContain('Folga operacional');
});

// ---------- Faixa 2: 0 < NCG ≤ 10% Vendas ----------

it('Faixa 2 — NCG positivo moderado (NCG 5% das vendas)', function () {
    // Vendas anuais = 1.2M. 5% = 60000. NCG = 60000 → Q03+Q04−Q07 = 60k. 50+40-30=60.
    $r = calcNcgAbs(pBaseNa(['Q03' => '50000', 'Q04' => '40000', 'Q07' => '30000']));
    expect($r['valor'])->toBe(60000.0)
        ->and($r['mensagem'])->toContain('moderado');
});

it('Faixa 2 — fronteira superior NCG = 10% Vendas exato', function () {
    // 10% × 1.2M = 120000.
    $r = calcNcgAbs(pBaseNa(['Q03' => '100000', 'Q04' => '40000', 'Q07' => '20000']));
    expect($r['valor'])->toBe(120000.0)
        ->and($r['mensagem'])->toContain('moderado');
});

// ---------- Faixa 3: NCG > 10% Vendas ----------

it('Faixa 3 — NCG positivo alto (12% das vendas)', function () {
    // 12% × 1.2M = 144000.
    $r = calcNcgAbs(pBaseNa(['Q03' => '120000', 'Q04' => '50000', 'Q07' => '26000']));
    expect($r['valor'])->toBe(144000.0)
        ->and($r['mensagem'])->toContain('alto');
});

it('Faixa 3 — fronteira logo acima de 10% (120001)', function () {
    $r = calcNcgAbs(pBaseNa(['Q03' => '100001', 'Q04' => '40000', 'Q07' => '20000']));
    expect($r['valor'])->toBeGreaterThan(120000.0);
    expect($r['mensagem'])->toContain('alto');
});

// ---------- fallback quando Vendas faltante ----------

it('fallback Faixa 2 quando Vendas é null (e NCG > 0)', function () {
    $r = calcNcgAbs(pBaseNa(['Q09' => null]));
    expect($r['valor'])->toBe(110000.0)
        ->and($r['mensagem'])->toContain('moderado');
});

it('fallback Faixa 2 quando Vendas = 0 (e NCG > 0)', function () {
    $r = calcNcgAbs(pBaseNa(['Q09' => '0']));
    expect($r['valor'])->toBe(110000.0)
        ->and($r['mensagem'])->toContain('moderado');
});

it('NCG ≤ 0 com vendas ausente continua Faixa 1 (folga)', function () {
    $r = calcNcgAbs(pBaseNa(['Q03' => '0', 'Q04' => '0', 'Q07' => '10000', 'Q09' => null]));
    expect($r['valor'])->toBe(-10000.0)
        ->and($r['mensagem'])->toContain('Folga operacional');
});

// ---------- casos extremos (catálogo §9) ----------

it('indisponível — Q03 ausente', function () {
    $r = calcNcgAbs(pBaseNa(['Q03' => null]));
    expect($r['valor'])->toBeNull()
        ->and($r['farol'])->toBe('nenhum')
        ->and($r['motivo'])->toBe('indisponivel:ncg_componente_faltante');
});

it('indisponível — Q04 ausente', function () {
    $r = calcNcgAbs(pBaseNa(['Q04' => null]));
    expect($r['valor'])->toBeNull()
        ->and($r['motivo'])->toBe('indisponivel:ncg_componente_faltante');
});

it('indisponível — Q07 ausente', function () {
    $r = calcNcgAbs(pBaseNa(['Q07' => null]));
    expect($r['valor'])->toBeNull()
        ->and($r['motivo'])->toBe('indisponivel:ncg_componente_faltante');
});

it('indisponível — todos os componentes ausentes', function () {
    $r = calcNcgAbs(['Q09' => '100000']);
    expect($r['valor'])->toBeNull()
        ->and($r['motivo'])->toBe('indisponivel:ncg_componente_faltante');
});

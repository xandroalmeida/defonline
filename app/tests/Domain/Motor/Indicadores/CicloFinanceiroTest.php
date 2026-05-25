<?php

declare(strict_types=1);

use App\Domain\Motor\Calculos\DreAdaptada;
use App\Domain\Motor\Indicadores\CicloFinanceiro;

function calcCicloFinanceiro(array $payload): array
{
    return (new CicloFinanceiro)->calcular($payload, new DreAdaptada($payload))->toArray();
}

// Ciclo = PME + PMR − PMC. Anexo E Todos: ≤ 30 verde · 30.01–60 amarelo · > 60 vermelho.

it('verde — ciclo curto: PME 15 + PMR 15 − PMC 30 = 0', function () {
    $r = calcCicloFinanceiro(['Q10' => 30, 'Q11' => 15, 'Q12' => 15]);
    expect($r['valor'])->toBe(0.0)->and($r['farol'])->toBe('verde');
});

it('verde — ciclo negativo (PMC > PME+PMR — caso ideal extremo)', function () {
    $r = calcCicloFinanceiro(['Q10' => 90, 'Q11' => 15, 'Q12' => 15]);
    expect($r['valor'])->toBe(-60.0)->and($r['farol'])->toBe('verde');
});

it('verde — fronteira 30 exato', function () {
    $r = calcCicloFinanceiro(['Q10' => 30, 'Q11' => 30, 'Q12' => 30]);
    expect($r['valor'])->toBe(30.0)->and($r['farol'])->toBe('verde');
});

it('amarelo — fronteira 30.01', function () {
    $r = calcCicloFinanceiro(['Q10' => 30, 'Q11' => 30, 'Q12' => '30.01']);
    expect($r['valor'])->toBeGreaterThan(30.0);
    expect($r['farol'])->toBe('amarelo');
});

it('amarelo — típico 45 dias', function () {
    $r = calcCicloFinanceiro(['Q10' => 30, 'Q11' => 30, 'Q12' => 45]);
    expect($r['valor'])->toBe(45.0)->and($r['farol'])->toBe('amarelo');
});

it('amarelo — fronteira superior 60', function () {
    $r = calcCicloFinanceiro(['Q10' => 30, 'Q11' => 45, 'Q12' => 45]);
    expect($r['valor'])->toBe(60.0)->and($r['farol'])->toBe('amarelo');
});

it('vermelho — fronteira 60.01', function () {
    $r = calcCicloFinanceiro(['Q10' => 30, 'Q11' => 45, 'Q12' => '45.01']);
    expect($r['valor'])->toBeGreaterThan(60.0);
    expect($r['farol'])->toBe('vermelho');
});

it('vermelho — ciclo 120 dias (típico alto)', function () {
    $r = calcCicloFinanceiro(['Q10' => 30, 'Q11' => 60, 'Q12' => 90]);
    expect($r['valor'])->toBe(120.0)->and($r['farol'])->toBe('vermelho');
});

it('verde — ciclo extremo negativo (−100 dias)', function () {
    // Caso "ideal extremo" mencionado em casos-extremos.md §8.2. Valor calculado normalmente.
    $r = calcCicloFinanceiro(['Q10' => 130, 'Q11' => 15, 'Q12' => 15]);
    expect($r['valor'])->toBe(-100.0)->and($r['farol'])->toBe('verde');
});

// ---------- casos extremos ----------

it('indisponível — Q11 (PME) ausente', function () {
    $r = calcCicloFinanceiro(['Q10' => 30, 'Q11' => null, 'Q12' => 30]);
    expect($r['valor'])->toBeNull()->and($r['motivo'])->toBe('indisponivel:prazo_faltante');
});

it('indisponível — Q12 (PMR) ausente', function () {
    $r = calcCicloFinanceiro(['Q10' => 30, 'Q11' => 30, 'Q12' => null]);
    expect($r['valor'])->toBeNull()->and($r['motivo'])->toBe('indisponivel:prazo_faltante');
});

it('indisponível — Q10 (PMC) ausente', function () {
    $r = calcCicloFinanceiro(['Q10' => null, 'Q11' => 30, 'Q12' => 30]);
    expect($r['valor'])->toBeNull()->and($r['motivo'])->toBe('indisponivel:prazo_faltante');
});

it('indisponível — todos os prazos ausentes', function () {
    $r = calcCicloFinanceiro([]);
    expect($r['valor'])->toBeNull()->and($r['motivo'])->toBe('indisponivel:prazo_faltante');
});

it('indisponível — algum prazo não-numérico', function () {
    $r = calcCicloFinanceiro(['Q10' => 30, 'Q11' => 'abc', 'Q12' => 30]);
    expect($r['valor'])->toBeNull()->and($r['motivo'])->toBe('indisponivel:prazo_faltante');
});

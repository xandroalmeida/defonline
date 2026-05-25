<?php

declare(strict_types=1);

use App\Domain\Motor\Calculos\DreAdaptada;
use App\Domain\Motor\Indicadores\Pme;

function calcPme(array $payload): array
{
    return (new Pme)->calcular($payload, new DreAdaptada($payload))->toArray();
}

// Anexo E (Indústria) PME: ≤ 30 verde · 30.01–60 amarelo · > 60 vermelho.

it('verde — PME 0 dias (extremo válido — produção sob encomenda)', function () {
    $r = calcPme(['Q11' => 0]);
    expect($r['valor'])->toBe(0.0)->and($r['farol'])->toBe('verde');
});

it('verde — PME 15 dias', function () {
    $r = calcPme(['Q11' => 15]);
    expect($r['valor'])->toBe(15.0)->and($r['farol'])->toBe('verde');
});

it('verde — fronteira 30 (≤ 30)', function () {
    $r = calcPme(['Q11' => 30]);
    expect($r['valor'])->toBe(30.0)->and($r['farol'])->toBe('verde');
});

it('amarelo — fronteira 30.01 (> 30)', function () {
    $r = calcPme(['Q11' => '30.01']);
    expect($r['valor'])->toBeGreaterThan(30.0);
    expect($r['farol'])->toBe('amarelo');
});

it('amarelo — típico 45 dias', function () {
    $r = calcPme(['Q11' => 45]);
    expect($r['valor'])->toBe(45.0)->and($r['farol'])->toBe('amarelo');
});

it('amarelo — fronteira superior 60', function () {
    $r = calcPme(['Q11' => 60]);
    expect($r['valor'])->toBe(60.0)->and($r['farol'])->toBe('amarelo');
});

it('vermelho — fronteira 60.01 (> 60)', function () {
    $r = calcPme(['Q11' => '60.01']);
    expect($r['valor'])->toBeGreaterThan(60.0);
    expect($r['farol'])->toBe('vermelho');
});

it('vermelho — típico 90 dias', function () {
    $r = calcPme(['Q11' => 90]);
    expect($r['valor'])->toBe(90.0)->and($r['farol'])->toBe('vermelho');
});

it('vermelho — atípico mas matematicamente válido 400 dias', function () {
    // > 365 dispara validação cruzada no quiz §6.8; se passar, motor classifica.
    $r = calcPme(['Q11' => 400]);
    expect($r['valor'])->toBe(400.0)->and($r['farol'])->toBe('vermelho');
});

// ---------- casos extremos ----------

it('indisponível — Q11 ausente (PME faltante)', function () {
    $r = calcPme(['Q11' => null]);
    expect($r['valor'])->toBeNull()
        ->and($r['farol'])->toBe('nenhum')
        ->and($r['motivo'])->toBe('indisponivel:pme_faltante');
});

it('indisponível — Q11 não-numérico', function () {
    $r = calcPme(['Q11' => 'abc']);
    expect($r['valor'])->toBeNull()->and($r['motivo'])->toBe('indisponivel:pme_faltante');
});

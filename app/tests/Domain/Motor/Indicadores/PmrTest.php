<?php

declare(strict_types=1);

use App\Domain\Motor\Calculos\DreAdaptada;
use App\Domain\Motor\Indicadores\Pmr;

function calcPmr(array $payload): array
{
    return (new Pmr)->calcular($payload, new DreAdaptada($payload))->toArray();
}

// Anexo E PMR: ≤ 30 verde · 30.01–60 amarelo · > 60 vermelho.

it('verde — PMR 15 dias', function () {
    $r = calcPmr(['Q12' => 15]);
    expect($r['valor'])->toBe(15.0)->and($r['farol'])->toBe('verde');
});

it('verde — PMR 0 dias (extremo válido — pagamento à vista)', function () {
    $r = calcPmr(['Q12' => 0]);
    expect($r['valor'])->toBe(0.0)->and($r['farol'])->toBe('verde');
});

it('verde — fronteira 30 (≤ 30)', function () {
    $r = calcPmr(['Q12' => 30]);
    expect($r['valor'])->toBe(30.0)->and($r['farol'])->toBe('verde');
});

it('amarelo — fronteira 30.01 (> 30)', function () {
    $r = calcPmr(['Q12' => '30.01']);
    expect($r['valor'])->toBeGreaterThan(30.0);
    expect($r['farol'])->toBe('amarelo');
});

it('amarelo — típico 45 dias', function () {
    $r = calcPmr(['Q12' => 45]);
    expect($r['farol'])->toBe('amarelo');
});

it('amarelo — fronteira superior 60', function () {
    $r = calcPmr(['Q12' => 60]);
    expect($r['valor'])->toBe(60.0)->and($r['farol'])->toBe('amarelo');
});

it('vermelho — fronteira 60.01 (> 60)', function () {
    $r = calcPmr(['Q12' => '60.01']);
    expect($r['valor'])->toBeGreaterThan(60.0);
    expect($r['farol'])->toBe('vermelho');
});

it('vermelho — típico 90 dias', function () {
    $r = calcPmr(['Q12' => 90]);
    expect($r['farol'])->toBe('vermelho');
});

it('vermelho — atípico mas matematicamente válido 365+ dias', function () {
    // > 365 dispara validação cruzada no quiz §6.8; se passar, motor classifica.
    $r = calcPmr(['Q12' => 400]);
    expect($r['valor'])->toBe(400.0)->and($r['farol'])->toBe('vermelho');
});

// ---------- casos extremos ----------

it('indisponível — Q12 ausente (PMR faltante)', function () {
    $r = calcPmr(['Q12' => null]);
    expect($r['valor'])->toBeNull()
        ->and($r['farol'])->toBe('nenhum')
        ->and($r['motivo'])->toBe('indisponivel:pmr_faltante');
});

it('indisponível — Q12 não-numérico', function () {
    $r = calcPmr(['Q12' => 'abc']);
    expect($r['valor'])->toBeNull()->and($r['motivo'])->toBe('indisponivel:pmr_faltante');
});

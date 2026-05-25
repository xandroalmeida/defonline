<?php

declare(strict_types=1);

use App\Domain\Motor\Calculos\DreAdaptada;
use App\Domain\Motor\Indicadores\Pmc;

function calcPmc(array $payload): array
{
    return (new Pmc)->calcular($payload, new DreAdaptada($payload))->toArray();
}

// Anexo E PMC ("maior melhor"): > 60 verde · 30.01–60 amarelo · ≤ 30 vermelho.

it('verde — PMC 90 dias', function () {
    $r = calcPmc(['Q10' => 90]);
    expect($r['valor'])->toBe(90.0)->and($r['farol'])->toBe('verde');
});

it('verde — fronteira inferior 60.01', function () {
    $r = calcPmc(['Q10' => '60.01']);
    expect($r['valor'])->toBeGreaterThan(60.0);
    expect($r['farol'])->toBe('verde');
});

it('amarelo — fronteira superior 60 (≤ 60 → amarelo)', function () {
    $r = calcPmc(['Q10' => 60]);
    expect($r['valor'])->toBe(60.0)->and($r['farol'])->toBe('amarelo');
});

it('amarelo — típico 45 dias', function () {
    $r = calcPmc(['Q10' => 45]);
    expect($r['farol'])->toBe('amarelo');
});

it('amarelo — fronteira inferior 30.01', function () {
    $r = calcPmc(['Q10' => '30.01']);
    expect($r['valor'])->toBeGreaterThan(30.0);
    expect($r['farol'])->toBe('amarelo');
});

it('vermelho — fronteira superior 30 (≤ 30 → vermelho)', function () {
    $r = calcPmc(['Q10' => 30]);
    expect($r['valor'])->toBe(30.0)->and($r['farol'])->toBe('vermelho');
});

it('vermelho — típico 15 dias', function () {
    $r = calcPmc(['Q10' => 15]);
    expect($r['farol'])->toBe('vermelho');
});

it('vermelho — PMC 0 (paga à vista — desvantagem)', function () {
    $r = calcPmc(['Q10' => 0]);
    expect($r['valor'])->toBe(0.0)->and($r['farol'])->toBe('vermelho');
});

it('verde — atípico 365+ dias (passou validação cruzada no quiz)', function () {
    $r = calcPmc(['Q10' => 400]);
    expect($r['valor'])->toBe(400.0)->and($r['farol'])->toBe('verde');
});

// ---------- casos extremos ----------

it('indisponível — Q10 ausente (PMC faltante)', function () {
    $r = calcPmc(['Q10' => null]);
    expect($r['valor'])->toBeNull()->and($r['motivo'])->toBe('indisponivel:pmc_faltante');
});

it('indisponível — Q10 não-numérico', function () {
    $r = calcPmc(['Q10' => 'xyz']);
    expect($r['valor'])->toBeNull()->and($r['motivo'])->toBe('indisponivel:pmc_faltante');
});

it('indisponível — Q10 string vazia (canonicalizer normaliza p/ null)', function () {
    $r = calcPmc(['Q10' => '']);
    // is_numeric('') = false → cai em PMC_FALTANTE.
    expect($r['valor'])->toBeNull()->and($r['motivo'])->toBe('indisponivel:pmc_faltante');
});

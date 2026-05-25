<?php

declare(strict_types=1);

use App\Domain\Motor\Calculos\DreAdaptada;
use App\Domain\Motor\Indicadores\Inadimplencia;

function calcInadimplencia(array $payload): array
{
    return (new Inadimplencia)->calcular($payload, new DreAdaptada($payload))->toArray();
}

// Anexo E (Indústria) Inadimplência: ≤ 3% verde · 3.01–5% amarelo · > 5% vermelho.

it('verde — inadimplência 0% (extremo válido — carteira sem perdas)', function () {
    $r = calcInadimplencia(['Q13' => 0]);
    expect($r['valor'])->toBe(0.0)->and($r['farol'])->toBe('verde');
});

it('verde — inadimplência 2%', function () {
    $r = calcInadimplencia(['Q13' => '2']);
    expect($r['valor'])->toBe(2.0)->and($r['farol'])->toBe('verde');
});

it('verde — fronteira 3% (≤ 3)', function () {
    $r = calcInadimplencia(['Q13' => 3]);
    expect($r['valor'])->toBe(3.0)->and($r['farol'])->toBe('verde');
});

it('amarelo — fronteira 3.01% (> 3)', function () {
    $r = calcInadimplencia(['Q13' => '3.01']);
    expect($r['valor'])->toBeGreaterThan(3.0);
    expect($r['farol'])->toBe('amarelo');
});

it('amarelo — típico 4%', function () {
    $r = calcInadimplencia(['Q13' => 4]);
    expect($r['valor'])->toBe(4.0)->and($r['farol'])->toBe('amarelo');
});

it('amarelo — fronteira superior 5%', function () {
    $r = calcInadimplencia(['Q13' => 5]);
    expect($r['valor'])->toBe(5.0)->and($r['farol'])->toBe('amarelo');
});

it('vermelho — fronteira 5.01% (> 5)', function () {
    $r = calcInadimplencia(['Q13' => '5.01']);
    expect($r['valor'])->toBeGreaterThan(5.0);
    expect($r['farol'])->toBe('vermelho');
});

it('vermelho — típico 10%', function () {
    $r = calcInadimplencia(['Q13' => 10]);
    expect($r['valor'])->toBe(10.0)->and($r['farol'])->toBe('vermelho');
});

it('vermelho — fronteira superior válida 100%', function () {
    // 100% é válido (carteira inteira inadimplente); > 100% que é invariante violada.
    $r = calcInadimplencia(['Q13' => 100]);
    expect($r['valor'])->toBe(100.0)->and($r['farol'])->toBe('vermelho');
});

// ---------- casos extremos ----------

it('indisponível — Q13 ausente (inadimplência faltante)', function () {
    $r = calcInadimplencia(['Q13' => null]);
    expect($r['valor'])->toBeNull()
        ->and($r['farol'])->toBe('nenhum')
        ->and($r['motivo'])->toBe('indisponivel:inadimplencia_faltante');
});

it('indisponível — Q13 não-numérico', function () {
    $r = calcInadimplencia(['Q13' => 'NaN']);
    expect($r['valor'])->toBeNull()->and($r['motivo'])->toBe('indisponivel:inadimplencia_faltante');
});

it('exception — Q13 > 100% (invariante violada — quiz deveria ter bloqueado)', function () {
    calcInadimplencia(['Q13' => '101']);
})->throws(DomainException::class, 'Inadimplência (Q13) inválida');

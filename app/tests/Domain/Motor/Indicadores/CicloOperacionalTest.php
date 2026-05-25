<?php

declare(strict_types=1);

use App\Domain\Motor\Calculos\DreAdaptada;
use App\Domain\Motor\Indicadores\CicloOperacional;

function calcCicloOperacional(array $payload): array
{
    return (new CicloOperacional)->calcular($payload, new DreAdaptada($payload))->toArray();
}

// Informativo — sem farol. Valor = Q11 + Q12.

it('informativo — PME 15 + PMR 15 = 30 dias', function () {
    $r = calcCicloOperacional(['Q11' => 15, 'Q12' => 15]);
    expect($r['valor'])->toBe(30.0)
        ->and($r['farol'])->toBe('nenhum')
        ->and($r['motivo'])->toBeNull()
        ->and($r['mensagem'])->toBe(CicloOperacional::MSG_INFORMATIVO);
});

it('informativo — PME 30 + PMR 45 = 75 dias', function () {
    $r = calcCicloOperacional(['Q11' => 30, 'Q12' => 45]);
    expect($r['valor'])->toBe(75.0)->and($r['farol'])->toBe('nenhum');
});

it('informativo — PME 0 + PMR 0 = 0 dias (extremo válido)', function () {
    $r = calcCicloOperacional(['Q11' => 0, 'Q12' => 0]);
    expect($r['valor'])->toBe(0.0)->and($r['farol'])->toBe('nenhum');
});

it('informativo — PME 60 + PMR 90 = 150 dias (ciclo longo)', function () {
    $r = calcCicloOperacional(['Q11' => 60, 'Q12' => 90]);
    expect($r['valor'])->toBe(150.0)->and($r['farol'])->toBe('nenhum');
});

it('informativo — aceita string numérica em Q11/Q12 (consistente com canonicalização)', function () {
    $r = calcCicloOperacional(['Q11' => '20', 'Q12' => '40']);
    expect($r['valor'])->toBe(60.0)->and($r['farol'])->toBe('nenhum');
});

it('informativo — PME 365 + PMR 365 = 730 (extremo atípico mas calculável)', function () {
    $r = calcCicloOperacional(['Q11' => 365, 'Q12' => 365]);
    expect($r['valor'])->toBe(730.0)->and($r['farol'])->toBe('nenhum');
});

it('informativo — Q11 grande + Q12 pequeno', function () {
    $r = calcCicloOperacional(['Q11' => 100, 'Q12' => 5]);
    expect($r['valor'])->toBe(105.0)->and($r['farol'])->toBe('nenhum');
});

// ---------- casos extremos ----------

it('indisponível — Q11 ausente (PME)', function () {
    $r = calcCicloOperacional(['Q11' => null, 'Q12' => 30]);
    expect($r['valor'])->toBeNull()
        ->and($r['farol'])->toBe('nenhum')
        ->and($r['motivo'])->toBe('indisponivel:ciclo_operacional_prazo_faltante');
});

it('indisponível — Q12 ausente (PMR)', function () {
    $r = calcCicloOperacional(['Q11' => 30, 'Q12' => null]);
    expect($r['valor'])->toBeNull()
        ->and($r['motivo'])->toBe('indisponivel:ciclo_operacional_prazo_faltante');
});

it('indisponível — ambos ausentes', function () {
    $r = calcCicloOperacional(['Q11' => null, 'Q12' => null]);
    expect($r['valor'])->toBeNull()
        ->and($r['motivo'])->toBe('indisponivel:ciclo_operacional_prazo_faltante');
});

it('indisponível — Q11 não-numérico', function () {
    $r = calcCicloOperacional(['Q11' => 'abc', 'Q12' => 30]);
    expect($r['valor'])->toBeNull()
        ->and($r['motivo'])->toBe('indisponivel:ciclo_operacional_prazo_faltante');
});

it('indisponível — Q12 não-numérico', function () {
    $r = calcCicloOperacional(['Q11' => 30, 'Q12' => 'NaN']);
    expect($r['valor'])->toBeNull()
        ->and($r['motivo'])->toBe('indisponivel:ciclo_operacional_prazo_faltante');
});

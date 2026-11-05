<?php

declare(strict_types=1);

use App\Domain\Motor\Calculos\DreAdaptada;
use App\Domain\Motor\Indicadores\NcgAbsoluto;
use Tests\Support\RecomendacoesCongeladas;

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

// ---------- texto da matriz: positivo ou negativo (PDR-018) ----------

function textoNcg(string $situacao): string
{
    return RecomendacoesCongeladas::matriz()['ncg_absoluto'][$situacao];
}

it('NCG negativo usa o texto de negativo', function () {
    $r = calcNcgAbs(pBaseNa(['Q03' => '20000', 'Q04' => '10000', 'Q07' => '50000']));
    expect($r['valor'])->toBe(-20000.0)
        ->and($r['mensagem'])->toBe(textoNcg('negativa'));
});

it('NCG = 0 exato conta como negativo', function () {
    $r = calcNcgAbs(pBaseNa(['Q03' => '20000', 'Q04' => '10000', 'Q07' => '30000']));
    expect($r['valor'])->toBe(0.0)
        ->and($r['mensagem'])->toBe(textoNcg('negativa'));
});

it('NCG logo acima de zero usa o texto de positivo', function () {
    $r = calcNcgAbs(pBaseNa(['Q03' => '0.01', 'Q04' => '0', 'Q07' => '0']));
    expect($r['mensagem'])->toBe(textoNcg('positiva'));
});

it('NCG positivo usa o mesmo texto, seja qual for a proporção sobre as vendas', function (array $overrides) {
    expect(calcNcgAbs(pBaseNa($overrides))['mensagem'])->toBe(textoNcg('positiva'));
})->with([
    'até 10 meses de vendas' => [['Q03' => '1000000', 'Q04' => '0', 'Q07' => '0']],
    'acima de 10 meses de vendas' => [['Q03' => '1000001', 'Q04' => '0', 'Q07' => '0']],
    'vendas ausentes' => [['Q09' => null]],
    'vendas zeradas' => [['Q09' => '0']],
]);

it('NCG negativo com vendas ausentes continua negativo', function () {
    $r = calcNcgAbs(pBaseNa(['Q03' => '0', 'Q04' => '0', 'Q07' => '10000', 'Q09' => null]));
    expect($r['valor'])->toBe(-10000.0)
        ->and($r['mensagem'])->toBe(textoNcg('negativa'));
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

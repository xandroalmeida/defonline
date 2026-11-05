<?php

declare(strict_types=1);

use App\Domain\Drm\Grupo;
use App\Domain\Drm\Lancamento;
use App\Domain\Drm\Modo;
use App\Domain\Drm\PreenchimentoInicial;
use App\Domain\Drm\Setor;

/*
|-------------------------------------------------------------------------
| STORY-047 CA-5 — semente a partir do lançamento anterior (DDR-003).
|-------------------------------------------------------------------------
*/

function payloadAnterior(Modo $modo, Setor $setor = Setor::Comercio): array
{
    $modos = array_fill_keys(array_map(fn (Grupo $g) => $g->value, Grupo::cases()), $modo);
    $texto = ['D01' => '84.300,00', 'D02' => '5.058,00', 'D40' => '4.000,00', 'D41' => '', 'D42' => ''];
    foreach (Grupo::cases() as $g) {
        foreach (Lancamento::camposDoGrupo($g, $setor, $modos) as $campo) {
            $texto[$campo] = '10,00';
        }
    }
    $texto['D16'] = '12.400,00';

    return Lancamento::deTexto($setor, $modos, $texto)->payload();
}

it('herda tudo e marca como repetido quando os modos não mudaram', function () {
    $modos = array_fill_keys(array_map(fn (Grupo $g) => $g->value, Grupo::cases()), Modo::Detalhado);
    $semente = PreenchimentoInicial::de(Setor::Comercio, $modos, payloadAnterior(Modo::Detalhado));

    expect($semente->valores['D01'])->toBe('84.300,00')
        ->and($semente->valores['D16'])->toBe('12.400,00')
        ->and($semente->valores['D41'])->toBe('0,00')
        ->and($semente->repetidos)->toContain('D01', 'D16', 'D40')
        ->and($semente->repetidos)->toHaveCount(count($semente->valores))
        ->and($semente->totalAnterior)->toBe([])
        ->and($semente->somados)->toBe([]);
});

it('não herda o que o mês anterior não coletou (campo suprimido)', function () {
    $modos = array_fill_keys(array_map(fn (Grupo $g) => $g->value, Grupo::cases()), Modo::Detalhado);
    $semente = PreenchimentoInicial::de(Setor::Servicos, $modos, payloadAnterior(Modo::Detalhado, Setor::Servicos));

    expect($semente->valores)->not->toHaveKey('D04')->not->toHaveKey('D05');
});

it('detalhado → agregado: o total vem da soma dos itens do mês anterior', function () {
    $modos = array_fill_keys(array_map(fn (Grupo $g) => $g->value, Grupo::cases()), Modo::Agregado);
    $semente = PreenchimentoInicial::de(Setor::Comercio, $modos, payloadAnterior(Modo::Detalhado));

    // 20 itens de R$ 10,00 + salários de R$ 12.400,00 (substitui o item D16).
    expect($semente->valores['D15'])->toBe('12.600,00')
        ->and($semente->valores['D03'])->toBe('30,00')   // comércio: D04, D06, D07
        ->and($semente->repetidos)->toContain('D15')
        ->and($semente->somados)->toBe(['custo', 'vendas', 'administrativas', 'financeiras'])
        ->and($semente->valores)->not->toHaveKey('D16');
});

it('agregado → detalhado é herança parcial: itens vazios e só o total anterior é informado', function () {
    $modos = array_fill_keys(array_map(fn (Grupo $g) => $g->value, Grupo::cases()), Modo::Detalhado);
    $semente = PreenchimentoInicial::de(Setor::Comercio, $modos, payloadAnterior(Modo::Agregado));

    expect($semente->valores)->not->toHaveKey('D16')->not->toHaveKey('D04')
        ->and($semente->totalAnterior)->toBe(['custo' => 1000, 'vendas' => 1000, 'administrativas' => 1000, 'financeiras' => 1000])
        ->and($semente->repetidos)->toBe(['D01', 'D02', 'D40', 'D41', 'D42']);
});

it('modos misturados tratam cada grupo por si', function () {
    $modos = [...array_fill_keys(array_map(fn (Grupo $g) => $g->value, Grupo::cases()), Modo::Detalhado), 'custo' => Modo::Agregado];
    $semente = PreenchimentoInicial::de(Setor::Comercio, $modos, payloadAnterior(Modo::Detalhado));

    expect($semente->somados)->toBe(['custo'])
        ->and($semente->valores)->toHaveKey('D03')->toHaveKey('D09');
});

it('sem modos no payload anterior assume detalhado', function () {
    $anterior = payloadAnterior(Modo::Detalhado);
    unset($anterior['modos']);
    $modos = array_fill_keys(array_map(fn (Grupo $g) => $g->value, Grupo::cases()), Modo::Detalhado);

    expect(PreenchimentoInicial::de(Setor::Comercio, $modos, $anterior)->somados)->toBe([]);
});

it('campo ausente no payload anterior simplesmente não é herdado', function () {
    $anterior = payloadAnterior(Modo::Detalhado);
    unset($anterior['D41'], $anterior['D16']);
    $modos = array_fill_keys(array_map(fn (Grupo $g) => $g->value, Grupo::cases()), Modo::Detalhado);
    $semente = PreenchimentoInicial::de(Setor::Comercio, $modos, $anterior);

    expect($semente->valores)->not->toHaveKey('D41')->not->toHaveKey('D16')->toHaveKey('D42');
});

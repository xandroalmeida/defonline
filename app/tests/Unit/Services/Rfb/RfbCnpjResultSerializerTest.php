<?php

declare(strict_types=1);

use App\Domain\Rfb\RfbCnpjResult;
use App\Domain\SituacaoCadastral;
use App\Services\Rfb\RfbCnpjResultSerializer;
use Illuminate\Support\Carbon;

/**
 * UnitPure — serializador para cache (STORY-015 CA-6).
 *
 * Garante roundtrip exato: o que volta do cache reabre nos mesmos campos do
 * DTO original (mesmo enum, mesma data, mesma UF).
 */
it('faz roundtrip do DTO completo', function () {
    $original = new RfbCnpjResult(
        razaoSocial: 'Marcenaria Roberto LTDA',
        nomeFantasia: 'Marcenaria Roberto',
        cnae: '1622699',
        municipio: 'São Paulo',
        uf: 'SP',
        situacaoCadastral: SituacaoCadastral::Ativa,
        dataFundacao: Carbon::create(2010, 3, 15),
        fonteProvedor: 'cnpja',
        consultadoAt: Carbon::create(2026, 5, 23, 10, 30, 0),
    );

    $reconstruido = RfbCnpjResultSerializer::fromArray(
        RfbCnpjResultSerializer::toArray($original),
    );

    expect($reconstruido->razaoSocial)->toBe($original->razaoSocial);
    expect($reconstruido->nomeFantasia)->toBe($original->nomeFantasia);
    expect($reconstruido->cnae)->toBe($original->cnae);
    expect($reconstruido->municipio)->toBe($original->municipio);
    expect($reconstruido->uf)->toBe($original->uf);
    expect($reconstruido->situacaoCadastral)->toBe($original->situacaoCadastral);
    expect($reconstruido->dataFundacao?->format('Y-m-d'))->toBe('2010-03-15');
    expect($reconstruido->fonteProvedor)->toBe('cnpja');
    expect($reconstruido->consultadoAt->equalTo($original->consultadoAt))->toBeTrue();
});

it('faz roundtrip preservando nullables', function () {
    $original = new RfbCnpjResult(
        razaoSocial: 'EI sem fantasia',
        nomeFantasia: null,
        cnae: null,
        municipio: 'Recife',
        uf: 'PE',
        situacaoCadastral: SituacaoCadastral::Suspensa,
        dataFundacao: null,
        fonteProvedor: 'receitaws',
        consultadoAt: Carbon::create(2026, 1, 1),
    );

    $r = RfbCnpjResultSerializer::fromArray(
        RfbCnpjResultSerializer::toArray($original),
    );

    expect($r->nomeFantasia)->toBeNull();
    expect($r->cnae)->toBeNull();
    expect($r->dataFundacao)->toBeNull();
    expect($r->situacaoCadastral)->toBe(SituacaoCadastral::Suspensa);
});

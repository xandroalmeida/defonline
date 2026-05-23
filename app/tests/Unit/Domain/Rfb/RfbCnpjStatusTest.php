<?php

declare(strict_types=1);

use App\Domain\Rfb\RfbCnpjStatus;

/**
 * UnitPure — enum de status da consulta RFB (STORY-015 CA-1/CA-4).
 */
it('classifica sucesso como ehSucesso=true e ehErroDoProvedor=false', function () {
    expect(RfbCnpjStatus::Sucesso->ehSucesso())->toBeTrue();
    expect(RfbCnpjStatus::Sucesso->ehErroDoProvedor())->toBeFalse();
});

it('classifica cnpj_inexistente como nem sucesso nem erro do provedor', function () {
    expect(RfbCnpjStatus::CnpjInexistente->ehSucesso())->toBeFalse();
    expect(RfbCnpjStatus::CnpjInexistente->ehErroDoProvedor())->toBeFalse();
});

it('classifica timeout/erro_5xx/erro_rede como erro do provedor', function (RfbCnpjStatus $status) {
    expect($status->ehSucesso())->toBeFalse();
    expect($status->ehErroDoProvedor())->toBeTrue();
})->with([
    [RfbCnpjStatus::Timeout],
    [RfbCnpjStatus::Erro5xx],
    [RfbCnpjStatus::ErroRede],
]);

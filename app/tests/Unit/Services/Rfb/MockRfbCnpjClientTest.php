<?php

declare(strict_types=1);

use App\Domain\Rfb\RfbCnpjStatus;
use App\Services\Rfb\MockRfbCnpjClient;
use App\Services\Rfb\RfbCnpjFalhouException;
use Database\Factories\EmpresaAnalisadaFactory;

/**
 * UnitPure — mock determinístico da consulta RFB (STORY-015 CA-1).
 *
 * Os gatilhos por prefixo permitem que toda a baterria Feature/Dusk fabrique
 * cenários sem mock de framework, só com a abstração de domínio.
 */
beforeEach(function () {
    $this->client = new MockRfbCnpjClient;
});

it('dispara CnpjInexistente para CNPJs com prefixo 00 (CA-1)', function () {
    $cnpj = EmpresaAnalisadaFactory::cnpjComRaiz('00112233');

    $this->client->consultarCnpj($cnpj);
})->throws(RfbCnpjFalhouException::class);

it('reporta o status correto para cada gatilho de prefixo', function (string $raiz, RfbCnpjStatus $esperado) {
    $cnpj = EmpresaAnalisadaFactory::cnpjComRaiz($raiz);

    try {
        $this->client->consultarCnpj($cnpj);
        $this->fail('deveria ter lançado RfbCnpjFalhouException');
    } catch (RfbCnpjFalhouException $e) {
        expect($e->status)->toBe($esperado);
        expect($e->provedor)->toBe('mock');
    }
})->with([
    ['00112233', RfbCnpjStatus::CnpjInexistente],
    ['99887766', RfbCnpjStatus::Timeout],
    ['88776655', RfbCnpjStatus::Erro5xx],
    ['77665544', RfbCnpjStatus::ErroRede],
]);

it('retorna dados sintéticos determinísticos para o caminho feliz (mesmo CNPJ → mesmos dados)', function () {
    $cnpj = EmpresaAnalisadaFactory::cnpjComRaiz('12345678');

    $a = $this->client->consultarCnpj($cnpj);
    $b = $this->client->consultarCnpj($cnpj);

    expect($a->razaoSocial)->toBe($b->razaoSocial);
    expect($a->cnae)->toBe($b->cnae);
    expect($a->uf)->toBe($b->uf);
    expect($a->municipio)->toBe($b->municipio);
    expect($a->situacaoCadastral)->toBe($b->situacaoCadastral);
});

it('retorna campos preenchidos para o caminho feliz', function () {
    $cnpj = EmpresaAnalisadaFactory::cnpjComRaiz('12345678');

    $r = $this->client->consultarCnpj($cnpj);

    expect($r->razaoSocial)->not->toBe('');
    expect($r->cnae)->toMatch('/^\d{7}$/');
    expect($r->municipio)->not->toBe('');
    expect($r->uf)->toHaveLength(2);
    expect($r->fonteProvedor)->toBe('mock');
    expect($r->dataFundacao)->not->toBeNull();
});

it('rejeita CNPJ com DV inválido (defesa em camadas)', function () {
    $this->client->consultarCnpj('11111111111111');
})->throws(RfbCnpjFalhouException::class);

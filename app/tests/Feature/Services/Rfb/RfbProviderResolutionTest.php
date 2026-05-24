<?php

declare(strict_types=1);

use App\Services\Rfb\CnpjaRfbCnpjClient;
use App\Services\Rfb\MockRfbCnpjClient;
use App\Services\Rfb\ReceitawsRfbCnpjClient;
use App\Services\Rfb\RfbCnpjClient;

/**
 * STORY-018 CA-4 — bind condicional do RfbCnpjClient via `services.rfb.provider`.
 *
 * O container precisa ser limpo entre os cenários porque o bind é singleton — uma
 * vez resolvido, retornaria a mesma instância e mascaria a troca de config.
 */
function resolverRfbCom(string $provider): RfbCnpjClient
{
    config()->set('services.rfb.provider', $provider);
    app()->forgetInstance(RfbCnpjClient::class);

    return app(RfbCnpjClient::class);
}

it('resolve MockRfbCnpjClient quando provider=mock', function () {
    expect(resolverRfbCom('mock'))->toBeInstanceOf(MockRfbCnpjClient::class);
});

it('resolve CnpjaRfbCnpjClient quando provider=cnpja (CA-4)', function () {
    expect(resolverRfbCom('cnpja'))->toBeInstanceOf(CnpjaRfbCnpjClient::class);
});

it('resolve ReceitawsRfbCnpjClient quando provider=receitaws (CA-4)', function () {
    expect(resolverRfbCom('receitaws'))->toBeInstanceOf(ReceitawsRfbCnpjClient::class);
});

it('lança InvalidArgumentException nomeando o provider quando valor é desconhecido (CA-4)', function () {
    try {
        resolverRfbCom('cpnja'); // typo proposital
        $this->fail('deveria ter lançado');
    } catch (InvalidArgumentException $e) {
        expect($e->getMessage())->toContain("'cpnja'");
        expect($e->getMessage())->toContain('mock|cnpja|receitaws');
    }
});

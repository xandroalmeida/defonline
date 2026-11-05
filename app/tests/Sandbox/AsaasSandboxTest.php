<?php

declare(strict_types=1);

use App\Domain\Cobranca\MeioPagamento;
use App\Domain\Cobranca\PedidoCheckout;
use App\Domain\Cobranca\ReferenciaProvedor;
use App\Infrastructure\Cobranca\Asaas\AsaasPaymentProvider;
use Illuminate\Support\Str;

/**
 * STORY-076 CA-8 — teste SOB DEMANDA contra o sandbox do Asaas (nunca produção). Fora da suíte
 * padrão (a pasta não está em nenhum testsuite) e do portão de release:
 *
 *   docker compose exec -T web-test ./vendor/bin/pest tests/Sandbox --group=asaas-sandbox
 *
 * Lê `ASAAS_SANDBOX_API_KEY` do ambiente do container. Sem ela, é pulado. Abre e cancela um
 * checkout Pix de R$ 5,00; não paga nada.
 */
function _asaasSandbox(): AsaasPaymentProvider
{
    return new AsaasPaymentProvider('https://api-sandbox.asaas.com/v3', env('ASAAS_SANDBOX_API_KEY'), 'tok-nao-usado-aqui', 30);
}

it('abre e cancela um checkout Pix no sandbox', function () {
    if (! is_string(env('ASAAS_SANDBOX_API_KEY')) || env('ASAAS_SANDBOX_API_KEY') === '') {
        $this->markTestSkipped('ASAAS_SANDBOX_API_KEY não definida.');
    }

    $provedor = _asaasSandbox();
    $sessao = $provedor->startCheckout(new PedidoCheckout(
        (string) Str::uuid7(), 'Teste STORY-076', 500, [MeioPagamento::Pix], 30,
        'https://example.com/ok', 'https://example.com/cancelar', 'https://example.com/expirou',
    ));

    expect($sessao->url)->toStartWith('https://sandbox.asaas.com/');

    $provedor->cancelCheckout(new ReferenciaProvedor('asaas', $sessao->referencia->id));
})->group('asaas-sandbox');

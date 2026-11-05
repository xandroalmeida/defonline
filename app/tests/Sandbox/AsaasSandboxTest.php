<?php

declare(strict_types=1);

use App\Domain\Cobranca\CicloCobranca;
use App\Domain\Cobranca\EstadoCobranca;
use App\Domain\Cobranca\MeioPagamento;
use App\Domain\Cobranca\PedidoCheckout;
use App\Domain\Cobranca\ReferenciaProvedor;
use App\Domain\Cobranca\ResultadoReembolso;
use App\Infrastructure\Cobranca\Asaas\AsaasPaymentProvider;
use Illuminate\Support\Facades\Http;
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

it('abre e cancela um checkout de assinatura com cartão (RECURRENT) no sandbox', function () {
    if (! is_string(env('ASAAS_SANDBOX_API_KEY')) || env('ASAAS_SANDBOX_API_KEY') === '') {
        $this->markTestSkipped('ASAAS_SANDBOX_API_KEY não definida.');
    }

    $provedor = _asaasSandbox();

    foreach ([CicloCobranca::Mensal, CicloCobranca::Anual] as $ciclo) {
        $sessao = $provedor->startCheckout(new PedidoCheckout(
            (string) Str::uuid7(), 'Teste STORY-077 '.$ciclo->value, 4990, [MeioPagamento::Cartao], 60,
            'https://example.com/ok', 'https://example.com/cancelar', 'https://example.com/expirou', $ciclo,
        ));

        expect($sessao->url)->toStartWith('https://sandbox.asaas.com/');

        $provedor->cancelCheckout(new ReferenciaProvedor('asaas', $sessao->referencia->id));
    }
})->group('asaas-sandbox');

/**
 * STORY-079 — o que a régua e a conciliação usam, no sandbox de verdade: lista as cobranças de uma
 * assinatura (com paginação por query string), reagenda a MESMA cobrança para hoje (retentativa) sem
 * criar cobrança nova, lista um checkout aberto (vazio) e cancela a recorrência. A assinatura é criada
 * direto na API com o cartão de TESTE público do Asaas, só neste teste e só no sandbox.
 */
it('lista, retenta e cancela uma assinatura no sandbox', function () {
    $chave = env('ASAAS_SANDBOX_API_KEY');

    if (! is_string($chave) || $chave === '') {
        $this->markTestSkipped('ASAAS_SANDBOX_API_KEY não definida.');
    }

    $api = fn () => Http::baseUrl('https://api-sandbox.asaas.com/v3')->withHeaders(['access_token' => $chave, 'User-Agent' => 'DEFOnline-story079'])->acceptJson()->asJson();
    $cliente = $api()->post('/customers', ['name' => 'Teste STORY-079', 'cpfCnpj' => '24971563792', 'email' => 'sandbox@example.com'])->json('id');
    $assinaturaId = null;

    try {
        $assinaturaId = $api()->post('/subscriptions', [
            'customer' => $cliente, 'billingType' => 'CREDIT_CARD', 'value' => 49.9, 'cycle' => 'MONTHLY', 'description' => 'Teste STORY-079',
            'nextDueDate' => now('America/Sao_Paulo')->addDay()->format('Y-m-d'),
            'creditCard' => ['holderName' => 'Teste Sandbox', 'number' => '4539656048392220', 'expiryMonth' => '12', 'expiryYear' => '2030', 'ccv' => '123'],
            'creditCardHolderInfo' => ['name' => 'Teste Sandbox', 'email' => 'sandbox@example.com', 'cpfCnpj' => '24971563792', 'postalCode' => '89223005', 'addressNumber' => '277', 'phone' => '4738010919'],
            'remoteIp' => '203.0.113.10',
        ])->json('id');
        expect($assinaturaId)->toBeString();

        $provedor = _asaasSandbox();
        $cobrancas = $provedor->listSubscriptionCharges(new ReferenciaProvedor('asaas', $assinaturaId), new DateTimeImmutable('-1 day'));

        expect($cobrancas)->toHaveCount(1)
            ->and($cobrancas[0]->estado)->toBe(EstadoCobranca::Pendente)
            ->and($cobrancas[0]->assinaturaId)->toBe($assinaturaId)
            ->and($cobrancas[0]->vencimento)->not->toBeNull();

        // Retentativa = reagendar a mesma cobrança: continua sendo uma só.
        $depois = $provedor->retryCharge($cobrancas[0]->referencia);
        $de = $provedor->listSubscriptionCharges(new ReferenciaProvedor('asaas', $assinaturaId), new DateTimeImmutable('-1 day'));

        expect($depois->referencia->id)->toBe($cobrancas[0]->referencia->id)
            ->and($depois->vencimento?->format('Y-m-d'))->toBe(now('America/Sao_Paulo')->format('Y-m-d'))
            ->and($de)->toHaveCount(1);

        // Checkout aberto e sem pagamento: nenhuma cobrança.
        $sessao = $provedor->startCheckout(new PedidoCheckout((string) Str::uuid7(), 'Teste STORY-079', 500, [MeioPagamento::Pix], 30, 'https://example.com/ok', 'https://example.com/c', 'https://example.com/e'));
        expect($provedor->listCheckoutCharges($sessao->referencia))->toBe([]);
        $provedor->cancelCheckout($sessao->referencia);

        $provedor->cancelSubscription(new ReferenciaProvedor('asaas', $assinaturaId));
        $provedor->cancelSubscription(new ReferenciaProvedor('asaas', $assinaturaId)); // já cancelada: sem erro
        $assinaturaId = null;
    } finally {
        if ($assinaturaId !== null) {
            $api()->delete("/subscriptions/{$assinaturaId}");
        }

        $api()->delete("/customers/{$cliente}");
    }
})->group('asaas-sandbox');

/**
 * STORY-085 — estorno no sandbox de verdade: cartão estorna por inteiro na hora (REFUNDED), o segundo
 * pedido é recusado, e o Pix confirmado fica aguardando a autorização da ação crítica. Cobranças criadas
 * direto na API com o cartão de TESTE público do Asaas, só neste teste e só no sandbox; limpeza no fim.
 */
it('estorna cartão por inteiro, recusa o segundo estorno e deixa o Pix aguardando autorização no sandbox', function () {
    $chave = env('ASAAS_SANDBOX_API_KEY');

    if (! is_string($chave) || $chave === '') {
        $this->markTestSkipped('ASAAS_SANDBOX_API_KEY não definida.');
    }

    $api = fn () => Http::baseUrl('https://api-sandbox.asaas.com/v3')->withHeaders(['access_token' => $chave, 'User-Agent' => 'DEFOnline-story085'])->acceptJson()->asJson();
    $cliente = $api()->post('/customers', ['name' => 'Teste STORY-085', 'cpfCnpj' => '24971563792', 'email' => 'sandbox@example.com'])->json('id');
    $cobrancas = [];

    try {
        $provedor = _asaasSandbox();
        $vence = now('America/Sao_Paulo')->format('Y-m-d');

        $cartao = $api()->post('/payments', [
            'customer' => $cliente, 'billingType' => 'CREDIT_CARD', 'value' => 49.9, 'dueDate' => $vence, 'description' => 'Teste STORY-085 cartão',
            'creditCard' => ['holderName' => 'Teste Sandbox', 'number' => '4539656048392220', 'expiryMonth' => '12', 'expiryYear' => '2030', 'ccv' => '123'],
            'creditCardHolderInfo' => ['name' => 'Teste Sandbox', 'email' => 'sandbox@example.com', 'cpfCnpj' => '24971563792', 'postalCode' => '89223005', 'addressNumber' => '277', 'phone' => '4738010919'],
            'remoteIp' => '203.0.113.10',
        ])->json('id');
        $cobrancas[] = $cartao;
        expect($cartao)->toBeString();
        $ref = new ReferenciaProvedor('asaas', $cartao);
        expect($provedor->getCharge($ref)->estado)->toBe(EstadoCobranca::Paga);

        // Cobrança recém-criada: o Asaas responde "tente novamente em alguns instantes" (400) até assentar.
        for ($t = 0; $t < 12; $t++) {
            $primeiro = $provedor->refund($ref, null, 'Teste STORY-085');

            if ($primeiro->resultado !== ResultadoReembolso::Recusado) {
                break;
            }

            sleep(5);
        }
        expect($primeiro->detalhe)->toBeNull()
            ->and($primeiro->resultado)->toBe(ResultadoReembolso::Concluido)
            ->and($provedor->getCharge($ref)->estado)->toBe(EstadoCobranca::Estornada)
            ->and($provedor->refund($ref)->resultado)->toBe(ResultadoReembolso::Recusado);

        $pix = $api()->post('/payments', ['customer' => $cliente, 'billingType' => 'PIX', 'value' => 10, 'dueDate' => $vence, 'description' => 'Teste STORY-085 pix'])->json('id');
        $cobrancas[] = $pix;
        $api()->post("/sandbox/payment/{$pix}/confirm");
        $refPix = new ReferenciaProvedor('asaas', $pix);
        expect($provedor->getCharge($refPix)->estado)->toBe(EstadoCobranca::Paga);

        for ($t = 0; $t < 12; $t++) {
            $porPix = $provedor->refund($refPix, null, 'Teste STORY-085');

            if ($porPix->resultado !== ResultadoReembolso::Recusado) {
                break;
            }

            sleep(5);
        }

        expect($porPix->detalhe)->toBeNull()->and($porPix->resultado)->toBe(ResultadoReembolso::AguardandoAutorizacao);
    } finally {
        foreach ($cobrancas as $c) {
            $api()->delete("/payments/{$c}");
        }

        $api()->delete("/customers/{$cliente}");
    }
})->group('asaas-sandbox');

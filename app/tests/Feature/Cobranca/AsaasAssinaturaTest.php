<?php

declare(strict_types=1);

use App\Domain\Cobranca\CicloCobranca;
use App\Domain\Cobranca\EstadoCobranca;
use App\Domain\Cobranca\MeioPagamento;
use App\Domain\Cobranca\PaymentProvider;
use App\Domain\Cobranca\PedidoCheckout;
use App\Domain\Cobranca\ReferenciaProvedor;
use App\Domain\Cobranca\WebhookBruto;
use App\Models\Assinatura;
use App\Models\Invoice;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * STORY-077 — o adaptador do Asaas na assinatura recorrente: payload do checkout, leitura da
 * cobrança (assinatura, bandeira, 4 dígitos) e o ciclo ativação -> renovação por webhook, com a
 * rede simulada. Nada de cartão cru em lugar nenhum.
 */
beforeEach(function () {
    _asaasSimulado();
    $this->usuario = _compradorConfirmado();
});

function _pedidoRecorrente(CicloCobranca $ciclo): PedidoCheckout
{
    return new PedidoCheckout('11111111-1111-4111-8111-111111111111', 'Plano Básico · mensal', 4990, [MeioPagamento::Cartao], 60,
        'https://app/ok', 'https://app/cancelar', 'https://app/expirou', $ciclo);
}

it('abre o checkout RECURRENT só com cartão, ciclo certo e a primeira cobrança hoje (fuso do negócio)', function (CicloCobranca $ciclo, string $esperado) {
    app(PaymentProvider::class)->startCheckout(_pedidoRecorrente($ciclo));

    Http::assertSent(function (Request $r) use ($esperado) {
        $corpo = $r->data();

        return str_ends_with($r->url(), '/checkouts')
            && $corpo['billingTypes'] === ['CREDIT_CARD']
            && $corpo['chargeTypes'] === ['RECURRENT']
            && $corpo['subscription']['cycle'] === $esperado
            && $corpo['subscription']['nextDueDate'] === now('America/Sao_Paulo')->format('Y-m-d').' 00:00:00'
            && $corpo['minutesToExpire'] === 60
            && $corpo['items'][0]['value'] === 49.9
            && $corpo['externalReference'] === '11111111-1111-4111-8111-111111111111'
            && ! preg_match('/credit_?card_?(number|token)|ccv|cvv|holderName/i', json_encode($corpo));
    });
})->with([[CicloCobranca::Mensal, 'MONTHLY'], [CicloCobranca::Anual, 'YEARLY']]);

it('o checkout avulso continua DETACHED e sem assinatura', function () {
    app(PaymentProvider::class)->startCheckout(new PedidoCheckout('11111111-1111-4111-8111-111111111111', 'Pacote', 3490, [MeioPagamento::Pix], 30, 'https://a/ok', 'https://a/c', 'https://a/e'));

    Http::assertSent(fn (Request $r) => $r->data()['chargeTypes'] === ['DETACHED'] && ! array_key_exists('subscription', $r->data()));
});

it('lê da cobrança a assinatura, a bandeira e só os 4 últimos dígitos, e nunca o token', function () {
    AsaasSimulado::$cobrancas['pay_9'] = [
        'id' => 'pay_9', 'status' => 'CONFIRMED', 'deleted' => false, 'value' => 49.9, 'subscription' => 'sub_9', 'paymentDate' => '2026-11-05',
        'creditCard' => ['creditCardNumber' => '5678', 'creditCardBrand' => 'visa', 'creditCardToken' => 'segredo-do-token'],
    ];

    $c = app(PaymentProvider::class)->getCharge(new ReferenciaProvedor('asaas', 'pay_9'));

    expect($c->assinaturaId)->toBe('sub_9')->and($c->cartaoBandeira)->toBe('VISA')->and($c->cartaoUltimos4)->toBe('5678')
        ->and($c->estado)->toBe(EstadoCobranca::Paga)
        ->and(json_encode($c))->not->toContain('segredo-do-token');
});

it('se o provedor não informar o cartão, não inventa; e normaliza número mascarado', function (mixed $numero, ?string $esperado) {
    AsaasSimulado::$cobrancas['pay_9'] = ['id' => 'pay_9', 'status' => 'CONFIRMED', 'deleted' => false, 'value' => 49.9, 'creditCard' => ['creditCardNumber' => $numero]];

    $c = app(PaymentProvider::class)->getCharge(new ReferenciaProvedor('asaas', 'pay_9'));

    expect($c->cartaoUltimos4)->toBe($esperado)->and($c->cartaoBandeira)->toBeNull()->and($c->assinaturaId)->toBeNull();
})->with([[null, null], ['12', null], ['************1234', '1234'], [['x'], null]]);

it('o webhook traz o id da assinatura e o payload guardado não tem dado de cartão', function () {
    $corpo = [
        'id' => 'evt_1', 'event' => 'PAYMENT_CONFIRMED',
        'payment' => ['id' => 'pay_1', 'subscription' => 'sub_1', 'creditCard' => ['creditCardNumber' => '4242', 'creditCardToken' => 'tok']],
    ];

    $evento = app(PaymentProvider::class)->parseWebhook(new WebhookBruto(['asaas-access-token' => 'tok-asaas-de-teste-aleatorio-com-32-caracteres'], json_encode($corpo)));

    expect($evento->assinaturaId)->toBe('sub_1')->and(json_encode($evento->payload))->not->toContain('creditCard')->not->toContain('tok');
});

it('ativa pela cobrança do checkout e renova pela cobrança seguinte, mesmo herdando a nossa referência', function () {
    $invoice = _assinaturaPendente($this->usuario);
    expect($invoice->provedor)->toBe('asaas')->and($invoice->checkout_id_externo)->toBe('chk-1');

    $cobranca = fn (string $id, string $ref) => AsaasSimulado::$cobrancas[$id] = [
        'id' => $id, 'status' => 'CONFIRMED', 'deleted' => false, 'value' => $invoice->valor_centavos / 100, 'subscription' => 'sub_1',
        'checkoutSession' => $id === 'pay_1' ? 'chk-1' : null, 'externalReference' => $ref, 'paymentDate' => '2026-11-05',
        'creditCard' => ['creditCardNumber' => '4242', 'creditCardBrand' => 'MASTERCARD'],
    ];
    $cobranca('pay_1', $invoice->id);
    $cobranca('pay_2', $invoice->id);

    _postarWebhookAsaas($this, 'PAYMENT_CONFIRMED', 'evt_a', ['payment' => ['id' => 'pay_1', 'subscription' => 'sub_1', 'checkoutSession' => 'chk-1', 'externalReference' => $invoice->id]])->assertOk();
    $a = Assinatura::query()->sole();
    expect($a->id_externo)->toBe('sub_1')->and($a->cartao_bandeira)->toBe('MASTERCARD')->and($a->cartao_ultimos4)->toBe('4242')->and($a->periodos_pagos)->toBe(1);

    // RECEIVED da mesma cobrança, depois de CONFIRMED: nada muda.
    _postarWebhookAsaas($this, 'PAYMENT_RECEIVED', 'evt_b', ['payment' => ['id' => 'pay_1', 'subscription' => 'sub_1', 'checkoutSession' => 'chk-1', 'externalReference' => $invoice->id]])->assertOk();
    expect(Assinatura::query()->sole()->periodos_pagos)->toBe(1);

    _postarWebhookAsaas($this, 'PAYMENT_CONFIRMED', 'evt_c', ['payment' => ['id' => 'pay_2', 'subscription' => 'sub_1', 'externalReference' => $invoice->id]])->assertOk();
    _postarWebhookAsaas($this, 'PAYMENT_RECEIVED', 'evt_d', ['payment' => ['id' => 'pay_2', 'subscription' => 'sub_1', 'externalReference' => $invoice->id]])->assertOk();

    expect(Assinatura::query()->sole()->periodos_pagos)->toBe(2)
        ->and(Invoice::query()->where('renovacao', true)->count())->toBe(1)
        ->and(Invoice::query()->where('tipo', 'assinatura')->count())->toBe(2);
});

it('cobrança de assinatura ainda pendente no provedor não ativa nada', function () {
    $invoice = _assinaturaPendente($this->usuario);
    AsaasSimulado::$cobrancas['pay_1'] = ['id' => 'pay_1', 'status' => 'PENDING', 'deleted' => false, 'value' => $invoice->valor_centavos / 100, 'subscription' => 'sub_1', 'externalReference' => $invoice->id];

    _postarWebhookAsaas($this, 'PAYMENT_CONFIRMED', 'evt_p', ['payment' => ['id' => 'pay_1', 'subscription' => 'sub_1', 'externalReference' => $invoice->id]])->assertOk();

    expect(Assinatura::query()->count())->toBe(0)->and($invoice->fresh()->status)->toBe(EstadoCobranca::Pendente);
});

it('cobrança pendente sem assinatura e sem Invoice conhecida continua sendo alerta, não renovação', function () {
    AsaasSimulado::$cobrancas['pay_x'] = ['id' => 'pay_x', 'status' => 'PENDING', 'deleted' => false, 'value' => 1.0];

    _postarWebhookAsaas($this, 'PAYMENT_CONFIRMED', 'evt_x', ['payment' => ['id' => 'pay_x']])->assertOk();

    expect(Assinatura::query()->count())->toBe(0);
});

<?php

declare(strict_types=1);

use App\Domain\Cobranca\CicloCobranca;
use App\Domain\Cobranca\MeioPagamento;
use App\Domain\Cobranca\PaymentProvider;
use App\Domain\Cobranca\PedidoCheckout;
use App\Domain\Cobranca\ProvedorIndisponivel;
use App\Domain\Cobranca\ReferenciaProvedor;
use App\Domain\Cobranca\TipoEvento;
use App\Domain\Cobranca\WebhookBruto;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * STORY-081 — o adaptador do Asaas na gestão da assinatura: pausar, religar e cancelar a recorrência,
 * e a 1ª cobrança do cartão novo no vencimento já pago. Rede simulada; nenhum corpo com dado de cartão.
 */
beforeEach(function () {
    _configAsaasDeTeste();
    Http::preventStrayRequests();
});

/** @param array<string, mixed> $corpo */
function _webhookDoCheckout(array $corpo): WebhookBruto
{
    return new WebhookBruto(['asaas-access-token' => 'tok-asaas-de-teste-aleatorio-com-32-caracteres'], (string) json_encode($corpo));
}

it('suspende e religa a assinatura trocando só o status, e cancela com DELETE', function () {
    Http::fake(fn () => Http::response(['id' => 'sub_1'], 200));
    $p = app(PaymentProvider::class);
    $ref = new ReferenciaProvedor('asaas', 'sub_1');

    $p->suspendSubscription($ref);
    $p->resumeSubscription($ref);
    $p->cancelSubscription($ref);

    Http::assertSent(fn (Request $r) => $r->method() === 'PUT' && str_ends_with($r->url(), '/subscriptions/sub_1') && $r->data() === ['status' => 'INACTIVE']);
    Http::assertSent(fn (Request $r) => $r->method() === 'PUT' && str_ends_with($r->url(), '/subscriptions/sub_1') && $r->data() === ['status' => 'ACTIVE']);
    Http::assertSent(fn (Request $r) => $r->method() === 'DELETE' && str_ends_with($r->url(), '/subscriptions/sub_1'));
});

it('cancelar uma assinatura que já não existe no provedor não é erro; outros erros são', function () {
    Http::fakeSequence()
        ->push(['errors' => [['code' => 'not_found']]], 404)
        ->push(['errors' => [['code' => 'invalid_action']]], 400);
    $p = app(PaymentProvider::class);
    $ref = new ReferenciaProvedor('asaas', 'sub_1');

    $p->cancelSubscription($ref);

    expect(fn () => $p->cancelSubscription($ref))->toThrow(ProvedorIndisponivel::class, 'HTTP 400');
});

it('suspender com o Asaas fora do ar vira ProvedorIndisponivel', function () {
    Http::fake(fn () => Http::response('', 503));

    expect(fn () => app(PaymentProvider::class)->suspendSubscription(new ReferenciaProvedor('asaas', 'sub_1')))->toThrow(ProvedorIndisponivel::class, 'HTTP 503');
});

it('a troca de cartão no meio do ciclo pede a 1ª cobrança no vencimento já pago, no fuso do negócio', function () {
    Http::fake(fn () => Http::response(['id' => 'chk-1', 'link' => 'https://sandbox.asaas.com/checkoutSession/show/chk-1'], 200));
    $venc = new DateTimeImmutable('2026-12-06 01:30:00', new DateTimeZone('UTC')); // 05/12 22:30 em São Paulo

    app(PaymentProvider::class)->startCheckout(new PedidoCheckout(
        '11111111-1111-4111-8111-111111111111', 'Troca de cartão · Plano Básico', 4990, [MeioPagamento::Cartao], 60,
        'https://app/ok', 'https://app/c', 'https://app/e', CicloCobranca::Mensal, $venc,
    ));

    Http::assertSent(fn (Request $r) => $r->data()['subscription']['nextDueDate'] === '2026-12-05 00:00:00');
});

it('CHECKOUT_PAID de uma recorrência leva a assinatura nova ao evento canônico', function () {
    $corpo = ['id' => 'evt_c1', 'event' => 'CHECKOUT_PAID', 'checkout' => ['id' => 'chk-1', 'externalReference' => 'ref-1', 'subscription' => 'sub_novo']];

    $e = app(PaymentProvider::class)->parseWebhook(_webhookDoCheckout($corpo));

    expect($e->tipo)->toBe(TipoEvento::CheckoutConcluido)->and($e->assinaturaId)->toBe('sub_novo')->and($e->checkoutId)->toBe('chk-1');
});

it('CHECKOUT_PAID sem assinatura no corpo não inventa uma', function () {
    $e = app(PaymentProvider::class)->parseWebhook(_webhookDoCheckout(['id' => 'evt_c2', 'event' => 'CHECKOUT_PAID', 'checkout' => ['id' => 'chk-1']]));

    expect($e->assinaturaId)->toBeNull();
});

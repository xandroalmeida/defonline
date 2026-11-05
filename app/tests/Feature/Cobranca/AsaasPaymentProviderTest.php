<?php

declare(strict_types=1);

use App\Domain\Cobranca\EstadoCobranca;
use App\Domain\Cobranca\MeioPagamento;
use App\Domain\Cobranca\PedidoCheckout;
use App\Domain\Cobranca\ProvedorIndisponivel;
use App\Domain\Cobranca\ReferenciaProvedor;
use App\Domain\Cobranca\TipoEvento;
use App\Domain\Cobranca\WebhookBruto;
use App\Domain\Cobranca\WebhookInvalido;
use App\Infrastructure\Cobranca\Asaas\AsaasPaymentProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * STORY-076 CA-4, CA-6 — o adaptador do Asaas contra respostas simuladas (sem rede).
 */
const ASAAS_TOKEN = 'tok-asaas-de-teste-aleatorio-com-32-caracteres';

function _asaas(?string $url = 'https://api-sandbox.asaas.com/v3', ?string $chave = '$aact_hmlg_abc'): AsaasPaymentProvider
{
    return new AsaasPaymentProvider($url, $chave, ASAAS_TOKEN, 5);
}

function _pedidoAsaas(): PedidoCheckout
{
    return new PedidoCheckout('11111111-1111-4111-8111-111111111111', 'Pacote Plus · 5 análises', 34990, [MeioPagamento::Pix], 30,
        'https://app/ok', 'https://app/cancelar', 'https://app/expirou');
}

function _webhookAsaas(array $corpo, ?string $token = ASAAS_TOKEN): WebhookBruto
{
    return new WebhookBruto($token === null ? [] : ['asaas-access-token' => $token], json_encode($corpo));
}

it('recusa subir sem host, com host desconhecido, sem https ou com chave de outro ambiente', function (?string $url, ?string $chave) {
    expect(fn () => _asaas($url, $chave))->toThrow(ProvedorIndisponivel::class);
})->with([
    'sem host' => [null, '$aact_hmlg_abc'],
    'host desconhecido' => ['https://example.com/v3', '$aact_hmlg_abc'],
    'sem https' => ['http://api-sandbox.asaas.com/v3', '$aact_hmlg_abc'],
    'sem chave' => ['https://api-sandbox.asaas.com/v3', null],
    'chave de produção no sandbox' => ['https://api-sandbox.asaas.com/v3', '$aact_prod_abc'],
    'chave de sandbox em produção' => ['https://api.asaas.com/v3', '$aact_hmlg_abc'],
]);

it('aceita o host de produção com chave de produção', function () {
    expect(_asaas('https://api.asaas.com/v3', '$aact_prod_abc')->nome())->toBe('asaas');
});

it('abre um checkout Pix avulso com a referência interna, o valor em reais e o prazo configurado', function () {
    Http::fake(['*/checkouts' => Http::response(['id' => 'chk-1', 'link' => 'https://sandbox.asaas.com/checkoutSession/show/chk-1'], 200)]);

    $sessao = _asaas()->startCheckout(_pedidoAsaas());

    expect($sessao->referencia->id)->toBe('chk-1')
        ->and($sessao->referencia->provedor)->toBe('asaas')
        ->and($sessao->url)->toBe('https://sandbox.asaas.com/checkoutSession/show/chk-1');

    Http::assertSent(function (Request $r) {
        return $r->url() === 'https://api-sandbox.asaas.com/v3/checkouts'
            && $r->hasHeader('access_token', '$aact_hmlg_abc')
            && $r->hasHeader('User-Agent')
            && $r['billingTypes'] === ['PIX']
            && $r['chargeTypes'] === ['DETACHED']
            && $r['minutesToExpire'] === 30
            && $r['externalReference'] === '11111111-1111-4111-8111-111111111111'
            && $r['items'][0]['value'] === 349.9
            && $r['callback']['successUrl'] === 'https://app/ok'
            && ! isset($r['creditCard']);
    });
});

it('trata resposta de checkout sem id ou link como falha do provedor', function () {
    Http::fake(['*' => Http::response(['id' => 'chk-1'], 200)]);

    expect(fn () => _asaas()->startCheckout(_pedidoAsaas()))->toThrow(ProvedorIndisponivel::class);
});

it('traduz erro HTTP em falha do provedor sem vazar corpo nem chave', function () {
    Http::fake(['*' => Http::response(['errors' => [['code' => 'invalid_object', 'description' => 'texto que não pode vazar']]], 400)]);

    try {
        _asaas()->startCheckout(_pedidoAsaas());
        $this->fail('Deveria lançar.');
    } catch (ProvedorIndisponivel $e) {
        expect($e->getMessage())->toContain('HTTP 400')->toContain('invalid_object')
            ->not->toContain('texto que não pode vazar')->not->toContain('aact_hmlg');
    }
});

it('traduz erro HTTP sem corpo de erros e queda de conexão', function () {
    Http::fake(['*' => Http::response('', 503)]);
    expect(fn () => _asaas()->startCheckout(_pedidoAsaas()))->toThrow(ProvedorIndisponivel::class, 'HTTP 503');

    Http::fake(fn () => throw new ConnectionException('cURL error 28: timed out'));
    expect(fn () => _asaas()->startCheckout(_pedidoAsaas()))->toThrow(ProvedorIndisponivel::class, 'a tempo');

    Http::fake(fn () => throw new RuntimeException('segredo'));
    expect(fn () => _asaas()->cancelCheckout(new ReferenciaProvedor('asaas', 'x')))->toThrow(ProvedorIndisponivel::class);
});

it('cancela o checkout', function () {
    Http::fake(['*' => Http::response(['id' => 'chk-1', 'status' => 'CANCELED'], 200)]);

    _asaas()->cancelCheckout(new ReferenciaProvedor('asaas', 'chk-1'));

    Http::assertSent(fn (Request $r) => $r->method() === 'POST' && $r->url() === 'https://api-sandbox.asaas.com/v3/checkouts/chk-1/cancel');
});

it('lê o estado atual da cobrança e converte o valor para centavos', function (string $status, bool $removida, EstadoCobranca $esperado) {
    Http::fake(['*/payments/*' => Http::response([
        'id' => 'pay_1', 'status' => $status, 'deleted' => $removida, 'value' => 349.9,
        'checkoutSession' => 'chk-1', 'externalReference' => 'ref-1', 'paymentDate' => '2026-11-05',
    ], 200)]);

    $c = _asaas()->getCharge(new ReferenciaProvedor('asaas', 'pay_1'));

    expect($c->estado)->toBe($esperado)
        ->and($c->valorCentavos)->toBe(34990)
        ->and($c->checkoutId)->toBe('chk-1')
        ->and($c->referenciaInterna)->toBe('ref-1')
        ->and($c->pagoEm?->format('Y-m-d'))->toBe('2026-11-05');
})->with([
    ['RECEIVED', false, EstadoCobranca::Paga],
    ['CONFIRMED', false, EstadoCobranca::Paga],
    ['PENDING', false, EstadoCobranca::Pendente],
    ['AWAITING_RISK_ANALYSIS', false, EstadoCobranca::Pendente],
    ['OVERDUE', false, EstadoCobranca::Vencida],
    ['REFUNDED', false, EstadoCobranca::Estornada],
    ['CHARGEBACK_DISPUTE', false, EstadoCobranca::Contestada],
    ['PENDING', true, EstadoCobranca::Cancelada],
]);

it('aceita cobrança sem valor, sem data e sem campos opcionais', function () {
    Http::fake(['*' => Http::response(['id' => 'pay_1', 'status' => 'PENDING'], 200)]);

    $c = _asaas()->getCharge(new ReferenciaProvedor('asaas', 'pay_1'));

    expect($c->valorCentavos)->toBe(0)->and($c->pagoEm)->toBeNull()->and($c->checkoutId)->toBeNull();
});

it('recusa webhook sem token, com token errado ou sem token configurado', function (?string $enviado) {
    expect(fn () => _asaas()->parseWebhook(_webhookAsaas(['id' => 'evt_1', 'event' => 'PAYMENT_RECEIVED'], $enviado)))
        ->toThrow(WebhookInvalido::class);
})->with([null, 'errado', '']);

it('recusa webhook quando o token do servidor não está configurado', function () {
    $semToken = new AsaasPaymentProvider('https://api-sandbox.asaas.com/v3', '$aact_hmlg_abc', null);

    expect(fn () => $semToken->parseWebhook(_webhookAsaas(['id' => 'evt_1', 'event' => 'PAYMENT_RECEIVED'], '')))
        ->toThrow(WebhookInvalido::class);
});

it('recusa corpo ilegível ou sem id e evento', function (string $corpo) {
    expect(fn () => _asaas()->parseWebhook(new WebhookBruto(['asaas-access-token' => ASAAS_TOKEN], $corpo)))
        ->toThrow(WebhookInvalido::class);
})->with(['não é json', '{"event":"PAYMENT_RECEIVED"}', '{"id":"","event":"X"}', '{"id":"e1"}', '[1,2]']);

it('traduz os eventos do Asaas para os canônicos e guarda o desconhecido como outro', function (string $evento, TipoEvento $esperado) {
    $e = _asaas()->parseWebhook(_webhookAsaas(['id' => 'evt_1', 'event' => $evento, 'payment' => ['id' => 'pay_1']]));

    expect($e->tipo)->toBe($esperado);
})->with([
    ['PAYMENT_CONFIRMED', TipoEvento::CobrancaPaga],
    ['PAYMENT_RECEIVED', TipoEvento::CobrancaPaga],
    ['PAYMENT_CREATED', TipoEvento::CobrancaCriada],
    ['PAYMENT_OVERDUE', TipoEvento::CobrancaVencida],
    ['PAYMENT_REFUNDED', TipoEvento::CobrancaEstornada],
    ['PAYMENT_CHARGEBACK_REQUESTED', TipoEvento::CobrancaContestada],
    ['PAYMENT_DELETED', TipoEvento::CobrancaCancelada],
    ['PAYMENT_CREDIT_CARD_CAPTURE_REFUSED', TipoEvento::CobrancaFalhou],
    ['CHECKOUT_PAID', TipoEvento::CheckoutConcluido],
    ['CHECKOUT_EXPIRED', TipoEvento::CheckoutExpirado],
    ['CHECKOUT_CANCELED', TipoEvento::CheckoutCancelado],
    ['EVENTO_NOVO_DO_FUTURO', TipoEvento::Outro],
]);

it('extrai as referências do pagamento e do checkout e descarta dado de cartão do payload', function () {
    $e = _asaas()->parseWebhook(_webhookAsaas([
        'id' => 'evt_1', 'event' => 'PAYMENT_RECEIVED',
        'payment' => [
            'id' => 'pay_1', 'externalReference' => 'ref-1', 'checkoutSession' => 'chk-1',
            'creditCard' => ['creditCardNumber' => '8829', 'creditCardToken' => 'tok', 'creditCardBrand' => 'VISA'],
        ],
    ]));

    expect($e->eventoId)->toBe('evt_1')->and($e->cobrancaId)->toBe('pay_1')
        ->and($e->referenciaInterna)->toBe('ref-1')->and($e->checkoutId)->toBe('chk-1')
        ->and(json_encode($e->payload))->not->toContain('8829')->not->toContain('creditCard')->not->toContain('tok"');

    $c = _asaas()->parseWebhook(_webhookAsaas(['id' => 'evt_2', 'event' => 'CHECKOUT_PAID', 'checkout' => ['id' => 'chk-9', 'externalReference' => 'ref-9']]));
    expect($c->checkoutId)->toBe('chk-9')->and($c->referenciaInterna)->toBe('ref-9')->and($c->cobrancaId)->toBeNull();
});

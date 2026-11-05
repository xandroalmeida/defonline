<?php

declare(strict_types=1);

use App\Domain\Cobranca\EstadoCobranca;
use App\Domain\Cobranca\PaymentProvider;
use App\Domain\Cobranca\ProvedorIndisponivel;
use App\Domain\Cobranca\ReferenciaProvedor;
use App\Domain\Cobranca\TentativaNaoRealizada;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * STORY-079 — o adaptador do Asaas na retentativa, no cancelamento da recorrência e na listagem para a
 * conciliação, com a rede simulada. A retentativa NUNCA cria cobrança: só reagenda a mesma.
 */
beforeEach(function () {
    _configAsaasDeTeste();
    Http::preventStrayRequests();
});

function _cobrancaAsaas079(array $extra = []): array
{
    return array_merge(['id' => 'pay_1', 'status' => 'PENDING', 'deleted' => false, 'value' => 49.9, 'subscription' => 'sub_1', 'dueDate' => '2026-12-05'], $extra);
}

function _provedorAsaas079(): PaymentProvider
{
    return app(PaymentProvider::class);
}

describe('retryCharge', function () {
    it('reagenda a MESMA cobrança para hoje (fuso do negócio) e devolve o estado atual, sem criar cobrança', function () {
        Http::fake([
            '*/payments/pay_1' => Http::sequence()
                ->push(_cobrancaAsaas079(['dueDate' => now('America/Sao_Paulo')->format('Y-m-d')]))
                ->push(_cobrancaAsaas079(['status' => 'CONFIRMED', 'paymentDate' => '2026-12-05'])),
        ]);

        $c = _provedorAsaas079()->retryCharge(new ReferenciaProvedor('asaas', 'pay_1'));

        expect($c->estado)->toBe(EstadoCobranca::Paga);
        Http::assertSentCount(2);
        Http::assertSent(fn (Request $r) => $r->method() === 'PUT' && str_ends_with($r->url(), '/payments/pay_1') && $r->data() === ['dueDate' => now('America/Sao_Paulo')->format('Y-m-d')]);
        Http::assertNotSent(fn (Request $r) => $r->method() === 'POST');
    });

    it('4xx do provedor (não editável) vira TentativaNaoRealizada', function () {
        Http::fake(['*/payments/pay_1' => Http::response(['errors' => [['code' => 'invalid_action']]], 400)]);

        expect(fn () => _provedorAsaas079()->retryCharge(new ReferenciaProvedor('asaas', 'pay_1')))->toThrow(TentativaNaoRealizada::class, 'HTTP 400');
    });

    it('5xx é ProvedorIndisponivel', function () {
        Http::fake(['*/payments/pay_1' => Http::response('', 503)]);

        expect(fn () => _provedorAsaas079()->retryCharge(new ReferenciaProvedor('asaas', 'pay_1')))->toThrow(ProvedorIndisponivel::class, 'HTTP 503');
    });

    it('rede caída é ProvedorIndisponivel', function () {
        Http::fake(['*/payments/pay_1' => fn () => throw new ConnectionException('timeout')]);

        expect(fn () => _provedorAsaas079()->retryCharge(new ReferenciaProvedor('asaas', 'pay_1')))->toThrow(ProvedorIndisponivel::class, 'não respondeu');
    });
});

describe('cancelSubscription', function () {
    it('apaga a recorrência no provedor; já apagada (404) também está certo', function () {
        Http::fake(['*/subscriptions/sub_1' => Http::sequence()->push(['deleted' => true], 200)->push(['errors' => []], 404)]);

        _provedorAsaas079()->cancelSubscription(new ReferenciaProvedor('asaas', 'sub_1'));
        _provedorAsaas079()->cancelSubscription(new ReferenciaProvedor('asaas', 'sub_1'));

        Http::assertSent(fn (Request $r) => $r->method() === 'DELETE' && str_ends_with($r->url(), '/subscriptions/sub_1'));
    });

    it('erro do provedor vira ProvedorIndisponivel (a régua refaz depois)', function () {
        Http::fake(['*/subscriptions/sub_1' => Http::response('', 500)]);

        expect(fn () => _provedorAsaas079()->cancelSubscription(new ReferenciaProvedor('asaas', 'sub_1')))->toThrow(ProvedorIndisponivel::class);
    });
});

describe('listagens para a conciliação', function () {
    it('lista as cobranças da assinatura paginando, a partir da data, e traduz estado e vencimento', function () {
        Http::fake(function (Request $r) {
            parse_str((string) parse_url($r->url(), PHP_URL_QUERY), $q);

            return Http::response($q['offset'] === '0'
                ? ['hasMore' => true, 'data' => [_cobrancaAsaas079(['id' => 'pay_a', 'status' => 'OVERDUE']), _cobrancaAsaas079(['id' => 'pay_estranha', 'subscription' => 'sub_outra'])]]
                : ['hasMore' => false, 'data' => [_cobrancaAsaas079(['id' => 'pay_b', 'status' => 'RECEIVED', 'paymentDate' => '2026-12-06'])]], 200);
        });

        $lista = _provedorAsaas079()->listSubscriptionCharges(new ReferenciaProvedor('asaas', 'sub_1'), new DateTimeImmutable('2026-11-20'));

        expect($lista)->toHaveCount(2)
            ->and($lista[0]->estado)->toBe(EstadoCobranca::Vencida)
            ->and($lista[0]->vencimento?->format('Y-m-d'))->toBe('2026-12-05')
            ->and($lista[1]->estado)->toBe(EstadoCobranca::Paga);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/subscriptions/sub_1/payments') && str_contains($r->url(), 'dateCreated%5Bge%5D=2026-11-20') && str_contains($r->url(), 'limit=100'));
        Http::assertSentCount(2);
    });

    it('as cobranças de um checkout vêm filtradas por checkoutSession, e as de outros checkouts são descartadas', function () {
        Http::fake(['*/payments?*' => Http::response(['hasMore' => false, 'data' => [
            _cobrancaAsaas079(['id' => 'pay_meu', 'status' => 'CONFIRMED', 'checkoutSession' => 'chk_1']),
            _cobrancaAsaas079(['id' => 'pay_de_outro', 'status' => 'CONFIRMED', 'checkoutSession' => 'chk_2']),
            _cobrancaAsaas079(['id' => 'pay_avulsa', 'status' => 'CONFIRMED']),
        ]], 200)]);

        $lista = _provedorAsaas079()->listCheckoutCharges(new ReferenciaProvedor('asaas', 'chk_1'));

        expect($lista)->toHaveCount(1)->and($lista[0]->referencia->id)->toBe('pay_meu');
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'checkoutSession=chk_1'));
    });

    it('para de paginar mesmo se o provedor insistir em hasMore (trava de segurança)', function () {
        Http::fake(['*' => Http::response(['hasMore' => true, 'data' => []], 200)]);

        _provedorAsaas079()->listCheckoutCharges(new ReferenciaProvedor('asaas', 'chk_1'));

        Http::assertSentCount(50);
    });

    it('lista vazia e resposta sem data são tratadas', function () {
        Http::fake(['*' => Http::response(['hasMore' => false], 200)]);

        expect(_provedorAsaas079()->listSubscriptionCharges(new ReferenciaProvedor('asaas', 'sub_1'), new DateTimeImmutable))->toBe([]);
    });

    it('falha de rede na listagem é ProvedorIndisponivel', function () {
        Http::fake(['*' => Http::response('', 502)]);

        expect(fn () => _provedorAsaas079()->listCheckoutCharges(new ReferenciaProvedor('asaas', 'chk_1')))->toThrow(ProvedorIndisponivel::class);
    });
});

describe('getCharge', function () {
    it('lê o vencimento da cobrança', function () {
        Http::fake(['*/payments/pay_1' => Http::response(_cobrancaAsaas079(['dueDate' => '2026-12-05']), 200)]);

        $c = _provedorAsaas079()->getCharge(new ReferenciaProvedor('asaas', 'pay_1'));

        expect($c->vencimento?->format('Y-m-d'))->toBe('2026-12-05');
    });

    it('cobrança sem vencimento ou com data vazia fica sem vencimento', function () {
        Http::fake(['*/payments/pay_1' => Http::response(['id' => 'pay_1', 'status' => 'PENDING', 'value' => 1, 'dueDate' => ''], 200)]);

        expect(_provedorAsaas079()->getCharge(new ReferenciaProvedor('asaas', 'pay_1'))->vencimento)->toBeNull();
    });
});

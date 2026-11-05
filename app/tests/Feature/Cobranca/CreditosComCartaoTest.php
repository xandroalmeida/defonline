<?php

declare(strict_types=1);

use App\Domain\Cobranca\EstadoCobranca;
use App\Domain\Cobranca\MeioPagamento;
use App\Domain\Cobranca\ProvedorIndisponivel;
use App\Domain\Cobranca\TipoEvento;
use App\Infrastructure\Cobranca\Fake\FakePaymentProvider;
use App\Mail\LinkDePagamentoMessage;
use App\Models\Assinatura;
use App\Models\Invoice;
use App\Models\LoteCredito;
use App\Models\ProviderEvent;
use App\Services\Cobranca\IniciarCompraDeCreditos;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

/**
 * STORY-078 — pacote de créditos pago com cartão no checkout hospedado (DETACHED, CREDIT_CARD, ADR-010):
 * a mesma compra única da STORY-076, só que sem Pix e sem nenhuma recorrência.
 */
beforeEach(function () {
    _usarProvedor('fake');
    Mail::fake();
    $this->usuario = _compradorConfirmado();
});

function _comprarNoCartao($usuario, ?string $pacote = null): Invoice
{
    return app(IniciarCompraDeCreditos::class)->executar($usuario, _pacote($pacote ?? 'plus'), '203.0.113.7', 'PestAgent', MeioPagamento::Cartao);
}

describe('CA-1 escolha do meio', function () {
    it('abre o checkout com o meio cartão, prazo do cartão e e-mail com o link do cartão', function () {
        config(['services.payments.cartao_expiracao_minutos' => 45, 'services.payments.pix_expiracao_minutos' => 30]);

        $invoice = _comprarNoCartao($this->usuario);

        expect($invoice->meio)->toBe('cartao')
            ->and($invoice->tipo)->toBe('credito')
            ->and($invoice->status)->toBe(EstadoCobranca::Pendente)
            ->and($invoice->checkout_expira_em->between(now()->addMinutes(44), now()->addMinutes(46)))->toBeTrue();
        Mail::assertSent(LinkDePagamentoMessage::class, fn (LinkDePagamentoMessage $m) => $m->cartao === true && $m->link === $invoice->checkout_url
            && str_contains($m->envelope()->subject, 'cartão'));
    });

    it('o e-mail do Pix continua falando de Pix', function () {
        app(IniciarCompraDeCreditos::class)->executar($this->usuario, _pacote(), null, null);

        Mail::assertSent(LinkDePagamentoMessage::class, fn (LinkDePagamentoMessage $m) => $m->cartao === false && str_contains($m->envelope()->subject, 'Pix'));
        expect(view('mail.link-de-pagamento', ['nome' => 'A', 'descricao' => 'd', 'valor' => 'v', 'link' => 'l', 'expiraEm' => '10:00', 'cartao' => false])->render())->toContain('Pagar com Pix');
        expect(view('mail.link-de-pagamento', ['nome' => 'A', 'descricao' => 'd', 'valor' => 'v', 'link' => 'l', 'expiraEm' => '10:00', 'cartao' => true])->render())->toContain('Pagar com cartão')->not->toContain('Pix');
    });

    it('Pix e cartão não reaproveitam o checkout um do outro, mas cada um reaproveita o seu', function () {
        $pix = app(IniciarCompraDeCreditos::class)->executar($this->usuario, _pacote(), null, null);
        $cartao = _comprarNoCartao($this->usuario);

        expect($cartao->id)->not->toBe($pix->id)
            ->and(_comprarNoCartao($this->usuario)->id)->toBe($cartao->id)
            ->and(app(IniciarCompraDeCreditos::class)->executar($this->usuario, _pacote(), null, null)->id)->toBe($pix->id);
    });
});

describe('CA-2 aprovado credita e não cria recorrência', function () {
    it('o pagamento aprovado credita o lote, guarda só bandeira e 4 dígitos e não cria assinatura', function () {
        $invoice = _comprarNoCartao($this->usuario);

        _entregar(_fake()->simularPagamento((string) $invoice->checkout_id_externo));

        $invoice = $invoice->fresh();
        $lote = LoteCredito::query()->sole();
        expect($invoice->status)->toBe(EstadoCobranca::Paga)
            ->and($invoice->cartao_bandeira)->toBe('VISA')
            ->and($invoice->cartao_ultimos4)->toBe('4242')
            ->and($invoice->assinatura_id)->toBeNull()
            ->and($lote->quantidade_comprada)->toBe(_pacote()->quantidade_creditos)
            ->and(Assinatura::query()->count())->toBe(0);
    });

    it('o checkout do cartão fica pendente, sem crédito, até o evento chegar', function () {
        _comprarNoCartao($this->usuario);

        expect(LoteCredito::query()->count())->toBe(0);
    });

    it('no Pix o cartão não é gravado', function () {
        $invoice = app(IniciarCompraDeCreditos::class)->executar($this->usuario, _pacote(), null, null);
        _entregar(_fake()->simularPagamento((string) $invoice->checkout_id_externo));

        expect($invoice->fresh()->cartao_ultimos4)->toBeNull();
    });
});

describe('CA-3 recusa', function () {
    it('cartão recusado não gera crédito e fica registrado como recusado', function () {
        $invoice = _comprarNoCartao($this->usuario);

        _entregar(_fake()->simularRecusa((string) $invoice->checkout_id_externo));

        expect($invoice->fresh()->status)->toBe(EstadoCobranca::Falhou)
            ->and($invoice->fresh()->motivo_encerramento)->toBe('recusado')
            ->and(LoteCredito::query()->count())->toBe(0);
    });

    it('se o usuário acertar o cartão na mesma página do provedor, o pagamento ainda credita uma vez', function () {
        $invoice = _comprarNoCartao($this->usuario);
        _entregar(_fake()->simularRecusa((string) $invoice->checkout_id_externo));
        _entregar(_fake()->simularPagamento((string) $invoice->checkout_id_externo));

        expect($invoice->fresh()->status)->toBe(EstadoCobranca::Paga)->and(LoteCredito::query()->count())->toBe(1);
    });

    it('a falha do provedor ao abrir o checkout não confunde com recusa', function () {
        FakePaymentProvider::falharProximoCheckout();

        expect(fn () => _comprarNoCartao($this->usuario))->toThrow(ProvedorIndisponivel::class);
        expect(Invoice::query()->sole()->motivo_encerramento)->toBe('provedor');
    });
});

describe('CA-4 idempotência', function () {
    it('o mesmo evento recebido duas vezes credita uma única vez', function () {
        $invoice = _comprarNoCartao($this->usuario);
        $webhook = _fake()->simularPagamento((string) $invoice->checkout_id_externo, 'evt-cartao-1');

        _entregar($webhook);
        _entregar($webhook);

        expect(LoteCredito::query()->count())->toBe(1)->and(ProviderEvent::query()->where('evento_id', 'evt-cartao-1')->count())->toBe(1);
    });

    it('dois eventos diferentes da mesma cobrança (confirmada e recebida) creditam uma vez', function () {
        $invoice = _comprarNoCartao($this->usuario);

        _entregar(_fake()->simularPagamento((string) $invoice->checkout_id_externo, 'evt-a'));
        _entregar(_fake()->webhook(TipoEvento::CobrancaPaga, (string) $invoice->checkout_id_externo, 'evt-b'));

        expect(LoteCredito::query()->count())->toBe(1);
    });
});

describe('CA-5 dados de cartão', function () {
    it('o Asaas recebe um checkout DETACHED só com cartão e nada de dado de cartão nosso', function () {
        _asaasSimulado();
        $usuario = _compradorConfirmado();

        $invoice = _comprarNoCartao($usuario);

        Http::assertSent(function (Request $r) use ($invoice) {
            $c = $r->data();

            return str_ends_with($r->url(), '/checkouts')
                && $c['billingTypes'] === ['CREDIT_CARD']
                && $c['chargeTypes'] === ['DETACHED']
                && ! array_key_exists('subscription', $c)
                && $c['externalReference'] === $invoice->id
                && ! preg_match('/credit_?card_?(number|token)|ccv|cvv|holderName/i', json_encode($c));
        });
    });

    it('com o Asaas, o pagamento credita, guarda só bandeira e 4 dígitos e o payload não tem cartão', function () {
        _asaasSimulado();
        $usuario = _compradorConfirmado();
        $invoice = _comprarNoCartao($usuario);
        _asaasCobranca('pay_c1', $invoice);
        AsaasSimulado::$cobrancas['pay_c1']['creditCard'] = ['creditCardNumber' => '9999', 'creditCardBrand' => 'mastercard', 'creditCardToken' => 'tok-secreto'];

        _postarWebhookAsaas($this, 'PAYMENT_CONFIRMED', 'evt_c1', ['payment' => [
            'id' => 'pay_c1', 'checkoutSession' => 'chk-1', 'externalReference' => $invoice->id,
            'creditCard' => ['creditCardNumber' => '9999', 'creditCardToken' => 'tok-secreto'],
        ]])->assertOk();

        $invoice = $invoice->fresh();
        expect($invoice->status)->toBe(EstadoCobranca::Paga)
            ->and($invoice->cartao_bandeira)->toBe('MASTERCARD')
            ->and($invoice->cartao_ultimos4)->toBe('9999')
            ->and(LoteCredito::query()->count())->toBe(1)
            ->and(Assinatura::query()->count())->toBe(0)
            ->and(json_encode(ProviderEvent::query()->get()->toArray()))->not->toContain('tok-secreto');
    });

    it('recusa pelo Asaas (captura recusada) marca recusado sem creditar', function () {
        _asaasSimulado();
        $usuario = _compradorConfirmado();
        $invoice = _comprarNoCartao($usuario);

        _postarWebhookAsaas($this, 'PAYMENT_CREDIT_CARD_CAPTURE_REFUSED', 'evt_r1', ['payment' => ['id' => 'pay_r1', 'checkoutSession' => 'chk-1', 'externalReference' => $invoice->id]])->assertOk();

        expect($invoice->fresh()->status)->toBe(EstadoCobranca::Falhou)->and($invoice->fresh()->motivo_encerramento)->toBe('recusado')->and(LoteCredito::query()->count())->toBe(0);
    });
});

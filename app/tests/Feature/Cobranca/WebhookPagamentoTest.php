<?php

declare(strict_types=1);

use App\Domain\Cobranca\EstadoCobranca;
use App\Domain\Cobranca\PaymentProvider;
use App\Models\Invoice;
use App\Models\LoteCredito;
use App\Models\ProviderEvent;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * STORY-076 CA-3, CA-4, CA-5 — webhook assinado no formato do Asaas, de ponta a ponta no HTTP:
 * autenticação, idempotência, desordem e valor. O adaptador é o real; só a rede é simulada.
 */
beforeEach(function () {
    _asaasSimulado();
    $this->usuario = _compradorConfirmado();
    $this->invoice = _compraPendenteAsaas($this->usuario);
});

function _pagamento(Invoice $invoice, string $payId = 'pay_1', bool $comReferencia = true): array
{
    return ['payment' => ['id' => $payId, 'externalReference' => $comReferencia ? $invoice->id : null, 'checkoutSession' => $invoice->checkout_id_externo]];
}

it('recusa webhook sem token, com token errado, e não grava nada (CA-4)', function (?string $token) {
    _postarWebhookAsaas($this, 'PAYMENT_RECEIVED', 'evt_a', _pagamento($this->invoice), $token)->assertUnauthorized();

    expect(ProviderEvent::query()->count())->toBe(0)->and(LoteCredito::query()->count())->toBe(0);
})->with([null, 'token-errado']);

it('não exige sessão nem CSRF e não cria sessão para o webhook', function () {
    _asaasCobranca('pay_1', $this->invoice);
    $antes = DB::table('sessions')->count();

    _postarWebhookAsaas($this, 'PAYMENT_RECEIVED', 'evt_b', _pagamento($this->invoice))->assertOk();

    expect(DB::table('sessions')->count())->toBe($antes);
});

it('credita os créditos com a validade do pacote quando o provedor confirma o pagamento (CA-3)', function () {
    _asaasCobranca('pay_1', $this->invoice);

    _postarWebhookAsaas($this, 'PAYMENT_RECEIVED', 'evt_c', _pagamento($this->invoice))->assertOk()->assertJson(['status' => 'novo']);

    $invoice = $this->invoice->fresh();
    $lote = LoteCredito::query()->where('invoice_id', $invoice->id)->sole();

    expect($invoice->status)->toBe(EstadoCobranca::Paga)
        ->and($invoice->id_externo)->toBe('pay_1')
        ->and($invoice->pago_em)->not->toBeNull()
        ->and($lote->usuario_id)->toBe($this->usuario->id)
        ->and($lote->quantidade_comprada)->toBe($invoice->quantidade_creditos)
        ->and($lote->data_expiracao->isSameDay($invoice->pago_em->copy()->addMonthsNoOverflow($invoice->validade_meses)))->toBeTrue();
    expect(ProviderEvent::query()->sole()->status)->toBe('processado');
});

it('o mesmo webhook recebido duas vezes credita uma única vez (CA-4)', function () {
    _asaasCobranca('pay_1', $this->invoice);

    _postarWebhookAsaas($this, 'PAYMENT_RECEIVED', 'evt_d', _pagamento($this->invoice))->assertJson(['status' => 'novo']);
    _postarWebhookAsaas($this, 'PAYMENT_RECEIVED', 'evt_d', _pagamento($this->invoice))->assertOk()->assertJson(['status' => 'duplicado']);

    expect(LoteCredito::query()->count())->toBe(1)->and(ProviderEvent::query()->count())->toBe(1);
});

it('CONFIRMED seguido de RECEIVED (dois eventos distintos) credita uma vez só', function () {
    _asaasCobranca('pay_1', $this->invoice);

    _postarWebhookAsaas($this, 'PAYMENT_CONFIRMED', 'evt_e1', _pagamento($this->invoice))->assertOk();
    _postarWebhookAsaas($this, 'PAYMENT_RECEIVED', 'evt_e2', _pagamento($this->invoice))->assertOk();

    expect(LoteCredito::query()->count())->toBe(1)
        ->and(ProviderEvent::query()->where('status', 'ignorado')->count())->toBe(1);
});

it('acha a compra pelo checkout quando o pagamento não traz a referência', function () {
    _asaasCobranca('pay_1', $this->invoice, comReferencia: false);

    _postarWebhookAsaas($this, 'PAYMENT_RECEIVED', 'evt_f', _pagamento($this->invoice, comReferencia: false))->assertOk();

    expect(LoteCredito::query()->count())->toBe(1);
});

it('acha a compra só pelo id da cobrança depois de conhecida', function () {
    $this->invoice->forceFill(['id_externo' => 'pay_9'])->save();
    _asaasCobranca('pay_9', $this->invoice->fresh(), comReferencia: false);
    AsaasSimulado::$cobrancas['pay_9']['checkoutSession'] = null;

    _postarWebhookAsaas($this, 'PAYMENT_RECEIVED', 'evt_g', ['payment' => ['id' => 'pay_9']])->assertOk();

    expect(LoteCredito::query()->count())->toBe(1);
});

it('não credita quando o estado atual no provedor ainda não é pago, mesmo com o evento dizendo que sim', function () {
    _asaasCobranca('pay_1', $this->invoice, 'PENDING');

    _postarWebhookAsaas($this, 'PAYMENT_RECEIVED', 'evt_h', _pagamento($this->invoice))->assertOk();

    expect(LoteCredito::query()->count())->toBe(0)
        ->and($this->invoice->fresh()->status)->toBe(EstadoCobranca::Pendente)
        ->and(ProviderEvent::query()->sole()->status)->toBe('ignorado');
});

it('Pix expirado não credita e fica vencido; o banco confirmando depois do prazo ainda credita (CA-5)', function () {
    _postarWebhookAsaas($this, 'CHECKOUT_EXPIRED', 'evt_i1', ['checkout' => ['id' => $this->invoice->checkout_id_externo, 'externalReference' => $this->invoice->id]])->assertOk();

    expect($this->invoice->fresh()->status)->toBe(EstadoCobranca::Vencida)
        ->and($this->invoice->fresh()->expirada())->toBeTrue()
        ->and(LoteCredito::query()->count())->toBe(0);

    _asaasCobranca('pay_1', $this->invoice);
    _postarWebhookAsaas($this, 'PAYMENT_RECEIVED', 'evt_i2', _pagamento($this->invoice))->assertOk();

    expect($this->invoice->fresh()->status)->toBe(EstadoCobranca::Paga)->and(LoteCredito::query()->count())->toBe(1);
});

it('compra cancelada é estado final: pagamento tardio não credita', function () {
    _postarWebhookAsaas($this, 'CHECKOUT_CANCELED', 'evt_j1', ['checkout' => ['id' => $this->invoice->checkout_id_externo]])->assertOk();
    _asaasCobranca('pay_1', $this->invoice);
    _postarWebhookAsaas($this, 'PAYMENT_RECEIVED', 'evt_j2', _pagamento($this->invoice))->assertOk();

    expect($this->invoice->fresh()->status)->toBe(EstadoCobranca::Cancelada)->and(LoteCredito::query()->count())->toBe(0);
});

it('expiração que chega depois do pagamento não desfaz a compra paga (fora de ordem)', function () {
    _asaasCobranca('pay_1', $this->invoice);
    _postarWebhookAsaas($this, 'PAYMENT_RECEIVED', 'evt_k1', _pagamento($this->invoice))->assertOk();
    _postarWebhookAsaas($this, 'CHECKOUT_EXPIRED', 'evt_k2', ['checkout' => ['id' => $this->invoice->checkout_id_externo, 'externalReference' => $this->invoice->id]])->assertOk();

    expect($this->invoice->fresh()->status)->toBe(EstadoCobranca::Paga)
        ->and(ProviderEvent::query()->where('evento_id', 'evt_k2')->sole()->status)->toBe('ignorado');
});

it('valor pago diferente do da compra não credita, vira erro e alerta', function () {
    Log::spy();
    _asaasCobranca('pay_1', $this->invoice, valorCentavos: 100);

    _postarWebhookAsaas($this, 'PAYMENT_RECEIVED', 'evt_l', _pagamento($this->invoice))->assertOk();

    expect(LoteCredito::query()->count())->toBe(0)
        ->and($this->invoice->fresh()->status)->toBe(EstadoCobranca::Pendente)
        ->and(ProviderEvent::query()->sole()->status)->toBe('erro');
    Log::shouldHaveReceived('error')->with('cobranca.valor_divergente', Mockery::any());
});

it('pagamento sem compra correspondente é ignorado e alerta', function () {
    Log::spy();
    AsaasSimulado::$cobrancas['pay_x'] = ['id' => 'pay_x', 'status' => 'RECEIVED', 'value' => 10, 'externalReference' => 'nao-existe', 'checkoutSession' => 'chk-x'];

    _postarWebhookAsaas($this, 'PAYMENT_RECEIVED', 'evt_m', ['payment' => ['id' => 'pay_x']])->assertOk();

    expect(ProviderEvent::query()->sole()->status)->toBe('ignorado')->and(LoteCredito::query()->count())->toBe(0);
    Log::shouldHaveReceived('error')->with('cobranca.evento_sem_invoice', Mockery::any());
});

it('pagamento sem id de cobrança e evento de checkout sem compra são ignorados com alerta', function () {
    Log::spy();

    _postarWebhookAsaas($this, 'PAYMENT_RECEIVED', 'evt_n1', ['payment' => []])->assertOk();
    _postarWebhookAsaas($this, 'CHECKOUT_EXPIRED', 'evt_n2', ['checkout' => ['id' => 'chk-desconhecido']])->assertOk();

    expect(ProviderEvent::query()->where('status', 'ignorado')->count())->toBe(2);
    Log::shouldHaveReceived('error')->with('cobranca.evento_sem_invoice', Mockery::any())->twice();
});

it('guarda evento desconhecido como outro, ignorado, e falha de cartão marca a compra como falhou', function () {
    _postarWebhookAsaas($this, 'EVENTO_NOVO', 'evt_o1', ['qualquer' => 'coisa'])->assertOk();
    _postarWebhookAsaas($this, 'PAYMENT_CREDIT_CARD_CAPTURE_REFUSED', 'evt_o2', _pagamento($this->invoice))->assertOk();

    expect(ProviderEvent::query()->where('evento_id', 'evt_o1')->sole()->tipo)->toBe('outro')
        ->and($this->invoice->fresh()->status)->toBe(EstadoCobranca::Falhou);
});

it('erro ao consultar o provedor deixa o evento em erro, responde 200 e o comando reprocessa', function () {
    // Sem a cobrança cadastrada no Asaas simulado, getCharge dá 404.
    _postarWebhookAsaas($this, 'PAYMENT_RECEIVED', 'evt_p', _pagamento($this->invoice))->assertOk();

    expect(ProviderEvent::query()->sole()->status)->toBe('erro')->and(LoteCredito::query()->count())->toBe(0);

    // O reenvio do mesmo evento reenfileira o que ainda não foi tratado.
    _asaasCobranca('pay_1', $this->invoice);
    _postarWebhookAsaas($this, 'PAYMENT_RECEIVED', 'evt_p', _pagamento($this->invoice))->assertOk()->assertJson(['status' => 'reenfileirado']);

    expect(LoteCredito::query()->count())->toBe(1)->and(ProviderEvent::query()->sole()->status)->toBe('processado');
});

it('o comando reprocessa eventos parados e deixa os já tratados', function () {
    _postarWebhookAsaas($this, 'PAYMENT_RECEIVED', 'evt_q', _pagamento($this->invoice))->assertOk();
    DB::statement("update provider_events set recebido_em = now() - interval '10 minutes'");
    _asaasCobranca('pay_1', $this->invoice);

    $this->artisan('cobranca:reprocessar-eventos')->assertSuccessful();
    expect(LoteCredito::query()->count())->toBe(1);

    $this->artisan('cobranca:reprocessar-eventos')->expectsOutputToContain('Eventos a reprocessar: 0')->assertSuccessful();
});

it('o comando acusa falha quando o evento continua sem processar', function () {
    _postarWebhookAsaas($this, 'PAYMENT_RECEIVED', 'evt_r', _pagamento($this->invoice))->assertOk();
    DB::statement("update provider_events set recebido_em = now() - interval '10 minutes'");

    $this->artisan('cobranca:reprocessar-eventos')->assertFailed();
});

it('o banco recusa dois lotes para a mesma compra', function () {
    _asaasCobranca('pay_1', $this->invoice);
    _postarWebhookAsaas($this, 'PAYMENT_RECEIVED', 'evt_s', _pagamento($this->invoice))->assertOk();

    expect(fn () => LoteCredito::query()->create([
        'usuario_id' => $this->usuario->id, 'invoice_id' => $this->invoice->id, 'quantidade_comprada' => 1,
        'quantidade_restante' => 1, 'status' => 'ativo', 'data_compra' => now(), 'data_expiracao' => now()->addYear(),
    ]))->toThrow(UniqueConstraintViolationException::class);
});

it('o webhook funciona com o provedor fake no formato dele, e fake com token errado é 401', function () {
    _usarProvedor('fake');
    $usuario = _compradorConfirmado();
    $invoice = _compraPendenteAsaas($usuario, _pacote('mini'));
    $fake = app(PaymentProvider::class);

    $bruto = $fake->simularPagamento($invoice->checkout_id_externo);
    $this->postJson('/webhooks/pagamentos', json_decode($bruto->corpo, true), ['x-fake-token' => 'errado'])->assertUnauthorized();
    $this->postJson('/webhooks/pagamentos', json_decode($bruto->corpo, true), ['x-fake-token' => $bruto->cabecalho('x-fake-token')])->assertOk();

    expect(LoteCredito::query()->where('invoice_id', $invoice->id)->count())->toBe(1);
});

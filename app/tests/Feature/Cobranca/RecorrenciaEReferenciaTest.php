<?php

declare(strict_types=1);

use App\Domain\Cobranca\Carteira;
use App\Domain\Cobranca\EstadoCobranca;
use App\Domain\Cobranca\PaymentProvider;
use App\Domain\Cobranca\ProvedorIndisponivel;
use App\Domain\Cobranca\StatusAssinatura;
use App\Domain\Cobranca\TipoEvento;
use App\Domain\Cobranca\WebhookBruto;
use App\Infrastructure\Cobranca\Fake\FakePaymentProvider;
use App\Models\Assinatura;
use App\Models\Invoice;
use App\Models\ProviderEvent;
use App\Services\Cobranca\ProcessarEvento;
use App\Support\DataExibicao;
use Illuminate\Support\Facades\Mail;

/**
 * STORY-088 (V087 D-01, D-02, D-03, D-16): a recorrência duplicada não segue cobrando em silêncio, a cobrança é
 * correlacionada pela PRÓPRIA cobrança (nunca pela referência herdada da 1ª) e os eventos que faltavam têm tratamento.
 * Cenários E1, E1b, E2, E3, E3b e E15 do exploratório da STORY-087, agora como regressão.
 */
function _subDe(string $checkout): string
{
    return 'fakesub_'.Str::after($checkout, 'fakechk_');
}

describe('fake', function () {
    beforeEach(function () {
        _usarProvedor('fake');
        Mail::fake();
        $this->u = _compradorConfirmado();
    });

    it('CA-1 / E1: checkout duplicado pago cancela no provedor a recorrência excedente e marca a cobrança para devolução', function () {
        $i1 = _assinaturaPendente($this->u, _plano('Básico'));
        $i2 = _assinaturaPendente($this->u, _plano('Pro'));
        _pagarAssinatura($i1);
        _pagarAssinatura($i2);

        $dup = $i2->fresh();
        expect($dup->devolucao_motivo)->toBe('assinatura_duplicada')
            ->and($dup->assinatura_id_externo)->toBe(_subDe($i2->checkout_id_externo))
            ->and(FakePaymentProvider::assinaturaCancelada(_subDe($i2->checkout_id_externo)))->toBeTrue()
            ->and(FakePaymentProvider::assinaturaCancelada(_subDe($i1->checkout_id_externo)))->toBeFalse()
            ->and(Assinatura::query()->count())->toBe(1);
    });

    it('CA-1 / E1b: a cobrança do mês seguinte da recorrência excedente vira devolução e não renova nada', function () {
        $i1 = _assinaturaPendente($this->u, _plano('Básico'));
        $i2 = _assinaturaPendente($this->u, _plano('Pro'));
        _pagarAssinatura($i1);
        _pagarAssinatura($i2);
        $antes = Invoice::query()->count();

        $this->travelTo(now()->addMonth());
        $resposta = _entregar(_fake()->simularRenovacao($i2->checkout_id_externo));

        $nova = Invoice::query()->whereNotNull('devolucao_motivo')->where('id', '!=', $i2->id)->sole();
        expect($resposta)->toBe('novo')
            ->and(Invoice::query()->count())->toBe($antes + 1)
            ->and($nova->devolucao_motivo)->toBe('assinatura_duplicada')
            ->and($nova->usuario_id)->toBe($this->u->id)
            ->and($nova->status)->toBe(EstadoCobranca::Paga)
            ->and($nova->renovacao)->toBeFalse()
            ->and(Assinatura::query()->sole()->periodos_pagos)->toBe(1)
            ->and(ProviderEvent::query()->latest('recebido_em')->first()->status)->toBe('ignorado');

        // O aviso repetido não registra de novo.
        $this->travelTo(now()->addMonth());
        _entregar(_fake()->simularRenovacao($i2->checkout_id_externo));
        expect(Invoice::query()->whereNotNull('devolucao_motivo')->count())->toBe(3);
    });

    it('CA-1: se o provedor falha ao cancelar a recorrência excedente, o evento fica em erro e a repetição cancela', function () {
        $i1 = _assinaturaPendente($this->u, _plano('Básico'));
        $i2 = _assinaturaPendente($this->u, _plano('Pro'));
        _pagarAssinatura($i1);
        $fake = _fake();
        $webhook = $fake->simularPagamento((string) $i2->checkout_id_externo);
        app()->instance(PaymentProvider::class, _provedorQueFalha(['cancelSubscription' => fn () => new ProvedorIndisponivel('fora do ar')]));

        app()->forgetInstance(ProcessarEvento::class);
        _entregar($webhook);

        $evento = ProviderEvent::query()->where('tipo', TipoEvento::CobrancaPaga->value)->where('assinatura_id_externo', _subDe($i2->checkout_id_externo))->sole();
        expect($evento->status)->toBe('erro')
            ->and($i2->fresh()->devolucao_motivo)->toBe('assinatura_duplicada')
            ->and(FakePaymentProvider::assinaturaCancelada(_subDe($i2->checkout_id_externo)))->toBeFalse();

        app()->instance(PaymentProvider::class, $fake);
        app()->forgetInstance(ProcessarEvento::class);
        app(ProcessarEvento::class)->executar($evento->id);

        expect(FakePaymentProvider::assinaturaCancelada(_subDe($i2->checkout_id_externo)))->toBeTrue();
    });

    it('CA-2: com ou sem referência herdada, a renovação paga depois de contestar a 1ª cobrança ainda renova (E3)', function (bool $herda) {
        FakePaymentProvider::herdarReferenciaNasRenovacoes($herda);
        [$a, $i1] = _assinaturaAtivaNoFake($this->u);
        _entregar(_fake()->simularContestacao($i1->id_externo));
        expect($i1->fresh()->status)->toBe(EstadoCobranca::Contestada);

        $this->travelTo($a->proximo_vencimento->copy()->addHour());
        _entregar(_fake()->simularRenovacao((string) $i1->checkout_id_externo));

        $renovacao = Invoice::query()->where('renovacao', true)->sole();
        expect($a->fresh()->periodos_pagos)->toBe(2)
            ->and($renovacao->status)->toBe(EstadoCobranca::Paga)
            ->and($i1->fresh()->status)->toBe(EstadoCobranca::Contestada)
            ->and(ProviderEvent::query()->latest('recebido_em')->first()->status)->toBe('processado');
    })->with(['referência herdada' => [true], 'sem referência' => [false]]);

    it('CA-2: o estorno do mês 2 cai na Invoice do mês 2 e deixa a do mês 1 paga (E2)', function (bool $herda) {
        FakePaymentProvider::herdarReferenciaNasRenovacoes($herda);
        [$a, $i1] = _assinaturaAtivaNoFake($this->u);
        $this->travelTo($a->proximo_vencimento->copy()->addHour());
        _entregar(_fake()->simularRenovacao((string) $i1->checkout_id_externo));
        $mes2 = Invoice::query()->where('renovacao', true)->sole();

        _entregar(_fake()->autorizarReembolso((string) $mes2->id_externo));

        expect($mes2->fresh()->status)->toBe(EstadoCobranca::Estornada)->and($i1->fresh()->status)->toBe(EstadoCobranca::Paga);
    })->with(['referência herdada' => [true], 'sem referência' => [false]]);
});

describe('asaas simulado', function () {
    beforeEach(function () {
        _asaasSimulado();
        $this->u = _compradorConfirmado();
    });

    /** Ativa no pay_1; devolve a Invoice e o criador de cobranças (com ou sem a referência herdada). */
    function _doisCiclosAsaas($t, bool $herda): array
    {
        $i = _assinaturaPendente($t->u);
        $cob = fn (string $id, string $status = 'CONFIRMED') => AsaasSimulado::$cobrancas[$id] = [
            'id' => $id, 'status' => $status, 'deleted' => false, 'value' => $i->valor_centavos / 100, 'subscription' => 'sub_1',
            'checkoutSession' => $id === 'pay_1' ? 'chk-1' : null, 'externalReference' => $id === 'pay_1' || $herda ? $i->id : null,
            'paymentDate' => DataExibicao::agora()->format('Y-m-d'),
        ];
        $payment = fn (string $id) => ['payment' => ['id' => $id, 'subscription' => 'sub_1', 'externalReference' => $id === 'pay_1' || $herda ? $i->id : null] + ($id === 'pay_1' ? ['checkoutSession' => 'chk-1'] : [])];
        $cob('pay_1');
        _postarWebhookAsaas($t, 'PAYMENT_CONFIRMED', 'evt_1', $payment('pay_1'))->assertOk();

        return [$i, $cob, $payment];
    }

    it('CA-2 / E3: contestação da 1ª cobrança não faz a renovação paga ser ignorada', function (bool $herda) {
        [$i, $cob, $payment] = _doisCiclosAsaas($this, $herda);
        $a = Assinatura::query()->sole();
        $cob('pay_1', 'CHARGEBACK_REQUESTED');
        _postarWebhookAsaas($this, 'PAYMENT_CHARGEBACK_REQUESTED', 'evt_c', $payment('pay_1'))->assertOk();
        expect($i->fresh()->status)->toBe(EstadoCobranca::Contestada);

        $this->travelTo($a->proximo_vencimento->copy()->addHour());
        $cob('pay_2');
        _postarWebhookAsaas($this, 'PAYMENT_CONFIRMED', 'evt_2', $payment('pay_2'))->assertOk();

        expect($a->fresh()->periodos_pagos)->toBe(2)
            ->and(Invoice::query()->where('id_externo', 'pay_2')->sole()->status)->toBe(EstadoCobranca::Paga)
            ->and(ProviderEvent::query()->where('evento_id', 'evt_2')->value('status'))->toBe('processado');
    })->with(['referência herdada' => [true], 'sem referência' => [false]]);

    it('CA-2 / E2: o estorno da cobrança do mês 2 estorna a Invoice do mês 2, não a do mês 1', function (bool $herda) {
        [$i, $cob, $payment] = _doisCiclosAsaas($this, $herda);
        $a = Assinatura::query()->sole();
        $this->travelTo($a->proximo_vencimento->copy()->addHour());
        $cob('pay_2');
        _postarWebhookAsaas($this, 'PAYMENT_CONFIRMED', 'evt_2', $payment('pay_2'))->assertOk();
        $mes2 = Invoice::query()->where('id_externo', 'pay_2')->sole();

        $cob('pay_2', 'REFUNDED');
        _postarWebhookAsaas($this, 'PAYMENT_REFUNDED', 'evt_3', $payment('pay_2'))->assertOk();

        expect($mes2->fresh()->status)->toBe(EstadoCobranca::Estornada)->and($i->fresh()->status)->toBe(EstadoCobranca::Paga);
    })->with(['referência herdada' => [true], 'sem referência' => [false]]);

    it('CA-2 / E3b: estornar a Invoice antiga mantém a assinatura e a renovação paga seguinte ainda renova', function (bool $herda) {
        [$i, $cob, $payment] = _doisCiclosAsaas($this, $herda);
        $a = Assinatura::query()->sole();
        $this->travelTo($a->proximo_vencimento->copy()->addHour());
        $cob('pay_2');
        _postarWebhookAsaas($this, 'PAYMENT_CONFIRMED', 'evt_2', $payment('pay_2'))->assertOk();
        $cob('pay_1', 'REFUNDED');
        _postarWebhookAsaas($this, 'PAYMENT_REFUNDED', 'evt_r', $payment('pay_1'))->assertOk();
        expect($a->fresh()->status)->toBe(StatusAssinatura::Ativa)->and($i->fresh()->status)->toBe(EstadoCobranca::Estornada);

        $this->travelTo($a->fresh()->proximo_vencimento->copy()->addHour());
        $cob('pay_3');
        _postarWebhookAsaas($this, 'PAYMENT_CONFIRMED', 'evt_3', $payment('pay_3'))->assertOk();

        expect($a->fresh()->periodos_pagos)->toBe(3)->and(ProviderEvent::query()->where('evento_id', 'evt_3')->value('status'))->toBe('processado');
    })->with(['referência herdada' => [true], 'sem referência' => [false]]);

    it('CA-3: pagamento confirmado de um checkout já cancelado vai para a devolução, com alerta, e cancela a recorrência', function () {
        $i = _assinaturaPendente($this->u);
        _postarWebhookAsaas($this, 'CHECKOUT_CANCELED', 'evt_x', ['checkout' => ['id' => 'chk-1']])->assertOk();
        expect($i->fresh()->status)->toBe(EstadoCobranca::Cancelada);
        _asaasCobranca('pay_1', $i);
        AsaasSimulado::$cobrancas['pay_1'] += ['subscription' => 'sub_9'];

        Log::spy();
        _postarWebhookAsaas($this, 'PAYMENT_CONFIRMED', 'evt_1', ['payment' => ['id' => 'pay_1', 'subscription' => 'sub_9', 'checkoutSession' => 'chk-1', 'externalReference' => $i->id]])->assertOk();

        // Um destino só (STORY-093): a própria Invoice cancelada vira a devolução; não nasce uma segunda.
        $devolver = Invoice::query()->where('id_externo', 'pay_1')->sole();
        expect($devolver->id)->toBe($i->id)
            ->and($devolver->devolucao_motivo)->toBe('checkout_cancelado')
            ->and($devolver->assinatura_id_externo)->toBe('sub_9')
            ->and($devolver->status)->toBe(EstadoCobranca::Paga)
            ->and($devolver->usuario_id)->toBe($this->u->id)
            ->and(Invoice::query()->count())->toBe(1)
            ->and(Assinatura::query()->count())->toBe(0)
            ->and(AsaasSimulado::$assinaturasCanceladas)->toBe(['sub_9'])
            ->and(ProviderEvent::query()->where('evento_id', 'evt_1')->value('invoice_id'))->toBe($devolver->id);
        Log::shouldHaveReceived('error')->with('cobranca.pagamento_de_checkout_cancelado', Mockery::any())->once();
        Log::shouldNotHaveReceived('error', ['cobranca.pagamento_de_checkout_encerrado', Mockery::any()]);
    });

    it('CA-3: pagamento de assinatura desconhecida, sem dono, não some em silêncio: fica o alerta', function () {
        AsaasSimulado::$cobrancas['pay_7'] = ['id' => 'pay_7', 'status' => 'CONFIRMED', 'deleted' => false, 'value' => 49.9, 'subscription' => 'sub_x', 'paymentDate' => '2026-11-05'];

        Log::spy();
        _postarWebhookAsaas($this, 'PAYMENT_CONFIRMED', 'evt_7', ['payment' => ['id' => 'pay_7', 'subscription' => 'sub_x']])->assertOk();

        Log::shouldHaveReceived('error')->with('cobranca.evento_sem_invoice', Mockery::any());
        expect(Invoice::query()->count())->toBe(0);
    });

    it('CA-4: contestação revertida volta a Invoice a paga; reversão fora de contestação não muda nada', function () {
        [$i, $cob, $payment] = _doisCiclosAsaas($this, true);
        $cob('pay_1', 'CHARGEBACK_REQUESTED');
        _postarWebhookAsaas($this, 'PAYMENT_CHARGEBACK_REQUESTED', 'evt_c', $payment('pay_1'))->assertOk();
        expect($i->fresh()->status)->toBe(EstadoCobranca::Contestada);

        // O provedor ainda mostra contestada: a reversão não vale.
        _postarWebhookAsaas($this, 'PAYMENT_CHARGEBACK_REVERSED', 'evt_r0', $payment('pay_1'))->assertOk();
        expect($i->fresh()->status)->toBe(EstadoCobranca::Contestada)->and(ProviderEvent::query()->where('evento_id', 'evt_r0')->value('status'))->toBe('ignorado');

        $cob('pay_1', 'RECEIVED');
        _postarWebhookAsaas($this, 'PAYMENT_CHARGEBACK_REVERSED', 'evt_r1', $payment('pay_1'))->assertOk();
        expect($i->fresh()->status)->toBe(EstadoCobranca::Paga)->and(ProviderEvent::query()->where('evento_id', 'evt_r1')->value('status'))->toBe('processado');

        _postarWebhookAsaas($this, 'PAYMENT_CHARGEBACK_REVERSED', 'evt_r2', $payment('pay_1'))->assertOk();
        expect(ProviderEvent::query()->where('evento_id', 'evt_r2')->value('status'))->toBe('ignorado')
            ->and(Assinatura::query()->sole()->status)->toBe(StatusAssinatura::Ativa);
    });

    it('CA-4: estorno parcial registra o valor, mantém a cobrança paga e o acesso', function () {
        [$i, $cob, $payment] = _doisCiclosAsaas($this, true);

        Log::spy();
        _postarWebhookAsaas($this, 'PAYMENT_PARTIALLY_REFUNDED', 'evt_p', ['payment' => $payment('pay_1')['payment'] + ['refunds' => [['value' => 10.5], ['value' => 4.5]]]])->assertOk();

        expect($i->fresh()->status)->toBe(EstadoCobranca::Paga)
            ->and($i->fresh()->reembolsado_parcial_centavos)->toBe(1500)
            ->and(Assinatura::query()->sole()->status)->toBe(StatusAssinatura::Ativa)
            ->and(app(Carteira::class)->verificar($this->u->id)->bloqueio)->toBeNull();
        Log::shouldHaveReceived('error')->with('cobranca.estorno_parcial', Mockery::any());

        // Sem a lista de estornos o valor não se inventa: fica o anterior.
        _postarWebhookAsaas($this, 'PAYMENT_PARTIALLY_REFUNDED', 'evt_p2', ['payment' => $payment('pay_1')['payment']])->assertOk();
        expect($i->fresh()->reembolsado_parcial_centavos)->toBe(1500);
    });

    it('CA-4: estorno negado libera a trava do estorno pendente e alerta o admin; só vale com a cobrança paga', function () {
        [$i, $cob, $payment] = _doisCiclosAsaas($this, true);
        $i->forceFill(['reembolso_estado' => 'aguardando_autorizacao'])->save();

        Log::spy();
        $cob('pay_1', 'REFUND_REQUESTED');
        _postarWebhookAsaas($this, 'PAYMENT_REFUND_DENIED', 'evt_d', $payment('pay_1'))->assertOk();

        expect($i->fresh()->status)->toBe(EstadoCobranca::Paga)->and($i->fresh()->reembolso_estado)->toBeNull()
            ->and(ProviderEvent::query()->where('evento_id', 'evt_d')->value('status'))->toBe('processado');
        Log::shouldHaveReceived('error')->with('cobranca.reembolso_negado', Mockery::any())->once();

        // Repetido (ou sem estorno pendente): nada a fazer.
        _postarWebhookAsaas($this, 'PAYMENT_REFUND_DENIED', 'evt_d2', $payment('pay_1'))->assertOk();
        expect(ProviderEvent::query()->where('evento_id', 'evt_d2')->value('status'))->toBe('ignorado');

        // O provedor mostra a cobrança estornada: a negação é velha e não vale.
        $i->fresh()->forceFill(['reembolso_estado' => 'aguardando_autorizacao'])->save();
        $cob('pay_1', 'REFUNDED');
        _postarWebhookAsaas($this, 'PAYMENT_REFUND_DENIED', 'evt_d3', $payment('pay_1'))->assertOk();
        expect($i->fresh()->reembolso_estado)->toBe('aguardando_autorizacao');
    });

    it('CA-4: cobrança restaurada no provedor tem tratamento explícito e não reabre nada', function () {
        [$i, $cob, $payment] = _doisCiclosAsaas($this, true);

        _postarWebhookAsaas($this, 'PAYMENT_RESTORED', 'evt_rs', $payment('pay_1'))->assertOk();

        expect(ProviderEvent::query()->where('evento_id', 'evt_rs')->value('tipo'))->toBe(TipoEvento::CobrancaRestaurada->value)
            ->and(ProviderEvent::query()->where('evento_id', 'evt_rs')->value('status'))->toBe('ignorado')
            ->and($i->fresh()->status)->toBe(EstadoCobranca::Paga);
    });

    it('CA-4: os quatro eventos têm tipo canônico e o estorno parcial traz o valor em centavos', function () {
        $ler = fn (string $evento, array $extra = []) => app(PaymentProvider::class)->parseWebhook(new WebhookBruto(
            ['asaas-access-token' => 'tok-asaas-de-teste-aleatorio-com-32-caracteres'],
            json_encode(['id' => 'e-'.$evento, 'event' => $evento, ...$extra]),
        ));

        expect($ler('PAYMENT_CHARGEBACK_REVERSED')->tipo)->toBe(TipoEvento::ContestacaoRevertida)
            ->and($ler('PAYMENT_RESTORED')->tipo)->toBe(TipoEvento::CobrancaRestaurada)
            ->and($ler('PAYMENT_REFUND_DENIED')->tipo)->toBe(TipoEvento::EstornoNegado)
            ->and($ler('PAYMENT_PARTIALLY_REFUNDED')->tipo)->toBe(TipoEvento::EstornoParcial)
            ->and($ler('PAYMENT_PARTIALLY_REFUNDED', ['payment' => ['id' => 'p', 'refunds' => [['value' => 12.34]]]])->valorEstornadoCentavos)->toBe(1234)
            ->and($ler('PAYMENT_PARTIALLY_REFUNDED', ['payment' => ['id' => 'p', 'refunds' => 'x']])->valorEstornadoCentavos)->toBeNull()
            ->and($ler('PAYMENT_PARTIALLY_REFUNDED', ['payment' => ['id' => 'p', 'refunds' => [['value' => 0], 'x']]])->valorEstornadoCentavos)->toBeNull()
            ->and($ler('PAYMENT_CONFIRMED')->valorEstornadoCentavos)->toBeNull();
    });
});

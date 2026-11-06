<?php

declare(strict_types=1);

use App\Domain\Cobranca\CicloCobranca;
use App\Domain\Cobranca\EstadoCobranca;
use App\Domain\Cobranca\PaymentProvider;
use App\Domain\Cobranca\ReferenciaProvedor;
use App\Domain\Cobranca\StatusAssinatura;
use App\Infrastructure\Cobranca\Fake\FakePaymentProvider;
use App\Mail\FalhaDeCobrancaMessage;
use App\Models\Assinatura;
use App\Models\CobrancaConfig;
use App\Models\Invoice;
use App\Models\ProviderEvent;
use App\Support\DataExibicao;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * STORY-079 CA-6 / ADR-010 §3 — a conciliação horária e a diária recuperam o que o webhook não trouxe
 * (perdido) sem aplicar nada duas vezes. O estado atual do provedor manda.
 */
beforeEach(function () {
    _usarProvedor('fake');
    Mail::fake();
    $this->usuario = _compradorConfirmado();
});

function _conciliar(string $modo): int
{
    return Artisan::call('cobranca:conciliar', ['--modo' => $modo]);
}

describe('renovação perdida', function () {
    it('a horária acha a cobrança paga cujo webhook se perdeu e renova uma vez só', function () {
        [$a, $primeira] = _assinaturaAtivaNoFake($this->usuario);
        $this->travelTo($a->proximo_vencimento);
        _fake()->criarCobrancaDeRenovacao((string) $primeira->checkout_id_externo, EstadoCobranca::Paga); // sem webhook
        $this->travelTo(now()->addHours(3));

        expect(_conciliar('horaria'))->toBe(0);
        $a->refresh();
        expect($a->periodos_pagos)->toBe(2)->and($a->status)->toBe(StatusAssinatura::Ativa)->and(Invoice::query()->where('renovacao', true)->count())->toBe(1);

        // Rodar de novo, na horária ou na diária, não aplica nada outra vez.
        _conciliar('horaria');
        _conciliar('diaria');
        expect($a->fresh()->periodos_pagos)->toBe(2)->and(Invoice::query()->where('renovacao', true)->count())->toBe(1);
    });

    it('a diária acha a recusa perdida e põe a assinatura em atraso, uma vez', function () {
        [$a, $primeira] = _assinaturaAtivaNoFake($this->usuario);
        $this->travelTo($a->proximo_vencimento);
        _fake()->criarCobrancaDeRenovacao((string) $primeira->checkout_id_externo, EstadoCobranca::Vencida);

        _conciliar('diaria');
        _conciliar('diaria');
        $a->refresh();

        expect($a->status)->toBe(StatusAssinatura::EmAtraso)->and($a->tentativas_cobranca)->toBe(0);
        Mail::assertSent(FalhaDeCobrancaMessage::class, 1);
    });

    it('cobrança ainda no prazo, sem atraso real, não vira falha', function () {
        [$a, $primeira] = _assinaturaAtivaNoFake($this->usuario);
        $this->travelTo($a->proximo_vencimento);
        _fake()->criarCobrancaDeRenovacao((string) $primeira->checkout_id_externo, EstadoCobranca::Pendente);

        _conciliar('diaria');

        expect($a->fresh()->status)->toBe(StatusAssinatura::Ativa);
    });

    it('um dia depois do vencimento, cobrança ainda "pendente" no provedor conta como não paga', function () {
        [$a, $primeira] = _assinaturaAtivaNoFake($this->usuario);
        $this->travelTo($a->proximo_vencimento);
        _fake()->criarCobrancaDeRenovacao((string) $primeira->checkout_id_externo, EstadoCobranca::Pendente);
        $this->travelTo(now()->addDays(2));

        _conciliar('diaria');

        expect($a->fresh()->status)->toBe(StatusAssinatura::EmAtraso);
    });

    it('a horária só olha assinatura com ciclo vencido sem renovação; a diária olha todas', function () {
        [$a, $primeira] = _assinaturaAtivaNoFake($this->usuario);
        // Meio do ciclo: nada a conciliar na horária, mas a diária ainda confere a assinatura (e não acha nada).
        expect(_conciliar('horaria'))->toBe(0)->and(Artisan::output())->toContain('0 verificadas');

        _conciliar('diaria');
        expect(Artisan::output())->toContain('1 verificadas');
        expect($a->fresh()->periodos_pagos)->toBe(1);
    });
});

describe('checkout inicial', function () {
    it('pagamento perdido do checkout: a conciliação ativa a assinatura', function () {
        $invoice = _assinaturaPendente($this->usuario);
        _fake()->simularPagamento((string) $invoice->checkout_id_externo); // muda no provedor; o webhook nunca chega
        $this->travelTo(now()->addMinutes(20));

        _conciliar('horaria');

        expect($invoice->fresh()->status)->toBe(EstadoCobranca::Paga)->and(Assinatura::query()->count())->toBe(1);
        _conciliar('horaria');
        _conciliar('diaria');
        expect(Assinatura::query()->count())->toBe(1)->and(ProviderEvent::query()->where('evento_id', 'like', 'conciliacao:%')->count())->toBe(1);
    });

    it('checkout vencido e sem pagamento é encerrado pela conciliação', function () {
        $invoice = _assinaturaPendente($this->usuario);
        $this->travelTo(now()->addHours(2));

        _conciliar('horaria');

        expect($invoice->fresh()->status)->toBe(EstadoCobranca::Vencida);
    });

    it('checkout ainda dentro do prazo ou criado há menos de 15 minutos não é tocado', function () {
        $invoice = _assinaturaPendente($this->usuario);
        $this->travelTo(now()->addMinutes(5));

        _conciliar('horaria');

        expect($invoice->fresh()->status)->toBe(EstadoCobranca::Pendente);
    });

    it('a diária também alcança checkout que a horária já deu por vencido e que o banco confirmou depois', function () {
        $invoice = _assinaturaPendente($this->usuario);
        $this->travelTo(now()->addHours(2));
        _conciliar('horaria');
        expect($invoice->fresh()->status)->toBe(EstadoCobranca::Vencida);

        _fake()->simularPagamento((string) $invoice->checkout_id_externo);
        _conciliar('diaria');

        expect($invoice->fresh()->status)->toBe(EstadoCobranca::Paga)->and(Assinatura::query()->count())->toBe(1);
    });
});

describe('CA-7 provedor indisponível e interruptor', function () {
    it('com o provedor fora: alerta, nada muda, e o código de saída avisa a operação', function () {
        [$a, $primeira] = _assinaturaAtivaNoFake($this->usuario);
        $this->travelTo($a->proximo_vencimento);
        _fake()->criarCobrancaDeRenovacao((string) $primeira->checkout_id_externo, EstadoCobranca::Paga);
        $this->travelTo(now()->addHours(3));
        Log::spy();
        FakePaymentProvider::ficarIndisponivel();

        expect(_conciliar('horaria'))->toBe(1);

        expect($a->fresh()->periodos_pagos)->toBe(1);
        Log::shouldHaveReceived('error')->withArgs(fn (string $m, array $c) => $m === 'cobranca.provedor_indisponivel' && $c['action'] === 'conciliar')->once();

        FakePaymentProvider::ficarIndisponivel(false);
        expect(_conciliar('horaria'))->toBe(0)->and($a->fresh()->periodos_pagos)->toBe(2);
    });

    it('depois de 5 falhas seguidas do provedor a rodada para de insistir', function () {
        for ($i = 0; $i < 7; $i++) {
            $u = $i === 0 ? $this->usuario : _compradorConfirmado();
            _assinaturaAtivaNoFake($u);
        }

        $this->travelTo(now()->addMonths(1)->addHours(3));
        FakePaymentProvider::ficarIndisponivel();

        expect(_conciliar('diaria'))->toBe(1)->and(Artisan::output())->toContain('rodada abortada');
    });

    it('um erro inesperado numa assinatura é registrado e não impede a conciliação das outras', function () {
        [$a, $primeira] = _assinaturaAtivaNoFake($this->usuario);
        [$b, $primeiraDoOutro] = _assinaturaAtivaNoFake(_compradorConfirmado());
        $this->travelTo($a->proximo_vencimento->copy()->max($b->proximo_vencimento));
        _fake()->criarCobrancaDeRenovacao((string) $primeira->checkout_id_externo, EstadoCobranca::Paga);
        _fake()->criarCobrancaDeRenovacao((string) $primeiraDoOutro->checkout_id_externo, EstadoCobranca::Paga);
        app()->instance(PaymentProvider::class, _provedorQueFalha([
            'listSubscriptionCharges' => fn (ReferenciaProvedor $r) => $r->id === $a->id_externo ? new RuntimeException('bug') : null,
        ]));
        Log::spy();

        expect(_conciliar('diaria'))->toBe(1);

        expect($a->fresh()->periodos_pagos)->toBe(1)->and($b->fresh()->periodos_pagos)->toBe(2);
        Log::shouldHaveReceived('error')->withArgs(fn (string $m, array $c) => $m === 'cobranca.conciliacao_com_erro' && $c['exception'] === RuntimeException::class)->once();
    });

    it('com a cobrança desligada não concilia nada', function () {
        $invoice = _assinaturaPendente($this->usuario);
        _fake()->simularPagamento((string) $invoice->checkout_id_externo);
        CobrancaConfig::atual()->update(['ativa' => false]);
        $this->travelTo(now()->addMinutes(20));

        _conciliar('diaria');

        expect($invoice->fresh()->status)->toBe(EstadoCobranca::Pendente);
    });

    it('recusa um modo que não existe', function () {
        expect(_conciliar('semanal'))->toBe(2);
    });

    it('mostra progresso com total, percentual, decorrido e ETA', function () {
        _assinaturaAtivaNoFake($this->usuario);

        _conciliar('diaria');

        expect(Artisan::output())->toContain('[conciliacao-diaria] 1/1 (100%)')->toContain('ETA')->toContain('decorrido');
    });
});

describe('anual', function () {
    it('renovação anual perdida é recuperada pela diária', function () {
        [$a, $primeira] = _assinaturaAtivaNoFake($this->usuario, CicloCobranca::Anual);
        $this->travelTo($a->proximo_vencimento);
        _fake()->criarCobrancaDeRenovacao((string) $primeira->checkout_id_externo, EstadoCobranca::Paga);

        _conciliar('diaria');

        expect($a->fresh()->periodos_pagos)->toBe(2)->and(DataExibicao::converter($a->fresh()->proximo_vencimento)->toDateString())->toBe(DataExibicao::converter($a->data_inicio)->addYears(2)->toDateString());
    });
});

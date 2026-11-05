<?php

declare(strict_types=1);

use App\Domain\Cobranca\CicloCobranca;
use App\Domain\Cobranca\EstadoCobranca;
use App\Domain\Cobranca\MeioPagamento;
use App\Domain\Cobranca\StatusAssinatura;
use App\Infrastructure\Cobranca\Fake\FakePaymentProvider;
use App\Mail\AssinaturaEncerradaMessage;
use App\Mail\FalhaDeCobrancaMessage;
use App\Mail\RenovacaoPixMessage;
use App\Mail\RenovacaoProximaMessage;
use App\Models\Assinatura;
use App\Models\Invoice;
use App\Models\Usuario;
use App\Services\Cobranca\FalhaDeRenovacao;
use App\Services\Cobranca\IniciarRenovacaoPix;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;

/**
 * STORY-079 × STORY-080 — o plano anual pago com Pix não é cartão: não entra na régua de cobrança nem no
 * aviso D-7 de cartão (segue só o aviso e o encerramento da 080), e a conciliação cobre as cobranças dele
 * (os QR Codes) sem renovar duas vezes.
 */
beforeEach(function () {
    _usarProvedor('fake');
    Mail::fake();
    $this->usuario = _compradorConfirmado();
});

describe('fora da régua de cartão', function () {
    it('nos últimos 7 dias o Pix recebe só o aviso da 080; o cartão anual recebe o D-7 de cartão', function () {
        $pix = _assinaturaPixAtiva($this->usuario);
        $cartaoUsuario = Usuario::factory()->create(['email_confirmed_at' => now()]);
        $cartao = Assinatura::query()->findOrFail(_pagarAssinatura(_assinaturaPendente($cartaoUsuario, null, CicloCobranca::Anual))->assinatura_id);
        $this->travelTo($pix->proximo_vencimento->copy()->subDays(5));

        Artisan::call('assinaturas:avisar-renovacao');
        Artisan::call('assinaturas:pix-anual');
        Artisan::call('assinaturas:avisar-renovacao');
        Artisan::call('assinaturas:pix-anual');

        Mail::assertSent(RenovacaoProximaMessage::class, fn (RenovacaoProximaMessage $m) => $m->hasTo($cartaoUsuario->email));
        Mail::assertNotSent(RenovacaoProximaMessage::class, fn (RenovacaoProximaMessage $m) => $m->hasTo($this->usuario->email));
        Mail::assertSent(RenovacaoPixMessage::class, 1);
        Mail::assertSent(RenovacaoPixMessage::class, fn (RenovacaoPixMessage $m) => $m->hasTo($this->usuario->email));
        expect($pix->fresh()->aviso_renovacao_periodo)->toBeNull()->and($cartao->fresh()->aviso_renovacao_periodo)->toBe(1);
    });

    it('a recusa de cobrança nunca põe o Pix em atraso, mesmo que o provedor avise com o id de uma assinatura', function () {
        $a = _assinaturaPixAtiva($this->usuario);
        $a->forceFill(['id_externo' => 'fakesub_x', 'provedor' => 'fake'])->save();
        $this->travelTo($a->proximo_vencimento);
        $cobranca = _fake()->criarCobrancaDeRenovacao('fakechk_x', EstadoCobranca::Vencida);

        expect(app(FalhaDeRenovacao::class)->registrar($a->refresh(), $cobranca))->toBe(['ignorado', null]);

        expect($a->refresh()->status)->toBe(StatusAssinatura::Ativa)->and($a->atraso_desde)->toBeNull();
        Mail::assertNotSent(FalhaDeCobrancaMessage::class);
    });

    it('a régua não retenta, não avisa nem encerra um Pix, mesmo marcado em atraso por engano', function () {
        $a = _assinaturaPixAtiva($this->usuario);
        $cobranca = _fake()->criarCobrancaDeRenovacao('fakechk_x', EstadoCobranca::Vencida);
        $a->forceFill([
            'id_externo' => 'fakesub_x', 'status' => StatusAssinatura::EmAtraso, 'atraso_desde' => now(), 'atraso_cobranca_id' => $cobranca,
            'proxima_tentativa_em' => now()->subHour(), 'atraso_aviso_em' => null, 'encerramento_pendente_no_provedor' => true,
        ])->save();

        expect(Artisan::call('cobranca:regua'))->toBe(0);

        $a->refresh();
        expect($a->tentativas_cobranca)->toBe(0)->and($a->status)->toBe(StatusAssinatura::EmAtraso)
            ->and(FakePaymentProvider::tentativasDe($cobranca))->toBe(0)
            ->and(FakePaymentProvider::assinaturaCancelada('fakesub_x'))->toBeFalse();
        Mail::assertNotSent(FalhaDeCobrancaMessage::class);
    });

    it('sem renovação paga, o fim do ciclo só encerra (080): nenhuma retentativa, e-mail de falha ou cancelamento no provedor', function () {
        $a = _assinaturaPixAtiva($this->usuario);
        $this->travelTo($a->proximo_vencimento->copy()->addHour());

        Artisan::call('assinaturas:pix-anual');
        Artisan::call('cobranca:regua');
        Artisan::call('cobranca:conciliar', ['--modo' => 'diaria']);
        Artisan::call('cobranca:regua');

        expect($a->refresh()->status)->toBe(StatusAssinatura::Expirada)->and($a->tentativas_cobranca)->toBe(0)->and($a->atraso_desde)->toBeNull();
        Mail::assertNotSent(FalhaDeCobrancaMessage::class);
        Mail::assertNotSent(AssinaturaEncerradaMessage::class);
    });
});

describe('conciliação do Pix anual', function () {
    it('a renovação paga cujo webhook se perdeu é achada e renova uma vez só, na horária ou na diária', function () {
        $a = _assinaturaPixAtiva($this->usuario);
        $fimAntigo = $a->proximo_vencimento->copy();
        $this->travelTo($fimAntigo->copy()->subDays(2));
        $renov = app(IniciarRenovacaoPix::class)->executar($this->usuario, '203.0.113.7', 'Pest');
        _fake()->simularPagamento((string) $renov->checkout_id_externo); // o banco confirmou; o webhook não chegou
        $this->travelTo(now()->addMinutes(20));

        Artisan::call('cobranca:conciliar', ['--modo' => 'horaria']);
        $a->refresh();
        expect($a->periodos_pagos)->toBe(2)
            ->and($a->proximo_vencimento->toDateString())->toBe($fimAntigo->copy()->addYear()->toDateString())
            ->and($renov->fresh()->status)->toBe(EstadoCobranca::Paga);

        Artisan::call('cobranca:conciliar', ['--modo' => 'horaria']);
        Artisan::call('cobranca:conciliar', ['--modo' => 'diaria']);
        // O webhook atrasado que enfim chega também não renova de novo.
        _entregar(_fake()->simularPagamento((string) $renov->checkout_id_externo, 'evt-tardio'));

        expect($a->fresh()->periodos_pagos)->toBe(2)
            ->and(Invoice::query()->where('renovacao', true)->count())->toBe(1)
            ->and(Invoice::query()->where('status', 'paga')->count())->toBe(2);
    });

    it('a contratação por Pix paga com webhook perdido também é ativada pela conciliação, uma vez', function () {
        $invoice = _assinaturaPendente($this->usuario, null, CicloCobranca::Anual, MeioPagamento::Pix);
        _fake()->simularPagamento((string) $invoice->checkout_id_externo);
        $this->travelTo(now()->addMinutes(20));

        Artisan::call('cobranca:conciliar', ['--modo' => 'horaria']);
        Artisan::call('cobranca:conciliar', ['--modo' => 'diaria']);

        $a = Assinatura::query()->where('usuario_id', $this->usuario->id)->sole();
        expect($a->status)->toBe(StatusAssinatura::Ativa)->and($a->pagoComPix())->toBeTrue()->and($a->periodos_pagos)->toBe(1)
            ->and($invoice->fresh()->status)->toBe(EstadoCobranca::Paga);
    });

    it('a conciliação não cobra o Pix como assinatura no provedor: o QR sem pagamento só expira', function () {
        $a = _assinaturaPixAtiva($this->usuario);
        $this->travelTo($a->proximo_vencimento->copy()->subDays(2));
        $renov = app(IniciarRenovacaoPix::class)->executar($this->usuario, null, null);
        $this->travelTo(now()->addHours(2));

        Artisan::call('cobranca:conciliar', ['--modo' => 'horaria']);
        Artisan::call('cobranca:conciliar', ['--modo' => 'diaria']);

        expect($a->refresh()->periodos_pagos)->toBe(1)->and($a->status)->toBe(StatusAssinatura::Ativa)
            ->and($renov->fresh()->status)->toBe(EstadoCobranca::Vencida);
    });
});

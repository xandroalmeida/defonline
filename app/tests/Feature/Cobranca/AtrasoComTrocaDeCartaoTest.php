<?php

declare(strict_types=1);

use App\Domain\Cobranca\StatusAssinatura;
use App\Domain\Cobranca\TipoEvento;
use App\Infrastructure\Cobranca\Fake\FakePaymentProvider;
use App\Models\Assinatura;
use App\Models\Invoice;
use App\Services\Cobranca\TrocarCartao;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * STORY-079 × STORY-081 — os dois caminhos que tiram uma assinatura do atraso (pagamento pela régua e
 * pagamento da 1ª cobrança do cartão novo) convivem: ambos restabelecem e param a régua, sem cobrança
 * duplicada, e a troca concluída tira a cobrança antiga da régua.
 */
beforeEach(function () {
    _usarProvedor('fake');
    Mail::fake();
    $this->usuario = _compradorConfirmado();
});

/** Assinatura em atraso de verdade: ciclo 2 recusado pelo banco, régua armada. */
function _atrasoReal($t): Assinatura
{
    [$a, $primeira] = _assinaturaAtivaNoFake($t->usuario);
    $t->primeira = $primeira;

    return _recusarRenovacao($a, $primeira);
}

it('a retentativa da régua que passa restabelece e para a régua, sem cobrança duplicada', function () {
    $a = _atrasoReal($this);
    $cobranca = (string) $a->atraso_cobranca_id;
    FakePaymentProvider::aprovarProximaTentativa($cobranca);

    _rodarRegua(48);

    $a->refresh();
    expect($a->status)->toBe(StatusAssinatura::Ativa)
        ->and($a->atraso_cobranca_id)->toBeNull()
        ->and($a->proxima_tentativa_em)->toBeNull()
        ->and(Invoice::query()->where('renovacao', true)->count())->toBe(1)
        ->and(FakePaymentProvider::tentativasDe($cobranca))->toBe(1);

    _rodarRegua(200);
    expect(FakePaymentProvider::tentativasDe($cobranca))->toBe(1);
});

it('concluída a troca em atraso, a cobrança antiga não é mais retentada nem encerra o ciclo', function () {
    $a = _atrasoReal($this);
    $antigaCobranca = (string) $a->atraso_cobranca_id;
    $troca = app(TrocarCartao::class)->iniciar($this->usuario);

    _entregar(_fake()->webhook(TipoEvento::CheckoutConcluido, (string) $troca->checkout_id_externo));

    $a->refresh();
    expect($a->status)->toBe(StatusAssinatura::EmAtraso)
        ->and($a->atraso_cobranca_id)->toBeNull()
        ->and($a->proxima_tentativa_em)->toBeNull();

    // Passam as 3 janelas e o prazo de encerramento: a régua não toca na cobrança velha nem expira a assinatura.
    foreach ([48, 48, 48, 48] as $horas) {
        _rodarRegua($horas);
    }

    expect(FakePaymentProvider::tentativasDe($antigaCobranca))->toBe(0)
        ->and($a->refresh()->status)->toBe(StatusAssinatura::EmAtraso)
        ->and($a->encerrada_em)->toBeNull();
});

it('depois da troca em atraso, o pagamento do cartão novo restabelece uma vez só e a régua segue parada', function () {
    $a = _atrasoReal($this);
    $troca = app(TrocarCartao::class)->iniciar($this->usuario);
    $checkout = (string) $troca->checkout_id_externo;
    _entregar(_fake()->webhook(TipoEvento::CheckoutConcluido, $checkout));

    _entregar(_fake()->simularPagamento($checkout, 'evt-novo-1'));
    _entregar(_fake()->simularPagamento($checkout, 'evt-novo-2'));

    $a->refresh();
    expect($a->status)->toBe(StatusAssinatura::Ativa)
        ->and($a->periodos_pagos)->toBe(2)
        ->and($a->atraso_cobranca_id)->toBeNull()
        ->and($a->proxima_tentativa_em)->toBeNull()
        ->and(Invoice::query()->where('renovacao', true)->count())->toBe(1);

    _rodarRegua(300);
    expect($a->refresh()->status)->toBe(StatusAssinatura::Ativa);
});

it('se a 1ª cobrança do cartão novo é recusada, a régua recomeça sobre ela (a assinatura não fica parada em atraso)', function () {
    $a = _atrasoReal($this);
    $troca = app(TrocarCartao::class)->iniciar($this->usuario);
    $checkout = (string) $troca->checkout_id_externo;
    _entregar(_fake()->webhook(TipoEvento::CheckoutConcluido, $checkout));

    _entregar(_fake()->simularRecusa($checkout));

    $a->refresh();
    expect($a->status)->toBe(StatusAssinatura::EmAtraso)
        ->and($a->atraso_cobranca_id)->toStartWith('fakepay_')
        ->and($a->proxima_tentativa_em)->not->toBeNull();

    // E essa cobrança nova é a que a régua passa a retentar; passando, restabelece.
    FakePaymentProvider::aprovarProximaTentativa((string) $a->atraso_cobranca_id);
    _rodarRegua(48);

    expect($a->refresh()->status)->toBe(StatusAssinatura::Ativa)->and(Invoice::query()->where('renovacao', true)->count())->toBe(1);
});

it('pagamento da cobrança antiga que estava em voo quando a troca concluiu não renova e vira alerta de devolução', function () {
    Log::spy();
    $a = _atrasoReal($this);
    $antigaCobranca = (string) $a->atraso_cobranca_id;
    $troca = app(TrocarCartao::class)->iniciar($this->usuario);
    _entregar(_fake()->webhook(TipoEvento::CheckoutConcluido, (string) $troca->checkout_id_externo));

    _entregar(_fake()->simularPagamentoDaCobranca($antigaCobranca));

    expect(Invoice::query()->where('renovacao', true)->count())->toBe(0)
        ->and($a->refresh()->status)->toBe(StatusAssinatura::EmAtraso);
    Log::shouldHaveReceived('error')->withArgs(fn (string $m) => $m === 'cobranca.pagamento_da_assinatura_antiga')->once();
});

<?php

declare(strict_types=1);

use App\Domain\Cobranca\Carteira;
use App\Domain\Cobranca\PaymentProvider;
use App\Domain\Cobranca\ProvedorIndisponivel;
use App\Domain\Cobranca\StatusAssinatura;
use App\Domain\Cobranca\TipoEvento;
use App\Infrastructure\Cobranca\Fake\FakePaymentProvider;
use App\Models\Assinatura;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\TrocaDeCartao;
use App\Services\Cobranca\ExecutarReguaDeCobranca;
use App\Services\Cobranca\GerirRenovacao;
use App\Services\Cobranca\GestaoNegada;
use App\Services\Cobranca\TrocarCartao;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * STORY-079 × STORY-081, decisão do PO (Termo v2: cancelar "a qualquer momento") — cancelar a renovação
 * de uma assinatura EM ATRASO: a régua para, a recorrência é encerrada no provedor, nada mais é cobrado
 * nem retentado, o acesso é bloqueado na hora (o ciclo não foi pago) e o histórico fica.
 */
beforeEach(function () {
    _usarProvedor('fake');
    Mail::fake();
    $this->usuario = _compradorConfirmado();
});

/** Assinatura em atraso de verdade (ciclo 2 recusado), com a régua armada. */
function _emAtrasoDaRegua($t): Assinatura
{
    [$a, $primeira] = _assinaturaAtivaNoFake($t->usuario);
    $t->primeira = $primeira;

    return _recusarRenovacao($a, $primeira);
}

it('encerra a recorrência, para a régua, bloqueia o acesso na hora e preserva o histórico', function () {
    $a = _emAtrasoDaRegua($this);
    $cobranca = (string) $a->atraso_cobranca_id;
    $desde = $a->atraso_desde?->toIso8601String();
    $antes = Invoice::query()->count();

    $r = app(GerirRenovacao::class)->cancelar($this->usuario);

    expect($r->status)->toBe(StatusAssinatura::Expirada)
        ->and($r->encerrada_em)->not->toBeNull()
        ->and($r->data_cancelamento)->not->toBeNull()
        ->and($r->proxima_tentativa_em)->toBeNull()
        ->and($r->encerramento_pendente_no_provedor)->toBeFalse()
        ->and($r->atraso_desde?->toIso8601String())->toBe($desde)
        ->and($r->atraso_cobranca_id)->toBe($cobranca)
        ->and(FakePaymentProvider::situacaoDaAssinatura((string) $a->id_externo))->toBe('cancelada')
        ->and(FakePaymentProvider::assinaturaCancelada((string) $a->id_externo))->toBeTrue()
        ->and(Invoice::query()->count())->toBe($antes)
        ->and(Invoice::query()->where('status', 'paga')->count())->toBe(1)
        ->and(app(Carteira::class)->verificar($this->usuario->id)->bloqueio)->not->toBeNull()
        ->and(AuditLog::query()->where('action', 'cobranca.renovacao_cancelada_em_atraso')->where('subject_id', $a->id)->exists())->toBeTrue();
});

it('depois de cancelar não há retentativa nem encerramento pela régua, quanto tempo passar', function () {
    $a = _emAtrasoDaRegua($this);
    $cobranca = (string) $a->atraso_cobranca_id;
    app(GerirRenovacao::class)->cancelar($this->usuario);

    foreach ([48, 48, 48, 48, 100] as $horas) {
        _rodarRegua($horas);
    }

    expect(FakePaymentProvider::tentativasDe($cobranca))->toBe(0)
        ->and($a->refresh()->status)->toBe(StatusAssinatura::Expirada)
        ->and(AuditLog::query()->where('action', 'cobranca.assinatura_encerrada_por_falta_de_pagamento')->exists())->toBeFalse();
});

it('nenhuma cobrança nova entra: pagamento e recusa avisados depois do cancelamento não mudam nada', function () {
    Log::spy();
    $a = _emAtrasoDaRegua($this);
    $cobranca = (string) $a->atraso_cobranca_id;
    app(GerirRenovacao::class)->cancelar($this->usuario);

    _entregar(_fake()->simularPagamentoDaCobranca($cobranca));
    _entregar(_fake()->simularRenovacao((string) $this->primeira->checkout_id_externo));

    $a->refresh();
    expect($a->status)->toBe(StatusAssinatura::Expirada)
        ->and($a->periodos_pagos)->toBe(1)
        ->and(Invoice::query()->where('renovacao', true)->count())->toBe(0);
    Log::shouldHaveReceived('error')->withArgs(fn (string $m) => $m === 'cobranca.renovacao_de_assinatura_inativa')->atLeast()->once();
});

it('se o provedor não responde, nada muda e a régua segue valendo', function () {
    $a = _emAtrasoDaRegua($this);
    $proxima = $a->proxima_tentativa_em?->getTimestamp();
    FakePaymentProvider::falharProximaOperacaoDeAssinatura();

    expect(fn () => app(GerirRenovacao::class)->cancelar($this->usuario))->toThrow(ProvedorIndisponivel::class);

    $a->refresh();
    expect($a->status)->toBe(StatusAssinatura::EmAtraso)
        ->and($a->encerrada_em)->toBeNull()
        ->and($a->proxima_tentativa_em?->getTimestamp())->toBe($proxima)
        ->and(FakePaymentProvider::situacaoDaAssinatura((string) $a->id_externo))->toBe('ativa');
});

it('clique repetido não reabre nem chama o provedor: a assinatura já não existe para cancelar', function () {
    _emAtrasoDaRegua($this);
    app(GerirRenovacao::class)->cancelar($this->usuario);

    expect(fn () => app(GerirRenovacao::class)->cancelar($this->usuario))->toThrow(GestaoNegada::class);
});

it('o admin em "Logar como" e o plano sem recorrência não cancelam em atraso', function () {
    $a = _emAtrasoDaRegua($this);
    $a->forceFill(['id_externo' => null])->save();

    try {
        app(GerirRenovacao::class)->cancelar($this->usuario);
        $this->fail('devia negar');
    } catch (GestaoNegada $e) {
        expect($e->motivo)->toBe(GestaoNegada::NAO_RENOVA_SOZINHA);
    }

    $a->forceFill(['id_externo' => 'x'])->save();
    _personificar(_adminDaEquipe(), $this->usuario);

    try {
        app(GerirRenovacao::class)->cancelar($this->usuario);
        $this->fail('devia negar');
    } catch (GestaoNegada $e) {
        expect($e->motivo)->toBe(GestaoNegada::ADMIN_PERSONIFICANDO);
    }

    expect($a->refresh()->status)->toBe(StatusAssinatura::EmAtraso);
});

describe('corrida entre cancelar e a régua', function () {
    it('cancelar ANTES da rodada: a rodada devida não pede nova tentativa', function () {
        $a = _emAtrasoDaRegua($this);
        $cobranca = (string) $a->atraso_cobranca_id;
        $this->travelTo(now()->addHours(48));

        // A rodada já tinha listado a assinatura como devida; o cancelamento chega antes do lock dela.
        $regua = app(ExecutarReguaDeCobranca::class);
        app(GerirRenovacao::class)->cancelar($this->usuario);

        expect($regua->executar()['retentadas'])->toBe(0)
            ->and(FakePaymentProvider::tentativasDe($cobranca))->toBe(0)
            ->and($a->refresh()->status)->toBe(StatusAssinatura::Expirada);
    });

    it('cancelar COM a retentativa em voo: se o banco aprovar, a assinatura NÃO volta, nada renova e a equipe é alertada', function () {
        Log::spy();
        $a = _emAtrasoDaRegua($this);
        $cobranca = (string) $a->atraso_cobranca_id;
        FakePaymentProvider::aprovarProximaTentativa($cobranca);
        $this->travelTo(now()->addHours(48));
        $usuario = $this->usuario;

        // O provedor recebe o pedido de retentativa; no meio dele o usuário cancela.
        app()->instance(PaymentProvider::class, _provedorQueFalha([
            'retryCharge' => function () use ($usuario) {
                app(GerirRenovacao::class)->cancelar($usuario);

                return null;
            },
        ]));

        app(ExecutarReguaDeCobranca::class)->executar();

        $a->refresh();
        expect($a->status)->toBe(StatusAssinatura::Expirada)
            ->and($a->periodos_pagos)->toBe(1)
            ->and(Invoice::query()->where('renovacao', true)->count())->toBe(0)
            ->and(FakePaymentProvider::tentativasDe($cobranca))->toBe(1);
        Log::shouldHaveReceived('error')->withArgs(fn (string $m) => $m === 'cobranca.renovacao_de_assinatura_inativa')->once();

        // E a partir daí a régua não volta a mexer.
        _rodarRegua(100);
        expect(FakePaymentProvider::tentativasDe($cobranca))->toBe(1);
    });

    it('a rodada que reservou a tentativa e vê o cancelamento depois não encerra de novo nem reabre', function () {
        $a = _emAtrasoDaRegua($this);
        $this->travelTo(now()->addHours(48));
        $regua = app(ExecutarReguaDeCobranca::class);
        app(GerirRenovacao::class)->cancelar($this->usuario);

        $metodo = new ReflectionMethod($regua, 'reservar');
        $reserva = $metodo->invoke($regua, $a->id, false);

        expect($reserva['acao']->name)->toBe('Aguardar')
            ->and($a->refresh()->status)->toBe(StatusAssinatura::Expirada);
    });

    it('pagamento que chega antes do cancelamento restabelece, e então cancelar é o cancelamento comum (acesso até o fim do ciclo)', function () {
        $a = _emAtrasoDaRegua($this);
        _entregar(_fake()->simularPagamentoDaCobranca((string) $a->atraso_cobranca_id));

        $r = app(GerirRenovacao::class)->cancelar($this->usuario);

        expect($r->status)->toBe(StatusAssinatura::Cancelada)
            ->and($r->proximo_vencimento->isFuture())->toBeTrue()
            ->and(app(Carteira::class)->verificar($this->usuario->id)->bloqueio)->toBeNull()
            ->and(FakePaymentProvider::situacaoDaAssinatura((string) $a->id_externo))->toBe('suspensa');
    });
});

describe('troca de cartão aberta', function () {
    it('cancelar fecha a troca pendente; o pagamento tardio dela cancela a assinatura nova e não religa a cobrança', function () {
        Log::spy();
        $a = _emAtrasoDaRegua($this);
        $antiga = (string) $a->id_externo;
        $troca = app(TrocarCartao::class)->iniciar($this->usuario);
        $checkout = (string) $troca->checkout_id_externo;

        app(GerirRenovacao::class)->cancelar($this->usuario);
        expect($troca->refresh()->status)->toBe('cancelada');

        _entregar(_fake()->webhook(TipoEvento::CheckoutConcluido, $checkout));
        _entregar(_fake()->simularPagamento($checkout));

        $a->refresh();
        expect($a->status)->toBe(StatusAssinatura::Expirada)
            ->and($a->id_externo)->toBe($antiga)
            ->and(Invoice::query()->where('renovacao', true)->count())->toBe(0)
            ->and(FakePaymentProvider::situacaoDaAssinatura('fakesub_'.str_replace('fakechk_', '', $checkout)))->toBe('cancelada');
        Log::shouldHaveReceived('error')->withArgs(fn (string $m) => $m === 'cobranca.troca_de_cartao_apos_cancelamento')->atLeast()->once();
        expect(TrocaDeCartao::query()->count())->toBe(1);
    });

    it('troca pendente de quem cancelou a renovação em dia (Cancelada) também não religa a cobrança', function () {
        $a = _assinaturaAtivaNoFake($this->usuario)[0];
        $troca = app(TrocarCartao::class)->iniciar($this->usuario);
        $checkout = (string) $troca->checkout_id_externo;
        app(GerirRenovacao::class)->cancelar($this->usuario);

        _entregar(_fake()->webhook(TipoEvento::CheckoutConcluido, $checkout));

        $a->refresh();
        expect($a->status)->toBe(StatusAssinatura::Cancelada)
            ->and($a->id_externo)->toBe($troca->antiga_id_externo)
            ->and(FakePaymentProvider::situacaoDaAssinatura('fakesub_'.str_replace('fakechk_', '', $checkout)))->toBe('cancelada');
    });

    it('se o provedor não cancela a assinatura nova órfã, alerta', function () {
        Log::spy();
        _emAtrasoDaRegua($this);
        $troca = app(TrocarCartao::class)->iniciar($this->usuario);
        app(GerirRenovacao::class)->cancelar($this->usuario);
        app()->instance(PaymentProvider::class, _provedorQueFalha(['cancelSubscription' => fn () => new ProvedorIndisponivel('caiu')]));

        app(TrocarCartao::class)->confirmar($troca->refresh(), 'fakesub_orfa', null, null);

        Log::shouldHaveReceived('error')->withArgs(fn (string $m) => $m === 'cobranca.troca_de_cartao_nova_nao_cancelada')->once();
    });
});

<?php

declare(strict_types=1);

use App\Domain\Cobranca\Carteira;
use App\Domain\Cobranca\CicloCobranca;
use App\Domain\Cobranca\ProvedorIndisponivel;
use App\Domain\Cobranca\StatusAssinatura;
use App\Domain\Cobranca\TipoEvento;
use App\Domain\Cobranca\WebhookBruto;
use App\Infrastructure\Cobranca\Fake\FakePaymentProvider;
use App\Models\Assinatura;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\TrocaDeCartao;
use App\Models\Usuario;
use App\Services\Cobranca\CompraNaoPermitida;
use App\Services\Cobranca\GerirRenovacao;
use App\Services\Cobranca\GestaoNegada;
use App\Services\Cobranca\IniciarAssinatura;
use App\Services\Cobranca\TrocarCartao;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * STORY-081 CA-3, CA-4, CA-5, CA-8 e ajustes do ADR-010 — cancelar e reativar a renovação, e trocar o cartão
 * por novo checkout recorrente (a assinatura antiga só cai depois da confirmação). Provedor fake.
 */
beforeEach(function () {
    _usarProvedor('fake');
    Mail::fake();
    $this->usuario = _compradorConfirmado();
});

/** Usuário com assinatura mensal paga e ativa; devolve a Assinatura. */
function _assinante(Usuario $usuario, CicloCobranca $ciclo = CicloCobranca::Mensal): Assinatura
{
    _pagarAssinatura(_assinaturaPendente($usuario, null, $ciclo));

    return Assinatura::query()->where('usuario_id', $usuario->id)->sole();
}

function _gerir(): GerirRenovacao
{
    return app(GerirRenovacao::class);
}

function _trocar(): TrocarCartao
{
    return app(TrocarCartao::class);
}

describe('CA-4 cancelar a renovação', function () {
    it('cancela a renovação no provedor, mantém o acesso até o fim do ciclo e não mexe no vencimento nem na cota', function () {
        $antes = _assinante($this->usuario);
        $vencimento = $antes->proximo_vencimento->toIso8601String();

        $a = _gerir()->cancelar($this->usuario);

        expect($a->status)->toBe(StatusAssinatura::Cancelada)
            ->and($a->data_cancelamento)->not->toBeNull()
            ->and($a->proximo_vencimento->toIso8601String())->toBe($vencimento)
            ->and($a->cota_restante_ciclo)->toBe($antes->cota_restante_ciclo)
            ->and(FakePaymentProvider::situacaoDaAssinatura((string) $a->id_externo))->toBe('suspensa')
            ->and(app(Carteira::class)->verificar($this->usuario->id)->bloqueio)->toBeNull()
            ->and(AuditLog::query()->where('action', 'cobranca.renovacao_cancelada')->where('subject_id', $a->id)->exists())->toBeTrue();
    });

    it('não há nova cobrança: pagamento que ainda chegar de assinatura cancelada não renova', function () {
        Log::spy();
        $invoice = _pagarAssinatura(_assinaturaPendente($this->usuario));
        _gerir()->cancelar($this->usuario);

        _entregar(_fake()->simularRenovacao((string) $invoice->checkout_id_externo));

        expect(Assinatura::query()->sole()->periodos_pagos)->toBe(1);
        Log::shouldHaveReceived('error')->withArgs(fn (string $m) => $m === 'cobranca.renovacao_de_assinatura_inativa')->once();
    });

    it('clique repetido não falha nem chama o provedor de novo', function () {
        _assinante($this->usuario);
        _gerir()->cancelar($this->usuario);
        FakePaymentProvider::falharProximaOperacaoDeAssinatura();

        $a = _gerir()->cancelar($this->usuario);

        expect($a->status)->toBe(StatusAssinatura::Cancelada);
    });

    it('se o provedor não responde, nada muda e a assinatura continua ativa', function () {
        $a = _assinante($this->usuario);
        FakePaymentProvider::falharProximaOperacaoDeAssinatura();

        expect(fn () => _gerir()->cancelar($this->usuario))->toThrow(ProvedorIndisponivel::class);

        $a->refresh();
        expect($a->status)->toBe(StatusAssinatura::Ativa)->and($a->data_cancelamento)->toBeNull()
            ->and(FakePaymentProvider::situacaoDaAssinatura((string) $a->id_externo))->toBe('ativa');
    });

    it('quem não tem assinatura paga (nem trial) não cancela nada', function () {
        expect(fn () => _gerir()->cancelar($this->usuario))->toThrow(GestaoNegada::class);

        app(Carteira::class)->concederCota($this->usuario->id, 1, now(), now()->addDays(7), null, null, true);
        expect(fn () => _gerir()->cancelar($this->usuario))->toThrow(GestaoNegada::class);
    });

    it('assinatura em atraso cancela a renovação e encerra o acesso na hora (ver CancelarRenovacaoEmAtrasoTest)', function () {
        $a = _assinante($this->usuario);
        $a->forceFill(['status' => StatusAssinatura::EmAtraso])->save();

        expect(_gerir()->cancelar($this->usuario)->status)->toBe(StatusAssinatura::Expirada);
    });

    it('ciclo já vencido não pode mais ser alterado', function () {
        $a = _assinante($this->usuario);
        _gerir()->cancelar($this->usuario);
        $a->refresh()->forceFill(['proximo_vencimento' => now()->subDay()])->save();

        try {
            _gerir()->reativar($this->usuario);
            $this->fail('devia negar');
        } catch (GestaoNegada $e) {
            expect($e->motivo)->toBe(GestaoNegada::JA_ENCERRADA);
        }
    });

    it('plano sem recorrência no provedor (Pix anual) não tem renovação a cancelar', function () {
        $a = _assinante($this->usuario);
        $a->forceFill(['id_externo' => null])->save();

        try {
            _gerir()->cancelar($this->usuario);
            $this->fail('devia negar');
        } catch (GestaoNegada $e) {
            expect($e->motivo)->toBe(GestaoNegada::NAO_RENOVA_SOZINHA);
        }
    });

    it('o admin em "Logar como" não cancela nem reativa (CA-8)', function () {
        $a = _assinante($this->usuario);
        _personificar(_adminDaEquipe(), $this->usuario);

        foreach (['cancelar', 'reativar'] as $acao) {
            try {
                _gerir()->{$acao}($this->usuario);
                $this->fail('devia negar');
            } catch (GestaoNegada $e) {
                expect($e->motivo)->toBe(GestaoNegada::ADMIN_PERSONIFICANDO);
            }
        }

        expect($a->refresh()->status)->toBe(StatusAssinatura::Ativa);
    });

    it('enquanto o acesso dura, uma renovação cancelada ainda é "assinatura vigente": não dá para assinar de novo', function () {
        _assinante($this->usuario);
        _gerir()->cancelar($this->usuario);

        expect(fn () => _assinaturaPendente($this->usuario))->toThrow(CompraNaoPermitida::class);

        Assinatura::query()->update(['proximo_vencimento' => now()->subMinute()->format('Y-m-d H:i:sP')]);
        expect(IniciarAssinatura::assinaturaVigente($this->usuario->id))->toBeNull();
    });
});

describe('CA-5 reativar a renovação', function () {
    it('religa a cobrança no provedor, volta a Ativa e apaga a marca do cancelamento', function () {
        $a = _assinante($this->usuario);
        _gerir()->cancelar($this->usuario);

        $r = _gerir()->reativar($this->usuario);

        expect($r->status)->toBe(StatusAssinatura::Ativa)->and($r->data_cancelamento)->toBeNull()
            ->and(FakePaymentProvider::situacaoDaAssinatura((string) $a->id_externo))->toBe('ativa')
            ->and(AuditLog::query()->where('action', 'cobranca.renovacao_reativada')->exists())->toBeTrue();
    });

    it('depois de reativar, a renovação seguinte funciona normalmente', function () {
        $invoice = _pagarAssinatura(_assinaturaPendente($this->usuario));
        _gerir()->cancelar($this->usuario);
        _gerir()->reativar($this->usuario);

        _entregar(_fake()->simularRenovacao((string) $invoice->checkout_id_externo));

        expect(Assinatura::query()->sole()->periodos_pagos)->toBe(2);
    });

    it('se o provedor não responde, continua cancelada', function () {
        _assinante($this->usuario);
        _gerir()->cancelar($this->usuario);
        FakePaymentProvider::falharProximaOperacaoDeAssinatura();

        expect(fn () => _gerir()->reativar($this->usuario))->toThrow(ProvedorIndisponivel::class);
        expect(Assinatura::query()->sole()->status)->toBe(StatusAssinatura::Cancelada);
    });

    it('quem já está ativa não precisa reativar: clique repetido não chama o provedor', function () {
        _assinante($this->usuario);
        FakePaymentProvider::falharProximaOperacaoDeAssinatura();

        expect(_gerir()->reativar($this->usuario)->status)->toBe(StatusAssinatura::Ativa);
    });
});

describe('CA-3 trocar o cartão', function () {
    it('abre um checkout recorrente novo sem tocar na assinatura nem na antiga', function () {
        $a = _assinante($this->usuario);

        $troca = _trocar()->iniciar($this->usuario);

        expect($troca->status)->toBe('pendente')
            ->and($troca->cobra_agora)->toBeFalse()
            ->and($troca->antiga_id_externo)->toBe($a->id_externo)
            ->and($troca->checkout_url)->toContain('/pagamentos/fake/troca/')
            ->and($a->refresh()->id_externo)->toBe($troca->antiga_id_externo)
            ->and(FakePaymentProvider::situacaoDaAssinatura((string) $a->id_externo))->toBe('ativa')
            ->and(Invoice::query()->where('tipo', 'assinatura')->count())->toBe(1);
    });

    it('clique repetido reaproveita o checkout ainda válido', function () {
        _assinante($this->usuario);

        expect(_trocar()->iniciar($this->usuario)->id)->toBe(_trocar()->iniciar($this->usuario)->id);
        expect(TrocaDeCartao::query()->count())->toBe(1);
    });

    it('cartão aceito: o ponteiro vai para a assinatura nova e só então a antiga é cancelada, sem cobrança em dobro', function () {
        $a = _assinante($this->usuario);
        $antiga = (string) $a->id_externo;
        $troca = _trocar()->iniciar($this->usuario);

        expect(_entregar(_fake()->webhook(TipoEvento::CheckoutConcluido, (string) $troca->checkout_id_externo)))->not->toBe('');

        $a->refresh();
        $troca->refresh();
        expect($a->id_externo)->not->toBe($antiga)
            ->and($troca->status)->toBe('concluida')
            ->and($troca->nova_id_externo)->toBe($a->id_externo)
            ->and($troca->antiga_cancelada_em)->not->toBeNull()
            ->and(FakePaymentProvider::situacaoDaAssinatura($antiga))->toBe('cancelada')
            ->and(FakePaymentProvider::situacaoDaAssinatura((string) $a->id_externo))->toBe('ativa')
            ->and($a->status)->toBe(StatusAssinatura::Ativa)
            ->and($a->periodos_pagos)->toBe(1);
    });

    it('o aviso repetido não faz nada de novo (idempotente)', function () {
        $a = _assinante($this->usuario);
        $troca = _trocar()->iniciar($this->usuario);
        $webhook = _fake()->webhook(TipoEvento::CheckoutConcluido, (string) $troca->checkout_id_externo, 'evt-troca-1');

        _entregar($webhook);
        $nova = $a->refresh()->id_externo;
        _entregar($webhook);
        _entregar(_fake()->webhook(TipoEvento::CheckoutConcluido, (string) $troca->checkout_id_externo, 'evt-troca-2'));

        expect($a->refresh()->id_externo)->toBe($nova)->and(TrocaDeCartao::query()->count())->toBe(1);
    });

    it('depois da troca, a renovação do ciclo vem da assinatura nova e funciona', function () {
        _assinante($this->usuario);
        $troca = _trocar()->iniciar($this->usuario);
        _entregar(_fake()->webhook(TipoEvento::CheckoutConcluido, (string) $troca->checkout_id_externo));

        _entregar(_fake()->simularPagamento((string) $troca->checkout_id_externo));

        $a = Assinatura::query()->sole();
        expect($a->periodos_pagos)->toBe(2)
            ->and(Invoice::query()->where('renovacao', true)->count())->toBe(1)
            ->and($a->cartao_ultimos4)->toBe('4242');
    });

    it('se o provedor falha ao cancelar a antiga, alerta e a rotina horária conclui', function () {
        Log::spy();
        $a = _assinante($this->usuario);
        $antiga = (string) $a->id_externo;
        $troca = _trocar()->iniciar($this->usuario);
        FakePaymentProvider::falharProximaOperacaoDeAssinatura();

        _entregar(_fake()->webhook(TipoEvento::CheckoutConcluido, (string) $troca->checkout_id_externo));

        expect($troca->refresh()->antiga_cancelada_em)->toBeNull()
            ->and(FakePaymentProvider::situacaoDaAssinatura($antiga))->toBe('ativa');
        Log::shouldHaveReceived('error')->withArgs(fn (string $m) => $m === 'cobranca.troca_de_cartao_antiga_nao_cancelada')->once();

        Artisan::call('assinaturas:cancelar-antigas');

        expect($troca->refresh()->antiga_cancelada_em)->not->toBeNull()
            ->and(FakePaymentProvider::situacaoDaAssinatura($antiga))->toBe('cancelada');
        expect(_trocar()->cancelarAntigas())->toBe(0);
    });

    it('recusa, desistência ou prazo vencido deixam tudo como estava e o cartão antigo continua valendo', function (string $gatilho, string $esperado) {
        $a = _assinante($this->usuario);
        $antiga = (string) $a->id_externo;
        $troca = _trocar()->iniciar($this->usuario);

        _entregar(match ($gatilho) {
            'recusa' => _fake()->simularRecusa((string) $troca->checkout_id_externo),
            'desistencia' => _fake()->simularCancelamento((string) $troca->checkout_id_externo),
            'prazo' => _fake()->simularExpiracao((string) $troca->checkout_id_externo),
        });

        expect($troca->refresh()->status)->toBe($esperado)
            ->and($a->refresh()->id_externo)->toBe($antiga)
            ->and(FakePaymentProvider::situacaoDaAssinatura($antiga))->toBe('ativa');
    })->with([['recusa', 'falhou'], ['desistencia', 'cancelada'], ['prazo', 'expirada']]);

    it('uma troca já concluída não volta atrás por aviso tardio de prazo vencido', function () {
        _assinante($this->usuario);
        $troca = _trocar()->iniciar($this->usuario);
        _entregar(_fake()->webhook(TipoEvento::CheckoutConcluido, (string) $troca->checkout_id_externo));

        _entregar(_fake()->simularExpiracao((string) $troca->checkout_id_externo));

        expect($troca->refresh()->status)->toBe('concluida');
    });

    it('aviso de checkout concluído sem o id da assinatura nova deixa a troca pendente e alerta', function () {
        Log::spy();
        $a = _assinante($this->usuario);
        $antiga = (string) $a->id_externo;
        $troca = _trocar()->iniciar($this->usuario);
        $webhook = new WebhookBruto(
            [FakePaymentProvider::CABECALHO_TOKEN => hash('sha256', 'fake-webhook|'.(string) config('app.key')), 'content-type' => 'application/json'],
            (string) json_encode(['id' => 'evt-sem-sub', 'tipo' => 'checkout.concluido', 'checkout_id' => $troca->checkout_id_externo, 'referencia_externa' => $troca->id]),
        );

        _entregar($webhook);

        expect($troca->refresh()->status)->toBe('pendente')->and($a->refresh()->id_externo)->toBe($antiga);
        Log::shouldHaveReceived('error')->withArgs(fn (string $m) => $m === 'cobranca.troca_de_cartao_sem_assinatura')->once();
    });

    it('troca obsoleta (a assinatura já aponta para outra) não sobrescreve e alerta', function () {
        Log::spy();
        $a = _assinante($this->usuario);
        $troca = _trocar()->iniciar($this->usuario);
        $a->forceFill(['id_externo' => 'outra-assinatura'])->save();

        _entregar(_fake()->webhook(TipoEvento::CheckoutConcluido, (string) $troca->checkout_id_externo));

        expect($a->refresh()->id_externo)->toBe('outra-assinatura')->and($troca->refresh()->status)->toBe('pendente');
        Log::shouldHaveReceived('error')->withArgs(fn (string $m) => $m === 'cobranca.troca_de_cartao_obsoleta')->once();
    });

    it('checkout concluído que não é de troca de cartão continua ignorado', function () {
        $invoice = _assinaturaPendente($this->usuario);

        expect(_entregar(_fake()->webhook(TipoEvento::CheckoutConcluido, (string) $invoice->checkout_id_externo)))->not->toBe('');
        expect(Assinatura::query()->count())->toBe(0);
    });

    it('o provedor fora do ar ao abrir o checkout deixa a troca como falha e a assinatura intacta', function () {
        $a = _assinante($this->usuario);
        FakePaymentProvider::falharProximoCheckout();

        expect(fn () => _trocar()->iniciar($this->usuario))->toThrow(ProvedorIndisponivel::class);

        expect(TrocaDeCartao::query()->sole()->status)->toBe('falhou')->and($a->refresh()->status)->toBe(StatusAssinatura::Ativa);
    });

    it('plano sem recorrência, sem assinatura e "Logar como" não trocam cartão', function () {
        expect(fn () => _trocar()->iniciar($this->usuario))->toThrow(GestaoNegada::class);

        $a = _assinante($this->usuario);
        $a->forceFill(['id_externo' => null])->save();
        expect(fn () => _trocar()->iniciar($this->usuario))->toThrow(GestaoNegada::class);

        $a->forceFill(['id_externo' => 'x'])->save();
        _personificar(_adminDaEquipe(), $this->usuario);
        expect(fn () => _trocar()->iniciar($this->usuario))->toThrow(GestaoNegada::class);
        expect(TrocaDeCartao::query()->count())->toBe(0);
    });
});

describe('CA-3 assinatura em atraso é cobrada de novo com o cartão novo', function () {
    function _emAtraso(Usuario $usuario): Assinatura
    {
        $a = _assinante($usuario);
        $a->forceFill(['status' => StatusAssinatura::EmAtraso, 'cota_restante_ciclo' => 0])->save();

        return $a;
    }

    it('a troca em atraso cobra na hora, e o pagamento recupera a assinatura com a cota cheia', function (bool $pagoAntesDoAviso) {
        $a = _emAtraso($this->usuario);
        $vencimentoAntes = $a->proximo_vencimento->toIso8601String();
        $troca = _trocar()->iniciar($this->usuario);
        $checkout = (string) $troca->checkout_id_externo;

        expect($troca->cobra_agora)->toBeTrue();

        if ($pagoAntesDoAviso) {
            _entregar(_fake()->simularPagamento($checkout));
            _entregar(_fake()->webhook(TipoEvento::CheckoutConcluido, $checkout));
        } else {
            _entregar(_fake()->webhook(TipoEvento::CheckoutConcluido, $checkout));
            _entregar(_fake()->simularPagamento($checkout));
        }

        $a->refresh();
        expect($a->status)->toBe(StatusAssinatura::Ativa)
            ->and($a->cota_restante_ciclo)->toBe($a->cota_snapshot)
            ->and($a->periodos_pagos)->toBe(2)
            ->and($a->proximo_vencimento->toIso8601String())->not->toBe($vencimentoAntes)
            ->and($a->id_externo)->toBe($troca->refresh()->nova_id_externo)
            ->and($troca->cobra_agora)->toBeFalse()
            ->and(Invoice::query()->where('renovacao', true)->where('status', 'paga')->count())->toBe(1);
    })->with([[false], [true]]);

    it('o pagamento repetido não renova duas vezes', function () {
        _emAtraso($this->usuario);
        $troca = _trocar()->iniciar($this->usuario);
        $checkout = (string) $troca->checkout_id_externo;
        _entregar(_fake()->webhook(TipoEvento::CheckoutConcluido, $checkout));

        _entregar(_fake()->simularPagamento($checkout, 'evt-pago-1'));
        _entregar(_fake()->simularPagamento($checkout, 'evt-pago-2'));

        expect(Assinatura::query()->sole()->periodos_pagos)->toBe(2)->and(Invoice::query()->where('renovacao', true)->count())->toBe(1);
    });

    it('recusa do cartão novo mantém a assinatura em atraso, com a antiga intacta', function () {
        $a = _emAtraso($this->usuario);
        $antiga = (string) $a->id_externo;
        $troca = _trocar()->iniciar($this->usuario);

        _entregar(_fake()->simularRecusa((string) $troca->checkout_id_externo));

        expect($a->refresh()->status)->toBe(StatusAssinatura::EmAtraso)->and($a->id_externo)->toBe($antiga)
            ->and($troca->refresh()->status)->toBe('falhou');
    });
});

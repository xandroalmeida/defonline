<?php

declare(strict_types=1);

use App\Domain\Cobranca\Carteira;
use App\Domain\Cobranca\CicloCobranca;
use App\Domain\Cobranca\EstadoCobranca;
use App\Domain\Cobranca\MeioPagamento;
use App\Domain\Cobranca\PaymentProvider;
use App\Domain\Cobranca\ReferenciaProvedor;
use App\Domain\Cobranca\TipoEvento;
use App\Infrastructure\Cobranca\Fake\FakePaymentProvider;
use App\Models\Assinatura;
use App\Models\AuditLog;
use App\Models\Cupom;
use App\Models\CupomItem;
use App\Models\Invoice;
use App\Models\ProviderEvent;
use App\Services\Cobranca\GerirRenovacao;
use App\Services\Cobranca\GestaoNegada;
use App\Services\Cobranca\IniciarAssinatura;
use App\Services\Cobranca\ReembolsarCobranca;
use App\Services\Cobranca\TrocarCartao;
use App\Services\Cobranca\TrocarDePlano;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * STORY-093 (integração das correções 088 a 092, depois da junção). Cada item é um ponto em que duas correções
 * tratavam a mesma coisa, ou em que uma deixava uma lacuna para a outra.
 */
beforeEach(function () {
    _usarProvedor('fake');
    Mail::fake();
    $this->u = _compradorConfirmado();
});

describe('estorno negado (088 × 092)', function () {
    it('o webhook de estorno negado devolve o crédito travado e a cobrança volta a paga, com um alerta e uma auditoria', function () {
        $i = _compraPendenteAsaas($this->u);
        _entregar(_fake()->simularPagamento((string) $i->checkout_id_externo));
        app(ReembolsarCobranca::class)->solicitar($i->fresh(), _adminDaEquipe(), 'Arrependimento do cliente');
        expect($i->fresh()->reembolso_estado)->toBe('aguardando_autorizacao')
            ->and(app(Carteira::class)->verificar($this->u->id)->permitido())->toBeFalse();
        Log::spy();

        _entregar(_fake()->webhookDaCobranca(TipoEvento::EstornoNegado, (string) $i->fresh()->id_externo, 'evt-negado'));

        expect($i->fresh()->status)->toBe(EstadoCobranca::Paga)->and($i->fresh()->reembolso_estado)->toBeNull()
            ->and(app(Carteira::class)->verificar($this->u->id)->permitido())->toBeTrue()
            ->and(ProviderEvent::query()->where('evento_id', 'evt-negado')->value('status'))->toBe('processado')
            ->and(AuditLog::query()->where('action', 'cobranca.reembolso_negado_pelo_provedor')->count())->toBe(1);
        Log::shouldHaveReceived('error')->with('cobranca.reembolso_negado', Mockery::any())->once();
        Log::shouldNotHaveReceived('error', ['cobranca.estorno_negado', Mockery::any()]);
    });

    it('estorno negado repetido, ou de cobrança sem estorno pendente, não muda nada e não alerta de novo', function () {
        $i = _compraPendenteAsaas($this->u);
        _entregar(_fake()->simularPagamento((string) $i->checkout_id_externo));
        Log::spy();

        _entregar(_fake()->webhookDaCobranca(TipoEvento::EstornoNegado, (string) $i->fresh()->id_externo, 'evt-negado-2'));

        expect(ProviderEvent::query()->where('evento_id', 'evt-negado-2')->value('status'))->toBe('ignorado')
            ->and(AuditLog::query()->where('action', 'cobranca.reembolso_negado_pelo_provedor')->count())->toBe(0);
        Log::shouldNotHaveReceived('error', ['cobranca.reembolso_negado', Mockery::any()]);
    });
});

describe('pagamento sem efeito (088 × 092): um pagamento, um destino, um alerta', function () {
    it('checkout cancelado pago com recorrência: a própria Invoice vai para a devolução, a recorrência é cancelada e a cobrança do mês seguinte também vira devolução', function () {
        $i = _assinaturaPendente($this->u);
        _entregar(_fake()->simularCancelamento((string) $i->checkout_id_externo));
        expect($i->fresh()->status)->toBe(EstadoCobranca::Cancelada);
        $sub = 'fakesub_'.Str::after((string) $i->checkout_id_externo, 'fakechk_');
        Log::spy();

        _entregar(_fake()->simularPagamento((string) $i->checkout_id_externo));

        $i = $i->fresh();
        expect(Invoice::query()->count())->toBe(1)
            ->and($i->status)->toBe(EstadoCobranca::Paga)->and($i->devolucao_motivo)->toBe('checkout_cancelado')
            ->and($i->assinatura_id_externo)->toBe($sub)
            ->and(Assinatura::query()->count())->toBe(0)
            ->and(FakePaymentProvider::assinaturaCancelada($sub))->toBeTrue()
            ->and(AuditLog::query()->where('action', 'cobranca.pagamento_sem_efeito')->count())->toBe(1);
        Log::shouldHaveReceived('error')->with('cobranca.pagamento_de_checkout_cancelado', Mockery::any())->once();
        Log::shouldNotHaveReceived('error', ['cobranca.pagamento_de_checkout_encerrado', Mockery::any()]);

        // O provedor já tinha gerado a cobrança do mês seguinte: também é dinheiro a devolver, nunca renovação.
        $this->travelTo(now()->addMonth());
        _entregar(_fake()->simularRenovacao((string) $i->checkout_id_externo));

        expect(Invoice::query()->count())->toBe(2)->and(Assinatura::query()->count())->toBe(0)
            ->and(Invoice::query()->whereNotNull('devolucao_motivo')->where('status', EstadoCobranca::Paga)->count())->toBe(2);
    });

    it('o mesmo aviso reprocessado depois de uma falha no cancelamento da recorrência cancela de novo, sem segunda Invoice nem segundo alerta', function () {
        $i = _assinaturaPendente($this->u);
        _entregar(_fake()->simularCancelamento((string) $i->checkout_id_externo));
        $sub = 'fakesub_'.Str::after((string) $i->checkout_id_externo, 'fakechk_');
        $webhook = _fake()->simularPagamento((string) $i->checkout_id_externo, 'evt-repetido');
        Log::spy();

        FakePaymentProvider::falharProximaOperacaoDeAssinatura();
        try {
            _entregar($webhook);
        } catch (Throwable) {
            // O evento fica em `erro` e o reprocessador repete.
        }
        expect(FakePaymentProvider::assinaturaCancelada($sub))->toBeFalse();

        _entregar($webhook);

        expect(Invoice::query()->count())->toBe(1)->and(FakePaymentProvider::assinaturaCancelada($sub))->toBeTrue();
        Log::shouldHaveReceived('error')->with('cobranca.pagamento_de_checkout_cancelado', Mockery::any())->once();
    });
});

describe('troca de plano em ciclo gratuito (090 × 083)', function () {
    function _contratarComCupomGratis(object $t): Assinatura
    {
        $c = Cupom::query()->create(['codigo' => 'G100', 'tipo' => 'percentual', 'valor' => 1000, 'vigencia_inicio' => now()->subDay(), 'vigencia_fim' => now()->addYear(), 'ativo' => true, 'ciclos_duracao' => 3]);

        foreach (['Pro', 'Básico'] as $n) {
            CupomItem::query()->create(['cupom_id' => $c->id, 'plano_id' => _plano($n)->id, 'ciclo' => 'mensal']);
        }

        app(IniciarAssinatura::class)->executar($t->u, _plano('Pro'), CicloCobranca::Mensal, '1.1.1.1', 'x', MeioPagamento::Cartao, 'G100');
        $a = Assinatura::query()->where('usuario_id', $t->u->id)->sole();
        $troca = app(TrocarCartao::class)->iniciar($t->u);
        _entregar(_fake()->webhook(TipoEvento::CheckoutConcluido, $troca->checkout_id_externo));

        return $a->fresh();
    }

    it('quem cadastrou cartão durante o desconto só é cobrado ao fim dele, mesmo agendando um downgrade', function () {
        $a = _contratarComCupomGratis($this);
        expect($a->id_externo)->not->toBeNull();
        $fimDoDesconto = CicloCobranca::Mensal->vencimento($a->data_inicio, 3)->format('Y-m-d H:i:s');

        $t = app(TrocarDePlano::class)->confirmar($this->u, _plano('Básico'), CicloCobranca::Mensal);

        expect($t->tipo)->toBe('agendada')
            ->and(FakePaymentProvider::valorDaAssinatura($a->id_externo)['proxima'])->toBe($fimDoDesconto)
            ->and(FakePaymentProvider::valorDaAssinatura($a->id_externo)['proxima'])->not->toBe($a->proximo_vencimento->format('Y-m-d H:i:s'));
    });

    it('desfazer o downgrade também mantém a cobrança para o fim do desconto, no valor cheio', function () {
        $a = _contratarComCupomGratis($this);
        $fimDoDesconto = CicloCobranca::Mensal->vencimento($a->data_inicio, 3)->format('Y-m-d H:i:s');
        app(TrocarDePlano::class)->confirmar($this->u, _plano('Básico'), CicloCobranca::Mensal);

        $desfeito = app(TrocarDePlano::class)->desfazer($this->u);

        $no = FakePaymentProvider::valorDaAssinatura($a->id_externo);
        expect($desfeito)->toBeTrue()->and($no['proxima'])->toBe($fimDoDesconto)->and($no['valor'])->toBe(_plano('Pro')->preco_mensal_centavos);
    });

    it('sem ciclo gratuito, o downgrade continua empurrando o vencimento atual', function () {
        [$a] = _assinaturaAtivaNoFake($this->u, CicloCobranca::Mensal);
        $this->travelTo($a->proximo_vencimento->copy()->subHours(1));
        app(TrocarDePlano::class)->confirmar($this->u, _plano('Pro'), CicloCobranca::Mensal);
        $this->travelTo($a->data_inicio->copy()->addDays(10));

        $t = app(TrocarDePlano::class)->confirmar($this->u, _plano('Básico'), CicloCobranca::Mensal);

        expect($t->tipo)->toBe('agendada')
            ->and(FakePaymentProvider::valorDaAssinatura($a->fresh()->id_externo)['proxima'])->toBe($a->fresh()->proximo_vencimento->format('Y-m-d H:i:s'));
    });
});

describe('STORY-093 CA-6 — rede nunca dentro de transação com linha travada', function () {
    it('o fake recusa chamada ao provedor feita dentro de uma transação (e a aceita fora dela)', function () {
        $ref = new ReferenciaProvedor('fake', 'fakesub_x');

        expect(fn () => DB::transaction(fn () => _fake()->suspendSubscription($ref)))->toThrow(LogicException::class, 'dentro de uma transação');
        _fake()->suspendSubscription($ref);
        expect(FakePaymentProvider::situacaoDaAssinatura('fakesub_x'))->not->toBeNull();
    });

    it('troca de plano sem cobrança: a carteira muda, o provedor é avisado depois do commit e, se falhar, fica marcado e a rotina horária repete', function () {
        [$a] = _assinaturaAtivaNoFake($this->u);
        $this->travelTo($a->proximo_vencimento->copy()->subHours(1));
        $a = $a->fresh();
        FakePaymentProvider::falharProximaOperacaoDeAssinatura();

        $t = app(TrocarDePlano::class)->confirmar($this->u, _plano('Pro'), CicloCobranca::Mensal);

        $a = $a->fresh();
        expect($t->tipo)->toBe('aplicada')->and($a->plano_id)->toBe(_plano('Pro')->id)
            ->and($a->ajuste_provedor_pendente)->toBeTrue()
            ->and(FakePaymentProvider::valorCobradoDaAssinatura($a->id_externo))->not->toBe(_plano('Pro')->preco_mensal_centavos);

        Artisan::call('assinaturas:ajustar-no-provedor');

        expect($a->fresh()->ajuste_provedor_pendente)->toBeFalse()
            ->and(FakePaymentProvider::valorCobradoDaAssinatura($a->id_externo))->toBe(_plano('Pro')->preco_mensal_centavos);
    });

    it('troca de plano paga: o ajuste vai ao provedor depois do commit do pagamento', function () {
        [$a] = _assinaturaAtivaNoFake($this->u);
        $this->travelTo($a->proximo_vencimento->copy()->subDays(10));
        $t = app(TrocarDePlano::class)->confirmar($this->u, _plano('Pro'), CicloCobranca::Mensal);

        _pagarAssinatura($t->invoice);

        $a = $a->fresh();
        expect($a->plano_id)->toBe(_plano('Pro')->id)->and($a->ajuste_provedor_pendente)->toBeFalse()
            ->and(FakePaymentProvider::valorCobradoDaAssinatura($a->id_externo))->toBe(_plano('Pro')->preco_mensal_centavos);
    });

    it('cancelar a renovação: se a assinatura muda enquanto o provedor responde, o pedido é desfeito no provedor e a ação é negada', function () {
        [$a] = _assinaturaAtivaNoFake($this->u);
        $id = (string) $a->id_externo;
        app()->instance(PaymentProvider::class, _provedorQueFalha([
            // A régua (ou um pagamento) mexe na assinatura no meio do pedido.
            'suspendSubscription' => function () use ($a) {
                Assinatura::query()->whereKey($a->id)->update(['status' => 'em_atraso']);
            },
        ]));

        expect(fn () => app(GerirRenovacao::class)->cancelar($this->u))->toThrow(GestaoNegada::class);

        expect(FakePaymentProvider::situacaoDaAssinatura($id))->toBe('ativa')
            ->and($a->fresh()->status->value)->toBe('em_atraso');
    });
});

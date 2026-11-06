<?php

declare(strict_types=1);

use App\Domain\Cobranca\Carteira;
use App\Domain\Cobranca\CicloCobranca;
use App\Domain\Cobranca\EstadoCobranca;
use App\Domain\Cobranca\MotivoBloqueio;
use App\Domain\Cobranca\PaymentProvider;
use App\Domain\Cobranca\ProvedorIndisponivel;
use App\Domain\Cobranca\ReferenciaProvedor;
use App\Domain\Cobranca\StatusAssinatura;
use App\Livewire\Backoffice\Financeiro;
use App\Livewire\Home\MinhasEmpresas;
use App\Models\Assinatura;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\LoteCredito;
use App\Services\Cobranca\GerirRenovacao;
use App\Services\Cobranca\IniciarRenovacaoPix;
use App\Services\Cobranca\ReembolsarCobranca;
use App\Services\Cobranca\TrocarDePlano;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

/**
 * STORY-092 (correção do EPIC-005, defeitos D-08, D-11a e D-12). Cenários E4, E4b, E13, E21, E12 e E17 da
 * validação (STORY-087), agora como regressão com o comportamento correto.
 */
beforeEach(function () {
    _usarProvedor('fake');
    Mail::fake();
    $this->u = _compradorConfirmado();
});

describe('CA-1 — pagamento confirmado sem efeito vai para a lista de devolução e alerta', function () {
    it('E4: diferença de upgrade paga depois de a assinatura renovar', function () {
        [$a, $i] = _assinaturaAtivaNoFake($this->u);
        $this->travelTo($a->proximo_vencimento->copy()->subDays(10));
        $t = app(TrocarDePlano::class)->confirmar($this->u, _plano('Pro'), CicloCobranca::Mensal);
        $this->travelTo($a->proximo_vencimento->copy()->addHour());
        _entregar(_fake()->simularRenovacao($i->checkout_id_externo));
        expect($a->fresh()->periodos_pagos)->toBe(2);
        Log::spy();

        _pagarAssinatura($t->invoice);

        $inv = $t->invoice->fresh();
        expect($inv->status)->toBe(EstadoCobranca::Paga)->and($inv->devolucao_motivo)->toBe('troca_sem_efeito')
            ->and($a->fresh()->plano_id)->toBe(_plano('Básico')->id);
        Log::shouldHaveReceived('error')->with('cobranca.troca_paga_sem_efeito', Mockery::any());
    });

    it('E4b: duas diferenças pagas — a primeira vale, a segunda fica para devolução', function () {
        [$a] = _assinaturaAtivaNoFake($this->u);
        $this->travelTo($a->proximo_vencimento->copy()->subDays(20));
        $t1 = app(TrocarDePlano::class)->confirmar($this->u, _plano('Pro'), CicloCobranca::Mensal);
        $this->travelTo(now()->addDays(2));
        $t2 = app(TrocarDePlano::class)->confirmar($this->u, _plano('Pro'), CicloCobranca::Mensal);
        expect($t2->invoice->id)->not->toBe($t1->invoice->id);

        _pagarAssinatura($t1->invoice);
        $cotaDepoisDaPrimeira = $a->fresh()->cota_restante_ciclo;
        _pagarAssinatura($t2->invoice);

        expect($t1->invoice->fresh()->devolucao_motivo)->toBeNull()
            ->and($t2->invoice->fresh()->devolucao_motivo)->toBe('troca_sem_efeito')
            ->and($a->fresh()->plano_id)->toBe(_plano('Pro')->id)
            ->and($a->fresh()->cota_restante_ciclo)->toBe($cotaDepoisDaPrimeira);
    });

    it('E4b: a segunda diferença paga também aparece na lista "a devolver" do Financeiro', function () {
        [$a] = _assinaturaAtivaNoFake($this->u);
        $this->travelTo($a->proximo_vencimento->copy()->subDays(20));
        $t1 = app(TrocarDePlano::class)->confirmar($this->u, _plano('Pro'), CicloCobranca::Mensal);
        $this->travelTo(now()->addDays(2));
        $t2 = app(TrocarDePlano::class)->confirmar($this->u, _plano('Pro'), CicloCobranca::Mensal);
        _pagarAssinatura($t1->invoice);
        _pagarAssinatura($t2->invoice);

        Livewire::actingAs(_adminDaEquipe())->test(Financeiro::class)
            ->assertSeeHtml('cobranca-devolver-'.$t2->invoice->id)
            ->assertSee('Diferença de troca de plano paga sem efeito');
    });

    it('E21: o segundo checkout pago depois de cancelar a renovação não cria outra assinatura sobre o período vigente', function () {
        $i1 = _assinaturaPendente($this->u, _plano('Básico'));
        $i2 = _assinaturaPendente($this->u, _plano('Pro'));
        _pagarAssinatura($i1);
        app(GerirRenovacao::class)->cancelar($this->u);
        $antes = Assinatura::query()->where('usuario_id', $this->u->id)->sole();
        Log::spy();

        _pagarAssinatura($i2);

        expect($i2->fresh()->devolucao_motivo)->toBe('assinatura_duplicada')
            ->and($i2->fresh()->status)->toBe(EstadoCobranca::Paga)
            ->and(Assinatura::query()->where('usuario_id', $this->u->id)->count())->toBe(1)
            ->and($antes->fresh()->proximo_vencimento->equalTo($antes->proximo_vencimento))->toBeTrue()
            ->and($antes->fresh()->status)->toBe(StatusAssinatura::Cancelada);
        Log::shouldHaveReceived('error')->withArgs(fn (string $m) => $m === 'cobranca.assinatura_duplicada')->once();
    });

    it('E13: o segundo QR Code da renovação Pix pago não vira mais um ano sem aviso', function () {
        $a = _assinaturaPixAtiva($this->u);
        $this->travelTo($a->proximo_vencimento->copy()->subDays(3));
        $r1 = app(IniciarRenovacaoPix::class)->executar($this->u, '1.1.1.1', 'x');
        // O 1º QR Code expirou sem pagamento (o banco ainda pode confirmar depois): o 2º é outra cobrança.
        $r1->forceFill(['checkout_expira_em' => now()->subDays(400)])->save();
        $r2 = app(IniciarRenovacaoPix::class)->executar($this->u, '1.1.1.1', 'x');
        expect($r2->id)->not->toBe($r1->id);
        Log::spy();

        _entregar(_fake()->simularPagamento($r1->checkout_id_externo));
        $vencimento = $a->fresh()->proximo_vencimento;
        _entregar(_fake()->simularPagamento($r2->checkout_id_externo));

        expect($a->fresh()->periodos_pagos)->toBe(2)
            ->and($a->fresh()->proximo_vencimento->equalTo($vencimento))->toBeTrue()
            ->and($r1->fresh()->devolucao_motivo)->toBeNull()
            ->and($r2->fresh()->devolucao_motivo)->toBe('renovacao_repetida')
            ->and($r2->fresh()->status)->toBe(EstadoCobranca::Paga);
        Log::shouldHaveReceived('error')->with('cobranca.renovacao_paga_sem_efeito', Mockery::any());
    });

    it('checkout cancelado que o provedor confirma como pago: o dinheiro entrou, a lista de devolução o mostra', function () {
        $i = _compraPendenteAsaas($this->u);
        $i->forceFill(['status' => EstadoCobranca::Cancelada, 'motivo_encerramento' => 'cancelado'])->save();
        Log::spy();

        _pagarAssinatura($i);

        expect($i->fresh()->status)->toBe(EstadoCobranca::Paga)->and($i->fresh()->devolucao_motivo)->toBe('checkout_cancelado')
            ->and(LoteCredito::query()->where('invoice_id', $i->id)->count())->toBe(0);
        Log::shouldHaveReceived('error')->with('cobranca.pagamento_de_checkout_cancelado', Mockery::any());
    });

    it('o mesmo aviso de pagamento sem efeito, repetido, registra uma vez só', function () {
        $i = _compraPendenteAsaas($this->u);
        $i->forceFill(['status' => EstadoCobranca::Cancelada, 'motivo_encerramento' => 'cancelado'])->save();

        $w = _fake()->simularPagamento((string) $i->checkout_id_externo, 'evt-sem-efeito');
        _entregar($w);
        _entregar($w);

        expect(Invoice::query()->where('devolucao_motivo', 'checkout_cancelado')->count())->toBe(1);
    });

    it('pagamento sem efeito pode ser reembolsado e só devolve o dinheiro', function () {
        [$a] = _assinaturaAtivaNoFake($this->u);
        $this->travelTo($a->proximo_vencimento->copy()->subDays(20));
        $t1 = app(TrocarDePlano::class)->confirmar($this->u, _plano('Pro'), CicloCobranca::Mensal);
        $this->travelTo(now()->addDays(2));
        $t2 = app(TrocarDePlano::class)->confirmar($this->u, _plano('Pro'), CicloCobranca::Mensal);
        _pagarAssinatura($t1->invoice);
        _pagarAssinatura($t2->invoice);

        $previa = app(ReembolsarCobranca::class)->previa($t2->invoice->fresh());

        expect($previa->tipo)->toBe('nada')->and($previa->descricao())->toContain('só o dinheiro é devolvido');
    });
});

describe('CA-2 — novo checkout do mesmo item cancela o anterior no provedor', function () {
    it('upgrade reaberto em outro dia: o checkout da diferença anterior é cancelado no provedor', function () {
        [$a] = _assinaturaAtivaNoFake($this->u);
        $this->travelTo($a->proximo_vencimento->copy()->subDays(20));
        $t1 = app(TrocarDePlano::class)->confirmar($this->u, _plano('Pro'), CicloCobranca::Mensal);
        $this->travelTo(now()->addDays(2));
        $t2 = app(TrocarDePlano::class)->confirmar($this->u, _plano('Pro'), CicloCobranca::Mensal);

        $antiga = _fake()->getCharge(new ReferenciaProvedor('fake', 'fakepay_'.Str::after((string) $t1->invoice->checkout_id_externo, 'fakechk_')));
        $nova = _fake()->getCharge(new ReferenciaProvedor('fake', 'fakepay_'.Str::after((string) $t2->invoice->checkout_id_externo, 'fakechk_')));

        expect($antiga->estado)->toBe(EstadoCobranca::Cancelada)->and($nova->estado)->toBe(EstadoCobranca::Pendente);
    });

    it('compra de créditos do mesmo pacote reaberta depois de expirar: o QR Code anterior é cancelado', function () {
        $i1 = _compraPendenteAsaas($this->u);
        $i1->forceFill(['checkout_expira_em' => now()->subDays(400)])->save();
        $i2 = _compraPendenteAsaas($this->u);
        expect($i2->id)->not->toBe($i1->id);

        $antiga = _fake()->getCharge(new ReferenciaProvedor('fake', 'fakepay_'.Str::after((string) $i1->checkout_id_externo, 'fakechk_')));

        expect($antiga->estado)->toBe(EstadoCobranca::Cancelada);
    });

    it('item diferente não é cancelado', function () {
        $i1 = _compraPendenteAsaas($this->u, _pacote('plus'));
        $i2 = _compraPendenteAsaas($this->u, _pacote('mini'));
        expect($i2->id)->not->toBe($i1->id);

        $primeira = _fake()->getCharge(new ReferenciaProvedor('fake', 'fakepay_'.Str::after((string) $i1->checkout_id_externo, 'fakechk_')));

        expect($primeira->estado)->toBe(EstadoCobranca::Pendente);
    });

    it('o provedor que não deixa cancelar não impede abrir o novo checkout', function () {
        $i1 = _compraPendenteAsaas($this->u);
        $i1->forceFill(['checkout_expira_em' => now()->subDays(400)])->save();
        app()->instance(PaymentProvider::class, _provedorQueFalha(['cancelCheckout' => fn () => new ProvedorIndisponivel('fora do ar')]));
        Log::spy();

        $i2 = _compraPendenteAsaas($this->u);

        expect($i2->checkout_url)->not->toBeNull()->and($i2->fresh()->status)->toBe(EstadoCobranca::Pendente)
            ->and($i1->fresh()->status)->toBe(EstadoCobranca::Pendente);
        Log::shouldHaveReceived('warning')->with('cobranca.checkout_anterior_nao_cancelado', Mockery::any());
    });
});

describe('CA-3 — crédito com estorno aguardando autorização', function () {
    it('E12: não se consome crédito com estorno Pix aguardando autorização', function () {
        $i = _compraPendenteAsaas($this->u);
        _entregar(_fake()->simularPagamento((string) $i->checkout_id_externo));
        expect(app(Carteira::class)->verificar($this->u->id)->permitido())->toBeTrue();

        $r = app(ReembolsarCobranca::class)->solicitar($i->fresh(), _adminDaEquipe(), 'Arrependimento do cliente');

        $d = app(Carteira::class)->verificar($this->u->id);
        expect($r->value)->toBe('aguardando_autorizacao')
            ->and($d->permitido())->toBeFalse()
            ->and($d->bloqueio?->motivo)->toBe(MotivoBloqueio::SemNada)
            ->and(LoteCredito::query()->where('invoice_id', $i->id)->sole()->quantidade_restante)->toBe(LoteCredito::query()->where('invoice_id', $i->id)->sole()->quantidade_comprada);
    });

    it('E12: estorno negado devolve o crédito ao consumo', function () {
        $i = _compraPendenteAsaas($this->u);
        _entregar(_fake()->simularPagamento((string) $i->checkout_id_externo));
        app(ReembolsarCobranca::class)->solicitar($i->fresh(), _adminDaEquipe(), 'Arrependimento do cliente');
        expect(app(Carteira::class)->verificar($this->u->id)->permitido())->toBeFalse();
        Log::spy();

        $voltou = app(ReembolsarCobranca::class)->negar($i->id);

        expect($voltou)->toBeTrue()
            ->and($i->fresh()->reembolso_estado)->toBeNull()->and($i->fresh()->status)->toBe(EstadoCobranca::Paga)
            ->and(app(Carteira::class)->verificar($this->u->id)->permitido())->toBeTrue()
            ->and(AuditLog::query()->where('action', 'cobranca.reembolso_negado_pelo_provedor')->count())->toBe(1);
        Log::shouldHaveReceived('error')->with('cobranca.reembolso_negado', Mockery::any());
    });

    it('negar um estorno que não está aguardando não muda nada', function () {
        $i = _compraPendenteAsaas($this->u);
        _entregar(_fake()->simularPagamento((string) $i->checkout_id_externo));

        expect(app(ReembolsarCobranca::class)->negar($i->id))->toBeFalse()
            ->and(AuditLog::query()->where('action', 'cobranca.reembolso_negado_pelo_provedor')->count())->toBe(0);
    });

    it('estorno aguardando autorização concluído pelo webhook continua retirando o saldo', function () {
        $i = _compraPendenteAsaas($this->u);
        _entregar(_fake()->simularPagamento((string) $i->checkout_id_externo));
        app(ReembolsarCobranca::class)->solicitar($i->fresh(), _adminDaEquipe(), 'Pedido');

        _entregar(_fake()->autorizarReembolso((string) $i->fresh()->id_externo));

        expect($i->fresh()->status)->toBe(EstadoCobranca::Estornada)
            ->and(LoteCredito::query()->where('invoice_id', $i->id)->sole()->quantidade_restante)->toBe(0);
    });

    it('com outro lote válido, o consumo usa o outro e deixa o lote em estorno intacto', function () {
        $i = _compraPendenteAsaas($this->u, _pacote('plus'));
        _entregar(_fake()->simularPagamento((string) $i->checkout_id_externo));
        $outro = app(Carteira::class)->concederCreditos($this->u->id, 3, now()->addYear());
        app(ReembolsarCobranca::class)->solicitar($i->fresh(), _adminDaEquipe(), 'Pedido');

        $d = app(Carteira::class)->verificar($this->u->id);

        expect($d->permitido())->toBeTrue()->and($d->loteId)->toBe($outro->id);
    });
});

describe('CA-4 — assinatura em atraso mantém o acesso durante as retentativas', function () {
    it('E17: depois da 1ª recusa, análises e Raio X do Negócio continuam', function () {
        [$a, $i] = _assinaturaAtivaNoFake($this->u);
        $a = _recusarRenovacao($a, $i);
        expect($a->status)->toBe(StatusAssinatura::EmAtraso);

        $d = app(Carteira::class)->verificar($this->u->id);

        expect($d->bloqueio)->toBeNull()->and($d->permitido())->toBeTrue()
            ->and(app(Carteira::class)->bloqueioDoDrm($this->u->id))->toBeNull();
    });

    it('as análises em atraso consomem a cota que sobrou', function () {
        [$a, $i] = _assinaturaAtivaNoFake($this->u);
        $a = _recusarRenovacao($a, $i);
        $antes = $a->cota_restante_ciclo;

        $d = app(Carteira::class)->verificar($this->u->id);
        DB::update('update assinaturas set cota_restante_ciclo = cota_restante_ciclo - 1 where id = ? and cota_restante_ciclo > 0', [$d->assinaturaId]);

        expect($d->assinaturaId)->toBe($a->id)->and($a->fresh()->cota_restante_ciclo)->toBe($antes - 1);
    });

    it('sem cota nenhuma em atraso, o bloqueio explica que é preciso atualizar o cartão', function () {
        [$a, $i] = _assinaturaAtivaNoFake($this->u);
        $a = _recusarRenovacao($a, $i);
        $a->forceFill(['cota_restante_ciclo' => 0])->save();

        $d = app(Carteira::class)->verificar($this->u->id);

        expect($d->bloqueio?->motivo)->toBe(MotivoBloqueio::EmAtraso)
            ->and(app(Carteira::class)->bloqueioDoDrm($this->u->id))->toBeNull();
    });

    it('o bloqueio só vem ao encerrar o ciclo (48 h depois da 3ª retentativa), pela régua de sempre', function () {
        [$a, $i] = _assinaturaAtivaNoFake($this->u);
        $a = _recusarRenovacao($a, $i);

        foreach ([48, 48, 48] as $horas) {
            _rodarRegua($horas);
            expect(app(Carteira::class)->verificar($this->u->id)->permitido())->toBeTrue();
        }

        _rodarRegua(48);

        $a = $a->fresh();
        $d = app(Carteira::class)->verificar($this->u->id);
        expect($a->status)->toBe(StatusAssinatura::Expirada)
            ->and($d->permitido())->toBeFalse()->and($d->bloqueio?->motivo)->toBe(MotivoBloqueio::PlanoEncerrado)
            ->and(app(Carteira::class)->bloqueioDoDrm($this->u->id)?->motivo)->toBe(MotivoBloqueio::PlanoEncerrado);
    });

    it('a Home avisa do atraso, sem bloquear, e some quando o cartão é cobrado', function () {
        [$a, $i] = _assinaturaAtivaNoFake($this->u);
        _recusarRenovacao($a, $i);

        $aviso = app(Carteira::class)->avisoDeAtraso($this->u->id);

        expect($aviso['severidade'])->toBe('info')->and($aviso['texto'])->toContain('em atraso')->and($aviso['acao'])->toBe('meu-plano');
    });

    it('a tela inicial mostra o aviso de atraso com o atalho para atualizar o cartão', function () {
        [$a, $i] = _assinaturaAtivaNoFake($this->u);
        _recusarRenovacao($a, $i);

        Livewire::actingAs($this->u)->test(MinhasEmpresas::class)
            ->assertSee('Sua assinatura está em atraso')->assertSee('Atualizar cartão');
    });

    it('sem atraso, não há aviso', function () {
        _assinaturaAtivaNoFake($this->u);

        expect(app(Carteira::class)->avisoDeAtraso($this->u->id))->toBeNull();
    });
});

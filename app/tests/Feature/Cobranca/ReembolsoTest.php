<?php

declare(strict_types=1);

use App\Domain\Cobranca\CicloCobranca;
use App\Domain\Cobranca\EstadoCobranca;
use App\Domain\Cobranca\MeioPagamento;
use App\Domain\Cobranca\PedidoCheckout;
use App\Domain\Cobranca\ProvedorIndisponivel;
use App\Domain\Cobranca\ReferenciaProvedor;
use App\Domain\Cobranca\ResultadoReembolso;
use App\Domain\Cobranca\StatusAssinatura;
use App\Domain\Cobranca\StatusLote;
use App\Domain\Cobranca\TipoEvento;
use App\Infrastructure\Cobranca\Fake\FakePaymentProvider;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\LoteCredito;
use App\Models\ProviderEvent;
use App\Models\Usuario;
use App\Services\Cobranca\GerirRenovacao;
use App\Services\Cobranca\IniciarCompraDeCreditos;
use App\Services\Cobranca\MarcarNotaFiscal;
use App\Services\Cobranca\ReembolsarCobranca;
use App\Services\Cobranca\ReembolsoNaoRealizado;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * STORY-085 CA-4, CA-5, CA-6, CA-7 e ajustes do ADR-010 — reembolso total, Pix aguardando autorização,
 * contestação e pagamentos sem destino. Provedor fake.
 */
beforeEach(function () {
    _usarProvedor('fake');
    Mail::fake();
    $this->usuario = _compradorConfirmado();
    $this->admin = Usuario::factory()->create();
});

function _reembolsar(): ReembolsarCobranca
{
    return app(ReembolsarCobranca::class);
}

/** Compra de créditos paga (cartão ou Pix) no fake. */
function _creditosPagos(Usuario $u, MeioPagamento $meio = MeioPagamento::Cartao): Invoice
{
    $invoice = app(IniciarCompraDeCreditos::class)->executar($u, _pacote(), null, null, $meio);
    _entregar(_fake()->simularPagamento((string) $invoice->checkout_id_externo));

    return $invoice->fresh();
}

function _lote(Invoice $i): LoteCredito
{
    return LoteCredito::query()->where('invoice_id', $i->id)->sole();
}

describe('CA-4 estorno de cartão confirmado', function () {
    it('estorna, marca Estornada e retira os créditos restantes do lote', function () {
        $invoice = _creditosPagos($this->usuario);

        $r = _reembolsar()->solicitar($invoice, $this->admin, 'Arrependimento em 7 dias');

        $invoice->refresh();
        $lote = _lote($invoice);
        expect($r)->toBe(ResultadoReembolso::Concluido)
            ->and($invoice->status)->toBe(EstadoCobranca::Estornada)
            ->and($invoice->reembolso_estado)->toBe('concluido')
            ->and($invoice->reembolso_motivo)->toBe('Arrependimento em 7 dias')
            ->and($invoice->reembolso_por_id)->toBe($this->admin->id)
            ->and($lote->quantidade_restante)->toBe(0)
            ->and($lote->status)->toBe(StatusLote::Estornado)
            ->and(FakePaymentProvider::reembolsosDe((string) $invoice->id_externo))->toBe(1);
    });

    it('créditos já usados não voltam e o saldo nunca fica negativo; a prévia avisa antes', function () {
        $invoice = _creditosPagos($this->usuario);
        $lote = _lote($invoice);
        $lote->forceFill(['quantidade_restante' => $lote->quantidade_comprada - 3])->save();

        $previa = _reembolsar()->previa($invoice);
        expect($previa->creditosUsados)->toBe(3)
            ->and($previa->creditosARetirar)->toBe($lote->quantidade_comprada - 3)
            ->and($previa->descricao())->toContain('3 dos '.$lote->quantidade_comprada.' créditos já foram usados');

        _reembolsar()->solicitar($invoice, $this->admin, 'Pedido do cliente');

        expect(_lote($invoice)->quantidade_restante)->toBe(0);
        $log = AuditLog::query()->where('action', 'cobranca.reembolso_concluido')->sole();
        expect($log->after['creditos_ja_usados'])->toBe(3);
    });

    it('lote totalmente usado: nada a retirar, e a descrição fala no singular quando resta um', function () {
        $invoice = _creditosPagos($this->usuario);
        $lote = _lote($invoice);
        $lote->forceFill(['quantidade_restante' => 1])->save();
        expect(_reembolsar()->previa($invoice)->descricao())->toContain('1 crédito restante');

        $lote->forceFill(['quantidade_restante' => 0, 'status' => StatusLote::Esgotado])->save();
        $previa = _reembolsar()->previa($invoice);

        expect($previa->retiraAlgo())->toBeFalse();
        _reembolsar()->solicitar($invoice, $this->admin, 'Pedido do cliente');
        expect($invoice->fresh()->status)->toBe(EstadoCobranca::Estornada);
    });

    it('assinatura: encerra, zera a cota, pede o cancelamento da recorrência e bloqueia o acesso', function () {
        [$assinatura, $invoice] = _assinaturaAtivaNoFake($this->usuario);

        $previa = _reembolsar()->previa($invoice);
        expect($previa->encerraAssinatura)->toBeTrue()->and($previa->descricao())->toContain('encerrada');

        _reembolsar()->solicitar($invoice, $this->admin, 'Arrependimento');

        $assinatura->refresh();
        expect($assinatura->status)->toBe(StatusAssinatura::Expirada)
            ->and($assinatura->cota_restante_ciclo)->toBe(0)
            ->and($assinatura->encerramento_pendente_no_provedor)->toBeTrue()
            ->and($invoice->fresh()->status)->toBe(EstadoCobranca::Estornada);

        $this->artisan('cobranca:regua');
        expect(FakePaymentProvider::assinaturaCancelada((string) $assinatura->id_externo))->toBeTrue();
    });

    it('estornar uma cobrança antiga não derruba a assinatura que já tem cobrança paga mais recente', function () {
        [$assinatura, $primeira] = _assinaturaAtivaNoFake($this->usuario);
        $this->travelTo($assinatura->proximo_vencimento);
        _entregar(_fake()->simularRenovacao((string) $primeira->checkout_id_externo));
        $primeira->refresh();

        expect(_reembolsar()->previa($primeira)->descricao())->toContain('cobrança paga mais recente');

        _reembolsar()->solicitar($primeira, $this->admin, 'Erro de cobrança');

        expect($assinatura->fresh()->status)->toBe(StatusAssinatura::Ativa)
            ->and($primeira->fresh()->status)->toBe(EstadoCobranca::Estornada);
    });
});

describe('CA-5 não reembolsa duas vezes', function () {
    it('o segundo pedido é barrado e o provedor estorna uma vez só', function () {
        $invoice = _creditosPagos($this->usuario);
        _reembolsar()->solicitar($invoice, $this->admin, 'Pedido do cliente');

        expect(fn () => _reembolsar()->solicitar($invoice->fresh(), $this->admin, 'De novo'))->toThrow(ReembolsoNaoRealizado::class, 'já foi reembolsada');
        expect(FakePaymentProvider::reembolsosDe((string) $invoice->id_externo))->toBe(1);
    });

    it('resposta perdida: o estorno ocorreu lá; repetir só finaliza, sem estornar de novo', function () {
        $invoice = _creditosPagos($this->usuario);
        FakePaymentProvider::perderRespostaDoProximoReembolso();

        expect(fn () => _reembolsar()->solicitar($invoice, $this->admin, 'Pedido do cliente'))->toThrow(ProvedorIndisponivel::class);
        expect($invoice->fresh()->reembolso_estado)->toBeNull()->and($invoice->fresh()->status)->toBe(EstadoCobranca::Paga);

        $r = _reembolsar()->solicitar($invoice->fresh(), $this->admin, 'Pedido do cliente');

        expect($r)->toBe(ResultadoReembolso::Concluido)
            ->and($invoice->fresh()->status)->toBe(EstadoCobranca::Estornada)
            ->and(FakePaymentProvider::reembolsosDe((string) $invoice->id_externo))->toBe(1);
    });

    it('pedido em andamento (Pix) também barra um segundo pedido', function () {
        $invoice = _creditosPagos($this->usuario, MeioPagamento::Pix);
        _reembolsar()->solicitar($invoice, $this->admin, 'Pedido do cliente');

        expect(fn () => _reembolsar()->solicitar($invoice->fresh(), $this->admin, 'De novo'))->toThrow(ReembolsoNaoRealizado::class, 'aguarda autorização');
    });

    it('cobrança que não está paga não é reembolsada', function () {
        $pendente = app(IniciarCompraDeCreditos::class)->executar($this->usuario, _pacote(), null, null);

        expect(fn () => _reembolsar()->solicitar($pendente, $this->admin, 'Motivo qualquer'))->toThrow(ReembolsoNaoRealizado::class, 'Só cobrança paga');
        expect($pendente->fresh()->reembolso_estado)->toBeNull();
    });

    it('cobrança de outro provedor ou sem id externo manda estornar direto no provedor', function () {
        $invoice = _creditosPagos($this->usuario);
        $invoice->forceFill(['provedor' => 'asaas'])->save();

        expect(fn () => _reembolsar()->solicitar($invoice, $this->admin, 'Motivo qualquer'))->toThrow(ReembolsoNaoRealizado::class, 'direto no provedor');
    });

    it('o provedor mostra a cobrança como contestada: nada é estornado e a trava é liberada', function () {
        $invoice = _creditosPagos($this->usuario);
        _fake()->simularContestacao((string) $invoice->id_externo);

        expect(fn () => _reembolsar()->solicitar($invoice, $this->admin, 'Motivo qualquer'))->toThrow(ReembolsoNaoRealizado::class, 'contestada');
        expect($invoice->fresh()->reembolso_estado)->toBeNull()->and(FakePaymentProvider::reembolsosDe((string) $invoice->id_externo))->toBe(0);
    });

    it('já estornada no provedor (painel): só finaliza localmente', function () {
        $invoice = _creditosPagos($this->usuario);
        _fake()->refund(new ReferenciaProvedor('fake', (string) $invoice->id_externo));

        $r = _reembolsar()->solicitar($invoice, $this->admin, 'Motivo qualquer');

        expect($r)->toBe(ResultadoReembolso::Concluido)->and(FakePaymentProvider::reembolsosDe((string) $invoice->id_externo))->toBe(1)
            ->and(_lote($invoice)->status)->toBe(StatusLote::Estornado);
    });
});

describe('ajuste do ADR-010: recusa e indisponibilidade', function () {
    it('o provedor recusa: nada muda, a trava é liberada e o motivo aparece', function () {
        $invoice = _creditosPagos($this->usuario);
        FakePaymentProvider::recusarProximoReembolso();

        expect(fn () => _reembolsar()->solicitar($invoice, $this->admin, 'Motivo qualquer'))->toThrow(ReembolsoNaoRealizado::class, 'recusado');

        $invoice->refresh();
        expect($invoice->status)->toBe(EstadoCobranca::Paga)->and($invoice->reembolso_estado)->toBeNull()
            ->and(_lote($invoice)->status)->toBe(StatusLote::Ativo)
            ->and(AuditLog::query()->where('action', 'cobranca.reembolso_nao_realizado')->exists())->toBeTrue();
    });

    it('provedor fora do ar: nada muda e pode-se tentar de novo', function () {
        $invoice = _creditosPagos($this->usuario);
        FakePaymentProvider::ficarIndisponivel();

        expect(fn () => _reembolsar()->solicitar($invoice, $this->admin, 'Motivo qualquer'))->toThrow(ProvedorIndisponivel::class);
        expect($invoice->fresh()->reembolso_estado)->toBeNull()->and($invoice->fresh()->status)->toBe(EstadoCobranca::Paga);
    });
});

describe('Pix: aguardando autorização', function () {
    it('fica aguardando, nada é retirado, e o webhook de estorno conclui', function () {
        $invoice = _creditosPagos($this->usuario, MeioPagamento::Pix);

        $r = _reembolsar()->solicitar($invoice, $this->admin, 'Pedido do cliente');

        $invoice->refresh();
        expect($r)->toBe(ResultadoReembolso::AguardandoAutorizacao)
            ->and($invoice->reembolso_estado)->toBe('aguardando_autorizacao')
            ->and($invoice->status)->toBe(EstadoCobranca::Paga)
            ->and(_lote($invoice)->status)->toBe(StatusLote::Ativo);

        _entregar(_fake()->autorizarReembolso((string) $invoice->id_externo));

        $invoice->refresh();
        expect($invoice->status)->toBe(EstadoCobranca::Estornada)->and($invoice->reembolso_estado)->toBe('concluido')
            ->and(_lote($invoice)->quantidade_restante)->toBe(0);
    });

    it('o mesmo webhook de estorno duas vezes não retira nem registra de novo', function () {
        $invoice = _creditosPagos($this->usuario, MeioPagamento::Pix);
        _reembolsar()->solicitar($invoice, $this->admin, 'Pedido do cliente');
        $webhook = _fake()->autorizarReembolso((string) $invoice->id_externo, 'evt-estorno');

        _entregar($webhook);
        _entregar($webhook);

        expect(AuditLog::query()->where('action', 'cobranca.reembolso_concluido')->count())->toBe(1);
    });

    it('estorno feito direto no painel do provedor (sem pedido nosso) também retira o que foi concedido', function () {
        $invoice = _creditosPagos($this->usuario);
        _fake()->refund(new ReferenciaProvedor('fake', (string) $invoice->id_externo));

        _entregar(_fake()->webhookDaCobranca(TipoEvento::CobrancaEstornada, (string) $invoice->id_externo));

        expect($invoice->fresh()->status)->toBe(EstadoCobranca::Estornada)->and(_lote($invoice)->status)->toBe(StatusLote::Estornado);
        $evento = ProviderEvent::query()->where('tipo', 'cobranca.estornada')->sole();
        expect($evento->status)->toBe('processado');
        expect(AuditLog::query()->where('action', 'cobranca.reembolso_concluido')->sole()->actor_type)->toBe('system');
    });

    it('aviso de estorno que o provedor não confirma (ainda paga) é ignorado', function () {
        $invoice = _creditosPagos($this->usuario);

        _entregar(_fake()->webhookDaCobranca(TipoEvento::CobrancaEstornada, (string) $invoice->id_externo));

        expect($invoice->fresh()->status)->toBe(EstadoCobranca::Paga);
        expect(ProviderEvent::query()->where('tipo', 'cobranca.estornada')->sole()->status)->toBe('ignorado');
    });
});

describe('contestação (chargeback)', function () {
    it('o webhook marca Contestada, alerta a equipe e não retira o acesso sozinho', function () {
        Log::spy();
        $invoice = _creditosPagos($this->usuario);

        _entregar(_fake()->simularContestacao((string) $invoice->id_externo));

        expect($invoice->fresh()->status)->toBe(EstadoCobranca::Contestada)
            ->and(_lote($invoice)->status)->toBe(StatusLote::Ativo)
            ->and(AuditLog::query()->where('action', 'cobranca.contestada')->exists())->toBeTrue();
        Log::shouldHaveReceived('error')->withArgs(fn (string $m) => $m === 'cobranca.contestada')->once();
    });

    it('aviso repetido não muda nada, e contestação que o provedor não confirma é ignorada', function () {
        $invoice = _creditosPagos($this->usuario);
        $web = _fake()->simularContestacao((string) $invoice->id_externo, 'evt-c');
        _entregar($web);
        _entregar($web);
        expect(AuditLog::query()->where('action', 'cobranca.contestada')->count())->toBe(1);

        $outra = _creditosPagos(Usuario::factory()->create(['email_confirmed_at' => now()]));
        _entregar(_fake()->webhookDaCobranca(TipoEvento::CobrancaContestada, (string) $outra->id_externo));
        expect($outra->fresh()->status)->toBe(EstadoCobranca::Paga);
    });
});

describe('pagamentos sem destino viram Invoice para devolver', function () {
    it('pago depois do cancelamento em atraso: aparece como a devolver, sem conceder nada', function () {
        [$assinatura, $primeira] = _assinaturaAtivaNoFake($this->usuario);
        $assinatura = _recusarRenovacao($assinatura, $primeira);
        app(GerirRenovacao::class)->cancelar($this->usuario);
        expect($assinatura->fresh()->status)->toBe(StatusAssinatura::Expirada);

        $cobranca = FakePaymentProvider::cobrancasDaAssinatura((string) $assinatura->id_externo);
        $ultima = end($cobranca);
        _entregar(_fake()->simularPagamentoDaCobranca($ultima));
        _entregar(_fake()->simularPagamentoDaCobranca($ultima)); // repetido: uma só Invoice

        $orfa = Invoice::query()->whereNotNull('devolucao_motivo')->sole();
        expect($orfa->devolucao_motivo)->toBe('assinatura_cancelada')
            ->and($orfa->status)->toBe(EstadoCobranca::Paga)
            ->and($orfa->renovacao)->toBeFalse()
            ->and($orfa->usuario_id)->toBe($this->usuario->id)
            ->and($assinatura->fresh()->status)->toBe(StatusAssinatura::Expirada);

        $previa = _reembolsar()->previa($orfa);
        expect($previa->tipo)->toBe('nada')->and($previa->descricao())->toContain('só o dinheiro é devolvido');

        _reembolsar()->solicitar($orfa, $this->admin, 'Pago após o cancelamento');
        expect($orfa->fresh()->status)->toBe(EstadoCobranca::Estornada);
    });

    it('assinatura duplicada (segundo checkout pago com a assinatura já ativa) fica marcada para devolver', function () {
        [, $a] = _assinaturaAtivaNoFake($this->usuario);
        $b = $a->replicate(['id_externo', 'checkout_id_externo', 'pago_em', 'assinatura_id']);
        $b->forceFill(['status' => EstadoCobranca::Pendente, 'renovacao' => false])->save();
        $sessao = _fake()->startCheckout(new PedidoCheckout($b->id, 'Plano', $b->valor_centavos, [MeioPagamento::Cartao], 30, 'https://a/ok', 'https://a/c', 'https://a/e', CicloCobranca::Mensal));
        $b->forceFill(['checkout_id_externo' => $sessao->referencia->id])->save();

        _entregar(_fake()->simularPagamento($sessao->referencia->id));

        $b->refresh();
        expect($b->status)->toBe(EstadoCobranca::Paga)->and($b->devolucao_motivo)->toBe('assinatura_duplicada');
    });
});

describe('nota fiscal (CA-6, CA-7)', function () {
    it('marca a cobrança paga como emitida, com número, e audita', function () {
        $invoice = _creditosPagos($this->usuario);

        app(MarcarNotaFiscal::class)->marcar($invoice, $this->admin, '123');

        $invoice->refresh();
        expect($invoice->nf_emitida_em)->not->toBeNull()->and($invoice->nf_numero)->toBe('123')->and($invoice->nf_emitida_por_id)->toBe($this->admin->id);
        $log = AuditLog::query()->where('action', 'cobranca.nota_fiscal_emitida')->sole();
        expect($log->actor_id)->toBe($this->admin->id)->and($log->subject_id)->toBe($invoice->id);
    });

    it('não marca duas vezes nem cobrança que não está paga', function () {
        $invoice = _creditosPagos($this->usuario);
        app(MarcarNotaFiscal::class)->marcar($invoice, $this->admin, null);

        expect(fn () => app(MarcarNotaFiscal::class)->marcar($invoice, $this->admin, null))->toThrow(ReembolsoNaoRealizado::class, 'já foi marcada');

        $pendente = app(IniciarCompraDeCreditos::class)->executar($this->usuario, _pacote(), null, null);
        expect(fn () => app(MarcarNotaFiscal::class)->marcar($pendente, $this->admin, null))->toThrow(ReembolsoNaoRealizado::class, 'Só cobrança paga');
    });
});

describe('CA-7 auditoria do reembolso', function () {
    it('grava solicitação e conclusão com o admin, o motivo e a conta afetada', function () {
        $invoice = _creditosPagos($this->usuario);
        _reembolsar()->solicitar($invoice, $this->admin, 'Arrependimento em 7 dias');

        $pedido = AuditLog::query()->where('action', 'cobranca.reembolso_solicitado')->sole();
        expect($pedido->actor_id)->toBe($this->admin->id)->and($pedido->usuario_id)->toBe($this->usuario->id)
            ->and($pedido->after['motivo'])->toBe('Arrependimento em 7 dias');
        expect(AuditLog::query()->where('action', 'cobranca.reembolso_concluido')->sole()->after['retirado']['creditos'])->toBeGreaterThan(0);
    });
});

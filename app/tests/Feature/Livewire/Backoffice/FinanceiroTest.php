<?php

declare(strict_types=1);

use App\Domain\Cobranca\EstadoCobranca;
use App\Domain\Cobranca\MeioPagamento;
use App\Infrastructure\Cobranca\Fake\FakePaymentProvider;
use App\Livewire\Backoffice\Financeiro;
use App\Models\AuditLog;
use App\Models\ConsumoAnalise;
use App\Models\Invoice;
use App\Models\LoteCredito;
use App\Models\Usuario;
use App\Services\Cobranca\IniciarCompraDeCreditos;
use App\Support\PermissaoAdmin;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Livewire;

/**
 * STORY-085 — tela Financeiro do backoffice (permissão, filtros, arrependimento, reembolso, nota, "Logar como").
 */
beforeEach(function () {
    config(['backoffice.equipe_emails' => []]);
    _usarProvedor('fake');
    Mail::fake();
    $this->admin = Usuario::factory()->create();
    $this->admin->forceFill(['permissoes_admin' => ['financeiro.gerenciar']])->save();
    $this->cliente = _compradorConfirmado();
    $this->tela = fn () => Livewire::actingAs($this->admin)->test(Financeiro::class);
    $this->paga = function (?Usuario $u = null, MeioPagamento $meio = MeioPagamento::Cartao): Invoice {
        $invoice = app(IniciarCompraDeCreditos::class)->executar($u ?? $this->cliente, _pacote(), null, null, $meio);
        _entregar(_fake()->simularPagamento((string) $invoice->checkout_id_externo));

        return $invoice->fresh();
    };
});

describe('CA-1 permissão', function () {
    it('abre só com financeiro.gerenciar; 404 sem ela, para o comum e menu oculto', function () {
        $this->actingAs($this->admin)->get('/backoffice/financeiro')->assertOk()->assertSee('app-nav-backoffice-financeiro');

        $outro = Usuario::factory()->create();
        $outro->forceFill(['permissoes_admin' => ['planos.gerenciar']])->save();
        $this->actingAs($outro)->get('/backoffice/financeiro')->assertNotFound();
        $this->actingAs($outro)->get('/backoffice/planos')->assertOk()->assertDontSee('app-nav-backoffice-financeiro');
        $this->actingAs(Usuario::factory()->create())->get('/backoffice/financeiro')->assertNotFound();
    });

    it('anônimo vai ao login e as ações do Livewire também exigem a permissão', function () {
        auth()->logout();
        $this->get('/backoffice/financeiro')->assertRedirect('/login');
        $invoice = ($this->paga)();

        Livewire::actingAs(Usuario::factory()->create())->test(Financeiro::class)->assertNotFound();
        expect($invoice->fresh()->status)->toBe(EstadoCobranca::Paga);
    });

    it('a permissão tem rótulo e aparece para o raiz conceder', function () {
        expect(PermissaoAdmin::GerenciarFinanceiro->value)->toBe('financeiro.gerenciar')
            ->and(PermissaoAdmin::GerenciarFinanceiro->rotulo())->toContain('reembolsos');
    });
});

describe('CA-8 durante o "Logar como"', function () {
    it('a tela e as ações ficam 404', function () {
        $equipe = _adminDaEquipe();
        $invoice = ($this->paga)();

        $this->actingAs($this->cliente)->withSession(_sessaoDePersonificacao($equipe, $this->cliente))
            ->get('/backoffice/financeiro')->assertNotFound();

        $this->cliente->forceFill(['permissoes_admin' => ['financeiro.gerenciar']])->save();
        $this->actingAs($this->cliente)->withSession(_sessaoDePersonificacao($equipe, $this->cliente))
            ->get('/backoffice/financeiro')->assertNotFound();
        expect($invoice->fresh()->status)->toBe(EstadoCobranca::Paga);
    });
});

describe('CA-2 lista e filtros', function () {
    it('lista assinaturas e cobranças e filtra por usuário, status e período', function () {
        $outro = Usuario::factory()->create(['nome' => 'Maria Outra', 'email_confirmed_at' => now()]);
        $a = ($this->paga)();
        $b = ($this->paga)($outro);
        [$assinatura] = _assinaturaAtivaNoFake(Usuario::factory()->create(['nome' => 'Joao Assinante', 'email_confirmed_at' => now()]));
        $b->forceFill(['status' => EstadoCobranca::Cancelada])->save();

        $tela = ($this->tela)()->assertSee($a->descricao)->assertSeeHtml('dusk="assinatura-'.$assinatura->id.'"');

        $tela->set('busca', 'maria outra')->call('filtrar')
            ->assertSeeHtml('dusk="cobranca-'.$b->id.'"')->assertDontSeeHtml('dusk="cobranca-'.$a->id.'"')->assertDontSeeHtml('dusk="assinatura-'.$assinatura->id.'"');

        $tela->call('limparFiltros')->set('statusCobranca', 'cancelada')->call('filtrar')
            ->assertSeeHtml('dusk="cobranca-'.$b->id.'"')->assertDontSeeHtml('dusk="cobranca-'.$a->id.'"');

        $tela->call('limparFiltros')->set('statusAssinatura', 'cancelada')->call('filtrar')
            ->assertDontSeeHtml('dusk="assinatura-'.$assinatura->id.'"')->assertSeeHtml('dusk="assinaturas-vazio"');

        $hoje = now('America/Sao_Paulo')->format('Y-m-d');
        $tela->call('limparFiltros')->set('de', $hoje)->set('ate', $hoje)->call('filtrar')
            ->assertSeeHtml('dusk="cobranca-'.$a->id.'"')->assertSeeHtml('dusk="assinatura-'.$assinatura->id.'"');

        $tela->call('limparFiltros')->set('de', now()->addDays(2)->format('Y-m-d'))->call('filtrar')
            ->assertSeeHtml('dusk="cobrancas-vazio"');
    });

    it('rejeita data inválida e período invertido', function () {
        ($this->tela)()->set('de', '2026-13-45')->call('filtrar')->assertHasErrors(['de'])
            ->set('de', '2026-11-10')->set('ate', '2026-11-01')->call('filtrar')->assertHasErrors(['ate'])
            ->assertSee('A data final não pode ser anterior à inicial.');
    });

    it('rejeita status que não existe', function () {
        ($this->tela)()->set('statusCobranca', 'inventado')->call('filtrar')->assertHasErrors(['statusCobranca']);
    });
});

describe('CA-3 arrependimento', function () {
    it('mostra dentro dos 7 dias e sem análise: cabe arrependimento', function () {
        $i = ($this->paga)();

        ($this->tela)()->assertSeeInOrder(['Dentro dos 7 dias (dia 0)', 'Sem análise desde a contratação', 'Cabe arrependimento']);
    });

    it('mostra a análise feita desde a contratação (créditos) e o prazo vencido', function () {
        $i = ($this->paga)();
        $lote = LoteCredito::query()->where('invoice_id', $i->id)->sole();
        ConsumoAnalise::query()->insert([
            'id' => (string) Str::uuid7(), 'usuario_id' => $this->cliente->id, 'origem' => 'credito',
            'lote_credito_id' => $lote->id, 'consumido_em' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        ($this->tela)()->assertSee('Houve análise desde a contratação')->assertDontSee('Cabe arrependimento');

        $i->forceFill(['pago_em' => now()->subDays(8)])->save();
        ($this->tela)()->assertSee('Fora dos 7 dias');
    });

    it('assinatura: análise da cota só conta depois da contratação', function () {
        [$assinatura, $invoice] = _assinaturaAtivaNoFake($this->cliente);
        ConsumoAnalise::query()->insert([
            'id' => (string) Str::uuid7(), 'usuario_id' => $this->cliente->id, 'origem' => 'cota',
            'assinatura_id' => $assinatura->id, 'consumido_em' => now()->addMinute(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        ($this->tela)()->assertSee('Houve análise desde a contratação');
    });
});

describe('CA-4, CA-5, CA-7 reembolso pela tela', function () {
    it('exige motivo, mostra a prévia, e com a confirmação estorna, retira e audita', function () {
        $i = ($this->paga)();
        LoteCredito::query()->where('invoice_id', $i->id)->update(['quantidade_restante' => 2]);

        $tela = ($this->tela)()->call('abrirReembolso', $i->id)->assertSee('já foram usados');
        $tela->set('motivo', '')->call('confirmarReembolso')->assertHasErrors(['motivo'])->assertSee('Informe o motivo do reembolso.');
        $tela->set('motivo', 'abc')->call('confirmarReembolso')->assertHasErrors(['motivo']);
        expect($i->fresh()->status)->toBe(EstadoCobranca::Paga);

        $tela->set('motivo', 'Arrependimento em 7 dias')->call('confirmarReembolso')
            ->assertHasNoErrors()->assertSet('reembolsoId', null)->assertSee('Reembolso confirmado pelo provedor');

        expect($i->fresh()->status)->toBe(EstadoCobranca::Estornada)
            ->and(LoteCredito::query()->where('invoice_id', $i->id)->sole()->quantidade_restante)->toBe(0)
            ->and(AuditLog::query()->where('action', 'cobranca.reembolso_solicitado')->sole()->actor_id)->toBe($this->admin->id);
    });

    it('o segundo reembolso da mesma cobrança mostra o erro e não estorna de novo', function () {
        $i = ($this->paga)();
        $tela = ($this->tela)()->call('abrirReembolso', $i->id)->set('motivo', 'Arrependimento')->call('confirmarReembolso');
        $tela->call('abrirReembolso', $i->id)->set('motivo', 'Arrependimento')->call('confirmarReembolso')
            ->assertSee('já foi reembolsada');

        expect(FakePaymentProvider::reembolsosDe((string) $i->id_externo))->toBe(1);
    });

    it('Pix: orienta a autorizar no painel do provedor de pagamento e o estado muda com o evento', function () {
        $i = ($this->paga)(null, MeioPagamento::Pix);
        $tela = ($this->tela)()->call('abrirReembolso', $i->id)->set('motivo', 'Arrependimento')->call('confirmarReembolso')
            ->assertSee('autorize o estorno no painel do provedor de pagamento')
            ->assertSeeHtml('dusk="cobranca-aguardando-'.$i->id.'"')
            ->assertDontSeeHtml('dusk="cobranca-reembolsar-'.$i->id.'"');

        _entregar(_fake()->autorizarReembolso((string) $i->id_externo));

        $tela->call('cancelarReembolso')->assertDontSeeHtml('dusk="cobranca-aguardando-'.$i->id.'"');
        expect($i->fresh()->status)->toBe(EstadoCobranca::Estornada);
    });

    it('recusa e indisponibilidade do provedor aparecem como erro e nada muda', function () {
        $i = ($this->paga)();
        FakePaymentProvider::recusarProximoReembolso();
        ($this->tela)()->call('abrirReembolso', $i->id)->set('motivo', 'Arrependimento')->call('confirmarReembolso')
            ->assertSeeHtml('dusk="financeiro-erro"')->assertSee('recusado');

        FakePaymentProvider::ficarIndisponivel();
        ($this->tela)()->call('abrirReembolso', $i->id)->set('motivo', 'Arrependimento')->call('confirmarReembolso')
            ->assertSee('não respondeu');
        expect($i->fresh()->status)->toBe(EstadoCobranca::Paga);
    });

    it('cancelar fecha o painel sem estornar', function () {
        $i = ($this->paga)();
        ($this->tela)()->call('abrirReembolso', $i->id)->assertSeeHtml('dusk="reembolso-painel"')->call('cancelarReembolso')->assertDontSeeHtml('dusk="reembolso-painel"');
        expect($i->fresh()->status)->toBe(EstadoCobranca::Paga);
    });
});

describe('destaques', function () {
    it('contestada e pagamento a devolver aparecem em destaque', function () {
        $c = ($this->paga)();
        _entregar(_fake()->simularContestacao((string) $c->id_externo));
        $d = ($this->paga)();
        $d->forceFill(['devolucao_motivo' => 'assinatura_cancelada'])->save();

        ($this->tela)()
            ->assertSeeHtml('dusk="contestada-'.$c->id.'"')->assertSee('Contestação aberta')
            ->assertSeeHtml('dusk="devolver-'.$d->id.'"')->assertSee('Pago depois do cancelamento da assinatura')
            ->assertSeeHtml('dusk="cobranca-devolver-'.$d->id.'"')
            ->call('abrirReembolso', $d->id)->assertSee('só o dinheiro é devolvido');
    });
});

describe('CA-6 e CA-7 nota fiscal', function () {
    it('lista as pagas sem nota, marca como emitida e some da lista; audita', function () {
        $i = ($this->paga)();
        $pendente = app(IniciarCompraDeCreditos::class)->executar($this->cliente, _pacote(), null, null);

        $tela = ($this->tela)()->assertSeeHtml('dusk="sem-nota-'.$i->id.'"')->assertDontSeeHtml('dusk="sem-nota-'.$pendente->id.'"');
        $tela->call('abrirNota', $i->id)->set('notaNumero', str_repeat('9', 41))->call('confirmarNota')->assertHasErrors(['notaNumero']);
        $tela->set('notaNumero', '2026/77')->call('confirmarNota')->assertHasNoErrors()->assertSee('Nota fiscal marcada como emitida.')
            ->assertDontSeeHtml('dusk="sem-nota-'.$i->id.'"')->assertSee('nº 2026/77');

        expect($i->fresh()->nf_numero)->toBe('2026/77')
            ->and(AuditLog::query()->where('action', 'cobranca.nota_fiscal_emitida')->sole()->actor_id)->toBe($this->admin->id);
    });

    it('marcar de novo mostra o erro; cobrança estornada não pede nota', function () {
        $i = ($this->paga)();
        $tela = ($this->tela)()->call('abrirNota', $i->id)->call('confirmarNota');
        $tela->call('abrirNota', $i->id)->call('confirmarNota')->assertSee('já foi marcada');

        $j = ($this->paga)();
        $tela->call('abrirReembolso', $j->id)->set('motivo', 'Arrependimento')->call('confirmarReembolso')
            ->assertDontSeeHtml('dusk="sem-nota-'.$j->id.'"');
    });

    it('cancelar o painel de nota o fecha', function () {
        $i = ($this->paga)();
        ($this->tela)()->call('abrirNota', $i->id)->assertSeeHtml('dusk="nota-painel"')->call('cancelarNota')->assertDontSeeHtml('dusk="nota-painel"');
    });
});

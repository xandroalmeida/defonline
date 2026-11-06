<?php

declare(strict_types=1);

use App\Domain\Cobranca\EstadoCobranca;
use App\Domain\Cobranca\MeioPagamento;
use App\Domain\Cobranca\ProvedorIndisponivel;
use App\Domain\Cobranca\SituacaoDoWebhook;
use App\Infrastructure\Cobranca\Asaas\AsaasPaymentProvider;
use App\Infrastructure\Cobranca\Fake\FakePaymentProvider;
use App\Livewire\Cobranca\AcompanharPagamento;
use App\Models\CobrancaConfig;
use App\Models\Invoice;
use App\Models\LoteCredito;
use App\Observabilidade\MonitorDoAgendador;
use App\Services\Cobranca\ConferirPagamentoAtrasado;
use App\Services\Cobranca\ConferirWebhook;
use App\Services\Cobranca\IniciarCompraDeCreditos;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

/** STORY-101 — pagamento feito com o webhook fora não fica esperando, e a equipe fica sabendo. */
beforeEach(function () {
    _usarProvedor('fake');
    Mail::fake();
    $this->u = _compradorConfirmado();
});

function _compraPendente($usuario): Invoice
{
    return app(IniciarCompraDeCreditos::class)->executar($usuario, _pacote(), '127.0.0.1', 'teste', MeioPagamento::Pix, null);
}

function _pagarNoProvedorSemWebhook(Invoice $invoice): void
{
    _fake()->simularPagamento((string) $invoice->checkout_id_externo);
}

describe('CA-1 e CA-2 — conferência na tela de pagamento', function () {
    it('depois de 1 minuto, encontra o pagamento, libera o crédito uma vez e alerta a equipe', function () {
        $invoice = _compraPendente($this->u);
        _pagarNoProvedorSemWebhook($invoice);
        $this->travel(61)->seconds();
        Log::spy();

        Livewire::actingAs($this->u)->test(AcompanharPagamento::class, ['invoice' => $invoice->id])
            ->call('conferir')
            ->assertSee('Pagamento confirmado');

        expect($invoice->fresh()->status)->toBe(EstadoCobranca::Paga)
            ->and(LoteCredito::query()->where('invoice_id', $invoice->id)->count())->toBe(1);
        Log::shouldHaveReceived('error')->withArgs(fn ($m) => $m === 'cobranca.webhook_nao_chegou')->once();

        $this->travel(31)->seconds();
        app(ConferirPagamentoAtrasado::class)->executar($invoice->fresh());
        expect(LoteCredito::query()->where('invoice_id', $invoice->id)->count())->toBe(1);
    });

    it('antes de 1 minuto não pergunta ao provedor', function () {
        $invoice = _compraPendente($this->u);
        _pagarNoProvedorSemWebhook($invoice);
        $this->travel(30)->seconds();

        app(ConferirPagamentoAtrasado::class)->executar($invoice);

        expect($invoice->fresh()->status)->toBe(EstadoCobranca::Pendente);
    });

    it('pergunta no máximo a cada 30 segundos', function () {
        $invoice = _compraPendente($this->u);
        $this->travel(61)->seconds();
        app(ConferirPagamentoAtrasado::class)->executar($invoice);
        _pagarNoProvedorSemWebhook($invoice);

        app(ConferirPagamentoAtrasado::class)->executar($invoice);
        expect($invoice->fresh()->status)->toBe(EstadoCobranca::Pendente);

        $this->travel(31)->seconds();
        app(ConferirPagamentoAtrasado::class)->executar($invoice);
        expect($invoice->fresh()->status)->toBe(EstadoCobranca::Paga);
    });

    it('sem pagamento no provedor, continua pendente e sem alerta', function () {
        $invoice = _compraPendente($this->u);
        $this->travel(61)->seconds();
        Log::spy();

        app(ConferirPagamentoAtrasado::class)->executar($invoice);

        expect($invoice->fresh()->status)->toBe(EstadoCobranca::Pendente);
        Log::shouldNotHaveReceived('error');
    });

    it('com o provedor fora, a tela continua esperando', function () {
        $invoice = _compraPendente($this->u);
        $this->travel(61)->seconds();
        FakePaymentProvider::ficarIndisponivel();

        app(ConferirPagamentoAtrasado::class)->executar($invoice);
        FakePaymentProvider::ficarIndisponivel(false);

        expect($invoice->fresh()->status)->toBe(EstadoCobranca::Pendente);
    });

    it('a tela chama a conferência no polling', function () {
        $invoice = _compraPendente($this->u);

        Livewire::actingAs($this->u)->test(AcompanharPagamento::class, ['invoice' => $invoice->id])
            ->assertSeeHtml('wire:poll.3s="conferir"');
    });
});

describe('CA-3 — fila do webhook', function () {
    it('fila pausada: alerta P1 e retoma', function () {
        FakePaymentProvider::simularWebhook(new SituacaoDoWebhook('wh', true, true, false));
        Log::spy();

        expect(app(ConferirWebhook::class)->executar())->toBe(ConferirWebhook::RETOMADO)
            ->and(FakePaymentProvider::webhookRetomado())->toBeTrue();
        Log::shouldHaveReceived('error')->withArgs(fn ($m, $c) => $m === 'cobranca.webhook_fila_pausada' && $c['prioridade'] === 'P1')->once();
    });

    it('webhook desligado: alerta, sem religar', function () {
        FakePaymentProvider::simularWebhook(new SituacaoDoWebhook('wh', false, true, false));
        Log::spy();

        expect(app(ConferirWebhook::class)->executar())->toBe(ConferirWebhook::FALHOU)
            ->and(FakePaymentProvider::webhookRetomado())->toBeFalse();
        Log::shouldHaveReceived('error')->withArgs(fn ($m) => $m === 'cobranca.webhook_desligado')->once();
    });

    it('fila normal: nada acontece', function () {
        Log::spy();

        expect(app(ConferirWebhook::class)->executar())->toBe(ConferirWebhook::OK);
        Log::shouldNotHaveReceived('error');
    });

    it('sem cadastro: alerta só em produção', function () {
        FakePaymentProvider::simularWebhook(null);
        Log::spy();

        expect(app(ConferirWebhook::class)->executar())->toBe(ConferirWebhook::OK);
        Log::shouldNotHaveReceived('error');

        app()->detectEnvironment(fn () => 'production');
        expect(app(ConferirWebhook::class)->executar())->toBe(ConferirWebhook::FALHOU);
        Log::shouldHaveReceived('error')->withArgs(fn ($m) => $m === 'cobranca.webhook_nao_cadastrado')->once();
        app()->detectEnvironment(fn () => 'testing');
    });

    it('com a cobrança desligada não consulta o provedor', function () {
        CobrancaConfig::atual()->update(['ativa' => false]);
        FakePaymentProvider::simularWebhook(new SituacaoDoWebhook('wh', true, true, false));

        expect(app(ConferirWebhook::class)->executar())->toBe(ConferirWebhook::OK)
            ->and(FakePaymentProvider::webhookRetomado())->toBeFalse();
    });

    it('o Asaas só toca o cadastro com a nossa URL e retoma só a fila', function () {
        Http::fake([
            '*/webhooks?*' => Http::response(['hasMore' => false, 'data' => [
                ['id' => 'de-outro', 'url' => 'https://outro/webhook', 'enabled' => true, 'interrupted' => true],
                ['id' => 'nosso', 'url' => 'https://app/webhooks/pagamentos', 'enabled' => true, 'interrupted' => true, 'penalizedAt' => null],
            ]]),
            '*/webhooks/nosso' => Http::response(['id' => 'nosso']),
        ]);
        $asaas = new AsaasPaymentProvider('https://api-sandbox.asaas.com/v3', '$aact_hmlg_teste', str_repeat('t', 32));

        $s = $asaas->situacaoDoWebhook('https://app/webhooks/pagamentos');
        $asaas->retomarWebhook($s->id);

        expect($s->id)->toBe('nosso')->and($s->filaPausada)->toBeTrue()
            ->and($asaas->situacaoDoWebhook('https://nao-existe/x'))->toBeNull();
        Http::assertSent(fn ($r) => $r->method() === 'PUT' && str_ends_with($r->url(), '/webhooks/nosso') && $r->data() === ['interrupted' => false]);
        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'de-outro'));
    });

    it('recusa do Asaas ao retomar vira alerta', function () {
        FakePaymentProvider::simularWebhook(new SituacaoDoWebhook('wh', true, true, false));
        $provedor = _provedorQueFalha(['retomarWebhook' => fn () => new ProvedorIndisponivel('url inválida')]);
        Log::spy();

        expect((new ConferirWebhook($provedor))->executar())->toBe(ConferirWebhook::FALHOU);
        Log::shouldHaveReceived('error')->withArgs(fn ($m) => $m === 'cobranca.webhook_fila_nao_retomada')->once();
    });
});

describe('CA-4 — alerta da conciliação', function () {
    it('quando corrige um pagamento, alerta a equipe', function () {
        $invoice = _compraPendente($this->u);
        _pagarNoProvedorSemWebhook($invoice);
        $this->travel(16)->minutes();
        Log::spy();

        Artisan::call('cobranca:conciliar', ['--modo' => 'horaria']);

        expect($invoice->fresh()->status)->toBe(EstadoCobranca::Paga);
        Log::shouldHaveReceived('error')->withArgs(fn ($m) => $m === 'cobranca.conciliacao_corrigiu')->once();
    });

    it('sem nada a corrigir, não alerta', function () {
        Log::spy();

        Artisan::call('cobranca:conciliar', ['--modo' => 'horaria']);

        Log::shouldNotHaveReceived('error');
    });
});

describe('CA-5 — agendador parado', function () {
    it('alerta depois de 10 minutos sem batimento, e o batimento normaliza', function () {
        Artisan::call('agendador:batimento');
        Log::spy();

        $this->travel(9)->minutes();
        app(MonitorDoAgendador::class)->conferir();
        Log::shouldNotHaveReceived('error');

        $this->travel(2)->minutes();
        app(MonitorDoAgendador::class)->conferir();
        Log::shouldHaveReceived('error')->withArgs(fn ($m, $c) => $m === 'cobranca.agendador_parado' && $c['prioridade'] === 'P1')->once();

        Artisan::call('agendador:batimento');
        app(MonitorDoAgendador::class)->conferir();
        Log::shouldHaveReceived('error')->once();
    });

    it('sem batimento ainda, começa a contar, sem alerta imediato', function () {
        Log::spy();

        app(MonitorDoAgendador::class)->conferir();

        Log::shouldNotHaveReceived('error');
    });

    it('as rotinas estão agendadas e o /health segue respondendo', function () {
        Artisan::call('schedule:list');
        expect(Artisan::output())->toContain('agendador:batimento')->toContain('cobranca:conferir-webhook');

        $this->get('/health')->assertOk()->assertJson(['status' => 'ok']);
    });
});

<?php

declare(strict_types=1);

use App\Domain\Cobranca\Carteira;
use App\Domain\Cobranca\Trial;
use App\Livewire\Home\MinhasEmpresas;
use App\Models\CobrancaConfig;
use App\Models\TrialConfig;
use App\Models\Usuario;
use App\Observabilidade\AlertaDaEquipe;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

/**
 * STORY-074 — a Home oferece o teste a quem pode ativá-lo e avisa durante e depois dele
 * (spec STORY-070-trial §2 e §3). Com a cobrança desligada, nada disso existe (CA-9).
 */
beforeEach(function () {
    app()->instance(AlertaDaEquipe::class, Mockery::mock(AlertaDaEquipe::class)->shouldIgnoreMissing());
    CobrancaConfig::atual()->update(['ativa' => true]);
    $this->usuario = Usuario::factory()->create();
    $this->home = fn () => Livewire::actingAs($this->usuario)->test(MinhasEmpresas::class);
});

it('oferece o teste a quem ainda pode ativá-lo, com os valores da configuração', function () {
    TrialConfig::atual()->update(['dias' => 5, 'analises' => 2]);

    ($this->home)()->assertSee('5 dias, 2 análises e o Raio X do Negócio. Sem cartão.')
        ->assertSee('Começar teste grátis')->assertSee(route('trial.iniciar'), false);
});

it('não oferece com o trial desativado nem a quem já usou', function () {
    TrialConfig::atual()->update(['ativo' => false]);
    ($this->home)()->assertDontSee('Começar teste grátis');

    TrialConfig::atual()->update(['ativo' => true]);
    app(Trial::class)->ativar($this->usuario->id, null);
    ($this->home)()->assertDontSee('Começar teste grátis');
});

it('mostra o aviso do teste em andamento, no lugar da oferta', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-05 15:00:00', 'UTC'));
    app(Trial::class)->ativar($this->usuario->id, null);

    ($this->home)()->assertSee('Teste grátis: termina em 12/11/2026 · 1 análise restante.')->assertSee('Ver planos')->assertDontSee('Começar teste grátis');
});

it('avisa que o teste terminou depois do prazo, e os diagnósticos seguem', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-05 15:00:00', 'UTC'));
    app(Trial::class)->ativar($this->usuario->id, null);
    $this->travelTo(CarbonImmutable::parse('2026-11-14 15:00:00', 'UTC'));

    ($this->home)()->assertSee('Seu teste terminou em 12/11/2026. Seus diagnósticos continuam disponíveis.')->assertSee('Ver planos');
});

it('não avisa o fim do teste a quem tem crédito válido', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-05 15:00:00', 'UTC'));
    app(Trial::class)->ativar($this->usuario->id, null);
    app(Carteira::class)->concederCreditos($this->usuario->id, 1, CarbonImmutable::parse('2027-01-01', 'UTC'));
    $this->travelTo(CarbonImmutable::parse('2026-11-14 15:00:00', 'UTC'));

    ($this->home)()->assertDontSee('Seu teste terminou');
});

it('com a cobrança desligada não mostra oferta nem aviso (CA-9)', function () {
    CobrancaConfig::atual()->update(['ativa' => false]);
    ($this->home)()->assertDontSee('Teste grátis')->assertDontSee('Começar teste grátis');

    CobrancaConfig::atual()->update(['ativa' => true]);
    app(Trial::class)->ativar($this->usuario->id, null);
    CobrancaConfig::atual()->update(['ativa' => false]);
    ($this->home)()->assertDontSee('Teste grátis');
});

<?php

declare(strict_types=1);

use App\Domain\Cobranca\Carteira;
use App\Domain\Cobranca\MotivoBloqueio;
use App\Domain\Cobranca\StatusAssinatura;
use App\Livewire\Drm\Lancamento;
use App\Models\Assinatura;
use App\Models\CobrancaConfig;
use App\Models\DrmLancamento;
use App\Models\DrmRascunho;
use App\Models\EmpresaAnalisada;
use App\Models\TrialConfig;
use App\Models\Usuario;
use App\Support\Cobranca\AcessoDrm;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

/**
 * STORY-084 — o Raio X do Negócio exige plano ativo ou trial para lançar e retificar; ver o que já
 * foi lançado nunca depende disso. Regra num ponto só: {@see AcessoDrm}.
 */
beforeEach(function () {
    TrialConfig::query()->firstOrCreate([], ['ativo' => true, 'dias' => 7, 'analises' => 1]);
    $this->usuario = Usuario::factory()->create();
    $this->actingAs($this->usuario);
    $this->empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $this->usuario->id, 'cnae' => '4711301']);
});

function _ligarCobrancaDrm(bool $ligada = true): void
{
    CobrancaConfig::atual()->update(['ativa' => $ligada]);
}

function _assinar(Usuario $u, bool $trial = false, ?StatusAssinatura $status = null, string $vence = '+20 days'): void
{
    $a = app(Carteira::class)->concederCota($u->id, 3, now()->subDays(5), now()->modify($vence), trial: $trial);
    if ($status !== null) {
        app(Carteira::class)->mudarStatus($a->id, $status);
    }
}

function _rotas(EmpresaAnalisada $e): array
{
    return [route('drm.lancar', $e), route('drm.corrigir', [$e, '2026-09'])];
}

it('com o interruptor desligado, nada muda para quem não tem nada', function () {
    expect(AcessoDrm::bloqueioPara($this->usuario->id))->toBeNull();

    DrmLancamento::factory()->create(['empresa_analisada_id' => $this->empresa->id, 'competencia' => '2026-09-01']);
    foreach (_rotas($this->empresa) as $url) {
        $this->get($url)->assertOk()->assertDontSee('screen-bloqueio-raiox')->assertDontSee('faz parte dos planos');
    }
});

describe('CA-1 e CA-5: quem tem acesso', function () {
    it('libera plano ativo, trial vigente e plano cancelado dentro do ciclo, mesmo com a cota zerada', function (bool $trial, ?StatusAssinatura $status) {
        _ligarCobrancaDrm();
        _assinar($this->usuario, $trial, $status);
        $a = Assinatura::query()->where('usuario_id', $this->usuario->id)->first();
        $a->forceFill(['cota_restante_ciclo' => 0])->save();

        expect(AcessoDrm::permitido($this->usuario->id))->toBeTrue();
        $this->get(route('drm.lancar', $this->empresa))->assertOk()->assertDontSee('faz parte dos planos');
    })->with([
        'plano ativo' => [false, null],
        'trial' => [true, null],
        'plano cancelado, ciclo vigente' => [false, StatusAssinatura::Cancelada],
    ]);

    it('lançar não consome cota', function () {
        _ligarCobrancaDrm();
        _assinar($this->usuario);
        $this->get(route('drm.lancar', $this->empresa))->assertOk();

        expect(Assinatura::query()->first()->cota_restante_ciclo)->toBe(3);
    });
});

describe('CA-2: quem não tem acesso', function () {
    it('bloqueia lançar e retificar sem nada, e com só créditos', function (bool $creditos) {
        _ligarCobrancaDrm();
        if ($creditos) {
            app(Carteira::class)->concederCreditos($this->usuario->id, 5, now()->addYear());
        }
        DrmLancamento::factory()->create(['empresa_analisada_id' => $this->empresa->id, 'competencia' => '2026-09-01']);

        foreach (_rotas($this->empresa) as $url) {
            $this->get($url)->assertOk()
                ->assertSee('O Raio X do Negócio faz parte dos planos')
                ->assertSee('Os créditos avulsos não incluem o Raio X do Negócio')
                ->assertSee('screen-bloqueio-raiox', false)
                ->assertDontSee('Comprar créditos')
                ->assertDontSee('análises');
        }
        expect(AcessoDrm::bloqueioPara($this->usuario->id)?->motivo)->toBe(MotivoBloqueio::SemNada);
    })->with(['nada' => [false], 'só créditos' => [true]]);

    it('oferece o teste grátis quando elegível e o esconde quando já foi usado', function () {
        _ligarCobrancaDrm();
        $b = AcessoDrm::bloqueioPara($this->usuario->id);
        expect($b->trialElegivel)->toBeTrue();
        $this->get(route('drm.lancar', $this->empresa))->assertSee('ou comece o teste');

        _assinar($this->usuario, trial: true, vence: '-1 day');
        $this->get(route('drm.lancar', $this->empresa))->assertSee('Seu teste terminou em')->assertDontSee('ou comece o teste');
    });

    it('pausa o lançamento em atraso, plano encerrado e teste encerrado', function (bool $trial, ?StatusAssinatura $status, string $vence, MotivoBloqueio $motivo, string $texto) {
        _ligarCobrancaDrm();
        _assinar($this->usuario, $trial, $status, $vence);

        $this->get(route('drm.lancar', $this->empresa))->assertOk()
            ->assertSee('O lançamento do Raio X está pausado')->assertSee($texto)
            ->assertSee('Os meses já lançados continuam visíveis.');
        expect(AcessoDrm::bloqueioPara($this->usuario->id)->motivo)->toBe($motivo);
    })->with([
        'em atraso' => [false, StatusAssinatura::EmAtraso, '+5 days', MotivoBloqueio::EmAtraso, 'Não conseguimos cobrar'],
        'plano encerrado' => [false, null, '-1 day', MotivoBloqueio::PlanoEncerrado, 'Seu plano terminou em'],
        'cancelado e vencido' => [false, StatusAssinatura::Cancelada, '-1 day', MotivoBloqueio::PlanoEncerrado, 'Seu plano terminou em'],
        'teste encerrado' => [true, null, '-1 day', MotivoBloqueio::TesteEncerrado, 'Seu teste terminou em'],
    ]);

    it('o servidor recusa fechar mesmo com a página aberta de antes', function () {
        $c = Livewire::test(Lancamento::class, ['empresa' => $this->empresa]);
        _ligarCobrancaDrm();

        $c->call('fechar')->assertRedirect();
        expect(DrmLancamento::query()->count())->toBe(0);
    });

    it('com acesso, fechar segue o fluxo (sem redirecionar por falta de plano)', function () {
        _ligarCobrancaDrm();
        _assinar($this->usuario);
        Livewire::test(Lancamento::class, ['empresa' => $this->empresa])->call('fechar')->assertNoRedirect();
    });
});

describe('CA-3, CA-4 e CA-6: histórico e rascunho', function () {
    it('a demonstração continua visível e o bloqueio leva ao último mês', function () {
        _ligarCobrancaDrm();
        DrmLancamento::factory()->create(['empresa_analisada_id' => $this->empresa->id, 'competencia' => '2026-09-01']);

        $this->get(route('drm.mes', [$this->empresa, '2026-09']))->assertOk()->assertDontSee('faz parte dos planos');
        $this->get(route('drm.lancar', $this->empresa))->assertSee(route('drm.mes', [$this->empresa, '2026-09']), false);
    });

    it('sem lançamentos, não mostra link de meses', function () {
        _ligarCobrancaDrm();
        $this->get(route('drm.lancar', $this->empresa))->assertDontSee('screen-bloqueio-raiox-ver-meses', false);
    });

    it('o rascunho sobrevive ao bloqueio, é anunciado e fica disponível ao reassinar', function () {
        CarbonImmutable::setTestNow('2026-11-05 10:00:00');
        $rascunho = DrmRascunho::query()->create([
            'usuario_id' => $this->usuario->id, 'empresa_analisada_id' => $this->empresa->id, 'competencia' => '2026-10-01',
            'origem' => 'inicial', 'rascunho_payload' => ['valores' => [], 'bloco' => 2], 'expires_at' => now()->addDays(30),
        ]);
        _ligarCobrancaDrm();

        $this->get(route('drm.lancar', $this->empresa))->assertSee('Suas respostas estão salvas');
        expect(DrmRascunho::query()->find($rascunho->id))->not->toBeNull();

        _assinar($this->usuario);
        $this->get(route('drm.lancar', $this->empresa))->assertOk()->assertDontSee('faz parte dos planos');
        expect(DrmRascunho::query()->find($rascunho->id))->not->toBeNull();
        CarbonImmutable::setTestNow();
    });

    it('quem assina de novo volta a lançar com o histórico intacto', function () {
        _ligarCobrancaDrm();
        $l = DrmLancamento::factory()->create(['empresa_analisada_id' => $this->empresa->id, 'competencia' => '2026-09-01']);
        $this->get(route('drm.lancar', $this->empresa))->assertSee('faz parte dos planos');

        _assinar($this->usuario);
        $this->get(route('drm.lancar', $this->empresa))->assertDontSee('faz parte dos planos');
        expect($l->fresh()->versao)->toBe(1)->and(DrmLancamento::query()->count())->toBe(1);
    });

    it('empresa de outra conta continua 404, com ou sem plano', function () {
        _ligarCobrancaDrm();
        $alheia = EmpresaAnalisada::factory()->create(['usuario_id' => Usuario::factory()->create()->id]);
        $this->get(route('drm.lancar', $alheia))->assertNotFound();
    });
});

<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Domain\Cobranca\Carteira;
use App\Models\CobrancaConfig;
use App\Models\DrmLancamento;
use App\Models\EmpresaAnalisada;
use App\Models\TrialConfig;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * STORY-084 — sem plano nem trial, lançar e retificar mostram o bloqueio do Raio X (spec
 * STORY-070, variante), a demonstração continua aberta e, ao assinar, o formulário volta.
 */
final class AcessoDrmBrowserTest extends DuskTestCase
{
    use DatabaseTruncation;

    /** @var list<string> */
    protected array $tablesToTruncate = [
        'drm_lancamentos', 'drm_rascunhos', 'drm_preferencias', 'assinaturas', 'lotes_credito',
        'empresas_analisadas', 'usuarios', 'audit_logs', 'evento_produto', 'sessions', 'cache',
    ];

    protected function afterTruncatingDatabase(): void
    {
        TrialConfig::query()->firstOrCreate([], ['ativo' => true, 'dias' => 7, 'analises' => 1]);
        CobrancaConfig::atual();
    }

    protected function tearDown(): void
    {
        CobrancaConfig::atual()->update(['ativa' => false]);

        parent::tearDown();
    }

    public function test_sem_plano_bloqueia_lancar_e_corrigir_mas_a_demonstracao_abre_e_ao_assinar_o_formulario_volta(): void
    {
        $usuario = Usuario::factory()->create();
        $empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $usuario->id, 'cnae' => '4711301']);
        DrmLancamento::factory()->create(['empresa_analisada_id' => $empresa->id, 'competencia' => '2026-09-01']);
        CobrancaConfig::atual()->update(['ativa' => true]);

        $this->browse(function (Browser $browser) use ($usuario, $empresa) {
            $browser->resize(360, 800)->loginAs($usuario)
                ->visit('/empresas/'.$empresa->id.'/drm/lancar')
                ->waitFor('@screen-bloqueio-raiox')
                ->assertSeeIn('@screen-bloqueio-raiox-titulo', 'O Raio X do Negócio faz parte dos planos')
                ->assertSeeIn('@screen-bloqueio-raiox-causa', 'Os créditos avulsos não incluem')
                ->assertPresent('@screen-bloqueio-raiox-acao-primaria')
                ->visit('/empresas/'.$empresa->id.'/drm/2026-09/corrigir')
                ->waitFor('@screen-bloqueio-raiox')
                ->click('@screen-bloqueio-raiox-ver-meses')
                ->waitForLocation('/empresas/'.$empresa->id.'/drm/2026-09')
                ->assertMissing('@screen-bloqueio-raiox');

            app(Carteira::class)->concederCota($usuario->id, 1, now()->subDay(), now()->addDays(7), trial: true);

            $browser->visit('/empresas/'.$empresa->id.'/drm/lancar')
                ->waitFor('@drm-lancamento-bloco-1', 10)
                ->assertMissing('@screen-bloqueio-raiox');
        });
    }
}

<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Models\DrmLancamento;
use App\Models\EmpresaAnalisada;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * STORY-054 — a leitura em linguagem simples num browser real, no estado em que o módulo vai ao ar:
 * todas as faixas `nao_calibrada` (CA-1b). Sem farol, sem espaço vazio, sem scroll horizontal.
 * Farol calibrado é coberto no Pest (a config do servidor do Dusk não muda em runtime).
 */
final class DrmFaroisLeituraBrowserTest extends DuskTestCase
{
    use DatabaseTruncation;

    /** @var list<string> */
    protected array $tablesToTruncate = [
        'drm_lancamentos', 'drm_rascunhos', 'drm_preferencias', 'empresas_analisadas', 'usuarios',
        'audit_logs', 'business_metrics', 'term_acceptances', 'sessions', 'evento_produto', 'cache',
    ];

    public function test_grau_zero_mostra_a_leitura_sem_farol_em_360x800(): void
    {
        $usuario = Usuario::factory()->create();
        $empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $usuario->id, 'nome_fantasia' => 'Padaria do Bairro']);
        $golden = json_decode((string) file_get_contents(base_path('tests/Fixtures/Drm/golden/saudavel_comercio.json')), true, 512, JSON_THROW_ON_ERROR);
        foreach (['2026-08-01', '2026-09-01'] as $competencia) {
            DrmLancamento::factory()->create([
                'empresa_analisada_id' => $empresa->id, 'competencia' => $competencia,
                'setor' => $golden['entrada']['setor'], 'lancamento_payload' => $golden['entrada']['payload'],
            ]);
        }

        $this->browse(function (Browser $browser) use ($usuario, $empresa) {
            $browser->resize(360, 800)->loginAs($usuario)
                ->visit(route('drm.mes', [$empresa, '2026-09']))
                ->waitFor('@drm-leitura')
                ->assertSeeIn('@drm-leitura', 'O que este mês está dizendo')
                ->assertSeeIn('@drm-leitura-texto', 'Em setembro de 2026')
                ->assertSeeIn('@drm-leitura-texto', 'O resultado do mês ficou igual ao de agosto.')
                ->assertSeeIn('@drm-leitura-rodape', 'interpretação')
                ->assertSeeIn('@drm-leitura-rodape', 'não mudam')
                ->assertMissing('[data-farol]');

            $larguras = $browser->script('return [document.documentElement.scrollWidth, window.innerWidth]')[0];
            $this->assertLessThanOrEqual($larguras[1], $larguras[0], 'scroll horizontal');
        });
    }
}

<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Models\DrmLancamento;
use App\Models\DrmPreferencia;
use App\Models\EmpresaAnalisada;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Dusk\Browser;
use Tests\Browser\Concerns\VerificaAlinhamento;
use Tests\DuskTestCase;

/**
 * STORY-049 CA-9, CA-10 e CA-12 — a Demonstração do Mês num browser real.
 *
 * Prova o que o Pest não alcança: sem scroll horizontal em 360×800, cards no mobile e tabela no
 * desktop (só um dos dois visível), os três números na primeira dobra, a troca de modo por
 * teclado, e prejuízo sem cor de erro.
 */
final class DrmDemonstracaoBrowserTest extends DuskTestCase
{
    use DatabaseTruncation, VerificaAlinhamento;

    /** @var list<string> */
    protected array $tablesToTruncate = [
        'drm_lancamentos', 'drm_rascunhos', 'drm_preferencias', 'empresas_analisadas', 'usuarios',
        'audit_logs', 'business_metrics', 'term_acceptances', 'sessions', 'evento_produto', 'cache',
    ];

    private function mes(Usuario $usuario, string $cenario, array $extra = []): EmpresaAnalisada
    {
        $empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $usuario->id, 'nome_fantasia' => 'Padaria do Bairro']);
        $golden = json_decode((string) file_get_contents(base_path("tests/Fixtures/Drm/golden/{$cenario}.json")), true, 512, JSON_THROW_ON_ERROR);
        DrmLancamento::factory()->create(array_merge([
            'empresa_analisada_id' => $empresa->id,
            'competencia' => '2026-09-01',
            'setor' => $golden['entrada']['setor'],
            'lancamento_payload' => $golden['entrada']['payload'],
        ], $extra));

        return $empresa;
    }

    private function semScrollHorizontal(Browser $browser): void
    {
        $larguras = $browser->script('return [document.documentElement.scrollWidth, window.innerWidth]')[0];
        $this->assertLessThanOrEqual($larguras[1], $larguras[0], 'scroll horizontal');
    }

    public function test_mobile_mostra_cards_por_trecho_sem_scroll_horizontal_e_os_tres_numeros_na_primeira_dobra(): void
    {
        $usuario = Usuario::factory()->create();
        $empresa = $this->mes($usuario, 'saudavel_comercio');

        $this->browse(function (Browser $browser) use ($usuario, $empresa) {
            $browser->resize(360, 800)->loginAs($usuario)
                ->visit(route('drm.mes', [$empresa, '2026-09']))
                ->waitFor('@drm-demonstracao')
                ->assertSee('Setembro de 2026')
                ->assertVisible('@drm-cards')
                ->assertMissing('@drm-tabela');
            $this->semScrollHorizontal($browser);

            // Os três números cabem sem rolar: o último termina antes do pé da viewport.
            $fundo = $browser->script("return document.querySelector('[dusk=\"drm-destaque-L11\"]').getBoundingClientRect().bottom")[0];
            $this->assertLessThanOrEqual(800, $fundo, 'os três destaques passam da primeira dobra');

            $browser->assertSeeIn('@drm-destaque-L1', 'R$ 100.000,00')
                ->assertSeeIn('@drm-destaque-L9', 'R$ 34.349,25')
                ->assertSeeIn('@drm-destaque-L11', 'R$ 26.349,25');
        });
    }

    public function test_desktop_mostra_a_tabela_com_cabecalhos_associados_e_nao_os_cards(): void
    {
        $usuario = Usuario::factory()->create();
        $empresa = $this->mes($usuario, 'saudavel_comercio');

        $this->browse(function (Browser $browser) use ($usuario, $empresa) {
            $browser->resize(1280, 900)->loginAs($usuario)
                ->visit(route('drm.mes', [$empresa, '2026-09']))
                ->waitFor('@drm-demonstracao');
            // RNF-UI-02: os três números lado a lado com o mesmo topo e a mesma base.
            $this->assertAlinhados($browser, [
                ['drm-destaque-L1', 'drm-destaque-L9'],
                ['drm-destaque-L9', 'drm-destaque-L11'],
            ]);
            $browser
                ->assertVisible('@drm-tabela')
                ->assertMissing('@drm-cards');
            $this->semScrollHorizontal($browser);

            $semantica = $browser->script(<<<'JS'
                const t = document.querySelector('[dusk="drm-tabela"] table');
                return [
                    [...t.querySelectorAll('th[scope="col"]')].map(th => th.textContent.trim().replace(/\s+/g, ' ')).join('|'),
                    t.querySelectorAll('tbody th[scope="row"]').length,
                    t.querySelector('caption') !== null,
                    t.querySelector('[dusk="drm-linha-L3"] th').textContent.trim().startsWith('='),
                ];
            JS)[0];
            $this->assertSame(['Linha|Valor em reais|% da receita', 16, true, true], $semantica);
        });
    }

    public function test_troca_de_modo_por_teclado_reexibe_a_demonstracao_e_vale_para_a_serie(): void
    {
        $usuario = Usuario::factory()->create();
        $empresa = $this->mes($usuario, 'saudavel_comercio');

        $this->browse(function (Browser $browser) use ($usuario, $empresa) {
            $browser->resize(360, 800)->loginAs($usuario)
                ->visit(route('drm.mes', [$empresa, '2026-09']))
                ->waitFor('@drm-trocar-modo')
                ->assertSeeIn('@drm-modo-frase', 'fora do resultado operacional')
                ->assertMissing('@drm-cartao-linha-L6c');

            // Teclado: o foco chega ao botão e Enter aciona (é <button>, não link).
            $browser->script("document.querySelector('[dusk=\"drm-trocar-modo\"]').focus()");
            $browser->keys('@drm-trocar-modo', '{enter}')
                ->waitUntilMissing('@drm-destaque-L9')
                ->assertSeeIn('@drm-modo-frase', 'dentro das despesas operacionais')
                ->assertPresent('@drm-cartao-linha-L6c')
                ->assertSeeIn('@drm-destaque-L7', 'R$ 27.199,25')
                // O Lucro Líquido é o mesmo nos dois modos.
                ->assertSeeIn('@drm-destaque-L11', 'R$ 26.349,25');
            $this->semScrollHorizontal($browser);

            // A preferência foi gravada e a tela volta a abrir assim.
            $this->assertSame('dentro', DrmPreferencia::query()->where('empresa_analisada_id', $empresa->id)->value('posicao_prolabore'));
            $browser->refresh()->waitFor('@drm-modo-frase')->assertSeeIn('@drm-modo-frase', 'dentro das despesas operacionais');
        });
    }

    public function test_prejuizo_e_sinalizado_sem_cor_de_erro_e_a_ressalva_aceita_aparece(): void
    {
        $usuario = Usuario::factory()->create();
        $empresa = $this->mes($usuario, 'prejuizo_comercio_agregado', ['alertas_aceitos' => [
            ['regra' => 'A3', 'ocorrido_em' => '2026-11-05T09:30:00+00:00', 'valor_envolvido' => '3240.00'],
        ]]);

        $this->browse(function (Browser $browser) use ($usuario, $empresa) {
            $browser->resize(360, 800)->loginAs($usuario)
                ->visit(route('drm.mes', [$empresa, '2026-09']))
                ->waitFor('@drm-destaque-L11')
                ->assertSeeIn('@drm-destaque-L11', 'FALTOU DEPOIS DA SUA RETIRADA')
                ->assertSeeIn('@drm-destaque-L11', '−R$ ')
                ->assertPresent('@drm-pill-prejuizo');

            // Valor negativo na mesma cor do positivo (--color-primary): prejuízo é neutro.
            $cores = $browser->script(<<<'JS'
                const cor = (s) => getComputedStyle(document.querySelector(s)).color;
                return [cor('[dusk="drm-destaque-L11"] [dusk="drm-destaque-valor"]'), cor('[dusk="drm-destaque-L1"] [dusk="drm-destaque-valor"]')];
            JS)[0];
            $this->assertSame($cores[1], $cores[0]);

            // Ressalva: recolhida, abre com um toque.
            $browser->assertSee('Ressalvas registradas no lançamento (1)')
                ->assertDontSeeIn('@drm-ressalvas', 'prejuízo de R$ 3.240,00')
                ->click('@drm-ressalvas summary')
                ->assertSeeIn('@drm-ressalvas', 'Este mês fechou com prejuízo de R$ 3.240,00.');
        });
    }

    public function test_sem_faturamento_avisa_uma_vez_e_nao_parece_quebrado(): void
    {
        $usuario = Usuario::factory()->create();
        $empresa = $this->mes($usuario, 'sem_faturamento_servicos');

        $this->browse(function (Browser $browser) use ($usuario, $empresa) {
            $browser->resize(360, 800)->loginAs($usuario)
                ->visit(route('drm.mes', [$empresa, '2026-09']))
                ->waitFor('@drm-sem-faturamento')
                ->assertSeeIn('@drm-sem-faturamento', 'Sem faturamento neste mês')
                ->assertSeeIn('@drm-destaque-L1', 'R$ 0,00')
                ->assertDontSee('%');
            $this->semScrollHorizontal($browser);
        });
    }

    public function test_outra_conta_recebe_404_silente(): void
    {
        $dono = Usuario::factory()->create();
        $intruso = Usuario::factory()->create();
        $empresa = $this->mes($dono, 'saudavel_comercio');

        $this->browse(function (Browser $browser) use ($intruso, $empresa) {
            $browser->loginAs($intruso)->visit(route('drm.mes', [$empresa, '2026-09']))
                ->assertDontSee('Quanto vendeu')
                ->assertDontSee('Padaria do Bairro');
        });
    }
}

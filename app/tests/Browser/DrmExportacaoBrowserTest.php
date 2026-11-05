<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Models\DrmLancamento;
use App\Models\EmpresaAnalisada;
use App\Models\Usuario;
use Facebook\WebDriver\Chrome\ChromeDevToolsDriver;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * STORY-055 — exportação e impressão da Demonstração num browser real.
 *
 * O arquivo é pedido com a sessão do próprio navegador (o `fetch` leva o cookie) e conferido pelos
 * bytes recebidos; a camada de impressão é conferida com a mídia `print` emulada, que é o que o
 * Chrome usa ao imprimir.
 */
final class DrmExportacaoBrowserTest extends DuskTestCase
{
    use DatabaseTruncation;

    /** @var list<string> */
    protected array $tablesToTruncate = [
        'drm_lancamentos', 'drm_rascunhos', 'drm_preferencias', 'empresas_analisadas', 'usuarios',
        'audit_logs', 'business_metrics', 'term_acceptances', 'sessions', 'evento_produto', 'cache',
    ];

    private function mes(Usuario $usuario): EmpresaAnalisada
    {
        $empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $usuario->id, 'nome_fantasia' => 'Padaria do Bairro']);
        $golden = json_decode((string) file_get_contents(base_path('tests/Fixtures/Drm/golden/saudavel_comercio.json')), true, 512, JSON_THROW_ON_ERROR);
        DrmLancamento::factory()->create([
            'empresa_analisada_id' => $empresa->id,
            'competencia' => '2026-09-01',
            'setor' => $golden['entrada']['setor'],
            'lancamento_payload' => $golden['entrada']['payload'],
        ]);

        return $empresa;
    }

    public function test_da_demonstracao_ao_arquivo_recebido(): void
    {
        $usuario = Usuario::factory()->create();
        $empresa = $this->mes($usuario);

        $this->browse(function (Browser $browser) use ($usuario, $empresa) {
            $browser->loginAs($usuario)
                ->visit(route('drm.mes', [$empresa, '2026-09']))
                ->waitFor('@drm-demonstracao')
                ->assertSeeIn('@drm-exportar-xlsx', 'Exportar planilha (XLSX)')
                ->assertAttribute('@drm-exportar-xlsx', 'href', route('drm.exportar', [$empresa, '2026-09']));

            $recebido = $browser->driver->executeAsyncScript(
                'const pronto = arguments[arguments.length - 1];
                 fetch(arguments[0], {credentials: "same-origin"}).then(async (r) => {
                     const b = new Uint8Array(await r.arrayBuffer());
                     pronto({status: r.status, tipo: r.headers.get("content-type"), nome: r.headers.get("content-disposition"), tamanho: b.length, magia: String.fromCharCode(b[0], b[1])});
                 });',
                [route('drm.exportar', [$empresa, '2026-09'])],
            );

            $this->assertSame(200, $recebido['status']);
            $this->assertSame('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $recebido['tipo']);
            $this->assertStringContainsString('drm_padaria-do-bairro_2026-09.xlsx', $recebido['nome']);
            $this->assertSame('PK', $recebido['magia'], 'um XLSX é um zip');
            $this->assertGreaterThan(2000, $recebido['tamanho']);
        });
    }

    public function test_impressao_e_o_mesmo_markup_em_a4_sem_navegacao_nem_botoes(): void
    {
        $usuario = Usuario::factory()->create();
        $empresa = $this->mes($usuario);

        $this->browse(function (Browser $browser) use ($usuario, $empresa) {
            $browser->resize(1280, 900)->loginAs($usuario)
                ->visit(route('drm.mes', [$empresa, '2026-09']))
                ->waitFor('@drm-demonstracao')
                ->assertSeeIn('@drm-imprimir', 'Imprimir / Salvar em PDF')
                ->assertDontSee('Exportar PDF')
                ->assertMissing('@drm-rodape-impressao');

            (new ChromeDevToolsDriver($browser->driver))->execute('Emulation.setEmulatedMedia', ['media' => 'print']);
            $browser->resize(794, 1123); // A4 a 96 dpi: abaixo de `lg`, onde a tela mostraria os cards.

            $browser->assertVisible('@drm-tabela')
                ->assertMissing('@drm-cards')
                ->assertMissing('@drm-exportar-xlsx')
                ->assertMissing('@drm-imprimir')
                ->assertMissing('@drm-lancar-outro')
                ->assertMissing('@drm-trocar-modo')
                ->assertMissing('@drm-comparativos')
                ->assertMissing('nav[aria-label="breadcrumb"]')
                ->assertMissing('[data-testid="app-footer"]')
                ->assertMissing('header[class*="sticky"]')
                ->assertVisible('@drm-rodape-impressao')
                ->assertSeeIn('@drm-rodape-impressao', 'motor v1.0.0')
                ->assertSeeIn('@drm-rodape-impressao', 'conteúdo v1.0.0')
                ->assertSeeIn('@drm-rodape-impressao', 'gerado em');

            $pagina = $browser->script(
                'const achar = (regras) => { for (const r of regras) { if (r instanceof CSSPageRule) { return r.style.getPropertyValue("size"); } if (r.cssRules) { const x = achar(r.cssRules); if (x) { return x; } } } return null; };
                 for (const folha of document.styleSheets) { const x = achar(folha.cssRules); if (x) { return x; } } return null;',
            )[0];
            // O Chrome normaliza `A4 portrait` para `a4`: retrato é a orientação padrão.
            $this->assertSame('a4', is_string($pagina) ? strtolower(trim($pagina)) : $pagina);
        });
    }
}

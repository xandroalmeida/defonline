<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Models\EmpresaAnalisada;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Str;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * STORY-033 CA-3/CA-4/CA-5/CA-7 — comportamento do box de explicação (<x-help>)
 * por campo do quiz.
 *
 * Valida no navegador real o que o Pest (QuizTooltipsTest) não alcança:
 *   - Desktop (≥ 1024px): click no ícone "?" revela popover; Esc fecha.
 *   - Mobile (< 1024px): click revela bottom-sheet com backdrop; botão Fechar fecha.
 *   - Tooltip oculto por padrão; conteúdo é o texto da config (CA-7).
 *
 * Usa Q01 (bloco 1 — visível ao montar) para não depender de navegação.
 */
final class QuizTooltipsBrowserTest extends DuskTestCase
{
    use DatabaseTruncation;

    /** @var list<string> */
    protected array $tablesToTruncate = [
        'quiz_rascunhos', 'diagnosticos', 'empresas_analisadas', 'usuarios',
        'audit_logs', 'business_metrics', 'term_acceptances', 'sessions',
        'evento_produto', 'cache',
    ];

    private function snippetQ01(): string
    {
        // Prefixo plano (sem **negrito**) do texto da config — CA-2/CA-7: o teste
        // não fixa o conteúdo, lê da fonte da verdade.
        $texto = (string) config('quiz.help-industria.campos.Q01');

        return (string) Str::of($texto)->remove('**')->limit(30, '');
    }

    private function novoUsuarioComEmpresa(): array
    {
        $usuario = Usuario::factory()->create(['nome' => 'Roberto Souza']);
        $empresa = EmpresaAnalisada::factory()->create([
            'usuario_id' => $usuario->id,
            'razao_social' => 'Indústria Teste Ltda',
            'nome_fantasia' => 'Teste',
        ]);

        return [$usuario, $empresa];
    }

    public function test_desktop_click_no_icone_revela_popover_e_esc_fecha(): void
    {
        [$usuario, $empresa] = $this->novoUsuarioComEmpresa();
        $snippet = $this->snippetQ01();

        $this->browse(function (Browser $browser) use ($usuario, $empresa, $snippet) {
            $browser->resize(1280, 900)
                ->loginAs($usuario)
                ->visit(route('diagnosticos.novo', $empresa))
                ->waitFor('@help-trigger-Q01')
                ->assertNotVisible('@help-painel-Q01')
                ->assertAttribute('@help-trigger-Q01', 'aria-expanded', 'false')
                ->click('@help-trigger-Q01')
                ->waitFor('@help-painel-Q01')
                ->assertVisible('@help-painel-Q01')
                ->assertSeeIn('@help-painel-Q01', $snippet)
                ->assertAttribute('@help-trigger-Q01', 'aria-expanded', 'true')
                ->keys('', ['{escape}'])
                ->waitUntilMissing('@help-painel-Q01')
                ->assertAttribute('@help-trigger-Q01', 'aria-expanded', 'false');
        });
    }

    public function test_mobile_click_revela_bottom_sheet_e_botao_fechar_fecha(): void
    {
        [$usuario, $empresa] = $this->novoUsuarioComEmpresa();
        $snippet = $this->snippetQ01();

        $this->browse(function (Browser $browser) use ($usuario, $empresa, $snippet) {
            $browser->resize(360, 800)
                ->loginAs($usuario)
                ->visit(route('diagnosticos.novo', $empresa))
                ->waitFor('@help-trigger-Q01')
                ->assertNotVisible('@help-painel-Q01')
                ->click('@help-trigger-Q01')
                ->waitFor('@help-painel-Q01')
                ->assertVisible('@help-painel-Q01')
                ->assertSeeIn('@help-painel-Q01', $snippet)
                ->click('@help-painel-Q01 .help__fechar')
                ->waitUntilMissing('@help-painel-Q01')
                ->assertAttribute('@help-trigger-Q01', 'aria-expanded', 'false');
        });
    }
}

<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Actions\CalcularDiagnostico;
use App\Models\EmpresaAnalisada;
use App\Models\Usuario;
use Database\Factories\DiagnosticoFactory;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * STORY-059 CA-7 — captura de evidência das três variantes de farol, desktop e
 * mobile, para conferência visual de não-regressão.
 *
 * Não afirma nada sobre pixels: contraste é medido por
 * `Tests\Unit\Arch\ContrasteTokensTest` e pelo gate de acessibilidade. Este
 * arquivo existe para gerar as capturas que o CA-7 pede como evidência.
 */
final class EvidenciaFarolBrowserTest extends DuskTestCase
{
    use DatabaseTruncation;

    /** @var list<string> */
    protected array $tablesToTruncate = [
        'quiz_rascunhos', 'diagnosticos', 'empresas_analisadas', 'usuarios',
        'audit_logs', 'business_metrics', 'term_acceptances', 'sessions',
        'evento_produto', 'cache',
    ];

    public function test_captura_as_tres_variantes_de_farol(): void
    {
        $usuario = Usuario::factory()->create(['nome' => 'Roberto Souza']);
        $empresa = EmpresaAnalisada::factory()->create([
            'usuario_id' => $usuario->id,
            'razao_social' => 'Indústria Farol Ltda',
        ]);

        auth()->login($usuario);
        $diag = app(CalcularDiagnostico::class)->execute(
            $empresa,
            DiagnosticoFactory::quizPayloadIndustriaPadrao(),
        );
        auth()->logout();

        $this->browse(function (Browser $browser) use ($usuario, $diag) {
            $browser->resize(1280, 1400)
                ->loginAs($usuario)
                ->visit("/diagnosticos/{$diag->id}")
                ->waitFor('[data-farol]')
                ->screenshot('story-059-farol-desktop');

            $cores = $browser->script(<<<'JS'
                return [...document.querySelectorAll('[data-farol]')].map(el => {
                    const s = getComputedStyle(el);
                    return { farol: el.dataset.farol, cor: s.color, fundo: s.backgroundColor };
                });
            JS)[0];

            // Registro textual junto da captura — o que o olho não mede.
            $this->assertNotEmpty($cores);
            foreach ($cores as $c) {
                // O `color-mix` chega ao computed style como `color(srgb r g b)`
                // — sem canal alpha. Translucidez apareceria como `rgba(...)` ou
                // como um `/ 0.x` no fim, e é o que não pode voltar: era ela que
                // fazia o pill mudar de contraste conforme o fundo de trás.
                $this->assertStringNotContainsString('rgba(', $c['fundo'], "Fundo do farol {$c['farol']} voltou a ser translúcido.");
                $this->assertStringNotContainsString('/', $c['fundo'], "Fundo do farol {$c['farol']} tem canal alpha.");
            }

            $browser->resize(360, 900)
                ->visit("/diagnosticos/{$diag->id}")
                ->waitFor('[data-testid="indicadores-cards"] [data-farol]')
                ->screenshot('story-059-farol-mobile');
        });

        // O payload padrão é saudável e não produz vermelho. A terceira variante
        // vem do fixture canônico de alerta, o mesmo dos golden hashes.
        auth()->login($usuario);
        $alerta = app(CalcularDiagnostico::class)->execute(
            $empresa,
            (array) json_decode(
                (string) file_get_contents(base_path('tests/Domain/Motor/Fixtures/quiz_industria_alerta.json')),
                associative: true,
            ),
        );
        auth()->logout();

        $this->browse(function (Browser $browser) use ($usuario, $alerta) {
            $browser->resize(1280, 1400)
                ->loginAs($usuario)
                ->visit("/diagnosticos/{$alerta->id}")
                ->waitFor('[data-farol="vermelho"]')
                ->screenshot('story-059-farol-vermelho-desktop');
        });
    }
}

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
 * STORY-059 CA-7 — a geometria do pill de farol é a de antes da estória.
 *
 * Nasceu do achado **F-1** da validação: ao trazer o pill das utilities do
 * Tailwind para uma classe de componente, `font-size` herdou `--text-body-sm`
 * e o pill cresceu de 12px para 14px de fonte, de 24px para 28px de altura.
 * O `DDR-006` decidiu sobre **cor** e diz que a aparência não muda.
 *
 * Passou despercebido porque os testes da estória medem contraste, semântica e
 * comportamento — e a captura de tela do CA-7 não denuncia 2px de fonte: o
 * pill continua legível e distinguível, que é o que o olho confere.
 *
 * Por isso este teste mede número, em browser real, e não aparência.
 */
final class FarolGeometriaBrowserTest extends DuskTestCase
{
    use DatabaseTruncation;

    /** @var list<string> */
    protected array $tablesToTruncate = [
        'quiz_rascunhos', 'diagnosticos', 'empresas_analisadas', 'usuarios',
        'audit_logs', 'business_metrics', 'term_acceptances', 'sessions',
        'evento_produto', 'cache',
    ];

    public function test_o_pill_de_farol_mantem_a_geometria_de_antes_da_story_059(): void
    {
        $usuario = Usuario::factory()->create(['nome' => 'Roberto Souza']);
        $empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $usuario->id]);

        auth()->login($usuario);
        $diag = app(CalcularDiagnostico::class)->execute(
            $empresa,
            DiagnosticoFactory::quizPayloadIndustriaPadrao(),
        );
        auth()->logout();

        $this->browse(function (Browser $browser) use ($usuario, $diag) {
            $browser->resize(1280, 1000)
                ->loginAs($usuario)
                ->visit("/diagnosticos/{$diag->id}")
                ->waitFor('[data-farol]');

            $medida = $browser->script(<<<'JS'
                const el = document.querySelector('[data-farol]');
                const s = getComputedStyle(el);
                const r = el.getBoundingClientRect();
                return {
                    fontSize: s.fontSize,
                    padding: s.padding,
                    gap: s.gap,
                    altura: Math.round(r.height),
                };
            JS)[0];

            $this->assertSame('12px', $medida['fontSize'], 'O rótulo do farol mudou de tamanho — era 12px (text-xs).');
            $this->assertSame(24, $medida['altura'], 'A altura do pill mudou — era 24px.');
            $this->assertSame('4px 10px', $medida['padding']);
            $this->assertSame('6px', $medida['gap']);
        });
    }
}

<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Actions\CalcularDiagnostico;
use App\Models\Diagnostico;
use App\Models\EmpresaAnalisada;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\Auth;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * STORY-044 CA-5 / CA-7 — valores nominais em navegador real.
 *
 * O que só o browser prova:
 *   - **CA-7 mobile:** a 360×800 os cards exibem o nominal e a página não ganha
 *     scroll horizontal (`scrollWidth <= clientWidth`). É o risco concreto da
 *     estória: "R$ 1.200.000,00" é uma string longa entrando em layout estreito.
 *   - **CA-7 desktop:** a 1280 a tabela densa também não estoura na horizontal —
 *     a célula de valor tinha `whitespace-nowrap` herdado.
 *   - **CA-5 hierarquia:** o valor do indicador é tipograficamente dominante —
 *     `font-size` do indicador estritamente maior que a do bloco nominal, medido
 *     no DOM renderizado (classe utilitária pode não ter sido emitida no bundle;
 *     computed style não mente).
 *
 * Payload anualizado: Vendas 1.200.000 · LB 600.000 (margem bruta 50%).
 */
final class ValoresNominaisBrowserTest extends DuskTestCase
{
    use DatabaseTruncation;

    /** @var list<string> */
    protected array $tablesToTruncate = [
        'quiz_rascunhos', 'diagnosticos', 'empresas_analisadas', 'usuarios',
        'audit_logs', 'business_metrics', 'term_acceptances', 'sessions',
        'evento_produto', 'cache',
    ];

    /**
     * @return array{0: Usuario, 1: Diagnostico}
     */
    private function usuarioComDiagnostico(): array
    {
        $usuario = Usuario::factory()->create(['nome' => 'Roberto Souza']);

        Auth::login($usuario);
        $empresa = EmpresaAnalisada::factory()->create([
            'usuario_id' => $usuario->id,
            'razao_social' => 'Metalúrgica Nominal Ltda',
            'nome_fantasia' => 'Metalúrgica Nominal',
        ]);
        $diagnostico = app(CalcularDiagnostico::class)->execute($empresa, [
            'Q01' => 1,
            'Q02' => '50000.00', 'Q03' => '80000.00', 'Q04' => '60000.00',
            'Q05' => '300000.00', 'Q06' => '40000.00', 'Q07' => '30000.00',
            'Q08' => '50000.00', 'Q09' => '100000.00',
            'Q10' => 45, 'Q11' => 30, 'Q12' => 45, 'Q13' => '2.00',
            'Q14' => '20000.00', 'Q15' => '8000.00', 'Q16' => '2000.00',
        ]);
        Auth::logout();

        return [$usuario, $diagnostico];
    }

    public function test_mobile_360_exibe_valor_nominal_sem_scroll_horizontal(): void
    {
        [$usuario, $diagnostico] = $this->usuarioComDiagnostico();

        $this->browse(function (Browser $browser) use ($usuario, $diagnostico) {
            $browser->resize(360, 800)
                ->loginAs($usuario)
                ->visit("/diagnosticos/{$diagnostico->id}")
                ->waitFor('[data-testid="indicadores-cards"]')
                ->assertSee('Lucro bruto (12 meses)')
                ->assertSee('R$ 1.200.000,00');

            // O bloco nominal do card mobile está de fato visível (não só no DOM
            // da tabela, que a 360px está com `display: none`).
            $visivelNoCard = $browser->script(
                'const card = document.querySelector(\'[data-testid="indicadores-cards"] [data-testid="nominais-margem_bruta"]\');'
                .'return card !== null && card.getBoundingClientRect().height > 0;',
            )[0];
            $this->assertTrue($visivelNoCard, 'Bloco nominal não visível no card mobile');

            $estouraNaHorizontal = $browser->script(
                'return document.documentElement.scrollWidth > document.documentElement.clientWidth;',
            )[0];
            $this->assertFalse($estouraNaHorizontal, 'Página ganhou scroll horizontal em 360x800');
        });
    }

    public function test_desktop_exibe_valor_nominal_na_tabela_sem_estourar_largura(): void
    {
        [$usuario, $diagnostico] = $this->usuarioComDiagnostico();

        $this->browse(function (Browser $browser) use ($usuario, $diagnostico) {
            $browser->resize(1280, 900)
                ->loginAs($usuario)
                ->visit("/diagnosticos/{$diagnostico->id}")
                ->waitFor('[data-testid="indicadores-tabela"]')
                ->assertSee('Lucro bruto (12 meses)')
                ->assertSee('Vendas (12 meses)');

            $estouraNaHorizontal = $browser->script(
                'return document.documentElement.scrollWidth > document.documentElement.clientWidth;',
            )[0];
            $this->assertFalse($estouraNaHorizontal, 'Página ganhou scroll horizontal em 1280x900');
        });
    }

    public function test_indicador_permanece_dominante_sobre_o_valor_nominal(): void
    {
        [$usuario, $diagnostico] = $this->usuarioComDiagnostico();

        $this->browse(function (Browser $browser) use ($usuario, $diagnostico) {
            $browser->resize(1280, 900)
                ->loginAs($usuario)
                ->visit("/diagnosticos/{$diagnostico->id}")
                ->waitFor('[data-testid="indicadores-tabela"]');

            $tamanhos = $browser->script(
                'const linha = document.querySelector(\'#indicador-margem_bruta\');'
                .'const nominal = linha.querySelector(\'[data-testid="nominais-margem_bruta"]\');'
                .'const valor = linha.querySelector(\'td span.whitespace-nowrap\');'
                .'return [parseFloat(getComputedStyle(valor).fontSize), parseFloat(getComputedStyle(nominal).fontSize)];',
            )[0];

            [$fonteIndicador, $fonteNominal] = $tamanhos;
            $this->assertGreaterThan(
                $fonteNominal,
                $fonteIndicador,
                "Valor nominal ({$fonteNominal}px) não pode competir com o indicador ({$fonteIndicador}px)",
            );
        });
    }
}

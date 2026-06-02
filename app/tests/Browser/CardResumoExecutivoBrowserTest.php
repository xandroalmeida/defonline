<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Models\Diagnostico;
use App\Models\EmpresaAnalisada;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\Auth;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * STORY-040 CA-1/CA-2/CA-3/CA-8 — card de destaque do Resumo Executivo clicável.
 *
 * Valida no navegador real o que o Pest (DiagnosticoShowTest) não alcança:
 *   - Desktop (≥ md): clicar no destaque rola a página (window.scrollY aumenta), a
 *     linha-alvo da tabela fica visível e recebe a classe `.is-highlight` (CA-1/CA-8).
 *   - Mobile (< md): o mesmo clique rola até o CARD correspondente (CA-3).
 *
 * Usa um destaque para `pmr` — última linha renderizada do snapshot stub — para
 * garantir um scroll mensurável a partir do topo.
 */
final class CardResumoExecutivoBrowserTest extends DuskTestCase
{
    use DatabaseTruncation;

    /** @var list<string> */
    protected array $tablesToTruncate = [
        'quiz_rascunhos', 'diagnosticos', 'empresas_analisadas', 'usuarios',
        'audit_logs', 'business_metrics', 'term_acceptances', 'sessions',
        'evento_produto', 'cache',
    ];

    /**
     * Cria um usuário + diagnóstico cujo Resumo Executivo tem um destaque negativo
     * apontando para `pmr` (presente no snapshot stub da factory).
     *
     * @return array{0: Usuario, 1: Diagnostico}
     */
    private function usuarioComDiagnosticoDestaque(): array
    {
        $usuario = Usuario::factory()->create(['nome' => 'Roberto Souza']);

        Auth::login($usuario);
        $empresa = EmpresaAnalisada::factory()->create([
            'usuario_id' => $usuario->id,
            'razao_social' => 'Indústria Teste Ltda',
            'nome_fantasia' => 'Teste',
        ]);
        $diagnostico = Diagnostico::factory()->paraEmpresa($empresa)->create([
            'motor_version' => '1.2.0',
            'resumo_executivo' => [
                'veredito' => 'em_alerta',
                'veredito_texto' => 'Sua empresa está em alerta.',
                'destaques_negativos' => [
                    ['codigo' => 'pmr', 'texto' => 'PMR: prazo de recebimento elevado, atenção ao caixa.'],
                ],
                'destaque_positivo' => null,
                'fallback_acionado' => false,
            ],
        ]);
        Auth::logout();

        return [$usuario, $diagnostico];
    }

    public function test_desktop_clique_no_destaque_rola_e_destaca_a_linha(): void
    {
        [$usuario, $diagnostico] = $this->usuarioComDiagnosticoDestaque();

        $this->browse(function (Browser $browser) use ($usuario, $diagnostico) {
            $browser->resize(1280, 900)
                ->loginAs($usuario)
                ->visit(route('diagnosticos.show', $diagnostico))
                ->waitFor('[data-indicador-link="pmr"]')
                ->assertVisible('[data-testid="indicadores-tabela"]');

            // Partimos do topo (visit() carrega em scrollY=0).
            $antes = (int) $browser->script('return Math.round(window.pageYOffset);')[0];

            // Clica e confere o highlight ainda dentro da janela de ~1s (CA-8b).
            $browser->click('[data-indicador-link="pmr"]')
                ->pause(200);

            $temHighlight = $browser->script(
                'return document.querySelector(\'tr[data-codigo="pmr"]\').classList.contains("is-highlight");',
            )[0];
            $this->assertTrue($temHighlight, 'A linha-alvo deveria ter a classe .is-highlight após o clique.');

            // Espera o smooth scroll assentar e confere que rolou + alvo visível (CA-1/CA-2/CA-8a).
            $browser->pause(800);
            $depois = (int) $browser->script('return Math.round(window.pageYOffset);')[0];

            $this->assertGreaterThan($antes, $depois, 'window.scrollY deveria aumentar após clicar no destaque.');
            $browser->assertVisible('#indicador-pmr');
        });
    }

    public function test_mobile_clique_no_destaque_rola_ate_o_card(): void
    {
        [$usuario, $diagnostico] = $this->usuarioComDiagnosticoDestaque();

        $this->browse(function (Browser $browser) use ($usuario, $diagnostico) {
            $browser->resize(375, 800)
                ->loginAs($usuario)
                ->visit(route('diagnosticos.show', $diagnostico))
                ->waitFor('[data-indicador-link="pmr"]')
                ->assertVisible('[data-testid="indicadores-cards"]');

            $antes = (int) $browser->script('return Math.round(window.pageYOffset);')[0];

            $browser->click('[data-indicador-link="pmr"]')
                ->pause(200);

            // No mobile o alvo é o card (<article>), não a <tr> (escondida).
            $temHighlight = $browser->script(
                'return document.querySelector(\'article[data-codigo="pmr"]\').classList.contains("is-highlight");',
            )[0];
            $this->assertTrue($temHighlight, 'O card-alvo deveria ter a classe .is-highlight após o clique.');

            $browser->pause(800);
            $depois = (int) $browser->script('return Math.round(window.pageYOffset);')[0];

            $this->assertGreaterThan($antes, $depois, 'window.scrollY deveria aumentar no mobile.');
            $browser->assertVisible('article[data-codigo="pmr"]');
        });
    }
}

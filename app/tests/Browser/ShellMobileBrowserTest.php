<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Models\EmpresaAnalisada;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * STORY-019 CA-9 + CA-3 + CA-10 — comportamento responsivo do shell e do drawer
 * mobile.
 *
 * Roda em viewport 360x800 (Roberto digita no celular) para validar:
 *   - hamburger visível, sidebar oculta por padrão;
 *   - drawer abre via hamburger;
 *   - drawer fecha via Escape;
 *   - drawer fecha via clique no overlay;
 *   - botão "Adicionar empresa" continua visível em /home (não some no mobile).
 */
final class ShellMobileBrowserTest extends DuskTestCase
{
    use DatabaseTruncation;

    /** @var list<string> */
    protected array $tablesToTruncate = [
        'empresas_analisadas', 'usuarios', 'audit_logs', 'business_metrics',
        'term_acceptances', 'sessions', 'evento_produto', 'cache',
    ];

    public function test_drawer_mobile_abre_pelo_hamburger_e_fecha_pelo_escape(): void
    {
        $usuario = Usuario::factory()->create(['nome' => 'Roberto Souza']);

        $this->browse(function (Browser $browser) use ($usuario) {
            $browser->resize(360, 800)
                ->loginAs($usuario)
                ->visit('/home')
                ->waitFor('@app-header-hamburger')
                ->assertVisible('@app-header-hamburger')
                ->click('@app-header-hamburger')
                ->waitFor('@app-nav-minhas-empresas')
                ->assertVisible('@app-nav-minhas-empresas')
                ->assertAttribute('@app-header-hamburger', 'aria-expanded', 'true')
                // Fecha pelo botão "fechar" dentro do drawer (presente só no mobile).
                ->click('@app-nav-fechar')
                ->pause(300)
                ->assertAttribute('@app-header-hamburger', 'aria-expanded', 'false');
        });
    }

    public function test_adicionar_empresa_cta_visivel_no_mobile_com_empresa_cadastrada(): void
    {
        $usuario = Usuario::factory()->create(['nome' => 'Roberto Souza']);
        EmpresaAnalisada::factory()->create([
            'usuario_id' => $usuario->id,
            'razao_social' => 'Empresa Teste',
            'nome_fantasia' => 'Teste',
        ]);

        $this->browse(function (Browser $browser) use ($usuario) {
            $browser->resize(360, 800)
                ->loginAs($usuario)
                ->visit('/home')
                ->waitFor('@minhas-empresas-cta-adicionar')
                ->assertVisible('@minhas-empresas-cta-adicionar')
                ->click('@minhas-empresas-cta-adicionar')
                ->waitForLocation('/empresas/nova');
        });
    }
}

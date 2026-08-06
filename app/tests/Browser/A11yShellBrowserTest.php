<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Models\EmpresaAnalisada;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * STORY-059 CA-2 e CA-9 — o que só o browser real prova.
 *
 * `inert` e área de toque não existem no HTML do servidor: o primeiro é
 * aplicado pelo Alpine depois de consultar o breakpoint, e a segunda é
 * geometria calculada pelo layout engine. Os dois achados que este arquivo
 * cobre são exatamente os que um teste de markup daria por bons sem serem:
 *
 *   - CA-2: o drawer fechado precisa recusar o foco. `aria-hidden` sozinho o
 *     esconde do leitor de tela e deixa o Tab entrar — a combinação que o gate
 *     acusa como `aria-hidden-focus`.
 *   - CA-9: o `<x-help>` precisa aceitar toque em 44×44px **sem** que o
 *     círculo visível cresça. Só medindo o hit-testing dá para saber.
 */
final class A11yShellBrowserTest extends DuskTestCase
{
    use DatabaseTruncation;

    /** @var list<string> */
    protected array $tablesToTruncate = [
        'quiz_rascunhos', 'diagnosticos', 'empresas_analisadas', 'usuarios',
        'audit_logs', 'business_metrics', 'term_acceptances', 'sessions',
        'evento_produto', 'cache',
    ];

    public function test_ca2_drawer_fechado_no_mobile_recusa_o_foco(): void
    {
        $usuario = Usuario::factory()->create(['nome' => 'Roberto Souza']);

        $this->browse(function (Browser $browser) use ($usuario) {
            $browser->resize(360, 800)
                ->loginAs($usuario)
                ->visit('/home')
                ->waitFor('@app-header-hamburger');

            // Tentar focar um link de dentro do drawer fechado não pode dar em
            // nada: com `inert`, o elemento simplesmente não recebe foco.
            $focouDentro = $browser->script(<<<'JS'
                const link = document.querySelector('#app-nav-drawer a');
                link.focus();
                return document.activeElement === link;
            JS)[0];

            $this->assertFalse($focouDentro, 'O drawer fechado ainda aceita foco nos filhos — `aria-hidden-focus`.');

            $atributos = $browser->script(<<<'JS'
                const el = document.querySelector('#app-nav-drawer');
                return { inert: el.hasAttribute('inert'), ariaHidden: el.getAttribute('aria-hidden') };
            JS)[0];

            $this->assertTrue($atributos['inert'], 'Drawer fechado deveria estar `inert`.');
            $this->assertSame('true', $atributos['ariaHidden']);
        });
    }

    public function test_ca2_drawer_aberto_no_mobile_volta_a_aceitar_foco(): void
    {
        $usuario = Usuario::factory()->create(['nome' => 'Roberto Souza']);

        $this->browse(function (Browser $browser) use ($usuario) {
            $browser->resize(360, 800)
                ->loginAs($usuario)
                ->visit('/home')
                ->waitFor('@app-header-hamburger')
                ->click('@app-header-hamburger')
                ->waitFor('@app-nav-minhas-empresas')
                ->pause(250);

            $estado = $browser->script(<<<'JS'
                const el = document.querySelector('#app-nav-drawer');
                const link = el.querySelector('a');
                link.focus();
                return {
                    inert: el.hasAttribute('inert'),
                    ariaHidden: el.getAttribute('aria-hidden'),
                    focou: document.activeElement === link,
                };
            JS)[0];

            $this->assertFalse($estado['inert'], 'Drawer aberto não pode estar `inert`.');
            $this->assertNull($estado['ariaHidden'], 'Drawer aberto não pode carregar aria-hidden.');
            $this->assertTrue($estado['focou'], 'Drawer aberto tem que aceitar foco — senão vira drawer inútil.');
        });
    }

    public function test_ca2_sidebar_do_desktop_esta_na_arvore_de_acessibilidade(): void
    {
        // O defeito espelhado do anterior, e o mais silencioso dos dois: em
        // ≥1024px a navegação é sidebar fixa e visível, com `navOpen` false.
        // Amarrado só a `navOpen`, o aria-hidden escondia do leitor de tela uma
        // navegação que estava ali, à vista, o tempo todo.
        $usuario = Usuario::factory()->create(['nome' => 'Roberto Souza']);

        $this->browse(function (Browser $browser) use ($usuario) {
            $browser->resize(1280, 900)
                ->loginAs($usuario)
                ->visit('/home')
                ->waitFor('@app-nav-minhas-empresas')
                ->pause(250);

            $estado = $browser->script(<<<'JS'
                const el = document.querySelector('#app-nav-drawer');
                const link = el.querySelector('a');
                link.focus();
                return {
                    inert: el.hasAttribute('inert'),
                    ariaHidden: el.getAttribute('aria-hidden'),
                    focou: document.activeElement === link,
                };
            JS)[0];

            $this->assertFalse($estado['inert']);
            $this->assertNull($estado['ariaHidden']);
            $this->assertTrue($estado['focou']);
        });
    }

    public function test_ca9_help_aceita_toque_em_44px_sem_crescer_o_circulo(): void
    {
        $usuario = Usuario::factory()->create(['nome' => 'Roberto Souza']);
        $empresa = EmpresaAnalisada::factory()->create([
            'usuario_id' => $usuario->id,
            'razao_social' => 'Indústria Teste Ltda',
            'nome_fantasia' => 'Teste',
        ]);

        $this->browse(function (Browser $browser) use ($usuario, $empresa) {
            $browser->resize(360, 800)
                ->loginAs($usuario)
                ->visit(route('diagnosticos.novo', $empresa))
                ->waitFor('@help-trigger-Q01');

            $medida = $browser->script(<<<'JS'
                const btn = document.querySelector('[dusk="help-trigger-Q01"]');
                const r = btn.getBoundingClientRect();
                const cx = r.left + r.width / 2;
                const cy = r.top + r.height / 2;
                // 20px acima e 20px à direita do centro: fora do círculo de 18px,
                // dentro do alvo de 44px. Em ambos, quem responde tem que ser o botão.
                const acima = document.elementFromPoint(cx, cy - 20);
                const lado = document.elementFromPoint(cx + 20, cy);
                return {
                    largura: Math.round(r.width),
                    altura: Math.round(r.height),
                    acimaEhBotao: acima === btn,
                    ladoEhBotao: lado === btn,
                };
            JS)[0];

            // O círculo visível NÃO cresceu — CA-9 é explícito nisso.
            $this->assertSame(18, $medida['largura'], 'O círculo visível do <x-help> deveria continuar com 18px.');
            $this->assertSame(18, $medida['altura']);

            // Mas a área que responde ao toque, sim.
            $this->assertTrue($medida['acimaEhBotao'], 'Ponto 20px acima do centro deveria acertar o botão (alvo de 44px).');
            $this->assertTrue($medida['ladoEhBotao'], 'Ponto 20px ao lado do centro deveria acertar o botão (alvo de 44px).');
        });
    }

    public function test_ca9_toque_fora_dos_44px_nao_aciona_o_help(): void
    {
        // A borda do outro lado: se a área tivesse virado enorme, ela roubaria
        // toques do campo vizinho — que é pior do que o defeito original.
        $usuario = Usuario::factory()->create(['nome' => 'Roberto Souza']);
        $empresa = EmpresaAnalisada::factory()->create([
            'usuario_id' => $usuario->id,
            'razao_social' => 'Indústria Teste Ltda',
            'nome_fantasia' => 'Teste',
        ]);

        $this->browse(function (Browser $browser) use ($usuario, $empresa) {
            $browser->resize(360, 800)
                ->loginAs($usuario)
                ->visit(route('diagnosticos.novo', $empresa))
                ->waitFor('@help-trigger-Q01');

            $ehBotao = $browser->script(<<<'JS'
                const btn = document.querySelector('[dusk="help-trigger-Q01"]');
                const r = btn.getBoundingClientRect();
                const alvo = document.elementFromPoint(r.left + r.width / 2 + 40, r.top + r.height / 2);
                return alvo === btn;
            JS)[0];

            $this->assertFalse($ehBotao, 'A área de toque passou de 44px e está roubando toque da vizinhança.');
        });
    }
}

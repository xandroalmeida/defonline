<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Models\Usuario;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * E2E browser real cobrindo o walking skeleton da STORY-011 (CA-7):
 * cadastro → login → home → logout, em Chromium real via chromedriver no container.
 *
 * NÃO está no grupo `smoke` por design — ele faz writes (cria usuário no banco
 * sob teste). Smoke pós-deploy precisa ser read-only para não contaminar dados
 * em homologação. Veja `CadastroLoginSmokeBrowserTest` para o smoke leve.
 *
 * STORY-013 mudou o fluxo: agora login só passa após confirmar o email. Aqui
 * o cadastro acontece via UI e a confirmação é simulada via update direto no
 * banco — o fluxo "cadastro → Mailpit → click" tem teste próprio em
 * EmailConfirmacaoBrowserTest.
 */
final class CadastroLoginHomeBrowserTest extends DuskTestCase
{
    use DatabaseTruncation;

    /** @var list<string> tabelas truncadas entre testes para evitar UNIQUE colisão entre PHP-FPM e Pest */
    protected array $tablesToTruncate = ['usuarios', 'audit_logs', 'term_acceptances', 'sessions'];

    public function test_visitante_cria_conta_faz_login_acessa_home_e_sai(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/cadastro')
                ->assertSee('Criar conta')
                ->type('@cadastro-cpf', '529.982.247-25')
                ->type('@cadastro-nome', 'Roberto Souza')
                ->type('@cadastro-email', 'roberto.dusk@exemplo.com.br')
                ->type('@cadastro-senha', 'Senha1234')
                ->type('@cadastro-senha-confirmation', 'Senha1234')
                ->type('@cadastro-telefone', '11988887777')
                ->check('@cadastro-aceite-termo-adesao')
                ->check('@cadastro-aceite-lgpd')
                ->press('@cadastro-submit')
                ->waitForLocation('/login')
                ->assertSee('link de confirmação');

            // STORY-013 — confirmar o email programaticamente para destravar o
            // login neste teste de walking skeleton. O E2E completo do link
            // assinado vive em EmailConfirmacaoBrowserTest.
            Usuario::where('email', 'roberto.dusk@exemplo.com.br')
                ->update(['email_confirmed_at' => now()]);

            $browser->type('@login-email', 'roberto.dusk@exemplo.com.br')
                ->type('@login-senha', 'Senha1234')
                ->press('@login-submit')
                ->waitForLocation('/home')
                ->assertSeeIn('@saudacao', 'Olá, Roberto');

            // STORY-019 — logout agora vive dentro do dropdown "Conta" do header.
            // Abre o dropdown, clica "Sair", confirma redirect + flash.
            $browser->click('@app-header-conta-toggle')
                ->waitFor('@app-header-conta-sair')
                ->click('@app-header-conta-sair')
                ->waitForLocation('/login')
                ->assertSeeIn('@logout-sucesso', 'Você saiu da conta com sucesso.');
        });

        // Confirma persistência real no Postgres.
        $usuario = Usuario::firstWhere('email', 'roberto.dusk@exemplo.com.br');
        $this->assertNotNull($usuario);
        $this->assertSame('52998224725', $usuario->cpf);
    }
}

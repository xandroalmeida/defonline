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
                ->assertSee('Conta criada com sucesso');

            $browser->type('@login-email', 'roberto.dusk@exemplo.com.br')
                ->type('@login-senha', 'Senha1234')
                ->press('@login-submit')
                ->waitForLocation('/home')
                ->assertSeeIn('@saudacao', 'Olá, Roberto');

            $browser->press('@logout')
                ->waitForLocation('/login');
        });

        // Confirma persistência real no Postgres.
        $usuario = Usuario::firstWhere('email', 'roberto.dusk@exemplo.com.br');
        $this->assertNotNull($usuario);
        $this->assertSame('52998224725', $usuario->cpf);
    }
}

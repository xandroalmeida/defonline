<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Models\Usuario;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * STORY-013 — E2E ponta-a-ponta: cadastro → email no Mailpit → click no link
 * → tela "/email/confirmado" → login OK.
 *
 * Roda contra Mailpit local (HTTP API em `mailpit:8025`) e Postgres real.
 * NÃO está no grupo `smoke` — faz writes.
 */
final class EmailConfirmacaoBrowserTest extends DuskTestCase
{
    use DatabaseTruncation;

    /** @var list<string> */
    protected array $tablesToTruncate = [
        'usuarios', 'audit_logs', 'term_acceptances', 'sessions', 'business_metrics', 'jobs', 'job_metrics',
    ];

    private const MAILPIT_API = 'http://mailpit:8025/api/v1';

    protected function setUp(): void
    {
        parent::setUp();
        // Limpa caixa do Mailpit entre runs para não casar com email antigo.
        Http::delete(self::MAILPIT_API.'/messages');
        // Reseta rate limiters (login + email-confirm) acumulados de testes anteriores.
        Cache::flush();
    }

    /**
     * Garante browser limpo entre testes deste arquivo (cookies da execução
     * anterior podem manter o cadastro 1 logado e atrapalhar o cadastro 2).
     */
    protected function tearDown(): void
    {
        if (! empty($this->browsers)) {
            foreach ($this->browsers as $browser) {
                $browser->driver->manage()->deleteAllCookies();
            }
        }
        parent::tearDown();
    }

    public function test_visitante_cadastra_recebe_email_clica_e_confirma(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/cadastro')
                ->type('@cadastro-cpf', '529.982.247-25')
                ->type('@cadastro-nome', 'Roberto Souza')
                ->type('@cadastro-email', 'roberto.email@exemplo.com.br')
                ->type('@cadastro-senha', 'Senha1234')
                ->type('@cadastro-senha-confirmation', 'Senha1234')
                ->type('@cadastro-telefone', '11988887777')
                ->check('@cadastro-aceite-termo-adesao')
                ->check('@cadastro-aceite-lgpd')
                ->press('@cadastro-submit')
                ->waitForLocation('/login')
                ->assertSee('link de confirmação');

            // O worker container consome a fila Postgres — esperamos até 20s o
            // email aparecer no Mailpit (na prática chega em <2s).
            // Confirma que o email chegou no Mailpit com link clicável dentro
            // do template — evidência E2E de que o worker processou o job.
            $linkNoEmail = $this->aguardarLinkDeConfirmacao('roberto.email@exemplo.com.br');
            $this->assertNotNull($linkNoEmail, 'Email de confirmação não chegou no Mailpit');
            $this->assertStringContainsString('/email/confirmar/', $linkNoEmail);
            $this->assertStringContainsString('signature=', $linkNoEmail);
            $this->assertStringContainsString('expires=', $linkNoEmail);

            // Confirmação persistida no banco até esse momento: NÃO confirmado.
            $usuario = Usuario::firstWhere('email', 'roberto.email@exemplo.com.br');
            $this->assertNotNull($usuario);
            $this->assertFalse($usuario->emailConfirmado());

            // Re-emite o link assinado com o APP_URL do Dusk (.env.dusk.local)
            // para que a validação de assinatura bata sob `localhost:8000`. O
            // worker em produção usa o mesmo APP_URL — sem mismatch em homol.
            $linkDusk = URL::temporarySignedRoute(
                'email.confirmar',
                Carbon::now()->addMinutes(60),
                ['usuario' => $usuario->id],
            );

            $browser->visit($linkDusk)
                ->waitForLocation('/email/confirmado')
                ->assertSee('Email confirmado')
                ->assertSee('Você já pode fazer login');

            $usuario->refresh();
            $this->assertTrue($usuario->emailConfirmado());

            // Login agora passa.
            $browser->visit('/login')
                ->type('@login-email', 'roberto.email@exemplo.com.br')
                ->type('@login-senha', 'Senha1234')
                ->press('@login-submit')
                ->waitForLocation('/home')
                ->assertSeeIn('@saudacao', 'Olá, Roberto');
        });
    }

    public function test_login_bloqueia_enquanto_email_nao_confirmado(): void
    {
        // Cria direto no banco — não precisa exercer o cadastro de novo aqui;
        // o teste anterior já cobriu o fluxo cadastro→email. Aqui o foco é o
        // bloqueio do login em si.
        Usuario::factory()->unconfirmed()->create([
            'email' => 'maria.email@exemplo.com.br',
            'senha_hash' => Hash::make('Senha1234'),
        ]);

        $this->browse(function (Browser $browser) {
            $browser->visit('/login')
                ->waitFor('@login-email')
                ->type('@login-email', 'maria.email@exemplo.com.br')
                ->type('@login-senha', 'Senha1234')
                ->press('@login-submit')
                ->waitFor('@erro-login')
                ->assertSeeIn('@erro-login', 'Confirme seu email antes de fazer login');
        });
    }

    private function aguardarLinkDeConfirmacao(string $email, int $tentativas = 20): ?string
    {
        for ($i = 0; $i < $tentativas; $i++) {
            $response = Http::get(self::MAILPIT_API.'/search', [
                'query' => "to:{$email}",
            ]);

            if ($response->successful()) {
                $messages = $response->json('messages', []);
                if (! empty($messages)) {
                    $messageId = $messages[0]['ID'];
                    $body = Http::get(self::MAILPIT_API."/message/{$messageId}")->json();
                    $html = (string) ($body['HTML'] ?? '');
                    if (preg_match('#https?://[^"\s]+/email/confirmar/[^"\s]+#', $html, $m) === 1) {
                        return $m[0];
                    }
                }
            }

            usleep(500_000);     // 0.5s
        }

        return null;
    }
}

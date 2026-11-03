<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Models\Usuario;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * STORY-065 CA-7 — membro da equipe vê a lista de usuários e busca por e-mail;
 * um usuário comum recebe 404 na mesma URL.
 *
 * A allowlist vem de BACKOFFICE_EMAILS no servidor do Dusk; o teste usa um
 * e-mail dela (phpunit.dusk / .env.dusk.local) — ver setUp.
 *
 * NÃO está no grupo `smoke` por design — faz writes.
 */
final class BackofficeUsuariosBrowserTest extends DuskTestCase
{
    use DatabaseTruncation;

    /** @var list<string> */
    protected array $tablesToTruncate = ['usuarios', 'audit_logs', 'sessions', 'cache'];

    public function test_equipe_lista_e_busca_e_usuario_comum_recebe_404(): void
    {
        $equipe = Usuario::factory()->create(['nome' => 'Equipe EBP', 'email' => 'equipe@ebparcerias.com.br']);
        Usuario::factory()->create(['nome' => 'Maria Pereira', 'email' => 'maria.pereira@exemplo.com.br']);
        Usuario::factory()->create(['nome' => 'João Lima', 'email' => 'joao.lima@exemplo.com.br']);
        $comum = Usuario::factory()->create(['nome' => 'Roberto Comum']);

        $this->browse(function (Browser $browser) use ($equipe, $comum) {
            $browser->loginAs($equipe)
                ->visit('/backoffice/usuarios')
                ->waitFor('@backoffice-usuarios')
                ->assertSee('4 usuários')
                ->assertSee('Maria Pereira')
                ->assertSee('João Lima')
                ->type('@backoffice-busca', 'maria.pereira@')
                ->press('@backoffice-buscar')
                ->waitUntilMissingText('João Lima')
                ->assertSee('Maria Pereira')
                ->assertSee('1 usuário')
                ->assertQueryStringHas('q', 'maria.pereira@');

            $browser->logout()
                ->loginAs($comum)
                ->visit('/backoffice/usuarios')
                ->assertSee('404')
                ->assertDontSee('Maria Pereira');
        });
    }
}

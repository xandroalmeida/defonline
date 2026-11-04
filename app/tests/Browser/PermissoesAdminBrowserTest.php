<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Models\Usuario;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * STORY-067 — o admin raiz dá a permissão do glossário a um colega; o colega vê só
 * esse item no menu, cadastra e exclui um termo.
 *
 * NÃO está no grupo `smoke` por design — faz writes.
 */
final class PermissoesAdminBrowserTest extends DuskTestCase
{
    use DatabaseTruncation;

    /** @var list<string> */
    protected array $tablesToTruncate = ['usuarios', 'audit_logs', 'sessions', 'cache'];

    public function test_admin_com_permissao_mantem_o_glossario(): void
    {
        $raiz = Usuario::factory()->create(['email' => 'equipe@ebparcerias.com.br']);
        $colega = Usuario::factory()->create(['email' => 'colega@exemplo.com.br']);

        $this->browse(function (Browser $browser) use ($raiz, $colega) {
            $browser->loginAs($raiz)
                ->visit('/home')
                ->waitFor('@app-nav-admin')
                ->click('@app-nav-backoffice-administradores')
                ->waitForLocation('/backoffice/administradores')
                ->type('@admin-email', 'colega@exemplo.com.br')
                ->check('@admin-permissao-glossario.editar')
                ->press('@admin-salvar')
                ->waitFor('@admin-linha-'.$colega->id)
                ->assertSeeIn('@admin-linha-'.$colega->id, 'Manter glossário');

            $browser->logout()
                ->loginAs($colega)
                ->visit('/home')
                ->waitFor('@app-nav-admin')
                ->assertPresent('@app-nav-backoffice-glossario')
                ->assertMissing('@app-nav-backoffice-usuarios')
                ->assertMissing('@app-nav-backoffice-administradores')
                ->click('@app-nav-backoffice-glossario')
                ->waitForLocation('/backoffice/glossario')
                ->waitFor('@glossario-lista')
                ->type('@glossario-termo', 'Termo E2E')
                ->type('@glossario-definicao', 'Definição E2E')
                ->press('@glossario-salvar')
                ->waitForTextIn('@glossario-lista', 'Termo E2E');

            // O glossário não é truncado entre testes: remove o que criou.
            $termoId = \App\Models\GlossarioTermo::query()->where('termo', 'Termo E2E')->value('id');

            // Editar leva ao topo da tela, com o foco na definição.
            $browser->resize(1280, 700)
                ->scrollIntoView('@glossario-editar-'.$termoId);
            $this->assertGreaterThan(0, $browser->script('return window.scrollY;')[0]);
            $browser->press('@glossario-editar-'.$termoId)
                ->waitUsing(5, 100, fn () => $browser->script(
                    "return document.activeElement.id === 'glossario-definicao'
                        && document.activeElement.value === 'Definição E2E'
                        && window.scrollY === 0;"
                )[0] === true)
                ->press('@glossario-cancelar');

            $browser->press('@glossario-excluir-'.$termoId)
                ->waitUntilMissingText('Termo E2E')
                ->visit('/backoffice/administradores')
                ->assertSee('404');
        });
    }
}

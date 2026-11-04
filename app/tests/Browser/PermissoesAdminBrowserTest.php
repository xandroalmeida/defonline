<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Models\Usuario;
use Facebook\WebDriver\WebDriverKeys;
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

            // Remover usa a cor de alerta e pede confirmação: cancelar mantém o acesso.
            $this->assertSame('rgb(225, 29, 72)', $browser->script(
                "return getComputedStyle(document.querySelector('[dusk=\"admin-remover-{$colega->id}\"]')).backgroundColor;"
            )[0]);
            $browser->press('@admin-remover-'.$colega->id)
                ->waitFor('@confirmacao')
                ->assertSeeIn('@confirmacao', 'Remover acesso')
                ->press('@confirmacao-cancelar')
                ->waitUntilMissing('@confirmacao')
                ->pause(300)
                ->assertPresent('@admin-linha-'.$colega->id);
            $this->assertSame(['glossario.editar'], $colega->fresh()?->permissoes_admin);

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

            // Excluir usa a cor de alerta (#E11D48) e pede confirmação: cancelar mantém o termo.
            $this->assertSame('rgb(225, 29, 72)', $browser->script(
                "return getComputedStyle(document.querySelector('[dusk=\"glossario-excluir-{$termoId}\"]')).backgroundColor;"
            )[0]);
            $browser->press('@glossario-excluir-'.$termoId)
                ->waitFor('@confirmacao')
                ->assertSeeIn('@confirmacao-mensagem', 'Excluir “Termo E2E” do glossário?');
            $browser->driver->getKeyboard()->sendKeys(WebDriverKeys::ESCAPE);
            $browser->waitUntilMissing('@confirmacao')
                ->pause(300)
                ->assertSeeIn('@glossario-lista', 'Termo E2E')
                ->press('@glossario-excluir-'.$termoId)
                ->waitFor('@confirmacao')
                ->press('@confirmacao-confirmar')
                ->waitUntilMissingText('Termo E2E')
                ->visit('/backoffice/administradores')
                ->assertSee('404');

            // Confirmar o Remover tira o acesso.
            $browser->logout()
                ->loginAs($raiz)
                ->visit('/backoffice/administradores')
                ->waitFor('@admin-linha-'.$colega->id)
                ->press('@admin-remover-'.$colega->id)
                ->waitFor('@confirmacao')
                ->press('@confirmacao-confirmar')
                ->waitUntilMissing('@admin-linha-'.$colega->id);
        });

        $this->assertNull($colega->fresh()?->permissoes_admin);
    }
}

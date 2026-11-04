<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Models\GlossarioTermo;
use App\Models\Recomendacao;
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
                "return getComputedStyle(document.querySelector('[dusk=\"admin-remover-{$colega->id}\"]')).backgroundColor;",
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
            $termoId = GlossarioTermo::query()->where('termo', 'Termo E2E')->value('id');

            // Editar leva ao topo da tela, com o foco na definição.
            $browser->resize(1280, 700)
                ->scrollIntoView('@glossario-editar-'.$termoId);
            $this->assertGreaterThan(0, $browser->script('return window.scrollY;')[0]);
            $browser->press('@glossario-editar-'.$termoId)
                ->waitUsing(5, 100, fn () => $browser->script(
                    "return document.activeElement.id === 'glossario-definicao'
                        && document.activeElement.value === 'Definição E2E'
                        && window.scrollY === 0;",
                )[0] === true)
                ->press('@glossario-cancelar');

            // Excluir usa a cor de alerta (#E11D48) e pede confirmação: cancelar mantém o termo.
            $this->assertSame('rgb(225, 29, 72)', $browser->script(
                "return getComputedStyle(document.querySelector('[dusk=\"glossario-excluir-{$termoId}\"]')).backgroundColor;",
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

    /**
     * STORY-068 — sem filtros: escolhe o indicador, edita direto nos 9 textos, troca de
     * indicador sem perder o que digitou e salva tudo de uma vez.
     */
    public function test_admin_com_permissao_edita_recomendacoes(): void
    {
        $colega = Usuario::factory()->create(['email' => 'colega@exemplo.com.br']);
        $colega->forceFill(['permissoes_admin' => ['recomendacoes.editar']])->save();
        $industria = Recomendacao::query()->where(['setor' => 'industria', 'indicador' => 'margem_bruta', 'farol' => 'verde'])->firstOrFail();
        $comercio = Recomendacao::query()->where(['setor' => 'comercio', 'indicador' => 'pmr', 'farol' => 'amarelo'])->firstOrFail();
        $originais = [[$industria, $industria->only('texto', 'editado_em')], [$comercio, $comercio->only('texto', 'editado_em')]];

        try {
            $this->browse(function (Browser $browser) use ($colega) {
                $browser->loginAs($colega)
                    ->visit('/home')
                    ->waitFor('@app-nav-admin')
                    ->assertMissing('@app-nav-backoffice-glossario')
                    ->click('@app-nav-backoffice-recomendacoes')
                    ->waitForLocation('/backoffice/recomendacoes')
                    ->waitFor('@recomendacao-industria-verde');

                // A área de trabalho é toda usada, e os campos de uma mesma faixa têm a mesma altura.
                $browser->resize(1600, 1000)->press('@recomendacoes-ir-margem_liquida')->waitForTextIn('@recomendacoes-titulo', 'Margem Líquida');
                [$livre, $conteudo, $alturas] = $browser->script(<<<'JS'
                    const main = document.querySelector('[data-testid="app-main"]');
                    const estilo = getComputedStyle(main);
                    const campos = document.querySelectorAll('textarea[dusk$="-vermelho"]');
                    return [
                        main.clientWidth - parseFloat(estilo.paddingLeft) - parseFloat(estilo.paddingRight),
                        main.firstElementChild.offsetWidth,
                        [...new Set([...campos].map((c) => c.offsetHeight))],
                    ];
                JS)[0];
                $this->assertEqualsWithDelta($livre, $conteudo, 1);
                $this->assertSame([0, 0], $browser->script(<<<'JS'
                    const abas = document.querySelector('[role="tablist"]');
                    return [abas.scrollWidth - abas.clientWidth, abas.scrollHeight - abas.clientHeight];
                JS)[0], 'As 13 abas deveriam caber em uma linha, sem barra de rolagem.');
                $this->assertCount(1, $alturas, 'Campos da mesma faixa deveriam ter a mesma altura nos três cards.');
                $browser->press('@recomendacoes-ir-margem_bruta')->waitForTextIn('@recomendacoes-titulo', 'Margem Bruta');

                // Abas pelo teclado: a seta move o foco e o Enter ativa.
                $browser->click('@recomendacoes-ir-margem_bruta')
                    ->keys('@recomendacoes-ir-margem_bruta', WebDriverKeys::ARROW_RIGHT);
                $this->assertSame('aba-margem_ebitda', $browser->script('return document.activeElement.id;')[0]);
                $browser->keys('@recomendacoes-ir-margem_ebitda', WebDriverKeys::ENTER)
                    ->waitForTextIn('@recomendacoes-titulo', 'Margem EBITDA')
                    ->press('@recomendacoes-ir-margem_bruta')
                    ->waitForTextIn('@recomendacoes-titulo', 'Margem Bruta');

                $browser->assertSeeIn('@recomendacao-industria-verde-faixa', '25,01% +')
                    ->assertSeeIn('@recomendacao-comercio-vermelho-faixa', 'até 18%')
                    ->assertMissing('@recomendacoes-barra')
                    // Vazio: bloqueia ao salvar e mostra o erro na própria célula.
                    ->type('@recomendacao-industria-verde', ' ')
                    ->keys('@recomendacao-industria-verde', WebDriverKeys::TAB)
                    ->waitFor('@recomendacoes-salvar')
                    ->press('@recomendacoes-salvar')
                    ->waitFor('@recomendacao-industria-verde-erro')
                    ->assertSeeIn('@recomendacao-industria-verde-erro', 'Informe o texto da recomendação.')
                    // Texto válido, com contador ao vivo.
                    ->type('@recomendacao-industria-verde', 'Manter a margem.')
                    ->keys('@recomendacao-industria-verde', WebDriverKeys::TAB)
                    ->waitForTextIn('@recomendacoes-pendentes', '1 alteração não salva')
                    ->assertSee('16/300')
                    // Troca de indicador sem perder a alteração.
                    ->press('@recomendacoes-ir-pmr')
                    ->waitForTextIn('@recomendacoes-titulo', 'PMR')
                    ->assertPresent('@recomendacoes-ir-margem_bruta .tab__marca')
                    ->assertInputValue('@recomendacao-comercio-amarelo', 'Vender a prazos menores.')
                    ->type('@recomendacao-comercio-amarelo', 'Vender com prazo curto.')
                    ->keys('@recomendacao-comercio-amarelo', WebDriverKeys::TAB)
                    ->waitForTextIn('@recomendacoes-pendentes', '2 alterações não salvas')
                    // Atalho de teclado salva o campo em foco.
                    ->keys('@recomendacao-comercio-amarelo', [WebDriverKeys::CONTROL, 's'])
                    ->waitFor('@recomendacoes-mensagem')
                    ->assertSeeIn('@recomendacoes-mensagem', '2 textos salvos.')
                    ->assertMissing('@recomendacoes-barra')
                    ->assertMissing('@recomendacoes-ir-margem_bruta .tab__marca')
                    ->assertSeeIn('@recomendacoes-versao-servicos', 'dez-2025')
                    ->assertDontSeeIn('@recomendacoes-versao-industria', 'dez-2025');
            });

            $this->assertSame('Manter a margem.', $industria->fresh()?->texto);
            $this->assertSame('Vender com prazo curto.', $comercio->fresh()?->texto);
        } finally {
            // A truncagem deste teste não inclui `recomendacoes`: os textos vêm da migration.
            foreach ($originais as [$registro, $valores]) {
                Recomendacao::query()->whereKey($registro->id)->update($valores);
            }
        }
    }
}

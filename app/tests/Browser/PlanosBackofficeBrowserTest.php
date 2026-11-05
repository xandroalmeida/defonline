<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Models\PacoteCredito;
use App\Models\TrialConfig;
use App\Models\Usuario;
use Facebook\WebDriver\WebDriverKeys;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * STORY-071 — o admin com `planos.gerenciar` chega pelo menu, cria um pacote barato (recebe o
 * aviso e salva), desativa-o e tenta salvar um trial sem dias.
 *
 * NÃO está no grupo `smoke` por design — faz writes.
 */
final class PlanosBackofficeBrowserTest extends DuskTestCase
{
    use DatabaseTruncation;

    /** @var list<string> */
    protected array $tablesToTruncate = ['usuarios', 'audit_logs', 'sessions', 'cache'];

    public function test_admin_cria_pacote_com_aviso_desativa_e_valida_o_trial(): void
    {
        $admin = Usuario::factory()->create();
        $admin->forceFill(['permissoes_admin' => ['planos.gerenciar']])->save();

        try {
            $this->browse(function (Browser $browser) use ($admin) {
                $browser->loginAs($admin)
                    ->visit('/home')
                    ->waitFor('@app-nav-admin')
                    ->assertMissing('@app-nav-backoffice-usuarios')
                    ->click('@app-nav-backoffice-planos')
                    ->waitForLocation('/backoffice/planos')
                    ->waitFor('@planos-lista')
                    ->assertSeeIn('@planos-lista', 'Básico')
                    ->assertSeeIn('@pacotes-lista', 'R$ 349,90')
                    ->type('@pacote-nome', 'Barato E2E')
                    ->type('@pacote-creditos', '10')
                    ->type('@pacote-preco', '400,00')
                    ->waitFor('@pacote-aviso-form')
                    ->assertSeeIn('@pacote-aviso-form', 'plano Básico')
                    ->press('@pacote-salvar')
                    ->waitForTextIn('@pacotes-lista', 'Barato E2E');

                $id = PacoteCredito::query()->where('nome', 'Barato E2E')->value('id');

                $browser->assertSeeIn('@pacote-linha-'.$id, 'Abaixo do unitário do plano Básico')
                    ->press('@pacote-alternar-'.$id)
                    ->waitForTextIn('@pacote-situacao-'.$id, 'Inativo');

                $browser->keys('@trial-dias', [WebDriverKeys::BACKSPACE, WebDriverKeys::BACKSPACE])
                    ->press('@trial-salvar')
                    ->waitFor('@erro-trial-dias')
                    ->assertSeeIn('@erro-trial-dias', 'Informe os dias do trial.')
                    ->keys('@trial-dias', '10')
                    ->press('@trial-salvar')
                    ->waitFor('@trial-mensagem')
                    ->assertSeeIn('@trial-mensagem', 'Trial salvo.');
            });

            $this->assertSame(10, TrialConfig::atual()->dias);
        } finally {
            // O catálogo não é truncado entre testes: devolve o que alterou.
            PacoteCredito::query()->where('nome', 'Barato E2E')->delete();
            TrialConfig::atual()->update(['dias' => 7]);
        }
    }
}

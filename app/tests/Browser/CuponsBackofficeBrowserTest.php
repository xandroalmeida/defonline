<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Models\Cupom;
use App\Models\CupomConcessao;
use App\Models\Plano;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * STORY-072 — o admin com `cupons.gerenciar` chega pelo menu, vê o erro por campo, cria um cupom
 * de 100% para o beta, confere quantas contas serão afetadas, concede e desativa.
 *
 * NÃO está no grupo `smoke` por design — faz writes.
 */
final class CuponsBackofficeBrowserTest extends DuskTestCase
{
    use DatabaseTruncation;

    /** @var list<string> */
    protected array $tablesToTruncate = ['usuarios', 'audit_logs', 'sessions', 'cache', 'cupons'];

    public function test_admin_cria_cupom_concede_ao_beta_e_desativa(): void
    {
        $admin = Usuario::factory()->create();
        $admin->forceFill(['permissoes_admin' => ['cupons.gerenciar']])->save();
        Usuario::factory()->count(2)->create();
        $planoId = Plano::query()->orderBy('ordem')->value('id');

        // O campo de data segue o idioma do navegador e não aceita apagar e redigitar: define-se pelo valor ISO.
        $definirData = fn (string $dusk, string $iso): string => "{ const f = document.querySelector('[dusk=\"{$dusk}\"]'); f.value = '{$iso}'; f.dispatchEvent(new Event('input', {bubbles: true})); }";

        $this->browse(function (Browser $browser) use ($admin, $planoId, $definirData) {
            $browser->loginAs($admin)
                ->visit('/home')
                ->waitFor('@app-nav-admin')
                ->assertMissing('@app-nav-backoffice-planos')
                ->click('@app-nav-backoffice-cupons')
                ->waitForLocation('/backoffice/cupons')
                ->waitFor('@cupons-lista')
                ->assertSeeIn('@cupons-lista', 'Nenhum cupom criado ainda.')
                // Percentual fora da faixa, vigência invertida e nenhum item: mensagem por campo.
                ->type('@cupom-codigo', 'beta')
                ->type('@cupom-valor', '150')
                ->script($definirData('cupom-inicio', '2026-12-01').$definirData('cupom-fim', '2026-11-01'));

            $browser->pause(300)
                ->press('@cupom-salvar')
                ->waitFor('@erro-cupom-valor')
                ->assertSeeIn('@erro-cupom-valor', 'O percentual deve ficar entre 1 e 100.')
                ->assertSeeIn('@erro-cupom-fim', 'O fim da vigência não pode ser anterior ao início.')
                ->assertSeeIn('@erro-cupom-itens', 'Escolha ao menos um item coberto.')
                // Corrige e cria.
                ->type('@cupom-valor', '100')
                ->script($definirData('cupom-fim', '2026-12-31'));

            $browser->pause(300)
                ->check('@cupom-item-plano-'.$planoId.'-mensal')
                ->press('@cupom-salvar')
                ->waitForTextIn('@cupons-lista', 'BETA');

            $cupom = Cupom::query()->where('codigo', 'BETA')->firstOrFail();

            $browser->assertSeeIn('@cupom-linha-'.$cupom->id, '100%')
                ->press('@cupom-conceder-'.$cupom->id)
                ->waitFor('@concessao-modo-todas')
                ->click('@concessao-modo-todas')
                ->waitFor('@concessao-ate')
                ->script($definirData('concessao-ate', date('Y-m-d')));

            $browser->pause(300)
                ->press('@concessao-calcular')
                ->waitFor('@concessao-previa')
                ->assertSeeIn('@concessao-previa', 'Serão afetadas 3 contas')
                ->press('@concessao-confirmar')
                ->acceptDialog()
                ->waitFor('@cupom-mensagem')
                ->assertSeeIn('@cupom-mensagem', '3 contas receberam o cupom BETA.')
                ->assertSeeIn('@cupom-concessoes-'.$cupom->id, '3')
                ->press('@cupom-alternar-'.$cupom->id)
                ->waitForTextIn('@cupom-situacao-'.$cupom->id, 'Inativo')
                ->assertMissing('@cupom-conceder-'.$cupom->id);

            $this->assertSame(3, CupomConcessao::query()->where('cupom_id', $cupom->id)->count());
            $this->assertFalse($cupom->fresh()->ativo);
        });
    }
}

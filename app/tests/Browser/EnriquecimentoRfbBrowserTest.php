<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Models\EmpresaAnalisada;
use App\Models\Usuario;
use Database\Factories\EmpresaAnalisadaFactory;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Dusk E2E da STORY-015 CA-7 — fluxo `cadastrar empresa com CNPJ → consultar
 * Receita → ver pré-preenchimento → submit → badge Receita Federal`.
 *
 * Não está no grupo `smoke` por design (escreve no banco). Smoke pós-deploy
 * read-only é responsabilidade da bateria CadastroLoginSmokeBrowserTest.
 */
final class EnriquecimentoRfbBrowserTest extends DuskTestCase
{
    use DatabaseTruncation;

    /** @var list<string> */
    protected array $tablesToTruncate = ['empresas_analisadas', 'usuarios', 'audit_logs', 'business_metrics', 'term_acceptances', 'sessions'];

    public function test_fluxo_completo_de_enriquecimento_via_rfb(): void
    {
        $usuario = Usuario::factory()->create([
            'email' => 'roberto.rfb@exemplo.com.br',
        ]);
        $cnpj = EmpresaAnalisadaFactory::cnpjComRaiz('12345678');

        $this->browse(function (Browser $browser) use ($usuario, $cnpj) {
            $browser->loginAs($usuario)
                ->visit('/empresas/nova')
                ->assertSee('Cadastrar empresa')
                ->radio('@empresa-tipo-cnpj', 'cnpj')
                ->type('@empresa-documento', $cnpj)
                ->waitFor('@empresa-consultar-receita')
                ->waitUntilEnabled('@empresa-consultar-receita')
                ->click('@empresa-consultar-receita')
                ->waitFor('@empresa-enriquecido-ok')
                ->assertSeeIn('@empresa-enriquecido-ok', 'Receita Federal')
                ->assertInputValueIsNot('@empresa-razao-social', '')
                ->assertInputValueIsNot('@empresa-municipio', '')
                ->press('@empresa-submit')
                ->waitFor('@empresa-show-fonte')
                ->assertSeeIn('@empresa-show-fonte', 'Receita Federal');
        });

        $empresa = EmpresaAnalisada::firstWhere('usuario_id', $usuario->id);
        $this->assertNotNull($empresa);
        $this->assertSame('rfb', $empresa->fonte_enriquecimento->value);
        $this->assertNotNull($empresa->enriquecido_at);
    }

    public function test_fallback_mostra_aviso_amarelo_quando_provedor_falha(): void
    {
        $usuario = Usuario::factory()->create([
            'email' => 'roberto.fallback@exemplo.com.br',
        ]);
        $cnpjTimeout = EmpresaAnalisadaFactory::cnpjComRaiz('99887766');

        $this->browse(function (Browser $browser) use ($usuario, $cnpjTimeout) {
            $browser->loginAs($usuario)
                ->visit('/empresas/nova')
                ->radio('@empresa-tipo-cnpj', 'cnpj')
                ->type('@empresa-documento', $cnpjTimeout)
                ->waitFor('@empresa-consultar-receita')
                ->waitUntilEnabled('@empresa-consultar-receita')
                ->click('@empresa-consultar-receita')
                ->waitFor('@empresa-fallback')
                ->assertSeeIn('@empresa-fallback', 'Não conseguimos consultar a Receita agora')
                ->assertInputValue('@empresa-razao-social', '');
        });
    }
}

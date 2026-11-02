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
 * Dusk E2E da STORY-063 CA-11 (antes STORY-015 CA-7) — CNPJ → ficha RFB
 * só-leitura → observação → confirmar → tela da empresa; e falha da Receita
 * bloqueando o cadastro (PDR-010).
 *
 * Não está no grupo `smoke` por design (escreve no banco). Smoke pós-deploy
 * read-only é responsabilidade da bateria CadastroLoginSmokeBrowserTest.
 */
final class EnriquecimentoRfbBrowserTest extends DuskTestCase
{
    use DatabaseTruncation;

    /** @var list<string> */
    protected array $tablesToTruncate = ['empresas_analisadas', 'usuarios', 'audit_logs', 'business_metrics', 'term_acceptances', 'sessions'];

    public function test_cnpj_ficha_observacao_confirmar_e_tela_da_empresa(): void
    {
        $usuario = Usuario::factory()->create([
            'email' => 'roberto.rfb@exemplo.com.br',
        ]);
        // Mock: Simples optante, 3 CNAEs secundários, 3 sócios (STORY-063 CA-10).
        $cnpj = EmpresaAnalisadaFactory::cnpjComRaiz('45237000');

        $this->browse(function (Browser $browser) use ($usuario, $cnpj) {
            $browser->loginAs($usuario)
                ->visit('/empresas/nova')
                ->assertSee('Cadastrar empresa')
                ->assertMissing('@empresa-razao-social')
                ->type('@empresa-documento', $cnpj)
                ->press('@empresa-consultar-receita')
                ->waitFor('@ficha-rfb')
                ->assertSeeIn('@empresa-enriquecido-ok', 'Receita Federal')
                ->assertSeeIn('@ficha-simples', 'Optante')
                ->assertVisible('@ficha-cnaes-secundarios')
                ->assertMissing('@empresa-razao-social')
                ->type('@empresa-observacoes', 'Indicação do contador Marcos.')
                ->press('@empresa-confirmar')
                ->waitFor('@empresa-show-fonte')
                ->assertSeeIn('@empresa-show-fonte', 'Receita Federal')
                ->assertVisible('@ficha-rfb')
                ->assertSeeIn('@ficha-simples', 'Optante')
                ->assertInputValue('@empresa-show-observacoes', 'Indicação do contador Marcos.')
                ->clear('@empresa-show-observacoes')
                ->type('@empresa-show-observacoes', 'Retornar em dezembro.')
                ->press('@empresa-show-salvar-observacoes')
                ->waitFor('@empresa-show-observacoes-salvas');

            $this->assertCount(3, $browser->elements('@ficha-cnae-secundario'));
            $this->assertCount(3, $browser->elements('@ficha-socio'));
        });

        $empresa = EmpresaAnalisada::firstWhere('usuario_id', $usuario->id);
        $this->assertNotNull($empresa);
        $this->assertSame('rfb', $empresa->fonte_enriquecimento->value);
        $this->assertNotNull($empresa->enriquecido_at);
        $this->assertNotNull($empresa->ficha_rfb);
        $this->assertSame('Retornar em dezembro.', $empresa->observacoes);
    }

    public function test_falha_da_receita_bloqueia_sem_liberar_preenchimento_manual(): void
    {
        $usuario = Usuario::factory()->create([
            'email' => 'roberto.fallback@exemplo.com.br',
        ]);
        $cnpjTimeout = EmpresaAnalisadaFactory::cnpjComRaiz('99887766');

        $this->browse(function (Browser $browser) use ($usuario, $cnpjTimeout) {
            $browser->loginAs($usuario)
                ->visit('/empresas/nova')
                ->type('@empresa-documento', $cnpjTimeout)
                ->press('@empresa-consultar-receita')
                ->waitFor('@empresa-erro-consulta')
                ->assertSeeIn('@empresa-erro-consulta', 'Tente novamente em alguns minutos')
                ->assertMissing('@ficha-rfb')
                ->assertMissing('@empresa-razao-social')
                ->assertMissing('@empresa-confirmar');
        });

        $this->assertSame(0, EmpresaAnalisada::count());
    }
}

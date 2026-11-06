<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Models\EmpresaAnalisada;
use App\Models\EventoProduto;
use App\Models\Usuario;
use Database\Factories\EmpresaAnalisadaFactory;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Dusk\Browser;
use Tests\Browser\Concerns\VerificaAlinhamento;
use Tests\DuskTestCase;

/**
 * STORY-016 CA-7 — Dusk E2E do fluxo completo do EPIC-001:
 *
 *   cadastrar Usuário → confirmar email → cadastrar Empresa via RFB mock
 *   → ver Minhas Empresas com a empresa listada e badge "Receita Federal".
 *
 * Cadastro e confirmação programáticos via factory + update direto (o E2E do
 * link assinado vive em EmailConfirmacaoBrowserTest). Aqui o foco é o final
 * do fluxo: tela "Minhas Empresas" + evidência dos dois eventos de produto.
 *
 * NÃO está no grupo `smoke` por design — faz writes.
 */
final class MinhasEmpresasBrowserTest extends DuskTestCase
{
    use DatabaseTruncation, VerificaAlinhamento;

    /** @var list<string> */
    protected array $tablesToTruncate = [
        'empresas_analisadas', 'usuarios', 'audit_logs', 'business_metrics',
        'term_acceptances', 'sessions', 'evento_produto', 'cache',
    ];

    public function test_fluxo_completo_cadastra_empresa_via_rfb_e_ve_em_minhas_empresas(): void
    {
        $usuario = Usuario::factory()->create([
            'email' => 'roberto.minhas@exemplo.com.br',
            'nome' => 'Roberto Souza',
        ]);
        $cnpj = EmpresaAnalisadaFactory::cnpjComRaiz('12345678');

        $this->browse(function (Browser $browser) use ($usuario, $cnpj) {
            $browser->loginAs($usuario)
                ->visit('/home')
                ->waitFor('@minhas-empresas-vazio')
                ->assertSee('Olá, Roberto')
                ->assertSee('Nenhuma empresa cadastrada ainda')
                ->click('@minhas-empresas-cta-cadastrar')
                ->waitForLocation('/empresas/nova')
                ->type('@empresa-documento', $cnpj)
                ->press('@empresa-consultar-receita')
                ->waitFor('@empresa-enriquecido-ok')
                ->press('@empresa-confirmar')
                ->waitFor('@empresa-show-fonte')
                ->assertSeeIn('@empresa-show-fonte', 'Receita Federal')
                ->click('@empresa-show-voltar')
                ->waitForLocation('/home')
                ->waitFor('@minhas-empresas-lista');

            /** @var EmpresaAnalisada $empresa */
            $empresa = EmpresaAnalisada::firstWhere('usuario_id', $usuario->id);

            $browser->assertSeeIn('@minhas-empresas-fonte-'.$empresa->id, 'Receita Federal')
                ->assertSeeIn('@minhas-empresas-local-'.$empresa->id, '/');

            // Documento mascarado: bruto NÃO deve estar no DOM.
            $this->assertStringNotContainsString(
                $cnpj,
                $browser->driver->getPageSource(),
                'CNPJ cru não pode aparecer na tela Minhas Empresas',
            );

            // Botão "Iniciar diagnóstico" presente e habilitado (STORY-027 ativou o quiz).
            $browser->assertVisible('@minhas-empresas-diagnostico-'.$empresa->id)
                ->assertAttribute(
                    '@minhas-empresas-diagnostico-'.$empresa->id,
                    'href',
                    url(route('diagnosticos.novo', $empresa, absolute: false)),
                );
        });

        // Evidência da emissão dos dois eventos de produto (CA-3 + CA-4).
        // `usuario_cadastrado` não é emitido porque a factory já cria o usuário
        // confirmado (sem passar pelo controller de confirmação) — esse caminho
        // tem cobertura dedicada em EventosCadastroTest. Aqui exercitamos o
        // `empresa_cadastrada` no fluxo real do browser.
        $empresa = EmpresaAnalisada::firstWhere('usuario_id', $usuario->id);
        $this->assertNotNull($empresa);

        $evento = EventoProduto::where('nome_evento', 'empresa_cadastrada')
            ->where('empresa_id', $empresa->id)
            ->first();
        $this->assertNotNull($evento, 'evento empresa_cadastrada deveria existir após o fluxo');
        $this->assertSame($usuario->id, $evento->usuario_id);
        $this->assertSame('rfb', ((array) $evento->propriedades)['fonte_enriquecimento']);
    }

    /** RNF-UI-02: cards vizinhos com conteúdo de alturas diferentes, nas duas telas que listam empresas. */
    public function test_cards_de_empresa_ficam_alinhados_e_sem_rolagem_lateral_no_celular(): void
    {
        $usuario = Usuario::factory()->create();
        $curta = EmpresaAnalisada::factory()->create(['usuario_id' => $usuario->id, 'nome_fantasia' => 'Padaria', 'razao_social' => 'Padaria Ltda']);
        $longa = EmpresaAnalisada::factory()->create([
            'usuario_id' => $usuario->id,
            'nome_fantasia' => 'Indústria e Comércio de Alimentos do Nordeste',
            'razao_social' => 'Indústria e Comércio de Alimentos do Nordeste Brasileiro Sociedade Limitada',
        ]);

        $this->browse(function (Browser $browser) use ($usuario, $curta, $longa) {
            $browser->resize(1280, 900)->loginAs($usuario)->visit('/home')->waitFor('@minhas-empresas-lista');
            $this->assertAlinhados($browser, [
                ["minhas-empresas-item-{$curta->id}", "minhas-empresas-item-{$longa->id}"],
                ["minhas-empresas-diagnostico-{$curta->id}", "minhas-empresas-diagnostico-{$longa->id}"],
            ]);

            $browser->visit('/diagnosticos/novo')->waitFor('@selecionar-empresa-lista');
            $this->assertAlinhados($browser, [
                ["selecionar-empresa-item-{$curta->id}", "selecionar-empresa-item-{$longa->id}"],
                ["selecionar-empresa-cta-{$curta->id}", "selecionar-empresa-cta-{$longa->id}"],
            ]);

            $browser->resize(390, 1200)->visit('/home')->waitFor('@minhas-empresas-lista');
            $this->assertFalse($browser->script('return document.documentElement.scrollWidth > window.innerWidth')[0], 'A tela não pode rolar para o lado no celular.');
            $browser->resize(1920, 1080); // O navegador é o mesmo no teste seguinte, que usa o menu lateral.
        });
    }
}

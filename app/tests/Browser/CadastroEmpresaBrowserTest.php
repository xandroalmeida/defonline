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
 * E2E browser real cobrindo STORY-014 CA-7:
 * usuário autenticado → /empresas/nova → preenche manualmente → /empresas/{id} read-only.
 *
 * Não está no grupo `smoke` por design — escreve no banco. Smoke pós-deploy
 * read-only é responsabilidade da bateria CadastroLoginSmokeBrowserTest e
 * equivalentes.
 */
final class CadastroEmpresaBrowserTest extends DuskTestCase
{
    use DatabaseTruncation;

    /** @var list<string> */
    protected array $tablesToTruncate = ['empresas_analisadas', 'usuarios', 'audit_logs', 'term_acceptances', 'sessions'];

    public function test_usuario_autenticado_cadastra_empresa_manualmente_e_ve_tela_read_only(): void
    {
        $usuario = Usuario::factory()->create([
            'email' => 'roberto.empresa@exemplo.com.br',
        ]);
        $cnpj = EmpresaAnalisadaFactory::gerarCnpjValido();

        $this->browse(function (Browser $browser) use ($usuario, $cnpj) {
            $browser->loginAs($usuario)
                ->visit('/empresas/nova')
                ->assertSee('Cadastrar empresa')
                ->radio('@empresa-tipo-cnpj', 'cnpj')
                ->type('@empresa-documento', $cnpj)
                ->type('@empresa-razao-social', 'Marcenaria Roberto LTDA')
                ->type('@empresa-nome-fantasia', 'Marcenaria Roberto')
                ->type('@empresa-cnae', '1622699')
                ->type('@empresa-municipio', 'São Paulo')
                ->select('@empresa-uf', 'SP')
                ->select('@empresa-situacao', 'ativa')
                ->press('@empresa-submit')
                ->waitForText('Marcenaria Roberto LTDA')
                ->assertSeeIn('@empresa-show-titulo', 'Marcenaria Roberto LTDA')
                ->assertSeeIn('@empresa-show-fonte', 'Preenchimento manual')
                ->assertSeeIn('@empresa-show-localizacao', 'São Paulo / SP');
        });

        $empresa = EmpresaAnalisada::firstWhere('usuario_id', $usuario->id);
        $this->assertNotNull($empresa);
        $this->assertSame($cnpj, $empresa->documento);
        $this->assertSame('Marcenaria Roberto LTDA', $empresa->razao_social);
    }
}

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
 * E2E browser real cobrindo STORY-014 CA-7 e STORY-063 CA-9: autônomo sem
 * CNPJ → "Não tenho CNPJ" → preenche manualmente → /empresas/{id}.
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

    public function test_autonomo_cadastra_empresa_por_cpf_manualmente(): void
    {
        $usuario = Usuario::factory()->create([
            'email' => 'roberto.empresa@exemplo.com.br',
        ]);
        $cpf = EmpresaAnalisadaFactory::gerarCpfValido();

        $this->browse(function (Browser $browser) use ($usuario, $cpf) {
            $browser->loginAs($usuario)
                ->visit('/empresas/nova')
                ->assertSee('Cadastrar empresa')
                ->click('@empresa-usar-cpf')
                ->waitFor('@empresa-razao-social')
                ->type('@empresa-documento', $cpf)
                ->type('@empresa-razao-social', 'Marcenaria Roberto LTDA')
                ->type('@empresa-nome-fantasia', 'Marcenaria Roberto')
                ->type('@empresa-cnae', '1622699')
                ->type('@empresa-municipio', 'São Paulo')
                ->select('@empresa-uf', 'SP')
                ->select('@empresa-situacao', 'nao_informada')
                ->press('@empresa-submit')
                ->waitForText('Marcenaria Roberto LTDA')
                ->assertSeeIn('@empresa-show-titulo', 'Marcenaria Roberto LTDA')
                ->assertSeeIn('@empresa-show-fonte', 'Preenchimento manual')
                ->assertSeeIn('@empresa-show-localizacao', 'São Paulo / SP');
        });

        $empresa = EmpresaAnalisada::firstWhere('usuario_id', $usuario->id);
        $this->assertNotNull($empresa);
        $this->assertSame($cpf, $empresa->documento);
        $this->assertSame('cpf', $empresa->tipo_documento->value);
        $this->assertSame('Marcenaria Roberto LTDA', $empresa->razao_social);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Domain\TipoDocumento;
use App\Models\EmpresaAnalisada;
use App\Models\Usuario;
use App\Services\Rfb\MockRfbCnpjClient;
use Database\Factories\EmpresaAnalisadaFactory;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/** PDR-017 — edição do nome fantasia no próprio lugar, na ficha da empresa. */
final class NomeFantasiaInPlaceBrowserTest extends DuskTestCase
{
    use DatabaseTruncation;

    /** @var list<string> */
    protected array $tablesToTruncate = ['empresas_analisadas', 'usuarios', 'audit_logs', 'term_acceptances', 'sessions'];

    public function test_no_cadastro_aplica_o_nome_na_ficha_e_grava_ao_confirmar(): void
    {
        $usuario = Usuario::factory()->create();
        $cnpj = EmpresaAnalisadaFactory::cnpjComRaiz('45237000');

        $this->browse(function (Browser $browser) use ($usuario, $cnpj) {
            $browser->loginAs($usuario)
                ->visit('/empresas/nova')
                ->type('@empresa-documento', $cnpj)
                ->press('@empresa-consultar-receita')
                ->waitFor('@empresa-editar-nome-fantasia')
                ->assertMissing('@empresa-nome-fantasia-input')
                ->click('@empresa-editar-nome-fantasia')
                ->waitFor('@empresa-nome-fantasia-input')
                ->type('@empresa-nome-fantasia-input', 'Marca do Cadastro')
                ->press('@empresa-salvar-nome-fantasia')
                ->waitFor('@empresa-nome-na-receita')
                ->assertSeeIn('@ficha-nome-fantasia', 'Marca do Cadastro')
                ->press('@empresa-confirmar')
                ->waitFor('@empresa-show-nome-na-receita')
                ->assertSeeIn('h1', 'Marca do Cadastro');
        });

        expect(EmpresaAnalisada::query()->withoutGlobalScopes()->where('documento', $cnpj)->firstOrFail()->nome_fantasia_informado)
            ->toBe('Marca do Cadastro');
    }

    public function test_edita_cancela_e_salva_o_nome_na_ficha(): void
    {
        $usuario = Usuario::factory()->create();
        $cnpj = EmpresaAnalisadaFactory::cnpjComRaiz('45237000');
        $empresa = EmpresaAnalisada::factory()->create([
            'usuario_id' => $usuario->id,
            'tipo_documento' => TipoDocumento::Cnpj,
            'documento' => $cnpj,
            'razao_social' => 'Razão Social LTDA',
            'nome_fantasia' => 'Fantasia Receita',
            'ficha_rfb' => (new MockRfbCnpjClient)->consultarCnpj($cnpj)->ficha->toArray(),
        ]);

        $this->browse(function (Browser $browser) use ($usuario, $empresa) {
            $browser->loginAs($usuario)
                ->visit("/empresas/{$empresa->id}")
                ->assertSeeIn('@ficha-nome-fantasia', 'Fantasia Receita')
                ->assertMissing('@empresa-show-nome-fantasia-input')
                ->click('@empresa-show-editar-nome-fantasia')
                ->waitFor('@empresa-show-nome-fantasia-input')
                ->assertInputValue('@empresa-show-nome-fantasia-input', 'Fantasia Receita')
                ->type('@empresa-show-nome-fantasia-input', 'Rascunho')
                ->click('@empresa-show-cancelar-nome-fantasia')
                ->waitUntilMissing('@empresa-show-nome-fantasia-input')
                ->assertSeeIn('@ficha-nome-fantasia', 'Fantasia Receita')
                ->click('@empresa-show-editar-nome-fantasia')
                ->waitFor('@empresa-show-nome-fantasia-input')
                ->type('@empresa-show-nome-fantasia-input', 'Marca Nova')
                ->press('@empresa-show-salvar-nome-fantasia')
                ->waitFor('@empresa-show-nome-fantasia-salvo')
                ->assertSeeIn('@ficha-nome-fantasia', 'Marca Nova')
                ->assertSeeIn('@empresa-show-nome-na-receita', 'Fantasia Receita')
                ->assertSeeIn('h1', 'Marca Nova');
        });

        expect($empresa->refresh()->nome_fantasia)->toBe('Fantasia Receita');
    }
}

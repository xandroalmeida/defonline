<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Domain\FonteEnriquecimento;
use App\Models\EmpresaAnalisada;
use App\Models\Usuario;
use App\Support\DataExibicao;
use Database\Factories\EmpresaAnalisadaFactory;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Dusk E2E da STORY-064 CA-7 — empresa legada (manual) → aviso → "Atualizar dados
 * da Receita" → ficha completa, com as observações preservadas. Provedor mock.
 */
final class AtualizarReceitaBrowserTest extends DuskTestCase
{
    use DatabaseTruncation;

    /** @var list<string> */
    protected array $tablesToTruncate = ['empresas_analisadas', 'usuarios', 'audit_logs', 'business_metrics', 'term_acceptances', 'sessions'];

    public function test_empresa_legada_aviso_atualizar_ficha_completa_e_observacoes_preservadas(): void
    {
        $usuario = Usuario::factory()->create(['email' => 'legada.rfb@exemplo.com.br']);
        $empresa = EmpresaAnalisada::factory()->create([
            'usuario_id' => $usuario->id,
            'documento' => EmpresaAnalisadaFactory::cnpjComRaiz('45237000'),
            'razao_social' => 'Digitada à mão LTDA',
            'fonte_enriquecimento' => FonteEnriquecimento::Manual,
            'observacoes' => 'Cliente indicado pelo contador.',
        ]);

        $this->browse(function (Browser $browser) use ($usuario, $empresa) {
            $browser->loginAs($usuario)
                ->visit("/empresas/{$empresa->id}")
                ->waitFor('@empresa-aviso-legada')
                ->assertSeeIn('@empresa-aviso-legada', 'Estes dados não foram confirmados na Receita. Atualize para ver a ficha completa.')
                ->assertMissing('@ficha-rfb')
                ->assertSeeIn('@empresa-show-razao-social', 'Digitada à mão LTDA')
                ->press('@empresa-atualizar-receita')
                ->waitFor('@ficha-rfb')
                ->assertSeeIn('@empresa-atualizar-ok', 'Dados da Receita atualizados.')
                ->assertMissing('@empresa-aviso-legada')
                ->assertSeeIn('@empresa-show-fonte', 'Receita Federal')
                ->assertSeeIn('@ficha-simples', 'Optante')
                ->assertSeeIn('@empresa-show-consultado-em', 'Dados da Receita de '.DataExibicao::data(now()))
                ->assertInputValue('@empresa-show-observacoes', 'Cliente indicado pelo contador.');

            $this->assertCount(3, $browser->elements('@ficha-cnae-secundario'));
            $this->assertCount(3, $browser->elements('@ficha-socio'));
        });

        $this->assertSame('rfb', $empresa->fresh()->fonte_enriquecimento->value);
        $this->assertSame('Cliente indicado pelo contador.', $empresa->fresh()->observacoes);
    }
}

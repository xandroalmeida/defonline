<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Models\Diagnostico;
use App\Models\EmpresaAnalisada;
use App\Models\Usuario;
use Facebook\WebDriver\Chrome\ChromeDevToolsDriver;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Com a mídia de impressão ativa no Chrome, o relatório some e sobra a orientação
 * de usar o PDF. Na tela, é o contrário.
 *
 * NÃO está no grupo `smoke` por design — faz writes.
 */
final class DiagnosticoSemImpressaoBrowserTest extends DuskTestCase
{
    use DatabaseTruncation;

    /** @var list<string> */
    protected array $tablesToTruncate = ['diagnosticos', 'empresas_analisadas', 'usuarios', 'audit_logs', 'sessions', 'cache'];

    public function test_impressao_mostra_so_a_orientacao_de_usar_o_pdf(): void
    {
        $dono = Usuario::factory()->create();
        $empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $dono->id]);
        $diagnostico = Diagnostico::factory()->create(['usuario_id' => $dono->id, 'empresa_analisada_id' => $empresa->id]);

        $this->browse(function (Browser $browser) use ($dono, $diagnostico) {
            $browser->loginAs($dono)
                ->visit('/diagnosticos/'.$diagnostico->id)
                ->waitFor('@diagnostico-baixar-pdf')
                ->assertMissing('@impressao-bloqueada');

            (new ChromeDevToolsDriver($browser->driver))->execute('Emulation.setEmulatedMedia', ['media' => 'print']);

            $browser->assertVisible('@impressao-bloqueada')
                ->assertMissing('@diagnostico-baixar-pdf')
                ->assertDontSee('Glossário');
        });
    }
}

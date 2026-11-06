<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Models\Diagnostico;
use App\Models\EmpresaAnalisada;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/** STORY-099 — alerta em Minhas Empresas e na empresa, dispensa e escolha do prazo. */
class AvisoRenovacaoDiagnosticoBrowserTest extends DuskTestCase
{
    use DatabaseMigrations;

    public function test_usuario_ve_dispensa_e_muda_o_prazo_do_aviso(): void
    {
        $usuario = Usuario::factory()->create(['email_confirmed_at' => now()]);
        $empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $usuario->id]);
        $outra = EmpresaAnalisada::factory()->create(['usuario_id' => $usuario->id]);
        Diagnostico::factory()->paraEmpresa($empresa)->create(['gerado_em' => now()->subMonths(7)]);
        Diagnostico::factory()->paraEmpresa($outra)->create(['gerado_em' => now()->subMonths(7)]);

        $this->browse(function (Browser $browser) use ($usuario, $empresa, $outra) {
            $browser->resize(390, 1200)->loginAs($usuario)->visit('/home')
                ->waitFor("@renovacao-alerta-{$empresa->id}")
                ->pause(400);
            $this->assertFalse($browser->script('return document.documentElement.scrollWidth > window.innerWidth')[0], 'A tela não pode rolar para o lado no celular.');

            $browser->resize(1280, 900)->visit('/home')
                ->waitFor("@renovacao-alerta-{$empresa->id}")
                ->assertVisible("@renovacao-alerta-{$outra->id}")
                ->click("@renovacao-alerta-dispensar-{$outra->id}")
                ->waitUntilMissing("@renovacao-alerta-{$outra->id}")
                ->assertVisible("@renovacao-alerta-{$empresa->id}");

            $browser->visit("/empresas/{$empresa->id}")
                ->waitFor("@renovacao-alerta-{$empresa->id}")
                ->assertSelected('@renovacao-meses', '6')
                ->scrollIntoView('@renovacao-prazo')
                ->select('@renovacao-meses', '12')
                ->waitFor('@renovacao-meses-salvo')
                ->waitUntilMissing("@renovacao-alerta-{$empresa->id}");
        });

        $this->assertSame(12, $empresa->fresh()?->renovacao_diagnostico_meses);
    }
}

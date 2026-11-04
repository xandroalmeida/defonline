<?php

declare(strict_types=1);

use App\Models\Diagnostico;
use App\Models\EmpresaAnalisada;
use App\Models\Usuario;
use App\Support\Relatorio\PdfDiagnostico;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

uses(RefreshDatabase::class);

/*
| PDR-014 — NCG/Vendas na base mensal e Ciclo Operacional fora do relatório.
| Snapshot antigo continua exibindo o NCG/Vendas como foi calculado (IDR-010).
*/

function diagnosticoDrogaria(Usuario $dono, string $motor, float $ncgVendas): Diagnostico
{
    Auth::login($dono);
    $empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $dono->id]);

    $diag = Diagnostico::factory()->paraEmpresa($empresa)->create([
        'motor_version' => $motor,
        'quiz_payload' => ['Q03' => '1500', 'Q04' => '120000', 'Q07' => '30000', 'Q09' => '120000'],
        'indicadores_calculados' => array_merge(
            (array) Diagnostico::factory()->make()->indicadores_calculados,
            [
                'ncg_vendas' => ['valor' => $ncgVendas, 'farol' => 'amarelo', 'motivo' => null, 'mensagem' => 'Texto da matriz.'],
                'ciclo_operacional' => ['valor' => 50, 'farol' => 'nenhum', 'motivo' => null, 'mensagem' => 'Indicador informativo.'],
            ],
        ),
    ]);
    Auth::logout();

    return $diag;
}

it('motor 2.0.0 exibe a razão sobre a venda mensal', function () {
    $dono = Usuario::factory()->create();
    $diag = diagnosticoDrogaria($dono, '2.0.0', 0.7625);

    $this->actingAs($dono)->get("/diagnosticos/{$diag->id}")
        ->assertOk()
        ->assertSee('0,76')
        ->assertSee('Vendas (média mensal)')
        ->assertSee('R$ 120.000,00')
        ->assertDontSee('76,3%');
});

it('snapshot do motor 1.3.3 continua em percentual sobre as vendas de 12 meses', function () {
    $dono = Usuario::factory()->create();
    $diag = diagnosticoDrogaria($dono, '1.3.3', 0.0635);

    $this->actingAs($dono)->get("/diagnosticos/{$diag->id}")
        ->assertOk()
        ->assertSeeInOrder(['NCG / Vendas', '%'])
        ->assertSee('Vendas (12 meses)')
        ->assertSee('R$ 1.440.000,00');
});

it('Ciclo Operacional não aparece nem em snapshot antigo que o contém', function (string $motor) {
    $dono = Usuario::factory()->create();
    $diag = diagnosticoDrogaria($dono, $motor, 0.0635);

    $this->actingAs($dono)->get("/diagnosticos/{$diag->id}")
        ->assertOk()
        ->assertDontSee('Ciclo Operacional');
})->with(['1.3.3', '2.0.0']);

it('PDF exibe a razão mensal e o percentual antigo conforme a versão do snapshot', function () {
    $dono = Usuario::factory()->create();

    foreach ([['2.0.0', 0.7625, '0,76'], ['1.3.3', 0.0635, '6,']] as [$motor, $valor, $esperado]) {
        $diag = diagnosticoDrogaria($dono, $motor, $valor);
        $html = PdfDiagnostico::padrao()->html($diag);

        expect($html)->toContain($esperado)->not->toContain('Ciclo Operacional');
    }
});

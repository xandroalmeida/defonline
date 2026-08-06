<?php

declare(strict_types=1);

use App\Domain\Motor\Indicadores\NcgAbsoluto;
use App\Models\Diagnostico;
use App\Models\EmpresaAnalisada;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

uses(RefreshDatabase::class);

/*
|-------------------------------------------------------------------------
| STORY-060 CA-4 — diagnósticos legados preservam o texto com que nasceram.
|
| A estória reescreveu as três mensagens de faixa do NCG absoluto. Como elas
| entram em `diagnosticos.indicadores_calculados[*].mensagem` no momento da
| emissão, o relatório de um diagnóstico antigo tem que continuar exibindo a
| mensagem **da época** — IDR-010 §sub-decisão 2, sem exceção e sem recálculo
| retroativo.
|
| O modo de falha que este arquivo existe para pegar: alguém "melhorar" a view
| para resolver o texto a partir das constantes atuais em vez de ler o
| snapshot. O relatório passaria a mentir sobre o que foi dito ao usuário.
|-------------------------------------------------------------------------
*/

/** Mensagem exata que o motor 1.3.1 gravava na faixa MODERADO, antes da STORY-060. */
const MSG_MODERADO_1_3_1 = 'NCG positivo moderado: patamar gerenciável. Acompanhe seus prazos médios (PMC, PME e PMR).';

/** Mensagem exata que o motor 1.3.1 gravava na faixa ALTO, antes da STORY-060. */
const MSG_ALTO_1_3_1 = 'NCG positivo alto: pressão sobre o caixa. Considere rever prazos médios ou estruturar captação.';

/** Mensagem exata que o motor 1.3.1 gravava na faixa FOLGA, antes da STORY-060. */
const MSG_FOLGA_1_3_1 = 'Folga operacional: seu ciclo de pagamento a fornecedores cobre o ciclo de recebimento e estoques. Capital de giro positivo.';

function diagnosticoLegadoComNcg(Usuario $dono, string $mensagemDaEpoca, float $valor): Diagnostico
{
    Auth::login($dono);
    $empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $dono->id]);

    $diag = Diagnostico::factory()->paraEmpresa($empresa)->create([
        'motor_version' => '1.3.1',
        'indicadores_calculados' => array_merge(
            (array) Diagnostico::factory()->make()->indicadores_calculados,
            ['ncg_absoluto' => [
                'valor' => $valor,
                'farol' => 'nenhum',
                'motivo' => null,
                'mensagem' => $mensagemDaEpoca,
            ]],
        ),
    ]);
    Auth::logout();

    return $diag;
}

it('CA-4 — relatório de diagnóstico emitido em 1.3.1 exibe a mensagem MODERADO da época', function () {
    $dono = Usuario::factory()->create();
    $diag = diagnosticoLegadoComNcg($dono, MSG_MODERADO_1_3_1, 60000.0);

    $this->actingAs($dono)
        ->get("/diagnosticos/{$diag->id}")
        ->assertOk()
        ->assertSee(MSG_MODERADO_1_3_1)
        ->assertDontSee(NcgAbsoluto::MSG_MODERADO);
});

it('CA-4 — o mesmo vale para a faixa ALTO', function () {
    $dono = Usuario::factory()->create();
    $diag = diagnosticoLegadoComNcg($dono, MSG_ALTO_1_3_1, 144000.0);

    $this->actingAs($dono)
        ->get("/diagnosticos/{$diag->id}")
        ->assertOk()
        ->assertSee(MSG_ALTO_1_3_1)
        ->assertDontSee(NcgAbsoluto::MSG_ALTO);
});

it('CA-4 — e para a faixa FOLGA, com NCG negativo', function () {
    $dono = Usuario::factory()->create();
    $diag = diagnosticoLegadoComNcg($dono, MSG_FOLGA_1_3_1, -20000.0);

    $this->actingAs($dono)
        ->get("/diagnosticos/{$diag->id}")
        ->assertOk()
        ->assertSee(MSG_FOLGA_1_3_1)
        ->assertDontSee(NcgAbsoluto::MSG_FOLGA);
});

it('CA-4 (persistência) — reler o registro devolve a mensagem da época, não a atual', function () {
    $dono = Usuario::factory()->create();
    $diag = diagnosticoLegadoComNcg($dono, MSG_MODERADO_1_3_1, 60000.0);

    Auth::login($dono);
    $relido = Diagnostico::findOrFail($diag->id);
    Auth::logout();

    expect(((array) $relido->indicadores_calculados)['ncg_absoluto']['mensagem'])
        ->toBe(MSG_MODERADO_1_3_1)
        ->and($relido->motor_version)->toBe('1.3.1');
});

it('CA-4 (borda) — as três mensagens de 1.3.1 são diferentes das atuais, senão o teste não prova nada', function () {
    expect(MSG_FOLGA_1_3_1)->not->toBe(NcgAbsoluto::MSG_FOLGA)
        ->and(MSG_MODERADO_1_3_1)->not->toBe(NcgAbsoluto::MSG_MODERADO)
        ->and(MSG_ALTO_1_3_1)->not->toBe(NcgAbsoluto::MSG_ALTO);
});

<?php

declare(strict_types=1);

use App\Livewire\Diagnostico\Quiz;
use App\Models\EmpresaAnalisada;
use App\Models\Usuario;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * STORY-033 — tooltips/box de explicação por campo no quiz (§6.8 + Anexo A §A.6).
 *
 * Cobre CA-1 (23 campos com tooltip), CA-2 (texto editável só na config) e
 * CA-7 (view contém os textos esperados, lidos da config — nunca hard-coded).
 * O comportamento visual (popover vs bottom-sheet, Esc, click) é coberto pelo
 * Dusk em tests/Browser/QuizTooltipsBrowserTest.php (CA-3/CA-4/CA-5).
 */
beforeEach(function () {
    $this->usuario = Usuario::factory()->create();
    $this->actingAs($this->usuario);
    $this->empresa = EmpresaAnalisada::factory()
        ->state(['usuario_id' => $this->usuario->id])
        ->create();

    // CA-2/CA-7: a fonte da verdade é a config. O teste lê daqui, nunca de
    // literais — assim editar um texto na config não exige tocar o teste.
    $this->help = config('quiz.help-industria.campos');
});

/** Campos por bloco do quiz (espelha App\Livewire\Diagnostico\Quiz). */
function camposPorBloco(): array
{
    return [
        1 => ['Q01'],
        2 => ['Q08', 'Q09', 'Q14', 'Q15', 'Q16', 'Q10', 'Q11', 'Q12', 'Q13'],
        3 => ['Q02', 'Q03', 'Q04', 'Q05', 'Q06', 'Q07'],
        4 => ['Q17', 'Q18', 'Q19', 'Q20', 'Q21', 'Q22', 'Q23'],
    ];
}

/**
 * Replica a transformação do componente <x-help>: escapa o texto e promove
 * **negrito** a <strong>. O HTML resultante deve aparecer literal na view.
 */
function helpEsperado(string $texto): string
{
    return preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', e($texto));
}

/**
 * Monta o componente já posicionado no bloco pedido. Valores válidos espelham
 * o fixture saudável (não disparam erro de validação ao avançar). Em bloco 4,
 * marca Q17="sim" para revelar os campos condicionais Q18..Q23.
 */
function quizNoBloco(EmpresaAnalisada $empresa, int $bloco): Testable
{
    $c = Livewire::test(Quiz::class, ['empresa' => $empresa]);

    if ($bloco >= 2) {
        $c->call('proximoBloco');
    }
    if ($bloco >= 3) {
        $c->set('Q08', '50000')->set('Q09', '100000')->set('Q14', '20000')
            ->set('Q15', '8000')->set('Q16', '2000')->set('Q10', '90')
            ->set('Q11', '15')->set('Q12', '15')->set('Q13', '2')
            ->call('proximoBloco');
    }
    if ($bloco >= 4) {
        $c->set('Q02', '50000')->set('Q03', '20000')->set('Q04', '10000')
            ->set('Q05', '300000')->set('Q06', '40000')->set('Q07', '80000')
            ->call('proximoBloco')
            ->set('Q17', 'sim');
    }

    return $c;
}

// ============================================================================
// CA-1 — todos os 23 campos têm tooltip funcional
// ============================================================================

it('renderiza ícone de ajuda acessível para todos os 23 campos do quiz', function () {
    foreach (camposPorBloco() as $bloco => $campos) {
        $c = quizNoBloco($this->empresa, $bloco);

        foreach ($campos as $q) {
            $c->assertSeeHtml('dusk="help-trigger-'.$q.'"');
            // aria-describedby liga o ícone ao painel do texto (CA-5).
            $c->assertSeeHtml('aria-describedby="help-painel-'.$q.'"');
            $c->assertSeeHtml('id="help-painel-'.$q.'"');
        }
    }
});

it('cobre exatamente os 23 campos do Anexo A na config', function () {
    expect($this->help)->toBeArray()->toHaveCount(23);
    $esperados = array_merge(...array_values(camposPorBloco()));
    expect(array_keys($this->help))->toEqualCanonicalizing($esperados);
});

// ============================================================================
// CA-7 — a view contém os textos esperados, lidos da config
// ============================================================================

it('exibe o texto da config no painel de cada campo', function () {
    foreach (camposPorBloco() as $bloco => $campos) {
        $c = quizNoBloco($this->empresa, $bloco);

        foreach ($campos as $q) {
            $c->assertSeeHtml(helpEsperado($this->help[$q]));
        }
    }
});

it('promove **negrito** da config para <strong> no painel', function () {
    // Q01 tem trechos em **negrito** na config — devem virar <strong> na view.
    expect($this->help['Q01'])->toContain('**');

    quizNoBloco($this->empresa, 1)
        ->assertSeeHtml('<strong>')
        ->assertSeeHtml(helpEsperado($this->help['Q01']));
});

// ============================================================================
// CA-2 — texto editável só na config, sem tocar componente nem teste
// ============================================================================

it('reflete na view um texto alterado apenas na config (CA-2)', function () {
    config()->set('quiz.help-industria.campos.Q01', 'TEXTO DE TESTE EDITADO NA CONFIG.');

    quizNoBloco($this->empresa, 1)
        ->assertSeeHtml('TEXTO DE TESTE EDITADO NA CONFIG.');
});

it('omite o ícone de ajuda quando a config não tem texto para o campo (fallback gracioso)', function () {
    config()->set('quiz.help-industria.campos.Q01', null);

    quizNoBloco($this->empresa, 1)
        ->assertDontSeeHtml('dusk="help-trigger-Q01"');
});

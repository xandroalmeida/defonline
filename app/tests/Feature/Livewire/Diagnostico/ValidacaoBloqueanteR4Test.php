<?php

declare(strict_types=1);

use App\Domain\Quiz\ValidacoesCruzadas;
use App\Livewire\Diagnostico\Quiz;
use App\Models\Diagnostico;
use App\Models\EmpresaAnalisada;
use App\Models\Usuario;
use Illuminate\Support\Facades\Event;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/*
| ----------------------------------------------------------------------
| STORY-043 — R4 bloqueante (recebíveis × venda mensal) no componente.
|
| A regra do Anexo A §validações ("Q20 ≥ Q09: bloqueio rígido") saiu da
| closure `Quiz::regraQ20MenorQueQ09()` e passou a viver na engine
| declarativa da STORY-034 com severidade `blocking`. Aqui se testa o que
| a engine sozinha não alcança: o ponto de consumo (submit, não o gate do
| Bloco 3), a separação de UX e as bordas do sub-bloco de captação.
| ----------------------------------------------------------------------
*/

beforeEach(function () {
    $this->usuario = Usuario::factory()->create();
    $this->actingAs($this->usuario);
    $this->empresa = EmpresaAnalisada::factory()
        ->state(['usuario_id' => $this->usuario->id])
        ->create();
});

/**
 * Deixa o componente no Bloco 4 com dados saudáveis (nenhuma R1/R2/R3 dispara)
 * e o sub-bloco de captação aberto — pronto para variar só o Q20.
 * Q09 (venda mensal) = 100.000,00.
 */
function r4NoBloco4(TestCase $test): Testable
{
    return Livewire::test(Quiz::class, ['empresa' => $test->empresa])
        ->call('proximoBloco')   // 1 → 2
        ->set('Q08', '50000')
        ->set('Q09', '100000')
        ->set('Q14', '20000')
        ->set('Q15', '8000')
        ->set('Q16', '2000')
        ->set('Q10', '90')
        ->set('Q11', '15')
        ->set('Q12', '15')
        ->set('Q13', '2')
        ->call('proximoBloco')   // 2 → 3
        ->set('Q02', '50000')
        ->set('Q03', '20000')
        ->set('Q04', '10000')
        ->set('Q05', '300000')
        ->set('Q06', '40000')
        ->set('Q07', '80000')
        ->call('proximoBloco')   // 3 → 4
        ->set('Q17', 'sim')
        ->set('Q18', '50000')
        ->set('Q19', '20000')
        ->set('socios', [['cpf' => '529.982.247-25', 'nome' => 'Ana Lima']]);
}

// ============================================================================
// CA-2 / CA-3 — bloqueio no submit, com mensagem acionável
// ============================================================================

it('CA-2 — impede o submit quando os recebíveis passam da venda mensal', function () {
    Event::fake();

    $component = r4NoBloco4($this)
        ->set('Q20', '150.000,00')   // > Q09 = 100.000
        ->call('submeter')
        ->assertNoRedirect();

    $bloqueios = $component->get('alertas_bloqueantes');

    expect($bloqueios)->toHaveCount(1)
        ->and($bloqueios[0]['regra'])->toBe('R4')
        ->and($bloqueios[0]['severidade'])->toBe(ValidacoesCruzadas::SEVERIDADE_BLOQUEIO)
        ->and(Diagnostico::query()->count())->toBe(0);

    $component->assertSet('bloco_atual', 4);
});

it('CA-3 — o erro exibido nomeia os dois valores e oferece o botão de foco', function () {
    Event::fake();

    r4NoBloco4($this)
        ->set('Q20', '150.000,00')
        ->call('submeter')
        ->assertSee('Recebíveis (R$ 150.000,00)')
        ->assertSee('venda mensal média (R$ 100.000,00)')
        ->assertSee('Ir para Recebíveis (Q20)')
        ->assertSeeHtml('quiz-bloqueio-R4');
});

it('CA-3 — irParaCampo fecha o bloqueio e mantém Roberto no Bloco 4', function () {
    Event::fake();

    r4NoBloco4($this)
        ->set('Q20', '150.000,00')
        ->call('submeter')
        ->call('irParaCampo', 'Q20')
        ->assertSet('alertas_bloqueantes', [])
        ->assertSet('bloco_atual', 4)
        ->assertDispatched('focar-campo');
});

// ============================================================================
// CA-5 / CA-6 — não-disparado e limiar exato
// ============================================================================

it('CA-5 — recebíveis abaixo da venda mensal submetem normalmente', function () {
    Event::fake();

    r4NoBloco4($this)
        ->set('Q20', '26.000,00')   // < Q09 = 100.000
        ->call('submeter')
        ->assertHasNoErrors()
        ->assertSet('alertas_bloqueantes', [])
        ->assertRedirect();

    expect(Diagnostico::query()->count())->toBe(1);
});

it('CA-6 (limiar) — recebíveis IGUAIS à venda mensal bloqueiam: o Anexo A exige "inferior"', function () {
    Event::fake();

    $component = r4NoBloco4($this)
        ->set('Q20', '100.000,00')   // == Q09
        ->call('submeter')
        ->assertNoRedirect();

    expect($component->get('alertas_bloqueantes'))->toHaveCount(1)
        ->and(Diagnostico::query()->count())->toBe(0);
});

it('CA-6 (limiar) — 1 centavo abaixo da venda mensal já passa', function () {
    Event::fake();

    r4NoBloco4($this)
        ->set('Q20', '99.999,99')
        ->call('submeter')
        ->assertSet('alertas_bloqueantes', [])
        ->assertRedirect();
});

// ============================================================================
// CA-4 — separação de UX: R4 não é banner inline do gate Bloco 3 → 4
// ============================================================================

it('CA-4 — a R4 não aparece no gate do Bloco 3, mesmo com dados que a disparariam', function () {
    // Q20 já preenchido acima do Q09 antes de chegar ao gate. O gate do Bloco 3
    // só consulta severidade `warning`, então avança sem segurar por causa da R4.
    $component = Livewire::test(Quiz::class, ['empresa' => $this->empresa])
        ->call('proximoBloco')
        ->set('Q08', '50000')->set('Q09', '100000')
        ->set('Q14', '20000')->set('Q15', '8000')->set('Q16', '2000')
        ->set('Q10', '90')->set('Q11', '15')->set('Q12', '15')->set('Q13', '2')
        ->set('Q17', 'sim')
        ->set('Q20', '150000')            // dispararia a R4 se o gate a enxergasse
        ->call('proximoBloco')
        ->set('Q02', '50000')->set('Q03', '20000')->set('Q04', '10000')
        ->set('Q05', '300000')->set('Q06', '40000')->set('Q07', '80000')
        ->call('proximoBloco');           // gate 3 → 4

    $component->assertSet('bloco_atual', 4)
        ->assertSet('alertas_cruzados', [])
        ->assertSet('alertas_bloqueantes', []);
});

it('CA-4 — bloqueio não oferece "Continuar mesmo assim" nem vira alerta aceito', function () {
    Event::fake();

    $component = r4NoBloco4($this)
        ->set('Q20', '150.000,00')
        ->call('submeter');

    // O botão de escape das R1/R2/R3 não é renderizado para bloqueios.
    $component->assertDontSeeHtml('quiz-alertas-continuar');

    // E o bloqueio não entra na auditoria de alertas aceitos (nada a aceitar).
    expect($component->get('alertas_aceitos'))->toBe([]);
});

// ============================================================================
// Bordas do sub-bloco de captação
// ============================================================================

it('borda — sem captação (Q17="não") um Q20 residual não bloqueia o submit', function () {
    Event::fake();

    // Roberto digita o Q20, muda de ideia e marca "não": o campo some da tela e
    // não vai ao motor, então não pode segurar o submit por consistência.
    r4NoBloco4($this)
        ->set('Q20', '150.000,00')
        ->set('Q17', 'nao')
        ->call('submeter')
        ->assertSet('alertas_bloqueantes', [])
        ->assertRedirect();

    expect(Diagnostico::query()->count())->toBe(1);
});

it('borda — corrigir o valor depois do bloqueio libera o submit na mesma sessão', function () {
    Event::fake();

    $component = r4NoBloco4($this)
        ->set('Q20', '150.000,00')
        ->call('submeter')
        ->assertNoRedirect();

    expect($component->get('alertas_bloqueantes'))->toHaveCount(1);

    $component->set('Q20', '26.000,00')
        ->call('submeter')
        ->assertSet('alertas_bloqueantes', [])
        ->assertRedirect();

    expect(Diagnostico::query()->count())->toBe(1);
});

// ============================================================================
// CA-9 — regressão: R1/R2/R3 seguem não-bloqueantes
// ============================================================================

it('CA-9 — um aviso aceito no Bloco 3 não vira bloqueio no submit', function () {
    Event::fake();

    // Balanço inconsistente (R3, `warning`) + recebíveis válidos: aceita o aviso
    // e submete. Nenhum bloqueio deve aparecer no caminho.
    $component = Livewire::test(Quiz::class, ['empresa' => $this->empresa])
        ->call('proximoBloco')
        ->set('Q08', '50000')->set('Q09', '100000')
        ->set('Q14', '20000')->set('Q15', '8000')->set('Q16', '2000')
        ->set('Q10', '90')->set('Q11', '15')->set('Q12', '15')->set('Q13', '2')
        ->call('proximoBloco')
        ->set('Q02', '1000')->set('Q03', '1000')->set('Q04', '1000')
        ->set('Q05', '1000')->set('Q06', '100000')->set('Q07', '100000')
        ->call('proximoBloco')            // gate segura por R3
        ->assertSet('bloco_atual', 3);

    $component->call('continuarComAlertas')
        ->assertSet('bloco_atual', 4)
        ->set('Q17', 'sim')
        ->set('Q18', '50000')->set('Q19', '20000')->set('Q20', '26000')
        ->set('socios', [['cpf' => '529.982.247-25', 'nome' => 'Ana Lima']])
        ->call('submeter')
        ->assertSet('alertas_bloqueantes', [])
        ->assertRedirect();

    $payload = (array) Diagnostico::query()->firstOrFail()->quiz_payload;

    expect($payload['alertas_aceitos'][0]['regra'])->toBe('R3');
});

<?php

declare(strict_types=1);

use App\Actions\CalcularDiagnostico;
use App\Livewire\Diagnostico\Quiz;
use App\Models\EmpresaAnalisada;
use App\Models\EventoProduto;
use App\Models\Usuario;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Livewire;

/**
 * STORY-035 (ADR-004 §Decisão 2) — eventos analíticos `quiz_iniciado` e
 * `diagnostico_concluido`.
 *
 * O disparo + idempotência de `quiz_iniciado` no fluxo do quiz vivem em
 * tests/Feature/Livewire/Diagnostico/QuizTest.php (mesmo arquivo do trigger).
 * Aqui: schema do `diagnostico_concluido` (CA-3), derivação de `porte`,
 * idempotência por `diagnostico_id` (CA-2) e o funil ponta-a-ponta correlacionado.
 */
function payloadIndustria035(): array
{
    return [
        'Q01' => 'industria',
        'Q02' => '50000', 'Q03' => '20000', 'Q04' => '10000',
        'Q05' => '300000', 'Q06' => '40000', 'Q07' => '80000',
        'Q08' => '50000', 'Q09' => '100000',
        'Q10' => 90, 'Q11' => 15, 'Q12' => 15, 'Q13' => '2',
        'Q14' => '20000', 'Q15' => '8000', 'Q16' => '2000',
    ];
}

it('emite diagnostico_concluido com schema exato e sem PII (CA-3/CA-6)', function () {
    $u = Usuario::factory()->create();
    Auth::login($u);
    $empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $u->id]);

    $quizId = (string) Str::uuid7();

    $diag = app(CalcularDiagnostico::class)
        ->execute($empresa, payloadIndustria035(), 'industria', [], $quizId, 540);

    $eventos = EventoProduto::where('nome_evento', 'diagnostico_concluido')->get();
    expect($eventos)->toHaveCount(1);

    $evento = $eventos->first();
    expect($evento->usuario_id)->toBe($u->id);
    expect($evento->empresa_id)->toBe($empresa->id);
    expect($evento->request_id)->not->toBeEmpty();

    $props = (array) $evento->propriedades;
    expect(array_keys($props))->toEqualCanonicalizing([
        'quiz_id', 'diagnostico_id', 'duracao_preenchimento_seg', 'setor', 'porte',
    ]);
    expect($props['quiz_id'])->toBe($quizId);
    expect($props['diagnostico_id'])->toBe($diag->id);
    expect($props['duracao_preenchimento_seg'])->toBe(540);
    expect($props['setor'])->toBe('industria');
    expect($props['porte'])->toBe('epp'); // 100000 × 12 = 1,2M → EPP.

    // Sem dados financeiros absolutos no payload (apenas porte categorizado).
    $serializado = json_encode($props);
    expect($serializado)->not->toContain('100000');
    expect($serializado)->not->toContain('300000');
});

it('deriva porte pelo faturamento anual estimado (Q09 × 12)', function (string $q09, string $esperado) {
    $u = Usuario::factory()->create();
    Auth::login($u);
    $empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $u->id]);

    $payload = array_merge(payloadIndustria035(), ['Q09' => $q09]);
    app(CalcularDiagnostico::class)->execute($empresa, $payload);

    $evento = EventoProduto::where('nome_evento', 'diagnostico_concluido')->firstOrFail();
    expect(((array) $evento->propriedades)['porte'])->toBe($esperado);
})->with([
    'MEI no teto' => ['6750', 'mei'],   // 81.000/ano
    'ME' => ['20000', 'me'],   // 240.000/ano
    'EPP' => ['100000', 'epp'], // 1,2M/ano
    'Médio' => ['500000', 'medio'], // 6M/ano
]);

it('porte é null quando Q09 ausente', function () {
    $u = Usuario::factory()->create();
    Auth::login($u);
    $empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $u->id]);

    $payload = payloadIndustria035();
    unset($payload['Q09']);

    app(CalcularDiagnostico::class)->execute($empresa, $payload);

    $evento = EventoProduto::where('nome_evento', 'diagnostico_concluido')->firstOrFail();
    expect(((array) $evento->propriedades)['porte'])->toBeNull();
});

it('cada execute() emite exatamente um diagnostico_concluido — chave de idempotência = diagnostico_id (CA-2)', function () {
    $u = Usuario::factory()->create();
    Auth::login($u);
    $empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $u->id]);

    $action = app(CalcularDiagnostico::class);
    $d1 = $action->execute($empresa, payloadIndustria035());
    $d2 = $action->execute($empresa, payloadIndustria035()); // mesmo input, 2º registro consciente

    // IDR-010 §sub-decisão 2: 2 diagnósticos distintos → 2 eventos distintos, 1 por diagnostico_id.
    $eventos = EventoProduto::where('nome_evento', 'diagnostico_concluido')->get();
    expect($eventos)->toHaveCount(2);

    $idsEvento = $eventos->map(fn ($e) => ((array) $e->propriedades)['diagnostico_id'])->sort()->values()->all();
    expect($idsEvento)->toBe(collect([$d1->id, $d2->id])->sort()->values()->all());
});

it('não emite diagnostico_concluido quando o cálculo falha (rollback — CA-5)', function () {
    $u = Usuario::factory()->create();
    Auth::login($u);
    $empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $u->id]);

    expect(fn () => app(CalcularDiagnostico::class)->execute($empresa, payloadIndustria035(), 'agropecuaria'))
        ->toThrow(InvalidArgumentException::class);

    expect(EventoProduto::where('nome_evento', 'diagnostico_concluido')->count())->toBe(0);
});

it('funil ponta-a-ponta: 1 quiz_iniciado + 1 diagnostico_concluido com quiz_id correlacionado (CA-7)', function () {
    $u = Usuario::factory()->create();
    $this->actingAs($u);
    $empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $u->id]);

    Livewire::test(Quiz::class, ['empresa' => $empresa])
        ->call('proximoBloco')   // 1 → 2 (cria rascunho → quiz_iniciado)
        ->set('Q08', '50000')->set('Q09', '100000')
        ->set('Q14', '20000')->set('Q15', '8000')->set('Q16', '2000')
        ->set('Q10', '90')->set('Q11', '15')->set('Q12', '15')->set('Q13', '2')
        ->call('proximoBloco')   // 2 → 3
        ->set('Q02', '50000')->set('Q03', '20000')->set('Q04', '10000')
        ->set('Q05', '300000')->set('Q06', '40000')->set('Q07', '80000')
        ->call('proximoBloco')   // 3 → 4
        ->set('Q17', 'nao')
        ->call('submeter')
        ->assertRedirect();

    $iniciado = EventoProduto::where('nome_evento', 'quiz_iniciado')->get();
    $concluido = EventoProduto::where('nome_evento', 'diagnostico_concluido')->get();

    expect($iniciado)->toHaveCount(1);
    expect($concluido)->toHaveCount(1);

    // Mesmo quiz_id nos dois eventos — funil iniciado → concluído rastreável.
    $quizIdIniciado = ((array) $iniciado->first()->propriedades)['quiz_id'];
    $quizIdConcluido = ((array) $concluido->first()->propriedades)['quiz_id'];
    expect($quizIdConcluido)->toBe($quizIdIniciado)->not->toBeNull();
});

<?php

declare(strict_types=1);

use App\Livewire\Diagnostico\Quiz;
use App\Models\Diagnostico;
use App\Models\EmpresaAnalisada;
use App\Models\EventoProduto;
use App\Models\QuizRascunho;
use App\Models\Scopes\BelongsToUsuarioScope;
use App\Models\Usuario;
use Illuminate\Support\Facades\Event;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Feature da STORY-027 cobrindo CA-1..CA-13 do quiz de Indústria.
 *
 * Premissa: motor V1 (STORY-028) já entregue. Action `CalcularDiagnostico` é
 * o ponto de integração no submit final.
 */
beforeEach(function () {
    $this->usuario = Usuario::factory()->create();
    $this->actingAs($this->usuario);
    $this->empresa = EmpresaAnalisada::factory()
        ->state(['usuario_id' => $this->usuario->id])
        ->create();
});

// ============================================================================
// CA-1 — Rota, navegação, autenticação
// ============================================================================

it('exige autenticação para acessar o quiz', function () {
    auth()->logout();

    $this->get(route('diagnosticos.novo', $this->empresa))
        ->assertRedirect('/login');
});

it('renderiza o quiz começando no bloco 1 (Identificação)', function () {
    $this->get(route('diagnosticos.novo', $this->empresa))
        ->assertOk()
        ->assertSee('Bloco 1 de 4')
        ->assertSee('Identificação')
        ->assertSee('Indústria');
});

it('item Diagnósticos do menu deixou de ser disabled e aponta para o seletor', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee(route('diagnosticos.selecionar'));
});

it('seletor de empresa mostra a lista quando o usuário tem empresas', function () {
    $this->get(route('diagnosticos.selecionar'))
        ->assertOk()
        ->assertSee('Selecione uma empresa')
        ->assertSee($this->empresa->nome_fantasia ?: $this->empresa->razao_social)
        ->assertSee(route('diagnosticos.novo', $this->empresa));
});

it('seletor mostra estado vazio quando o usuário não tem empresas', function () {
    $solo = Usuario::factory()->create();
    $this->actingAs($solo);

    $this->get(route('diagnosticos.selecionar'))
        ->assertOk()
        ->assertSeeText('Você ainda não cadastrou nenhuma empresa.')
        ->assertSee(route('empresas.nova'));
});

it('cross-tenant retorna 404 (IDR-009)', function () {
    $outro = Usuario::factory()->create();
    $empresaDoOutro = EmpresaAnalisada::factory()
        ->state(['usuario_id' => $outro->id])
        ->create();

    $this->get(route('diagnosticos.novo', $empresaDoOutro))
        ->assertNotFound();
});

// ============================================================================
// CA-2 — 4 blocos navegáveis
// ============================================================================

it('avança do bloco 1 para o bloco 2 e persiste rascunho', function () {
    Livewire::test(Quiz::class, ['empresa' => $this->empresa])
        ->assertSet('bloco_atual', 1)
        ->call('proximoBloco')
        ->assertSet('bloco_atual', 2);

    expect(QuizRascunho::query()->count())->toBe(1);
    $rascunho = QuizRascunho::query()->first();
    expect($rascunho->ultimo_bloco_preenchido)->toBe(2);
    expect($rascunho->usuario_id)->toBe($this->usuario->id);
    expect($rascunho->empresa_analisada_id)->toBe($this->empresa->id);
});

it('volta do bloco 2 para o bloco 1 sem perder dados', function () {
    Livewire::test(Quiz::class, ['empresa' => $this->empresa])
        ->call('proximoBloco')
        ->set('Q08', '50.000,00')
        ->call('blocoAnterior')
        ->assertSet('bloco_atual', 1)
        ->assertSet('Q08', '50.000,00');
});

// ============================================================================
// CA-5 — Validações por campo
// ============================================================================

it('bloqueia avanço do bloco 2 quando campos obrigatórios estão vazios', function () {
    Livewire::test(Quiz::class, ['empresa' => $this->empresa])
        ->call('proximoBloco')
        ->assertSet('bloco_atual', 2)
        ->call('proximoBloco')
        ->assertSet('bloco_atual', 2)
        ->assertHasErrors(['Q08', 'Q09', 'Q14', 'Q15', 'Q16', 'Q10', 'Q11', 'Q12', 'Q13']);
});

it('rejeita inadimplência > 100% (Q13)', function () {
    Livewire::test(Quiz::class, ['empresa' => $this->empresa])
        ->call('proximoBloco')
        ->set('Q08', '1000')->set('Q09', '2000')
        ->set('Q14', '500')->set('Q15', '200')->set('Q16', '100')
        ->set('Q10', '30')->set('Q11', '15')->set('Q12', '45')
        ->set('Q13', '120')
        ->call('proximoBloco')
        ->assertHasErrors('Q13');
});

it('rejeita PMC com texto inválido (Q10)', function () {
    Livewire::test(Quiz::class, ['empresa' => $this->empresa])
        ->call('proximoBloco')
        ->set('Q10', 'abc')
        ->call('proximoBloco')
        ->assertHasErrors('Q10');
});

it('bloqueia bloco 4 quando Q20 >= Q09 (regra Anexo A)', function () {
    $rascunho = quizPreencherAteBloco4($this);

    $rascunho->set('Q17', 'sim')
        ->set('Q18', '50.000,00')
        ->set('Q19', '20.000,00')
        ->set('Q20', '150.000,00')   // > Q09 = 100.000
        ->set('Q21', '529.982.247-25')
        ->set('Q22', '529.982.247-25')
        ->set('Q23', '529.982.247-25')
        ->call('submeter')
        ->assertHasErrors('Q20');
});

it('rejeita CPF inválido em Q21..Q23 quando Q17="sim"', function () {
    $rascunho = quizPreencherAteBloco4($this);

    $rascunho->set('Q17', 'sim')
        ->set('Q18', '50000')->set('Q19', '20000')->set('Q20', '10000')
        ->set('Q21', '111.111.111-11')   // sequência trivial → inválido
        ->set('Q22', '529.982.247-25')
        ->set('Q23', '529.982.247-25')
        ->call('submeter')
        ->assertHasErrors('Q21');
});

it('quando Q17="nao" permite submeter sem Q18..Q23', function () {
    Event::fake();

    $component = quizPreencherAteBloco4($this);

    $component->set('Q17', 'nao')
        ->call('submeter')
        ->assertHasNoErrors();

    expect(Diagnostico::query()->count())->toBe(1);
    $diagnostico = Diagnostico::query()->first();
    $payload = (array) $diagnostico->quiz_payload;
    expect($payload['Q17'])->toBeFalse();
    expect($payload['Q18'])->toBeNull();
    expect($payload['Q21'])->toBeNull();
});

// ============================================================================
// CA-6/CA-7 — Rascunho persistido + expiração
// ============================================================================

it('retoma o rascunho onde o usuário parou', function () {
    QuizRascunho::create([
        'usuario_id' => $this->usuario->id,
        'empresa_analisada_id' => $this->empresa->id,
        'quiz_payload' => ['Q01' => 'industria', 'Q08' => '12.345,67'],
        'ultimo_bloco_preenchido' => 2,
        'expires_at' => now()->addDays(30),
    ]);

    Livewire::test(Quiz::class, ['empresa' => $this->empresa])
        ->assertSet('bloco_atual', 2)
        ->assertSet('Q08', '12.345,67')
        ->assertSet('retomando_rascunho', true);
});

it('rascunho expirado não é hidratado e mostra banner', function () {
    QuizRascunho::create([
        'usuario_id' => $this->usuario->id,
        'empresa_analisada_id' => $this->empresa->id,
        'quiz_payload' => ['Q08' => '999'],
        'ultimo_bloco_preenchido' => 3,
        'expires_at' => now()->subDay(),
    ]);

    Livewire::test(Quiz::class, ['empresa' => $this->empresa])
        ->assertSet('bloco_atual', 1)
        ->assertSet('Q08', null)
        ->assertSet('rascunho_expirou', true);
});

it('scope ativos() exclui rascunhos expirados', function () {
    QuizRascunho::create([
        'usuario_id' => $this->usuario->id,
        'empresa_analisada_id' => $this->empresa->id,
        'quiz_payload' => [],
        'ultimo_bloco_preenchido' => 1,
        'expires_at' => now()->subMinute(),
    ]);

    expect(QuizRascunho::query()->count())->toBe(1)
        ->and(QuizRascunho::query()->ativos()->count())->toBe(0);
});

it('rascunho do usuário A não vaza para o usuário B (multi-tenant)', function () {
    QuizRascunho::create([
        'usuario_id' => $this->usuario->id,
        'empresa_analisada_id' => $this->empresa->id,
        'quiz_payload' => ['Q08' => '99'],
        'ultimo_bloco_preenchido' => 2,
        'expires_at' => now()->addDays(30),
    ]);

    $outro = Usuario::factory()->create();
    $this->actingAs($outro);

    expect(QuizRascunho::query()->count())->toBe(0);
    // Bypass do scope confirma que o registro existe — só está filtrado.
    expect(QuizRascunho::withoutGlobalScope(BelongsToUsuarioScope::class)->count())->toBe(1);
});

// ============================================================================
// CA-8 — Submit final + integração com o motor
// ============================================================================

it('submete o quiz completo e cria um Diagnostico (caminho feliz)', function () {
    Event::fake();

    $component = quizPreencherAteBloco4($this);
    $component->set('Q17', 'nao')
        ->call('submeter')
        ->assertHasNoErrors()
        ->assertRedirect();

    expect(Diagnostico::query()->count())->toBe(1);
    $diagnostico = Diagnostico::query()->first();
    expect($diagnostico->usuario_id)->toBe($this->usuario->id);
    expect($diagnostico->empresa_analisada_id)->toBe($this->empresa->id);
    expect($diagnostico->setor)->toBe('industria');
    expect($diagnostico->motor_version)->toBe(config('motor.version'));
    expect($diagnostico->payload_hash)->toMatch('/^[0-9a-f]{64}$/');
});

it('apaga o rascunho ativo após submit bem-sucedido', function () {
    QuizRascunho::create([
        'usuario_id' => $this->usuario->id,
        'empresa_analisada_id' => $this->empresa->id,
        'quiz_payload' => [],
        'ultimo_bloco_preenchido' => 3,
        'expires_at' => now()->addDays(30),
    ]);

    $component = quizPreencherAteBloco4($this);
    $component->set('Q17', 'nao')->call('submeter');

    // Soft delete — registro existe com deleted_at, mas Global Scope + scope
    // padrão do SoftDeletes esconde.
    expect(QuizRascunho::query()->count())->toBe(0);
});

// ============================================================================
// CA-13 / STORY-035 — Evento quiz_iniciado em evento_produto
// ============================================================================

it('emite quiz_iniciado em evento_produto na primeira transição 1 → 2 (uma vez)', function () {
    Livewire::test(Quiz::class, ['empresa' => $this->empresa])
        ->call('proximoBloco')
        ->call('proximoBloco');   // segundo Próximo NÃO reemite

    $eventos = EventoProduto::where('nome_evento', 'quiz_iniciado')->get();
    expect($eventos)->toHaveCount(1);

    $evento = $eventos->first();
    expect($evento->usuario_id)->toBe($this->usuario->id);
    expect($evento->empresa_id)->toBe($this->empresa->id);
    expect($evento->request_id)->not->toBeEmpty();

    // quiz_id = id do rascunho recém-criado; payload conforme ADR-004 §2.2 (CA-3).
    $rascunho = QuizRascunho::query()
        ->where('empresa_analisada_id', $this->empresa->id)
        ->first();

    $props = (array) $evento->propriedades;
    expect(array_keys($props))->toEqualCanonicalizing(['quiz_id', 'quiz_versao']);
    expect($props['quiz_id'])->toBe($rascunho->id);
    expect($props['quiz_versao'])->toBe('2026.1');
});

it('não reemite quiz_iniciado ao retomar rascunho do bloco 2+', function () {
    // Rascunho já existente (criado direto, sem passar pelo emissor) → retomada.
    QuizRascunho::create([
        'usuario_id' => $this->usuario->id,
        'empresa_analisada_id' => $this->empresa->id,
        'quiz_payload' => [],
        'ultimo_bloco_preenchido' => 2,
        'expires_at' => now()->addDays(30),
    ]);

    Livewire::test(Quiz::class, ['empresa' => $this->empresa])
        ->set('Q08', '1000')->set('Q09', '2000')
        ->set('Q14', '500')->set('Q15', '200')->set('Q16', '100')
        ->set('Q10', '30')->set('Q11', '15')->set('Q12', '45')
        ->set('Q13', '5')
        ->call('proximoBloco');

    expect(EventoProduto::where('nome_evento', 'quiz_iniciado')->count())->toBe(0);
});

// ============================================================================
// QuizRascunho model — relations + escopo
// ============================================================================

it('QuizRascunho expõe relations usuario() e empresa()', function () {
    $rascunho = QuizRascunho::create([
        'usuario_id' => $this->usuario->id,
        'empresa_analisada_id' => $this->empresa->id,
        'quiz_payload' => [],
        'ultimo_bloco_preenchido' => 1,
        'expires_at' => now()->addDays(30),
    ]);

    expect($rascunho->usuario)->not->toBeNull()
        ->and($rascunho->usuario->id)->toBe($this->usuario->id)
        ->and($rascunho->empresa)->not->toBeNull()
        ->and($rascunho->empresa->id)->toBe($this->empresa->id);
});

// ============================================================================
// CA-9 — Falha do motor não persiste registro parcial
// ============================================================================

it('caminho de erro do submit não cria Diagnóstico e mostra mensagem ao usuário', function () {
    // Forçando inválido por reflexão: setor diferente de 'industria' faz o motor
    // lançar InvalidArgumentException → quiz captura, loga e exibe erro genérico.
    $component = quizPreencherAteBloco4($this);

    $component->set('Q17', 'nao')
        ->set('Q01', 'comercio')   // inválido para o motor V1
        ->call('submeter')
        ->assertHasErrors('submeter');

    expect(Diagnostico::query()->count())->toBe(0);
});

// ============================================================================
// STORY-034 — validações cruzadas DRE × Balanço (gate Bloco 3 → 4)
// ============================================================================

it('segura o avanço do Bloco 3 quando há inconsistência cruzada (R3) — CA-5', function () {
    $component = quizComBalancoInconsistente($this)
        ->call('proximoBloco');   // tentativa 3 → 4

    $component->assertSet('bloco_atual', 3);   // não avançou

    $alertas = $component->get('alertas_cruzados');
    expect($alertas)->toHaveCount(1)
        ->and($alertas[0]['regra'])->toBe('R3')
        ->and($alertas[0]['severidade'])->toBe('warning')
        ->and($alertas[0]['mensagem'])->toContain('passivo total');
});

it('é não-bloqueante: continuarComAlertas registra o aceito e avança (CA-3)', function () {
    $component = quizComBalancoInconsistente($this)
        ->call('proximoBloco')           // gate dispara
        ->call('continuarComAlertas');

    $component->assertSet('bloco_atual', 4)
        ->assertSet('alertas_cruzados', []);

    $aceitos = $component->get('alertas_aceitos');
    expect($aceitos)->toHaveCount(1)
        ->and($aceitos[0]['regra'])->toBe('R3')
        ->and($aceitos[0])->toHaveKeys(['regra', 'ocorrido_em', 'valor_envolvido']);
});

it('irParaCampo leva ao bloco do campo suspeito e fecha o banner (CA-4)', function () {
    $component = quizComBalancoInconsistente($this)->call('proximoBloco');

    // Q16 (despesas financeiras) vive no Bloco 2.
    $component->call('irParaCampo', 'Q16')
        ->assertSet('bloco_atual', 2)
        ->assertSet('alertas_cruzados', []);

    // Q06 (dívidas) vive no Bloco 3.
    $component->call('irParaCampo', 'Q06')
        ->assertSet('bloco_atual', 3);
});

it('não duplica alertas_aceitos ao reaceitar a mesma regra', function () {
    $component = quizComBalancoInconsistente($this)
        ->call('proximoBloco')
        ->call('continuarComAlertas')   // aceita R3 (→ bloco 4)
        ->call('blocoAnterior')         // volta ao 3
        ->call('proximoBloco');         // dados iguais — já aceito, não re-segura

    // Como R3 já foi aceito, o gate não re-segura: avança direto.
    $component->assertSet('bloco_atual', 4);
    expect($component->get('alertas_aceitos'))->toHaveCount(1);
});

it('grava alertas_aceitos no Diagnóstico ao submeter (CA-3 ponta-a-ponta)', function () {
    Event::fake();

    $component = quizComBalancoInconsistente($this)
        ->call('proximoBloco')
        ->call('continuarComAlertas')   // → bloco 4
        ->set('Q17', 'nao')
        ->call('submeter')
        ->assertHasNoErrors()
        ->assertRedirect();

    $diagnostico = Diagnostico::query()->first();
    $payload = (array) $diagnostico->quiz_payload;

    expect($payload)->toHaveKey('alertas_aceitos')
        ->and($payload['alertas_aceitos'][0]['regra'])->toBe('R3');
});

it('não mostra alertas com dados consistentes — sem falso positivo', function () {
    quizPreencherAteBloco4($this)   // dados saudáveis: já avança 3 → 4
        ->assertSet('bloco_atual', 4)
        ->assertSet('alertas_cruzados', []);
});

// ============================================================================
// Helpers
// ============================================================================

/**
 * Avança o quiz até o bloco 4 (Q17 ainda em branco), retornando o testable.
 *
 * Valores espelham o fixture `quiz_industria_saudavel.json` para evitar surpresas
 * no motor (a STORY-028 valida que estes números produzem indicadores válidos).
 */
function quizPreencherAteBloco4(TestCase $test): Testable
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
        ->call('proximoBloco');  // 3 → 4
}

/**
 * Preenche os Blocos 2 e 3 com um balanço inconsistente (passivo ≫ ativo → R3),
 * deixando o componente parado no Bloco 3, pronto para tentar avançar e disparar
 * o gate de validações cruzadas (STORY-034). Bloco 2 fica saudável de propósito,
 * para que só a R3 dispare.
 */
function quizComBalancoInconsistente(TestCase $test): Testable
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
        ->set('Q02', '1000')
        ->set('Q03', '1000')
        ->set('Q04', '1000')
        ->set('Q05', '1000')
        ->set('Q06', '100000')
        ->set('Q07', '100000');
}

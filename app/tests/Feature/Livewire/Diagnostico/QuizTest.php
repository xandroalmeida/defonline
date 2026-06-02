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

// ============================================================================
// STORY-039 — Inadimplência (Q13) aceita 2 casas decimais no range [0,00;100,00]
// ============================================================================

it('CA-1/CA-2 — aceita inadimplência decimal BR dentro do range inclusivo', function (string $q13) {
    quizAvancaBloco2ComQ13($this, $q13)
        ->assertHasNoErrors('Q13')
        ->assertSet('bloco_atual', 3);
})->with(['3,46', '0,00', '100,00', '0', '100', '3.46']);

it('CA-3/CA-4 — rejeita fora do range com a mensagem literal do Anexo A', function (string $q13) {
    $c = quizAvancaBloco2ComQ13($this, $q13);
    $c->assertHasErrors('Q13')->assertSet('bloco_atual', 2);
    expect($c->errors()->first('Q13'))->toBe('Inadimplência deve ser entre 0,00% e 100,00%.');
})->with(['100,01', '-1', '-0,01']);

it('CA-6 — não-numérico rejeitado com mensagem genérica', function () {
    $c = quizAvancaBloco2ComQ13($this, 'abc');
    $c->assertHasErrors('Q13');
    expect($c->errors()->first('Q13'))->toBe('Informe um percentual válido (ex.: 5,3).');
});

it('CA-5 — 3+ casas decimais são truncadas (não arredondadas) para 2 no canônico', function () {
    Event::fake();

    Livewire::test(Quiz::class, ['empresa' => $this->empresa])
        ->call('proximoBloco')
        ->set('Q08', '50000')->set('Q09', '100000')->set('Q14', '20000')
        ->set('Q15', '8000')->set('Q16', '2000')
        ->set('Q10', '90')->set('Q11', '15')->set('Q12', '15')
        ->set('Q13', '3,469')   // 3.469 → trunca para 3.46 (arredondar daria 3.47)
        ->call('proximoBloco')
        ->set('Q02', '50000')->set('Q03', '20000')->set('Q04', '10000')
        ->set('Q05', '300000')->set('Q06', '40000')->set('Q07', '80000')
        ->call('proximoBloco')
        ->set('Q17', 'nao')
        ->call('submeter')
        ->assertHasNoErrors();

    $payload = (array) Diagnostico::query()->firstOrFail()->quiz_payload;
    expect($payload['Q13'])->toBe('3.46');
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
        ->set('socios', [['cpf' => '529.982.247-25', 'nome' => 'Ana Lima']])
        ->call('submeter')
        ->assertHasErrors('Q20');
});

it('rejeita CPF inválido em uma linha de sócio quando Q17="sim" (CA-4)', function () {
    $rascunho = quizPreencherAteBloco4($this);

    $rascunho->set('Q17', 'sim')
        ->set('Q18', '50000')->set('Q19', '20000')->set('Q20', '10000')
        ->set('socios', [
            ['cpf' => '111.111.111-11', 'nome' => 'Ana'],   // 1ª linha: CPF trivial → inválida
            ['cpf' => '529.982.247-25', 'nome' => 'Bruno'],
        ])
        ->call('submeter')
        ->assertHasErrors('socios.0.cpf')
        ->assertHasNoErrors('socios.1.cpf');

    expect(Diagnostico::query()->count())->toBe(0);
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
    expect($payload['socios'])->toBeNull();
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
// STORY-038 — Sócios como lista dinâmica (1+)
// ============================================================================

it('submete com exatamente 1 sócio válido e grava socios[] no payload (CA-1)', function () {
    Event::fake();

    quizPreencherAteBloco4($this)
        ->set('Q17', 'sim')
        ->set('Q18', '50000')->set('Q19', '20000')->set('Q20', '10000')
        ->set('socios', [['cpf' => '529.982.247-25', 'nome' => 'Ana Lima']])
        ->call('submeter')
        ->assertHasNoErrors()
        ->assertRedirect();

    $payload = (array) Diagnostico::query()->firstOrFail()->quiz_payload;
    expect($payload['socios'])->toBe([['cpf' => '52998224725', 'nome' => 'Ana Lima']]);
});

it('adicionarSocio acrescenta uma linha vazia até o cap de 10 (CA-2/CA-6)', function () {
    $component = Livewire::test(Quiz::class, ['empresa' => $this->empresa])
        ->assertSet('socios', [['cpf' => '', 'nome' => '']])
        ->call('adicionarSocio')
        ->assertCount('socios', 2)
        ->call('adicionarSocio')
        ->assertCount('socios', 3);

    // Cap: não passa de 10 por mais que clique.
    for ($i = 0; $i < 20; $i++) {
        $component->call('adicionarSocio');
    }
    $component->assertCount('socios', 10);
});

it('removerSocio remove a linha e renumera; não deixa ficar com 0 (CA-3)', function () {
    $a = ['cpf' => '111.444.777-35', 'nome' => 'Ana'];
    $b = ['cpf' => '123.456.789-09', 'nome' => 'Bruno'];
    $c = ['cpf' => '529.982.247-25', 'nome' => 'Carla'];

    Livewire::test(Quiz::class, ['empresa' => $this->empresa])
        ->set('socios', [$a, $b, $c])
        ->call('removerSocio', 1)
        ->assertSet('socios', [$a, $c])
        ->call('removerSocio', 0)
        ->assertSet('socios', [$c])
        // Só resta 1 → remover é no-op (botão fica desabilitado na UI).
        ->call('removerSocio', 0)
        ->assertSet('socios', [$c]);
});

it('bloqueia o submit com CPF duplicado na lista (CA-5)', function () {
    quizPreencherAteBloco4($this)
        ->set('Q17', 'sim')
        ->set('Q18', '50000')->set('Q19', '20000')->set('Q20', '10000')
        ->set('socios', [
            ['cpf' => '529.982.247-25', 'nome' => 'Ana'],
            ['cpf' => '529.982.247-25', 'nome' => 'Bruno'],
        ])
        ->call('submeter')
        ->assertHasErrors('socios.1.cpf');

    expect(Diagnostico::query()->count())->toBe(0);
});

it('exige ao menos 1 sócio preenchido quando Q17="sim" (CA-1 inverso)', function () {
    quizPreencherAteBloco4($this)
        ->set('Q17', 'sim')
        ->set('Q18', '50000')->set('Q19', '20000')->set('Q20', '10000')
        ->set('socios', [['cpf' => '', 'nome' => ''], ['cpf' => '  ', 'nome' => '  ']])
        ->call('submeter')
        ->assertHasErrors('socios.0.cpf');

    expect(Diagnostico::query()->count())->toBe(0);
});

it('descarta linhas vazias entre preenchidas no payload (CA-7)', function () {
    Event::fake();

    quizPreencherAteBloco4($this)
        ->set('Q17', 'sim')
        ->set('Q18', '50000')->set('Q19', '20000')->set('Q20', '10000')
        ->set('socios', [
            ['cpf' => '529.982.247-25', 'nome' => 'Carla'],
            ['cpf' => '', 'nome' => ''],
            ['cpf' => '111.444.777-35', 'nome' => 'Ana'],
        ])
        ->call('submeter')
        ->assertHasNoErrors();

    $payload = (array) Diagnostico::query()->firstOrFail()->quiz_payload;
    // Vazia descartada + ordenação alfabética canônica por CPF (CA-9).
    expect($payload['socios'])->toBe([
        ['cpf' => '11144477735', 'nome' => 'Ana'],
        ['cpf' => '52998224725', 'nome' => 'Carla'],
    ]);
});

it('persiste a lista de sócios no rascunho na mesma ordem ao avançar (CA-8)', function () {
    $lista = [
        ['cpf' => '529.982.247-25', 'nome' => 'Carla'],
        ['cpf' => '111.444.777-35', 'nome' => 'Ana'],
    ];

    Livewire::test(Quiz::class, ['empresa' => $this->empresa])
        ->set('socios', $lista)
        ->call('proximoBloco');   // 1 → 2 persiste rascunho

    $rascunho = QuizRascunho::query()->firstOrFail();
    expect(((array) $rascunho->quiz_payload)['socios'])->toBe($lista);

    // Reabrir o quiz devolve a mesma lista na mesma ordem.
    Livewire::test(Quiz::class, ['empresa' => $this->empresa])
        ->assertSet('socios', $lista);
});

it('payload_hash é idempotente à ordem de digitação dos sócios (CA-9)', function () {
    Event::fake();

    $submeter = function (array $socios): string {
        QuizRascunho::query()->withTrashed()->forceDelete();

        quizPreencherAteBloco4($this)
            ->set('Q17', 'sim')
            ->set('Q18', '50000')->set('Q19', '20000')->set('Q20', '10000')
            ->set('socios', $socios)
            ->call('submeter')
            ->assertHasNoErrors();

        return Diagnostico::query()->latest('gerado_em')->firstOrFail()->payload_hash;
    };

    $ana = ['cpf' => '111.444.777-35', 'nome' => 'Ana'];
    $bruno = ['cpf' => '123.456.789-09', 'nome' => 'Bruno'];
    $carla = ['cpf' => '529.982.247-25', 'nome' => 'Carla'];

    $hashA = $submeter([$carla, $ana, $bruno]);
    $hashB = $submeter([$bruno, $carla, $ana]);

    expect($hashA)->toBe($hashB);
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
/**
 * STORY-039 — preenche o Bloco 2 com valores válidos, seta apenas o Q13 sob teste
 * e tenta avançar. Devolve o testable já com erros (ou não) para inspeção.
 */
function quizAvancaBloco2ComQ13(TestCase $test, string $q13): Testable
{
    return Livewire::test(Quiz::class, ['empresa' => $test->empresa])
        ->call('proximoBloco')
        ->set('Q08', '1000')->set('Q09', '2000')
        ->set('Q14', '500')->set('Q15', '200')->set('Q16', '100')
        ->set('Q10', '30')->set('Q11', '15')->set('Q12', '45')
        ->set('Q13', $q13)
        ->call('proximoBloco');
}

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

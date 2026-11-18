<?php

declare(strict_types=1);

use App\Domain\Quiz\CatalogoPerguntas;
use App\Livewire\Backoffice\RespostasDiagnostico;
use App\Livewire\Backoffice\TravamentosQuiz;
use App\Livewire\Diagnostico\Quiz;
use App\Models\AuditLog;
use App\Models\Diagnostico;
use App\Models\EmpresaAnalisada;
use App\Models\EventoProduto;
use App\Models\Usuario;
use App\Observabilidade\EventosQuiz;
use App\Support\RespostaQuiz;
use Illuminate\Support\Str;
use Livewire\Livewire;

/** STORY-105 — respostas do quiz no backoffice e relatório de onde os clientes travam. */
beforeEach(function () {
    $this->admin = Usuario::factory()->create();
    $this->admin->forceFill(['permissoes_admin' => ['quiz.ver_respostas']])->save();
    $this->cliente = Usuario::factory()->create(['nome' => 'Roberto Cliente']);
    $this->empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $this->cliente->id, 'nome_fantasia' => 'Metal Forte']);
    $this->travamentos = fn (): array => EventoProduto::query()->where('nome_evento', EventosQuiz::TRAVAMENTO)
        ->get()->map(fn ($e) => $e->propriedades['tipo'].':'.$e->propriedades['chave'])->sort()->values()->all();
});

// ---------------------------------------------------------------- registro no quiz

it('registra os campos com erro ao tentar avançar, sem o valor digitado', function () {
    $this->actingAs($this->cliente);

    Livewire::test(Quiz::class, ['empresa' => $this->empresa])
        ->call('proximoBloco')
        ->set('Q08', '50000')->set('Q09', '')->set('Q13', '999')
        ->call('proximoBloco')
        ->assertHasErrors(['Q09', 'Q13']);

    $chaves = ($this->travamentos)();
    expect($chaves)->toContain('campo:Q09')->toContain('campo:Q13')->not->toContain('campo:Q08');

    $evento = EventoProduto::query()->where('nome_evento', EventosQuiz::TRAVAMENTO)->firstOrFail();
    expect($evento->propriedades['bloco'])->toBe(2)
        ->and($evento->propriedades['quiz_id'])->not->toBeNull()
        ->and(json_encode($evento->propriedades))->not->toContain('999');
});

it('registra o aviso de consistência mostrado ao sair do bloco 3', function () {
    $this->actingAs($this->cliente);

    Livewire::test(Quiz::class, ['empresa' => $this->empresa])
        ->call('proximoBloco')
        ->set('Q08', '50000')->set('Q09', '100000')->set('Q14', '20000')->set('Q15', '8000')->set('Q16', '2000')
        ->set('Q10', '90')->set('Q11', '15')->set('Q12', '15')->set('Q13', '2')
        ->call('proximoBloco')
        ->set('Q02', '1000')->set('Q03', '1000')->set('Q04', '1000')->set('Q05', '1000')->set('Q06', '100000')->set('Q07', '100000')
        ->call('proximoBloco')
        ->assertSet('bloco_atual', 3);

    expect(($this->travamentos)())->toContain('aviso:R3');
});

it('quiz preenchido sem erro não registra travamento', function () {
    $this->actingAs($this->cliente);

    Livewire::test(Quiz::class, ['empresa' => $this->empresa])->call('proximoBloco')->assertSet('bloco_atual', 2);

    expect(($this->travamentos)())->toBe([]);
});

// ---------------------------------------------------------------- respostas

it('mostra as respostas por bloco, os avisos aceitos e audita a abertura', function () {
    $diag = Diagnostico::factory()->create([
        'usuario_id' => $this->cliente->id, 'empresa_analisada_id' => $this->empresa->id,
        'quiz_payload' => [
            'Q01' => 'industria', 'Q09' => '100000.00', 'Q10' => 90, 'Q13' => '2.50', 'Q17' => true, 'Q18' => '50000.00',
            'socios' => [['cpf' => '52998224725', 'nome' => 'Ana Lima']],
            'alertas_aceitos' => [['regra' => 'R3', 'ocorrido_em' => '2026-11-18T12:00:00+00:00', 'valor_envolvido' => 1]],
        ],
    ]);

    $this->actingAs($this->admin)->get('/backoffice/diagnosticos/'.$diag->id)->assertOk()
        ->assertSee('Metal Forte')
        ->assertSee('Roberto Cliente')
        ->assertSeeInOrder(['Vendas (média mensal)', 'R$ 100.000,00'])
        ->assertSeeInOrder(['PMC — prazo médio de fornecedores', '90 dias'])
        ->assertSeeInOrder(['Inadimplência', '2,50%'])
        ->assertSeeInOrder(['Necessita captar recursos?', 'Sim'])
        ->assertSee('Ana Lima (***.982.247-**)')
        ->assertDontSee('52998224725')
        ->assertSee('Passivo maior que o ativo');

    expect(AuditLog::query()->where('action', 'backoffice.respostas_quiz_vistas')->where('subject_id', $diag->id)->count())->toBe(1);
});

it('sem avisos aceitos, diz que não houve', function () {
    $diag = Diagnostico::factory()->create(['usuario_id' => $this->cliente->id, 'empresa_analisada_id' => $this->empresa->id]);

    Livewire::actingAs($this->admin)->test(RespostasDiagnostico::class, ['diagnostico' => $diag->id])->assertSee('Nenhum: o cliente não recebeu aviso');
});

it('a ficha da empresa leva às respostas só para quem tem a permissão', function () {
    $diag = Diagnostico::factory()->create(['usuario_id' => $this->cliente->id, 'empresa_analisada_id' => $this->empresa->id]);
    $comFicha = Usuario::factory()->create();
    $comFicha->forceFill(['permissoes_admin' => ['usuarios.ver']])->save();

    $this->actingAs($comFicha)->get('/backoffice/empresas/'.$this->empresa->id)->assertOk()->assertDontSee(route('backoffice.diagnosticos.respostas', $diag->id));

    $comFicha->forceFill(['permissoes_admin' => ['usuarios.ver', 'quiz.ver_respostas']])->save();
    $this->actingAs($comFicha)->get('/backoffice/empresas/'.$this->empresa->id)->assertOk()->assertSee(route('backoffice.diagnosticos.respostas', $diag->id));
});

it('sem a permissão, respostas e relatório respondem 404', function () {
    $diag = Diagnostico::factory()->create(['usuario_id' => $this->cliente->id, 'empresa_analisada_id' => $this->empresa->id]);
    $outro = Usuario::factory()->create();
    $outro->forceFill(['permissoes_admin' => ['usuarios.ver']])->save();

    $this->actingAs($outro)->get('/backoffice/diagnosticos/'.$diag->id)->assertNotFound();
    $this->actingAs($outro)->get('/backoffice/quiz/travamentos')->assertNotFound();
    Livewire::actingAs($outro)->test(TravamentosQuiz::class)->assertNotFound();
});

it('diagnóstico inexistente responde 404', function () {
    $this->actingAs($this->admin)->get('/backoffice/diagnosticos/019a0000-0000-7000-8000-000000000000')->assertNotFound();
});

// ---------------------------------------------------------------- relatório

it('soma os erros por pergunta e os avisos por regra, sem contar o Logar como nem o que saiu do período', function () {
    EventosQuiz::travamentos(EventosQuiz::TIPO_CAMPO, ['Q13', 'Q09'], 2, 'quiz-a', $this->cliente->id, $this->empresa->id);
    EventosQuiz::travamentos(EventosQuiz::TIPO_CAMPO, ['Q13'], 2, 'quiz-b', $this->cliente->id, $this->empresa->id);
    EventosQuiz::travamentos(EventosQuiz::TIPO_CAMPO, ['Q13'], 2, 'quiz-b', $this->cliente->id, $this->empresa->id);
    EventosQuiz::travamentos(EventosQuiz::TIPO_AVISO, ['R3'], 3, 'quiz-a', $this->cliente->id, $this->empresa->id);
    EventosQuiz::travamentos(EventosQuiz::TIPO_BLOQUEIO, ['R4'], 4, 'quiz-a', $this->cliente->id, $this->empresa->id);
    // Logar como: fica de fora.
    EventoProduto::query()->create(['evento_id' => (string) Str::uuid(), 'nome_evento' => EventosQuiz::TRAVAMENTO, 'usuario_id' => $this->cliente->id,
        'propriedades' => ['tipo' => 'campo', 'chave' => 'Q02', 'bloco' => 3, 'quiz_id' => 'x', 'personificacao' => ['admin_id' => 'a']]]);
    // Fora do período de 30 dias.
    EventoProduto::query()->create(['evento_id' => (string) Str::uuid(), 'nome_evento' => EventosQuiz::TRAVAMENTO, 'usuario_id' => $this->cliente->id, 'ocorrido_em' => now()->subDays(60),
        'propriedades' => ['tipo' => 'campo', 'chave' => 'Q05', 'bloco' => 3, 'quiz_id' => 'y']]);
    Diagnostico::factory()->create(['usuario_id' => $this->cliente->id, 'empresa_analisada_id' => $this->empresa->id,
        'quiz_payload' => ['alertas_aceitos' => [['regra' => 'R3'], ['regra' => 'R1']]]]);

    $this->actingAs($this->admin)->get('/backoffice/quiz/travamentos?dias=30')->assertOk()
        ->assertSeeInOrder(['Inadimplência', '<td class="px-3 py-2">2</td>', '>3</td>', '>2</td>', 'Vendas (média mensal)', '>1</td>', '>1</td>'], false)
        ->assertDontSee('Recursos disponíveis')
        ->assertDontSee('Patrimônio (imobilizado)')
        ->assertSeeInOrder(['R1', '>0</td>', '>1</td>', 'R3', '>1</td>', '>1</td>', 'R4', 'bloqueia', '>1</td>', '>—</td>'], false);
});

it('sem dados, mostra o relatório vazio', function () {
    Livewire::actingAs($this->admin)->test(TravamentosQuiz::class)->assertSee('Nenhum erro de preenchimento registrado no período.');
});

it('período inválido cai no padrão de 90 dias', function () {
    Livewire::actingAs($this->admin)->test(TravamentosQuiz::class)->set('dias', 7)->assertOk();
});

// ---------------------------------------------------------------- formatação

it('formata cada tipo de resposta', function (string $tipo, mixed $valor, string $esperado) {
    expect(RespostaQuiz::formatar($tipo, $valor))->toBe($esperado);
})->with([
    ['moeda', '1234.5', 'R$ 1.234,50'],
    ['dias', 45, '45 dias'],
    ['percentual', '3.46', '3,46%'],
    ['simnao', false, 'Não'],
    ['simnao', 'sim', 'Sim'],
    ['setor', 'industria', 'Industria'],
    ['moeda', null, '—'],
    ['socios', [['cpf' => '111', 'nome' => 'X']], 'X (—)'],
    ['socios', 'lixo', '—'],
    ['outro', ['a'], '—'],
]);

it('o catálogo tem os 20 campos do quiz mais os sócios e os títulos das regras', function () {
    expect(CatalogoPerguntas::PADRAO)->toHaveCount(21)
        ->and(CatalogoPerguntas::PADRAO['Q09']['rotulo'])->toBe('Vendas (média mensal)')
        ->and(CatalogoPerguntas::regra('R1'))->toBe('Despesas financeiras altas para a dívida declarada')
        ->and(CatalogoPerguntas::regra('R9'))->toBe('R9');
});

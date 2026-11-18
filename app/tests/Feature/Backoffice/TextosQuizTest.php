<?php

declare(strict_types=1);

use App\Livewire\Backoffice\EditarTextosQuiz;
use App\Livewire\Diagnostico\Quiz;
use App\Models\AuditLog;
use App\Models\EmpresaAnalisada;
use App\Models\QuizTexto;
use App\Models\Usuario;
use App\Support\TextosQuiz;
use Livewire\Livewire;

/** STORY-106 — textos das perguntas do quiz editáveis no backoffice. */
beforeEach(function () {
    $this->admin = Usuario::factory()->create(['nome' => 'Ana Admin']);
    $this->admin->forceFill(['permissoes_admin' => ['quiz.editar_textos']])->save();
    $this->tela = fn () => Livewire::actingAs($this->admin)->test(EditarTextosQuiz::class);
});

it('sem edição, o quiz usa o texto original do código', function () {
    expect(TextosQuiz::rotulo('Q09'))->toBe('Vendas (média mensal)')
        ->and(TextosQuiz::auxiliar('Q09'))->toBe('Receita operacional bruta.')
        ->and(TextosQuiz::ajuda('Q09'))->toBe(config('quiz.help-industria.campos.Q09'))
        ->and(TextosQuiz::todos()['Q09']['editado'])->toBeFalse()
        ->and(TextosQuiz::rotulo('Q99'))->toBe('Q99');
});

it('edita enunciado, auxiliar e ajuda; o quiz passa a mostrar o novo texto', function () {
    ($this->tela)()->call('editar', 'Q09')
        ->assertSet('rotulo', 'Vendas (média mensal)')
        ->set('rotulo', '  Quanto você vende por mês, em média? ')
        ->set('auxiliar', 'Some tudo que entrou com vendas.')
        ->set('ajuda', 'Use a média dos **últimos 12 meses**.')
        ->assertSee('Como o cliente vê')
        ->assertSee('Alterações não salvas.')
        ->assertSee('Original: “Vendas (média mensal)”', false)
        ->call('salvar')->assertHasNoErrors()->assertSet('editando', 'Q09')
        ->assertSee('Salvo. O quiz já mostra o texto novo.')
        ->assertDontSee('Alterações não salvas.')
        ->assertSee('Quanto você vende por mês, em média?')->assertSee('Editado');

    $texto = QuizTexto::query()->findOrFail('Q09');
    expect($texto->rotulo)->toBe('Quanto você vende por mês, em média?')->and($texto->alterado_por_usuario_id)->toBe($this->admin->id);

    $cliente = Usuario::factory()->create();
    $empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $cliente->id]);
    Livewire::actingAs($cliente)->test(Quiz::class, ['empresa' => $empresa])->call('proximoBloco')
        ->assertSee('Quanto você vende por mês, em média?')
        ->assertSee('Some tudo que entrou com vendas.')
        ->assertSee('<strong>últimos 12 meses</strong>', false)
        ->assertDontSee('Receita operacional bruta.');
});

it('audita antes e depois, com o campo no contexto, e mostra o histórico', function () {
    ($this->tela)()->call('editar', 'Q13')->set('rotulo', 'Inadimplência (%)')->call('salvar');

    $log = AuditLog::query()->where('action', 'quiz.texto_alterado')->sole();
    expect($log->context['campo'])->toBe('Q13')
        ->and($log->before['rotulo'])->toBe('Inadimplência')
        ->and($log->after['rotulo'])->toBe('Inadimplência (%)');

    ($this->tela)()->call('editar', 'Q13')->assertSee('Ana Admin')->assertSee('Enunciado era: “Inadimplência”', false);
});

it('restaurar volta ao original, apaga a edição e audita', function () {
    ($this->tela)()->call('editar', 'Q13')->set('rotulo', 'Outro texto')->call('salvar');

    ($this->tela)()->call('restaurar', 'Q13')->assertDontSee('Outro texto');

    expect(QuizTexto::query()->count())->toBe(0)
        ->and(TextosQuiz::rotulo('Q13'))->toBe('Inadimplência')
        ->and(AuditLog::query()->where('action', 'quiz.texto_restaurado')->count())->toBe(1);
});

it('salvar igual ao original não guarda edição; salvar sem mudança não audita', function () {
    ($this->tela)()->call('editar', 'Q13')->set('rotulo', 'X')->call('salvar');
    ($this->tela)()->call('editar', 'Q13')->set('rotulo', 'Inadimplência')->call('salvar');
    expect(QuizTexto::query()->count())->toBe(0);

    ($this->tela)()->call('editar', 'Q13')->call('salvar');
    expect(AuditLog::query()->where('action', 'quiz.texto_alterado')->count())->toBe(2);
});

it('restaurar o que já é original não faz nada', function () {
    ($this->tela)()->call('restaurar', 'Q13');
    expect(AuditLog::query()->where('action', 'quiz.texto_restaurado')->count())->toBe(0);
});

it('recusa enunciado vazio e textos longos demais', function (string $propriedade, string $valor) {
    ($this->tela)()->call('editar', 'Q13')->set($propriedade, $valor)->call('salvar')->assertHasErrors($propriedade);
    expect(QuizTexto::query()->count())->toBe(0);
})->with([
    'enunciado vazio' => ['rotulo', '   '],
    'enunciado longo' => ['rotulo', str_repeat('a', 161)],
    'auxiliar longo' => ['auxiliar', str_repeat('a', 301)],
    'ajuda longa' => ['ajuda', str_repeat('a', 1201)],
]);

it('abre na primeira pergunta e descartar volta ao texto salvo', function () {
    ($this->tela)()->assertSet('editando', 'Q01')
        ->call('editar', 'Q13')->set('rotulo', 'Rascunho')->assertSee('Alterações não salvas.')
        ->call('descartar')->assertSet('rotulo', 'Inadimplência')->assertDontSee('Alterações não salvas.');

    expect(QuizTexto::query()->count())->toBe(0);
});

it('campo inexistente responde 404', function () {
    ($this->tela)()->call('editar', 'Q99')->assertNotFound();
});

it('salvar sem nada em edição responde 404', function () {
    ($this->tela)()->set('editando', null)->call('salvar')->assertNotFound();
});

it('sem a permissão, a tela responde 404', function () {
    $outro = Usuario::factory()->create();
    $outro->forceFill(['permissoes_admin' => ['quiz.ver_respostas']])->save();

    $this->actingAs($outro)->get('/backoffice/quiz/textos')->assertNotFound();
    Livewire::actingAs($outro)->test(EditarTextosQuiz::class)->assertNotFound();
    $this->actingAs($this->admin)->get('/backoffice/quiz/textos')->assertOk()->assertSee('Textos do quiz');
});

it('as respostas e o relatório usam o texto editado', function () {
    QuizTexto::query()->create(['campo' => 'Q13', 'rotulo' => 'Clientes que atrasam (%)', 'auxiliar' => '', 'ajuda' => '']);
    TextosQuiz::esquecer();

    expect(TextosQuiz::rotulo('Q13'))->toBe('Clientes que atrasam (%)')
        ->and(TextosQuiz::ajuda('Q13'))->toBe('');
});

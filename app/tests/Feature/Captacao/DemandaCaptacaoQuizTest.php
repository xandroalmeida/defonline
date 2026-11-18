<?php

declare(strict_types=1);

use App\Jobs\AvisarDemandaCaptacao;
use App\Livewire\Diagnostico\Quiz;
use App\Models\DemandaCaptacao;
use App\Models\Diagnostico;
use App\Models\EmpresaAnalisada;
use App\Models\QuizRascunho;
use App\Models\Usuario;
use Illuminate\Support\Facades\Queue;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * STORY-102 / PDR-021 — demanda de captação no bloco 4: autorização opt-out, registro ao concluir
 * o diagnóstico, avisos agendados e a mensagem de prazo no resultado.
 */
beforeEach(function () {
    Queue::fake();
    $this->usuario = Usuario::factory()->create();
    $this->actingAs($this->usuario);
    $this->empresa = EmpresaAnalisada::factory()->state(['usuario_id' => $this->usuario->id])->create();
});

function _captacaoBloco4(TestCase $test): Testable
{
    return Livewire::test(Quiz::class, ['empresa' => $test->empresa])
        ->call('proximoBloco')
        ->set('Q08', '50000')->set('Q09', '100000')->set('Q14', '20000')->set('Q15', '8000')->set('Q16', '2000')
        ->set('Q10', '90')->set('Q11', '15')->set('Q12', '15')->set('Q13', '2')
        ->call('proximoBloco')
        ->set('Q02', '50000')->set('Q03', '20000')->set('Q04', '10000')->set('Q05', '300000')->set('Q06', '40000')->set('Q07', '80000')
        ->call('proximoBloco');
}

function _comCaptacao(Testable $quiz): Testable
{
    return $quiz->set('Q17', 'sim')
        ->set('Q18', '50.000,00')->set('Q19', '20000')->set('Q20', '10000')
        ->set('socios', [['cpf' => '529.982.247-25', 'nome' => 'Ana Lima']]);
}

it('vem com o contato autorizado e mostra o prazo de 5 dias úteis', function () {
    _comCaptacao(_captacaoBloco4($this))
        ->assertSet('autoriza_contato_captacao', true)
        ->assertSee('Autorizo a equipe DEFOnline')
        ->assertSee('5 dias úteis');
});

it('desmarcado, some a mensagem de prazo', function () {
    _comCaptacao(_captacaoBloco4($this))
        ->set('autoriza_contato_captacao', false)
        ->assertSee('Autorizo a equipe DEFOnline')
        ->assertDontSee('5 dias úteis');
});

it('sem captação, não mostra a autorização', function () {
    _captacaoBloco4($this)->set('Q17', 'nao')->assertDontSee('Autorizo a equipe DEFOnline');
});

it('com captação autorizada, registra a demanda e agenda os avisos', function () {
    _comCaptacao(_captacaoBloco4($this))->call('submeter')->assertHasNoErrors()->assertRedirect();

    $diagnostico = Diagnostico::query()->firstOrFail();
    $demanda = DemandaCaptacao::query()->sole();

    expect($demanda->diagnostico_id)->toBe($diagnostico->id)
        ->and($demanda->usuario_id)->toBe($this->usuario->id)
        ->and($demanda->empresa_analisada_id)->toBe($this->empresa->id)
        ->and($demanda->valor_captar)->toBe('50000.00')
        ->and($demanda->autoriza_contato)->toBeTrue()
        // A autorização não entra no payload do motor (o hash do diagnóstico não muda por ela).
        ->and(array_key_exists('autoriza_contato_captacao', (array) $diagnostico->quiz_payload))->toBeFalse();

    Queue::assertPushed(AvisarDemandaCaptacao::class, fn (AvisarDemandaCaptacao $job) => $job->demandaId === $demanda->id);
});

it('com o contato desmarcado, registra a demanda sem autorização e não avisa ninguém', function () {
    _comCaptacao(_captacaoBloco4($this))->set('autoriza_contato_captacao', false)->call('submeter')->assertHasNoErrors();

    expect(DemandaCaptacao::query()->sole()->autoriza_contato)->toBeFalse();
    Queue::assertNotPushed(AvisarDemandaCaptacao::class);
});

it('sem captação, não registra demanda', function () {
    _captacaoBloco4($this)->set('Q17', 'nao')->call('submeter')->assertHasNoErrors();

    expect(Diagnostico::query()->count())->toBe(1)
        ->and(DemandaCaptacao::query()->count())->toBe(0);
    Queue::assertNotPushed(AvisarDemandaCaptacao::class);
});

it('a escolha sobrevive à retomada do rascunho', function () {
    _captacaoBloco4($this);
    // O rascunho é gravado ao avançar de bloco; simula o estado do bloco 4 gravado (como no saldo esgotado).
    $rascunho = QuizRascunho::paraEmpresa($this->empresa);
    $rascunho->update(['quiz_payload' => [...(array) $rascunho->quiz_payload, 'Q17' => 'sim', 'autoriza_contato_captacao' => false]]);

    Livewire::test(Quiz::class, ['empresa' => $this->empresa])->assertSet('autoriza_contato_captacao', false);
});

it('uma falha ao registrar a demanda não derruba o diagnóstico', function () {
    DemandaCaptacao::creating(fn () => throw new RuntimeException('banco fora'));

    _comCaptacao(_captacaoBloco4($this))->call('submeter')->assertHasNoErrors()->assertRedirect();

    expect(Diagnostico::query()->count())->toBe(1)
        ->and(DemandaCaptacao::query()->count())->toBe(0);
    Queue::assertNotPushed(AvisarDemandaCaptacao::class);
});

it('o resultado confirma o prazo só quando o contato foi autorizado', function () {
    $diagnostico = Diagnostico::factory()->create(['usuario_id' => $this->usuario->id, 'empresa_analisada_id' => $this->empresa->id]);
    $url = '/diagnosticos/'.$diagnostico->id;

    $this->get($url)->assertOk()->assertDontSee('Recebemos sua demanda de captação');

    $demanda = DemandaCaptacao::query()->create([
        'diagnostico_id' => $diagnostico->id, 'usuario_id' => $this->usuario->id,
        'empresa_analisada_id' => $this->empresa->id, 'valor_captar' => '1000', 'autoriza_contato' => false,
    ]);
    $this->get($url)->assertOk()->assertDontSee('Recebemos sua demanda de captação');

    $demanda->update(['autoriza_contato' => true]);
    $this->get($url)->assertOk()->assertSee('Recebemos sua demanda de captação')->assertSee('5 dias úteis');
});

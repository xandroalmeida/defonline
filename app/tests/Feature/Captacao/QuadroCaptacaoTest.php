<?php

declare(strict_types=1);

use App\Domain\Captacao\StatusDemanda;
use App\Livewire\Backoffice\Captacao;
use App\Models\AuditLog;
use App\Models\DemandaCaptacao;
use App\Models\DemandaCaptacaoEvento;
use App\Models\Diagnostico;
use App\Models\EmpresaAnalisada;
use App\Models\Usuario;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

/** STORY-103 — quadro Kanban das demandas de captação: etapas, responsável, notas, filtros e prazo. */
beforeEach(function () {
    $this->admin = Usuario::factory()->create(['nome' => 'Ana Admin']);
    $this->admin->forceFill(['permissoes_admin' => ['captacao.gerenciar']])->save();
    $this->colega = Usuario::factory()->create(['nome' => 'Bia Colega']);
    $this->colega->forceFill(['permissoes_admin' => ['captacao.gerenciar']])->save();
    $this->tela = fn () => Livewire::actingAs($this->admin)->test(Captacao::class);

    $this->nova = function (array $attrs = [], string $razao = 'Metalúrgica Exemplo Ltda', string $documento = '11222333000181'): DemandaCaptacao {
        $dono = Usuario::factory()->create();
        $empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $dono->id, 'razao_social' => $razao, 'nome_fantasia' => null, 'documento' => $documento]);
        $diagnostico = Diagnostico::factory()->create(['usuario_id' => $dono->id, 'empresa_analisada_id' => $empresa->id]);

        return DemandaCaptacao::query()->create([
            'diagnostico_id' => $diagnostico->id, 'usuario_id' => $dono->id, 'empresa_analisada_id' => $empresa->id,
            'valor_captar' => '50000', 'autoriza_contato' => true, ...$attrs,
        ]);
    };
});

it('mostra as quatro etapas e põe a demanda nova na primeira', function () {
    $d = ($this->nova)();

    expect($d->fresh()->status)->toBe(StatusDemanda::Nova);
    ($this->tela)()
        ->assertSeeInOrder(['Nova', 'Em análise', 'Respondida', 'Encerrada'])
        ->assertSee('Metalúrgica Exemplo Ltda')
        ->assertSee('R$ 50.000,00');
});

it('move entre etapas, registra no histórico e na auditoria', function () {
    $d = ($this->nova)();
    $this->travelTo(CarbonImmutable::parse('2026-11-18 15:00:00', 'UTC'));

    ($this->tela)()->call('mover', $d->id, 'em_analise')->assertHasNoErrors();

    $d->refresh();
    expect($d->status)->toBe(StatusDemanda::EmAnalise)
        ->and($d->status_alterado_em?->toIso8601String())->toBe('2026-11-18T15:00:00+00:00');

    $evento = DemandaCaptacaoEvento::query()->sole();
    expect($evento->tipo)->toBe('status')->and($evento->texto)->toBe('Moveu de Nova para Em análise.')->and($evento->autor_id)->toBe($this->admin->id);

    $log = AuditLog::query()->where('action', 'captacao.demanda_movida')->sole();
    expect($log->before->getArrayCopy())->toBe(['status' => 'nova'])->and($log->after->getArrayCopy())->toBe(['status' => 'em_analise']);
});

it('pode voltar etapa e mover para a mesma etapa não grava nada', function () {
    $d = ($this->nova)(['status' => 'respondida']);

    ($this->tela)()->call('mover', $d->id, 'respondida')->call('mover', $d->id, 'em_analise');

    expect($d->fresh()->status)->toBe(StatusDemanda::EmAnalise)
        ->and(DemandaCaptacaoEvento::query()->count())->toBe(1);
});

it('recusa etapa inexistente', function () {
    $d = ($this->nova)();

    ($this->tela)()->call('mover', $d->id, 'ganha')->assertStatus(422);
    expect($d->fresh()->status)->toBe(StatusDemanda::Nova);
});

it('atribui e remove o responsável, com histórico', function () {
    $d = ($this->nova)();

    ($this->tela)()->call('atribuir', $d->id, $this->colega->id)->assertHasNoErrors();
    expect($d->fresh()->responsavel_id)->toBe($this->colega->id);

    ($this->tela)()->call('atribuir', $d->id, '');
    expect($d->fresh()->responsavel_id)->toBeNull();

    expect(DemandaCaptacaoEvento::query()->oldest('created_at')->pluck('texto')->all())
        ->toBe(['Atribuiu a Bia Colega.', 'Removeu o responsável.']);
});

it('não aceita como responsável quem não tem acesso à captação', function () {
    $d = ($this->nova)();
    $outro = Usuario::factory()->create();

    ($this->tela)()->call('atribuir', $d->id, $outro->id)->assertStatus(422);
    expect($d->fresh()->responsavel_id)->toBeNull();
});

it('adiciona nota pelo detalhe, sem guardar o texto na auditoria', function () {
    $d = ($this->nova)();

    ($this->tela)()->call('abrir', $d->id)->assertSee('Retornar até')
        ->set('nota', '  Liguei, pediu proposta até sexta.  ')->call('anotar')
        ->assertHasNoErrors()->assertSet('nota', '')->assertSee('Liguei, pediu proposta até sexta.');

    $evento = DemandaCaptacaoEvento::query()->sole();
    expect($evento->tipo)->toBe('nota')->and($evento->texto)->toBe('Liguei, pediu proposta até sexta.');

    $log = AuditLog::query()->where('action', 'captacao.demanda_anotada')->sole();
    expect($log->after->getArrayCopy())->toBe(['evento_id' => $evento->id]);
});

it('recusa nota vazia ou longa demais', function (string $nota) {
    $d = ($this->nova)();

    ($this->tela)()->call('abrir', $d->id)->set('nota', $nota)->call('anotar')->assertHasErrors('nota');
    expect(DemandaCaptacaoEvento::query()->count())->toBe(0);
})->with(['vazia' => ['   '], 'longa' => [str_repeat('a', 2001)]]);

it('abre o cartão pelo link do e-mail (?demanda=) e fecha', function () {
    $d = ($this->nova)();

    $this->actingAs($this->admin)->get('/backoffice/captacao?demanda='.$d->id)->assertOk()->assertSee('captacao-detalhe', false);
    ($this->tela)()->call('abrir', $d->id)->assertSet('selecionadaId', $d->id)->call('fechar')->assertSet('selecionadaId', null);
});

it('marca como atrasada a demanda em aberto com o prazo vencido, e não a respondida', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-02 15:00:00', 'UTC')); // segunda
    $atrasada = ($this->nova)([], 'Atrasada Ltda', '11111111000191');
    $respondida = ($this->nova)(['status' => 'respondida'], 'Respondida Ltda', '22222222000191');
    $this->travelTo(CarbonImmutable::parse('2026-11-10 15:00:00', 'UTC')); // terça seguinte: prazo era 09/11

    expect($atrasada->fresh()->atrasada())->toBeTrue()->and($respondida->fresh()->atrasada())->toBeFalse();
    ($this->tela)()->assertSee('Atrasada · era até 09/11/2026');
});

it('no último dia do prazo ainda não está atrasada', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-02 15:00:00', 'UTC'));
    $d = ($this->nova)();
    $this->travelTo(CarbonImmutable::parse('2026-11-09 23:00:00', 'UTC')); // 20h de 09/11 em Brasília

    expect($d->fresh()->atrasada())->toBeFalse();
});

it('esconde as sem autorização, a menos que o filtro peça', function () {
    ($this->nova)(['autoriza_contato' => false], 'Sem Contato Ltda', '33333333000191');

    ($this->tela)()->assertDontSee('Sem Contato Ltda')->assertSee('Mostrar sem autorização de contato (1)')
        ->set('mostrarSemAutorizacao', true)->assertSee('Sem Contato Ltda')->assertSee('Sem autorização');
});

it('filtra por responsável: meus, sem responsável e um colega', function () {
    ($this->nova)(['responsavel_id' => $this->admin->id], 'Minha Ltda', '44444444000191');
    ($this->nova)(['responsavel_id' => $this->colega->id], 'Da Colega Ltda', '55555555000191');
    ($this->nova)([], 'Ninguem Ltda', '66666666000191');

    ($this->tela)()->set('responsavel', 'meus')->assertSee('Minha Ltda')->assertDontSee('Da Colega Ltda')->assertDontSee('Ninguem Ltda')
        ->set('responsavel', 'sem')->assertSee('Ninguem Ltda')->assertDontSee('Minha Ltda')
        ->set('responsavel', $this->colega->id)->assertSee('Da Colega Ltda')->assertDontSee('Ninguem Ltda');
});

it('busca por nome da empresa ou por CNPJ com máscara', function () {
    ($this->nova)([], 'Fundição Alfa Ltda', '12345678000195');
    ($this->nova)([], 'Plásticos Beta Ltda', '98765432000198');

    ($this->tela)()->set('busca', 'alfa')->assertSee('Fundição Alfa Ltda')->assertDontSee('Plásticos Beta Ltda')
        ->set('busca', '98.765.432/0001-98')->assertSee('Plásticos Beta Ltda')->assertDontSee('Fundição Alfa Ltda');
});

it('o quadro mostra só as 30 encerradas mais recentes, com o total', function () {
    foreach (range(1, 31) as $i) {
        ($this->nova)(['status' => 'encerrada', 'status_alterado_em' => now()->subMinutes($i)], "Encerrada {$i} Ltda", str_pad((string) $i, 14, '0', STR_PAD_LEFT));
    }

    ($this->tela)()->assertSee('Mostrando as 30 mais recentes.')->assertSee('Encerrada 1 Ltda')->assertDontSee('Encerrada 31 Ltda');
});

it('sem demandas, cada coluna diz que está vazia', function () {
    ($this->tela)()->assertSee('Nenhuma demanda.');
});

it('sem a permissão, as ações do quadro respondem 404', function () {
    $d = ($this->nova)();
    $outro = Usuario::factory()->create();
    $outro->forceFill(['permissoes_admin' => ['glossario.editar']])->save();

    Livewire::actingAs($outro)->test(Captacao::class)->assertNotFound();
    expect($d->fresh()->status)->toBe(StatusDemanda::Nova);
});

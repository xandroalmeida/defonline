<?php

declare(strict_types=1);

use App\Livewire\Backoffice\Glossario;
use App\Models\AuditLog;
use App\Models\Diagnostico;
use App\Models\EmpresaAnalisada;
use App\Models\GlossarioTermo;
use App\Models\Usuario;
use Livewire\Livewire;

/**
 * STORY-067 / PDR-013 — manutenção do glossário.
 */
beforeEach(function () {
    $this->admin = Usuario::factory()->create();
    $this->admin->forceFill(['permissoes_admin' => ['glossario.editar']])->save();
    $this->tela = fn () => Livewire::actingAs($this->admin)->test(Glossario::class);
    $this->relatorio = function (): string {
        $dono = Usuario::factory()->create();
        $empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $dono->id]);
        $diagnostico = Diagnostico::factory()->create(['usuario_id' => $dono->id, 'empresa_analisada_id' => $empresa->id]);

        return $this->actingAs($dono)->get('/diagnosticos/'.$diagnostico->id)->assertOk()->getContent();
    };
});

it('a migration traz os 17 termos na ordem que estava no código', function () {
    $termos = GlossarioTermo::query()->orderBy('ordem')->pluck('termo');

    expect($termos)->toHaveCount(17)
        ->and($termos[0])->toContain('Margem Bruta')
        ->and($termos[1])->toBe('EBITDA / Margem Operacional')
        ->and($termos->last())->toBe('Balanço Adaptado / DRE Adaptada');
});

it('adiciona o termo no fim da lista e ele aparece no relatório', function () {
    ($this->tela)()->set('termo', '  Ciclo Operacional ')->set('definicao', ' Tempo do ciclo. ')->call('salvar')
        ->assertHasNoErrors()->assertSet('termo', '')->assertSee('Ciclo Operacional');

    $termo = GlossarioTermo::query()->where('termo', 'Ciclo Operacional')->firstOrFail();

    expect($termo->definicao)->toBe('Tempo do ciclo.')
        ->and($termo->ordem)->toBe(18)
        ->and(($this->relatorio)())->toContain('Ciclo Operacional')->toContain('Tempo do ciclo.');
});

it('edita o termo sem acusar duplicidade com ele mesmo', function () {
    $termo = GlossarioTermo::query()->where('termo', 'Inadimplência')->firstOrFail();

    ($this->tela)()->call('editar', $termo->id)
        ->assertSet('termo', 'Inadimplência')
        ->set('definicao', 'Texto novo.')
        ->call('salvar')
        ->assertHasNoErrors()
        ->assertSet('editandoId', null);

    expect($termo->fresh()->definicao)->toBe('Texto novo.');
});

it('recusa termo ou definição vazios, longos demais ou termo repetido', function (string $termo, string $definicao, string $campo) {
    ($this->tela)()->set('termo', $termo)->set('definicao', $definicao)->call('salvar')->assertHasErrors($campo);

    expect(GlossarioTermo::query()->count())->toBe(17);
})->with(fn () => [
    'termo vazio' => ['   ', 'Definição', 'termo'],
    'definição vazia' => ['Novo', '   ', 'definicao'],
    'termo longo' => [str_repeat('a', 121), 'Definição', 'termo'],
    'definição longa' => ['Novo', str_repeat('a', 1001), 'definicao'],
    'repetido' => ['Inadimplência', 'Definição', 'termo'],
]);

it('exclui o termo, que some do relatório', function () {
    $termo = GlossarioTermo::query()->where('termo', 'Inadimplência')->firstOrFail();

    ($this->tela)()->call('editar', $termo->id)->call('excluir', $termo->id)->assertSet('editandoId', null);

    expect(GlossarioTermo::query()->whereKey($termo->id)->exists())->toBeFalse()
        ->and(($this->relatorio)())->not->toContain('Percentual médio de atraso');
});

it('tira a seção do relatório quando o glossário fica vazio', function () {
    GlossarioTermo::query()->delete();

    expect(($this->relatorio)())->not->toContain('data-testid="glossario"');
    ($this->tela)()->assertSee('O glossário está vazio');
});

it('move termos e o relatório segue a nova ordem, sem sair dos limites', function () {
    $ids = GlossarioTermo::query()->orderBy('ordem')->pluck('id');
    $tela = ($this->tela)();

    $tela->call('mover', $ids[1], -1);
    $ordem = GlossarioTermo::query()->orderBy('ordem')->pluck('id');
    expect($ordem[0])->toBe($ids[1])->and($ordem[1])->toBe($ids[0]);

    $tela->call('mover', $ids[1], -1)->call('mover', $ids->last(), 1)->call('mover', (string) str()->uuid7(), 1);
    expect(GlossarioTermo::query()->orderBy('ordem')->pluck('id')->all())->toBe($ordem->all());
});

it('audita criação, alteração, exclusão e movimento com antes e depois', function () {
    $tela = ($this->tela)()->set('termo', 'Novo')->set('definicao', 'Um')->call('salvar');
    $termo = GlossarioTermo::query()->where('termo', 'Novo')->firstOrFail();
    $tela->call('editar', $termo->id)->set('definicao', 'Dois')->call('salvar')
        ->call('mover', $termo->id, -1)
        ->call('excluir', $termo->id);

    $log = fn (string $acao) => AuditLog::where('action', $acao)->firstOrFail();

    expect($log('glossario.termo_criado')->after['definicao'])->toBe('Um')
        ->and($log('glossario.termo_alterado')->before['definicao'])->toBe('Um')
        ->and($log('glossario.termo_alterado')->after['definicao'])->toBe('Dois')
        ->and($log('glossario.termo_movido')->after['posicao'])->toBe(17)
        ->and($log('glossario.termo_excluido')->before['definicao'])->toBe('Dois')
        ->and($log('glossario.termo_excluido')->actor_id)->toBe($this->admin->id);
});

it('escapa o texto cadastrado no relatório', function () {
    ($this->tela)()->set('termo', '<b>Termo</b>')->set('definicao', '<script>alert(1)</script>')->call('salvar');

    expect(($this->relatorio)())->not->toContain('<script>alert(1)</script>')->not->toContain('<b>Termo</b>');
});

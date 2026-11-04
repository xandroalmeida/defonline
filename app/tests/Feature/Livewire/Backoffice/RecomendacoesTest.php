<?php

declare(strict_types=1);

use App\Actions\CalcularDiagnostico;
use App\Domain\Motor\FonteRecomendacoes;
use App\Domain\Motor\MatrizRecomendacoes;
use App\Livewire\Backoffice\Recomendacoes;
use App\Models\AuditLog;
use App\Models\EmpresaAnalisada;
use App\Models\Recomendacao;
use App\Models\Usuario;
use App\Support\Relatorio\IndicadorFormatter;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\Support\RecomendacoesCongeladas;

/**
 * STORY-068 / PDR-016 — manutenção da matriz de recomendações.
 */
beforeEach(function () {
    $this->admin = Usuario::factory()->create();
    $this->admin->forceFill(['permissoes_admin' => ['recomendacoes.editar']])->save();
    $this->tela = fn () => Livewire::actingAs($this->admin)->test(Recomendacoes::class);
    $this->registro = fn (string $setor, string $indicador, string $farol) => Recomendacao::query()
        ->where(['setor' => $setor, 'indicador' => $indicador, 'farol' => $farol])
        ->firstOrFail();
    $this->payload = fn (): array => json_decode(
        (string) file_get_contents(base_path('tests/Domain/Motor/Fixtures/quiz_industria_atencao.json')),
        true,
    );
});

it('a migration traz 117 textos, um por trio, todos preenchidos e dentro do limite', function () {
    $linhas = Recomendacao::query()->get();

    expect($linhas)->toHaveCount(117);

    foreach (array_keys(Recomendacoes::SETORES) as $setor) {
        foreach (IndicadorFormatter::ORDEM_ESSENCIAIS as $indicador) {
            foreach (Recomendacoes::FAROIS as $farol) {
                $linha = $linhas->first(fn (Recomendacao $r) => $r->setor === $setor && $r->indicador === $indicador && $r->farol === $farol);

                expect($linha)->not->toBeNull("Faltando {$setor}/{$indicador}/{$farol}")
                    ->and($linha->faixa)->not->toBe('')
                    ->and($linha->editado_em)->toBeNull()
                    ->and(mb_strlen($linha->texto))->toBeGreaterThan(0)->toBeLessThanOrEqual(Recomendacoes::TEXTO_MAX);
            }
        }
    }

    expect($linhas->where('indicador', 'ncg_absoluto'))->toBeEmpty();
});

it('os textos de Indústria na migration são os mesmos dos testes de domínio e golden hashes', function () {
    $congelada = RecomendacoesCongeladas::matriz();

    foreach (Recomendacao::query()->where('setor', 'industria')->get() as $linha) {
        expect($linha->texto)->toBe($congelada[$linha->indicador][$linha->farol]);
    }
});

it('traz Comércio e Serviços do Anexo F, com a faixa própria do segmento', function () {
    $comercio = ($this->registro)('comercio', 'margem_bruta', 'vermelho');
    $servicos = ($this->registro)('servicos', 'margem_bruta', 'vermelho');

    expect($comercio->faixa)->toBe('até 18%')
        ->and($comercio->texto)->toBe('Reduzir o estoque dos produtos com menor margem de contribuição e focar naqueles com maior margem.')
        ->and($servicos->faixa)->toBe('até 25%')
        ->and(($this->registro)('servicos', 'pme', 'verde')->texto)->toBe('Não se aplica a serviços.')
        ->and(($this->registro)('industria', 'pmr', 'amarelo')->faixa)->toBe('30,01–60 dias')
        ->and(($this->registro)('industria', 'ncg_vendas', 'vermelho')->faixa)->toBe('Acima de 10%');
});

it('filtra por segmento e indicador e mostra as 3 faixas', function () {
    ($this->tela)()
        ->assertSee('Buscar manter a margem atual.')
        ->assertSee('Matriz vigente: dez-2025')
        ->assertDontSee('ainda não gera diagnóstico')
        ->set('setor', 'comercio')
        ->assertSee('com melhoria contínua')
        ->assertSee('Comércio ainda não gera diagnóstico')
        ->set('indicador', 'pmr')
        ->assertSee('Vender a prazos menores.')
        ->assertDontSee('Buscar manter a margem atual.');
});

it('valor forjado no filtro volta ao padrão', function () {
    ($this->tela)()
        ->set('setor', 'agro')->assertSet('setor', 'industria')
        ->set('indicador', 'ncg_absoluto')->assertSet('indicador', 'margem_bruta');
});

it('salva o texto, normaliza espaços, audita e muda a versão só daquele segmento', function () {
    Carbon::setTestNow('2026-11-30 22:00:00 America/Sao_Paulo');
    $registro = ($this->registro)('industria', 'margem_bruta', 'verde');
    $antes = $registro->texto;

    ($this->tela)()
        ->call('editar', $registro->id)
        ->assertSet('texto', $antes)
        ->set('texto', "  Manter a margem\n  atual.  ")
        ->call('salvar')
        ->assertHasNoErrors()
        ->assertSet('editandoId', null)
        ->assertSet('salvoId', $registro->id)
        ->assertSee('Manter a margem atual.')
        ->assertSee('Matriz vigente: nov-2026');

    expect($registro->fresh()->texto)->toBe('Manter a margem atual.');

    $fonte = app(FonteRecomendacoes::class);
    expect($fonte->versao('industria'))->toBe('nov-2026')
        ->and($fonte->versao('comercio'))->toBe('dez-2025');

    $log = AuditLog::query()->where('action', 'recomendacoes.texto_alterado')->firstOrFail();
    expect($log->subject_id)->toBe($registro->id)
        ->and($log->actor_id)->toBe($this->admin->id)
        ->and($log->before->toArray())->toBe(['texto' => $antes, 'matrix_version' => 'dez-2025'])
        ->and($log->after->toArray())->toBe(['texto' => 'Manter a margem atual.', 'matrix_version' => 'nov-2026'])
        ->and($log->context->toArray())->toMatchArray(['setor' => 'industria', 'indicador' => 'margem_bruta', 'farol' => 'verde']);

    Carbon::setTestNow();
});

it('a versão usa o mês de Brasília, não o de UTC', function () {
    Carbon::setTestNow('2026-12-01 02:00:00 UTC');
    ($this->registro)('industria', 'pmr', 'verde')->update(['texto' => 'Novo.', 'editado_em' => now()]);

    expect(app(FonteRecomendacoes::class)->versao('industria'))->toBe('nov-2026');

    Carbon::setTestNow();
});

it('salvar sem mudar nada não audita nem muda a versão', function () {
    $registro = ($this->registro)('industria', 'pmc', 'amarelo');

    ($this->tela)()->call('editar', $registro->id)->call('salvar')->assertHasNoErrors()->assertSet('editandoId', null);

    expect($registro->fresh()->editado_em)->toBeNull()
        ->and(AuditLog::query()->where('action', 'recomendacoes.texto_alterado')->exists())->toBeFalse()
        ->and(app(FonteRecomendacoes::class)->versao('industria'))->toBe('dez-2025');
});

it('recusa texto vazio e acima do limite, sem gravar', function (string $texto, string $mensagem) {
    $registro = ($this->registro)('industria', 'margem_bruta', 'verde');
    $antes = $registro->texto;

    $tela = ($this->tela)()->call('editar', $registro->id)->set('texto', $texto)->call('salvar')
        ->assertHasErrors('texto')
        ->assertSet('editandoId', $registro->id);

    expect($tela->errors()->first('texto'))->toBe($mensagem)
        ->and($tela->html())->toContain($mensagem)
        ->and($registro->fresh()->texto)->toBe($antes);
})->with([
    'vazio' => ['   ', 'Informe o texto da recomendação.'],
    '301 caracteres' => [str_repeat('á', 301), 'O texto pode ter até 300 caracteres.'],
]);

it('aceita exatamente 300 caracteres', function () {
    $registro = ($this->registro)('industria', 'pme', 'verde');

    ($this->tela)()->call('editar', $registro->id)->set('texto', str_repeat('á', 300))->call('salvar')->assertHasNoErrors();

    expect($registro->fresh()->texto)->toBe(str_repeat('á', 300));
});

it('trocar o filtro cancela a edição em aberto', function () {
    $registro = ($this->registro)('industria', 'margem_bruta', 'verde');
    $antes = $registro->texto;

    ($this->tela)()->call('editar', $registro->id)->set('texto', 'Rascunho')->set('indicador', 'pmr')
        ->assertSet('editandoId', null)
        ->assertSet('texto', '');

    expect($registro->fresh()->texto)->toBe($antes);
});

it('o texto editado vale para o próximo diagnóstico; o já emitido mantém o da época', function () {
    Carbon::setTestNow('2026-11-10 12:00:00 America/Sao_Paulo');
    $dono = Usuario::factory()->create();
    $empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $dono->id]);
    $calcular = app(CalcularDiagnostico::class);

    $antigo = $calcular->execute($empresa, ($this->payload)());
    $farol = $antigo->indicadores_calculados['margem_bruta']['farol'];
    $textoAntigo = $antigo->indicadores_calculados['margem_bruta']['mensagem'];
    expect($antigo->matrix_version)->toBe('dez-2025');

    $registro = ($this->registro)('industria', 'margem_bruta', $farol);
    ($this->tela)()->call('editar', $registro->id)->set('texto', 'Texto editado pela equipe.')->call('salvar');

    $novo = $calcular->execute($empresa, ($this->payload)());

    expect($novo->indicadores_calculados['margem_bruta']['mensagem'])->toBe('Texto editado pela equipe.')
        ->and($novo->matrix_version)->toBe('nov-2026')
        ->and($novo->payload_hash)->toBe($antigo->payload_hash);

    $reaberto = $antigo->fresh();
    expect($reaberto->indicadores_calculados['margem_bruta']['mensagem'])->toBe($textoAntigo)
        ->and($reaberto->matrix_version)->toBe('dez-2025');

    $this->actingAs($dono)->get('/diagnosticos/'.$novo->id)->assertOk()
        ->assertSee('Texto editado pela equipe.')->assertSee('matriz nov-2026');
    $this->actingAs($dono)->get('/diagnosticos/'.$antigo->id)->assertOk()
        ->assertDontSee('Texto editado pela equipe.')->assertSee('matriz dez-2025');

    Carbon::setTestNow();
});

it('escapa o texto editado no relatório', function () {
    Carbon::setTestNow('2026-11-10 12:00:00');
    $dono = Usuario::factory()->create();
    $empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $dono->id]);
    Recomendacao::query()->where(['setor' => 'industria', 'indicador' => 'margem_bruta'])
        ->update(['texto' => '<script>alert(1)</script>']);

    $diagnostico = app(CalcularDiagnostico::class)->execute($empresa, ($this->payload)());

    $this->actingAs($dono)->get('/diagnosticos/'.$diagnostico->id)->assertOk()
        ->assertDontSee('<script>alert(1)</script>', false);

    Carbon::setTestNow();
});

it('sem a linha no banco, o relatório cai no aviso de revisão em vez de quebrar', function () {
    config(['motor.matriz_lacunas_path' => '']);
    Recomendacao::query()->where(['setor' => 'industria', 'indicador' => 'pmr', 'farol' => 'verde'])->delete();

    expect((new MatrizRecomendacoes)->texto('pmr', 'verde'))->toBe(MatrizRecomendacoes::FALLBACK);
});

it('sem a permissão: 404 na tela e nas ações, e sem item no menu', function () {
    $outro = Usuario::factory()->create();
    $outro->forceFill(['permissoes_admin' => ['glossario.editar']])->save();

    $this->actingAs($outro)->get('/backoffice/recomendacoes')->assertNotFound();
    $this->actingAs($outro)->get('/backoffice/glossario')->assertOk()
        ->assertDontSee('app-nav-backoffice-recomendacoes');
    Livewire::actingAs($outro)->test(Recomendacoes::class)->assertStatus(404);

    $this->actingAs($this->admin)->get('/backoffice/recomendacoes')->assertOk()
        ->assertSee('app-nav-backoffice-recomendacoes');
});

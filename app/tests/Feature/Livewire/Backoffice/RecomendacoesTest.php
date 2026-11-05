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

it('a migration traz 123 textos, um por trio, todos preenchidos e dentro do limite', function () {
    $linhas = Recomendacao::query()->get();

    expect($linhas)->toHaveCount(123);

    foreach (array_keys(Recomendacoes::SETORES) as $setor) {
        foreach (Recomendacoes::INDICADORES as $indicador) {
            $situacoes = $indicador === Recomendacoes::NCG_ABSOLUTO ? array_keys(Recomendacoes::SITUACOES_NCG) : Recomendacoes::FAROIS;

            foreach ($situacoes as $farol) {
                $linha = $linhas->first(fn (Recomendacao $r) => $r->setor === $setor && $r->indicador === $indicador && $r->farol === $farol);

                expect($linha)->not->toBeNull("Faltando {$setor}/{$indicador}/{$farol}")
                    ->and($linha->faixa)->not->toBe('')
                    ->and($linha->editado_em)->toBeNull()
                    ->and(mb_strlen($linha->texto))->toBeGreaterThan(0)->toBeLessThanOrEqual(Recomendacoes::TEXTO_MAX);
            }
        }
    }
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
        ->and(($this->registro)('industria', 'ncg_vendas', 'vermelho')->faixa)->toBe('Acima de 10,00');
});

it('abre no primeiro indicador com os 9 textos e navega entre indicadores', function () {
    ($this->tela)()
        ->assertSet('textos.industria.margem_bruta.verde', 'Buscar manter a margem atual.')
        ->assertSet('textos.comercio.margem_bruta.verde', 'Acompanhar os números para manter a margem atual com melhoria contínua.')
        ->assertSet('textos.servicos.margem_bruta.verde', 'Manter ou melhorar a margem atual.')
        ->assertSee('até 18%')
        ->assertSee('Margem Bruta')
        ->assertSee('Matriz dez-2025')
        ->call('irPara', 'pmr')
        ->assertSet('indicador', 'pmr')
        ->assertSet('textos.comercio.pmr.amarelo', 'Vender a prazos menores.')
        ->assertSee('30,01–60 dias')
        ->assertDontSee('até 18%');
});

it('toda aba tem rótulo curto, na ordem dos indicadores', function () {
    expect(array_keys(Recomendacoes::ROTULOS_ABAS))->toEqualCanonicalizing(Recomendacoes::INDICADORES);

    ($this->tela)()->assertSeeHtml('aria-selected="true"')->assertSee('Desp.Fin./Ebitda')->assertDontSee('Desp. / EBITDA');
});

it('indicador forjado é ignorado', function () {
    ($this->tela)()->call('irPara', 'inexistente')->assertSet('indicador', 'margem_bruta');
});

it('o indicador e o texto de origem não podem ser forjados pelo cliente', function () {
    expect(fn () => ($this->tela)()->set('indicador', 'pmr'))->toThrow(Exception::class);
    expect(fn () => ($this->tela)()->set('base.industria.margem_bruta.verde', 'x'))->toThrow(Exception::class);
});

it('salva, normaliza espaços, audita e muda a versão só do segmento alterado', function () {
    Carbon::setTestNow('2026-11-30 22:00:00 America/Sao_Paulo');
    $registro = ($this->registro)('industria', 'margem_bruta', 'verde');
    $antes = $registro->texto;

    ($this->tela)()
        ->set('textos.industria.margem_bruta.verde', "  Manter a margem\n  atual.  ")
        ->assertSee('1 alteração não salva')
        ->call('salvar')
        ->assertHasNoErrors()
        ->assertSee('1 texto salvo.')
        ->assertDontSee('alteração não salva')
        ->assertSet('textos.industria.margem_bruta.verde', 'Manter a margem atual.')
        ->assertSee('Matriz nov-2026')
        ->assertSee('Matriz dez-2025');

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

it('sem mudança real, nem só de espaços, não há pendência nem gravação', function () {
    $registro = ($this->registro)('industria', 'pmc', 'amarelo');

    ($this->tela)()->call('irPara', 'pmc')
        ->set('textos.industria.pmc.amarelo', '  '.$registro->texto.' ')
        ->assertDontSee('alteração não salva')
        ->call('salvar')
        ->assertHasNoErrors();

    expect($registro->fresh()->editado_em)->toBeNull()
        ->and(AuditLog::query()->where('action', 'recomendacoes.texto_alterado')->exists())->toBeFalse()
        ->and(app(FonteRecomendacoes::class)->versao('industria'))->toBe('dez-2025');
});

it('recusa texto vazio e acima do limite, sem gravar nada', function (string $texto, string $mensagem) {
    $invalido = ($this->registro)('industria', 'margem_bruta', 'verde');
    $valido = ($this->registro)('comercio', 'margem_bruta', 'verde');
    $antes = [$invalido->texto, $valido->texto];

    $tela = ($this->tela)()
        ->set('textos.comercio.margem_bruta.verde', 'Texto válido.')
        ->set('textos.industria.margem_bruta.verde', $texto)
        ->call('salvar')
        ->assertHasErrors('textos.industria.margem_bruta.verde')
        ->assertSee($mensagem);

    expect([$invalido->fresh()->texto, $valido->fresh()->texto])->toBe($antes)
        ->and($tela->get('textos.comercio.margem_bruta.verde'))->toBe('Texto válido.');
})->with([
    'vazio' => ['   ', 'Informe o texto da recomendação.'],
    '301 caracteres' => [str_repeat('á', 301), 'O texto pode ter até 300 caracteres.'],
]);

it('aceita exatamente 300 caracteres', function () {
    $registro = ($this->registro)('industria', 'margem_bruta', 'verde');

    ($this->tela)()->set('textos.industria.margem_bruta.verde', str_repeat('á', 300))->call('salvar')->assertHasNoErrors();

    expect($registro->fresh()->texto)->toBe(str_repeat('á', 300));
});

it('alteração continua pendente ao trocar de indicador e é salva junto com as outras', function () {
    $tela = ($this->tela)()
        ->set('textos.industria.margem_bruta.verde', 'Primeiro.')
        ->call('irPara', 'pmr')
        ->assertSet('textos.industria.margem_bruta.verde', 'Primeiro.')
        ->assertSeeHtml('tab__marca')
        ->set('textos.servicos.pmr.vermelho', 'Segundo.')
        ->assertSee('2 alterações não salvas')
        ->call('salvar')
        ->assertSee('2 textos salvos.');

    expect(($this->registro)('industria', 'margem_bruta', 'verde')->texto)->toBe('Primeiro.')
        ->and(($this->registro)('servicos', 'pmr', 'vermelho')->texto)->toBe('Segundo.')
        ->and(AuditLog::query()->where('action', 'recomendacoes.texto_alterado')->count())->toBe(2);

    $tela->assertDontSeeHtml('tab__marca');
});

it('erro em outro indicador leva o usuário até ele', function () {
    ($this->tela)()
        ->set('textos.industria.margem_bruta.verde', '')
        ->call('irPara', 'pmr')
        ->call('salvar')
        ->assertHasErrors('textos.industria.margem_bruta.verde')
        ->assertSet('indicador', 'margem_bruta')
        ->assertSee('Informe o texto da recomendação.');
});

it('desfazer restaura um texto e descartar restaura todos', function () {
    $original = ($this->registro)('industria', 'margem_bruta', 'verde')->texto;
    $outro = ($this->registro)('comercio', 'margem_bruta', 'verde')->texto;

    ($this->tela)()
        ->set('textos.industria.margem_bruta.verde', 'A')
        ->set('textos.comercio.margem_bruta.verde', 'B')
        ->assertSee('2 alterações não salvas')
        ->call('desfazer', 'industria', 'margem_bruta', 'verde')
        ->assertSet('textos.industria.margem_bruta.verde', $original)
        ->assertSee('1 alteração não salva')
        ->call('desfazer', 'industria', 'pmr', 'inexistente')
        ->call('descartar')
        ->assertSet('textos.comercio.margem_bruta.verde', $outro)
        ->assertSee('Alterações descartadas.');

    expect(Recomendacao::query()->whereNotNull('editado_em')->exists())->toBeFalse();
});

it('texto em formato inválido enviado pelo cliente é recusado', function () {
    ($this->tela)()->set('textos.industria.margem_bruta.verde', ['x'])->call('salvar')
        ->assertHasErrors('textos.industria.margem_bruta.verde');
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
    ($this->tela)()->set("textos.industria.margem_bruta.{$farol}", 'Texto editado pela equipe.')->call('salvar');

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

it('traz os textos do NCG por segmento, do Anexo F §F.13', function () {
    expect(Recomendacao::query()->where('indicador', 'ncg_absoluto')->count())->toBe(6)
        ->and(($this->registro)('comercio', 'ncg_absoluto', 'negativa')->texto)->toStartWith('Em tese, a empresa')
        ->and(($this->registro)('servicos', 'ncg_absoluto', 'positiva')->texto)->toStartWith('A elevação da NCG')
        ->and(($this->registro)('servicos', 'ncg_absoluto', 'negativa')->faixa)->toBe('Zero ou abaixo');
});

it('a aba do NCG mostra positivo e negativo nos 3 segmentos, sem farol', function () {
    ($this->tela)()->call('irPara', 'ncg_absoluto')
        ->assertSet('indicador', 'ncg_absoluto')
        ->assertSee('NCG — Necessidade de Capital de Giro')
        ->assertSee('Positivo')->assertSee('Negativo')
        ->assertSee('Acima de zero')->assertSee('Zero ou abaixo')
        ->assertSeeHtml('dusk="recomendacao-industria-positiva"')
        ->assertSeeHtml('dusk="recomendacao-comercio-negativa"')
        ->assertSeeHtml('dusk="recomendacao-servicos-positiva"')
        ->assertDontSeeHtml('data-farol');
});

it('o texto editado do NCG vale para o próximo diagnóstico; o já emitido mantém o da época', function () {
    Carbon::setTestNow('2026-11-10 12:00:00 America/Sao_Paulo');
    $dono = Usuario::factory()->create();
    $empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $dono->id]);
    $calcular = app(CalcularDiagnostico::class);
    $negativa = ($this->registro)('industria', 'ncg_absoluto', 'negativa')->texto;

    $antigo = $calcular->execute($empresa, ($this->payload)());
    $textoAntigo = $antigo->indicadores_calculados['ncg_absoluto']['mensagem'];
    expect($antigo->indicadores_calculados['ncg_absoluto']['valor'])->toBeGreaterThan(0)
        ->and($textoAntigo)->toBe(($this->registro)('industria', 'ncg_absoluto', 'positiva')->texto);

    ($this->tela)()->call('irPara', 'ncg_absoluto')
        ->set('textos.industria.ncg_absoluto.positiva', 'NCG positivo revisado.')
        ->call('salvar')
        ->assertHasNoErrors();

    $novo = $calcular->execute($empresa, ($this->payload)());

    expect($novo->indicadores_calculados['ncg_absoluto']['mensagem'])->toBe('NCG positivo revisado.')
        ->and($novo->matrix_version)->toBe('nov-2026')
        ->and($antigo->fresh()->indicadores_calculados['ncg_absoluto']['mensagem'])->toBe($textoAntigo)
        ->and(($this->registro)('industria', 'ncg_absoluto', 'negativa')->texto)->toBe($negativa);

    $this->actingAs($dono)->get('/diagnosticos/'.$novo->id)->assertOk()->assertSee('NCG positivo revisado.');

    Carbon::setTestNow();
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

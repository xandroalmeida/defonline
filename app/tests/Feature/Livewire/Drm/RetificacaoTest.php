<?php

declare(strict_types=1);

use App\Actions\Drm\RetificarLancamento;
use App\Domain\Drm\Competencia;
use App\Domain\Drm\Grupo;
use App\Domain\Drm\Lancamento as LancamentoDrm;
use App\Domain\Drm\Modo;
use App\Domain\Drm\Setor;
use App\Livewire\Drm\Demonstracao;
use App\Livewire\Drm\Lancamento;
use App\Models\AuditLog;
use App\Models\DrmLancamento;
use App\Models\DrmPreferencia;
use App\Models\DrmRascunho;
use App\Models\EmpresaAnalisada;
use App\Models\Usuario;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

/*
|-------------------------------------------------------------------------
| STORY-050 — a tela de retificação (spec §4.9) e o selo (spec da Demonstração §4.9).
| CA-1, CA-4, CA-6 e CA-8. A mecânica transacional está em tests/Feature/Drm/RetificacaoTest.php.
|-------------------------------------------------------------------------
*/

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-11-05 10:00:00');
    Carbon::setTestNow('2026-11-05 10:00:00');
    $this->usuario = Usuario::factory()->create();
    $this->actingAs($this->usuario);
    $this->empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $this->usuario->id, 'cnae' => '4711301', 'nome_fantasia' => 'Padaria do Bairro']);
    $this->vigente = DrmLancamento::factory()->create(['empresa_analisada_id' => $this->empresa->id, 'competencia' => '2026-09-01']);
});

afterEach(function () {
    CarbonImmutable::setTestNow();
    Carbon::setTestNow();
});

function corrigindo(EmpresaAnalisada $empresa, string $competencia = '2026-09')
{
    return Livewire::test(Lancamento::class, ['empresa' => $empresa, 'competencia' => $competencia]);
}

/** Retifica por baixo da tela, como "outra sessão" faria. */
function retificarPorFora(EmpresaAnalisada $empresa, array $texto, int $base): DrmLancamento
{
    $modos = array_fill_keys(array_map(fn (Grupo $g) => $g->value, Grupo::cases()), Modo::Agregado);
    $lancamento = LancamentoDrm::deTexto(Setor::Comercio, $modos, [
        'D01' => '84.300,00', 'D02' => '5.058,00', 'D03' => '41.200,00', 'D08' => '9.000,00',
        'D15' => '20.702,00', 'D37' => '1.100,00', 'D40' => '4.000,00', 'D41' => '', 'D42' => '', ...$texto,
    ]);

    return app(RetificarLancamento::class)->execute($empresa, Competencia::deTexto('2026-09'), $lancamento, [], $base);
}

describe('CA-1 e CA-8 — entrada, escopo e valores atuais', function () {
    it('abre para o dono, com o título "Corrigir setembro/2026" e o aviso permanente', function () {
        $this->get(route('drm.corrigir', [$this->empresa, '2026-09']))
            ->assertOk()
            ->assertSee('Corrigir setembro/2026')
            ->assertSee('Você está corrigindo um mês já fechado. A versão anterior fica guardada.')
            ->assertDontSee('Lançar setembro/2026')
            ->assertSeeLivewire(Lancamento::class);
    });

    it('vem com os valores da versão vigente, sem marca de herança nem a orientação de primeiro mês', function () {
        corrigindo($this->empresa)
            ->assertSet('valores.D01', '84.300,00')
            ->assertSet('valores.D02', '5.058,00')
            ->assertSet('valores.D40', '4.000,00')
            ->assertSet('repetidos', [])
            ->assertSet('modos.custo', 'agregado')
            ->assertDontSeeHtml('dusk="drm-lancamento-primeira-vez"')
            ->assertDontSeeHtml('dusk="drm-lancamento-conferir-tudo"')
            ->assertDontSee('veio de');
    });

    it('a competência aparece como fato e não pode ser trocada', function () {
        $c = corrigindo($this->empresa);

        $c->assertSeeHtml('dusk="drm-lancamento-campo-D00"')->assertDontSeeHtml('<select');
        $c->set('competencia', '2026-08')->assertSet('competencia', '2026-09');
    });

    it('devolve 404 silente para a empresa de outro usuário (IDR-009)', function () {
        $outro = Usuario::factory()->create();
        $alheia = EmpresaAnalisada::factory()->create(['usuario_id' => $outro->id]);
        DrmLancamento::factory()->create(['empresa_analisada_id' => $alheia->id, 'competencia' => '2026-09-01']);

        $this->get(route('drm.corrigir', [$alheia, '2026-09']))->assertNotFound();
        $this->actingAs($outro)->get(route('drm.corrigir', [$this->empresa, '2026-09']))->assertNotFound();
    });

    it('devolve 404 para competência sem lançamento e não cria rascunho', function () {
        $this->get(route('drm.corrigir', [$this->empresa, '2026-08']))->assertNotFound();

        expect(DrmRascunho::withTrashed()->count())->toBe(0);
    });

    it('competência com formato inválido nem casa com a rota', function () {
        $this->get('/empresas/'.$this->empresa->id.'/drm/2026-13/corrigir')->assertNotFound();
    });

    it('exige login', function () {
        auth()->logout();

        $this->get(route('drm.corrigir', [$this->empresa, '2026-09']))->assertRedirect();
    });

    it('competência, versão-base, setor e o próprio modo retificação não são editáveis pelo cliente', function (string $propriedade, mixed $valor) {
        expect(fn () => corrigindo($this->empresa)->set($propriedade, $valor))->toThrow(CannotUpdateLockedPropertyException::class);
    })->with([['retificando', false], ['base_versao', 99], ['competencia_fixa', '2026-01'], ['setor_fixo', 'industria']]);

    it('usa o setor gravado na versão vigente, não o da empresa de hoje', function () {
        $this->empresa->update(['cnae' => '1091101']); // virou indústria depois do lançamento

        corrigindo($this->empresa)->assertSee('Padaria do Bairro · Comércio')->call('irParaBloco', 2)->assertSee('Custo das Mercadorias Vendidas');
    });

    it('reabre no modo em que o mês foi lançado, mesmo que a preferência da empresa seja outra', function () {
        DrmPreferencia::gravarModos($this->empresa, array_fill_keys(array_map(fn (Grupo $g) => $g->value, Grupo::cases()), Modo::Detalhado));

        corrigindo($this->empresa)->assertSet('modos.custo', 'agregado')->assertSet('valores.D03', '41.200,00');
    });

    it('reabre um mês lançado item a item com todos os itens preenchidos', function () {
        $modos = array_fill_keys(array_map(fn (Grupo $g) => $g->value, Grupo::cases()), Modo::Detalhado);
        $itens = Grupo::Custo->itens(Setor::Comercio);
        $payload = LancamentoDrm::deTexto(Setor::Comercio, $modos, [
            'D01' => '10.000,00', 'D02' => '600,00', 'D40' => '1.000,00', $itens[0] => '3.000,00', $itens[1] => '10,00', 'D09' => '0,00',
        ])->payload();
        DrmLancamento::factory()->create(['empresa_analisada_id' => $this->empresa->id, 'competencia' => '2026-08-01', 'lancamento_payload' => $payload]);

        $c = corrigindo($this->empresa, '2026-08');
        $c->assertSet('modos.custo', 'detalhado')->assertSet('valores.'.$itens[0], '3.000,00')->assertSet('valores.'.$itens[1], '10,00');

        // abrir e fechar sem mexer em nada é no-op: o payload reconstruído do formulário bate com o gravado.
        $c->call('irParaBloco', 6)->call('fechar')->assertSet('sem_mudanca', true);
    });
});

describe('rascunho de retificação (ADR-007)', function () {
    it('abrir cria o rascunho origem=retificacao com a versão-base e não toca a vigente', function () {
        $antes = DrmLancamento::query()->findOrFail($this->vigente->id)->getAttributes();

        corrigindo($this->empresa);

        $rascunho = DrmRascunho::query()->sole();
        expect($rascunho->origem)->toBe('retificacao')
            ->and($rascunho->base_versao)->toBe(1)
            ->and($rascunho->competencia->format('Y-m-d'))->toBe('2026-09-01')
            ->and(DrmLancamento::query()->findOrFail($this->vigente->id)->getAttributes())->toEqual($antes)
            ->and(DrmLancamento::query()->count())->toBe(1);
    });

    it('abandonar o rascunho nunca toca a versão vigente nem as preferências', function () {
        $c = corrigindo($this->empresa);
        $c->set('valores.D02', '6.000,00')->call('proximo')->call('proximo');

        expect(DrmLancamento::query()->count())->toBe(1)
            ->and($this->vigente->fresh()->substituido_por_id)->toBeNull()
            ->and($this->vigente->fresh()->lancamento_payload['D02'])->toBe('5058.00')
            ->and(DrmPreferencia::query()->count())->toBe(0);
    });

    it('retoma o rascunho de retificação em outra sessão, no bloco e com os valores em que parou', function () {
        corrigindo($this->empresa)->set('valores.D02', '6.000,00')->call('proximo')->call('proximo');

        corrigindo($this->empresa)
            ->assertSet('bloco_atual', 3)
            ->assertSet('valores.D02', '6.000,00')
            ->assertSet('base_versao', 1)
            ->assertSee('Retomando seu rascunho');
        expect(DrmRascunho::query()->count())->toBe(1);
    });

    it('um rascunho inicial esquecido da mesma competência não vira retificação: parte da vigente', function () {
        DrmRascunho::query()->create([
            'usuario_id' => $this->usuario->id, 'empresa_analisada_id' => $this->empresa->id, 'competencia' => '2026-09-01',
            'origem' => 'inicial', 'base_versao' => null, 'rascunho_payload' => ['valores' => ['D01' => '1,00']], 'expires_at' => now()->addDays(90),
        ]);

        corrigindo($this->empresa)->assertSet('valores.D01', '84.300,00');

        expect(DrmRascunho::query()->sole()->origem)->toBe('retificacao');
    });
});

describe('fechamento da retificação (CA-2, CA-3, CA-7)', function () {
    it('confirma, grava a versão 2, audita e leva à demonstração com o selo', function () {
        $c = corrigindo($this->empresa);
        $c->set('valores.D02', '6.000,00')->call('proximo')->call('proximo')->call('proximo')->call('proximo')->call('proximo');
        $c->assertSet('bloco_atual', 6)->call('fechar')->assertRedirect(route('drm.mes', [$this->empresa, '2026-09']));

        $v2 = DrmLancamento::vigenteDaCompetencia($this->empresa, '2026-09-01');
        expect($v2->versao)->toBe(2)
            ->and($v2->lancamento_payload['D02'])->toBe('6000.00')
            ->and(DrmLancamento::query()->findOrFail($this->vigente->id)->substituido_por_id)->toBe($v2->id)
            ->and(DrmRascunho::query()->count())->toBe(0)
            ->and(DrmPreferencia::query()->count())->toBe(0)
            ->and(AuditLog::query()->where('action', 'drm.lancamento.retificado')->count())->toBe(1);
    });

    it('o primeiro fechamento só funciona uma vez: refazer o mesmo envio não duplica', function () {
        $c = corrigindo($this->empresa);
        $c->set('valores.D02', '6.000,00')->call('fechar')->assertRedirect();

        // a mesma tela, reaberta com o mesmo valor, é no-op
        corrigindo($this->empresa)->assertSet('valores.D02', '6.000,00')->call('fechar')->assertSet('sem_mudanca', true);

        expect(DrmLancamento::query()->where('competencia', '2026-09-01')->count())->toBe(2);
    });

    it('no-op: avisa com info, oferece voltar à demonstração e não grava nada', function () {
        $c = corrigindo($this->empresa)->call('irParaBloco', 6)->call('fechar');

        $c->assertSet('sem_mudanca', true)
            ->assertSee('Nenhum valor mudou, então não há o que corrigir.')
            ->assertSee('Voltar à demonstração')
            ->assertSeeHtml('href="'.route('drm.mes', [$this->empresa, '2026-09']).'"')
            ->assertNoRedirect();
        expect(DrmLancamento::query()->count())->toBe(1)
            ->and(AuditLog::query()->where('action', 'drm.lancamento.retificado')->count())->toBe(0);
    });

    it('depois do aviso de no-op, voltar e corrigir de verdade funciona', function () {
        $c = corrigindo($this->empresa)->call('irParaBloco', 6)->call('fechar')->assertSet('sem_mudanca', true);

        $c->call('voltar')->assertSet('sem_mudanca', false);
        $c->set('valores.D02', '6.000,00')->call('fechar')->assertRedirect();
    });

    it('conflito: outra sessão corrigiu antes — bloqueia, sem forçar, e oferece abrir a versão atual', function () {
        $c = corrigindo($this->empresa); // base_versao = 1
        retificarPorFora($this->empresa, ['D02' => '6.000,00'], 1);

        $c->set('valores.D02', '7.000,00')->call('irParaBloco', 6)->call('fechar');

        $c->assertSet('conflito', true)
            ->assertSee('Este mês foi corrigido em outra sessão enquanto você preenchia. Abra a versão atual para não sobrescrever a correção.')
            ->assertSee('Abrir a versão atual')
            ->assertSeeHtml('disabled')
            ->assertNoRedirect();
        expect(DrmLancamento::query()->where('competencia', '2026-09-01')->count())->toBe(2)
            ->and(DrmLancamento::vigenteDaCompetencia($this->empresa, '2026-09-01')->lancamento_payload['D02'])->toBe('6000.00');
    });

    it('"Abrir a versão atual" descarta o rascunho velho e leva à demonstração; reabrir parte da versão nova', function () {
        $c = corrigindo($this->empresa);
        retificarPorFora($this->empresa, ['D02' => '6.000,00'], 1);
        $c->set('valores.D02', '7.000,00')->call('fechar')->call('abrirVersaoAtual')->assertRedirect(route('drm.mes', [$this->empresa, '2026-09']));

        expect(DrmRascunho::query()->count())->toBe(0);
        corrigindo($this->empresa)->assertSet('base_versao', 2)->assertSet('valores.D02', '6.000,00');
    });

    it('um rascunho aberto sobre a versão 1 e retomado depois de a vigente virar 2 também é conflito', function () {
        corrigindo($this->empresa);
        retificarPorFora($this->empresa, ['D02' => '6.000,00'], 1);

        corrigindo($this->empresa)->assertSet('base_versao', 1)->set('valores.D02', '7.000,00')->call('fechar')->assertSet('conflito', true);
    });

    it('abrirVersaoAtual só existe na retificação', function () {
        Livewire::test(Lancamento::class, ['empresa' => $this->empresa])->call('abrirVersaoAtual')->assertNotFound();
    });
});

describe('CA-6 — mesmas regras, exceto B1', function () {
    it('B1 não se aplica: o mês já lançado não bloqueia nem mostra os botões de abrir/corrigir', function () {
        corrigindo($this->empresa)
            ->assertDontSeeHtml('dusk="drm-lancamento-bloqueio-B1"')
            ->set('valores.D02', '6.000,00')
            ->call('proximo')
            ->assertSet('bloco_atual', 2);
    });

    it('B3 — valor negativo colado é recusado', function () {
        $c = corrigindo($this->empresa)->set('valores.D02', '-5,00')->call('proximo');

        $c->assertSet('bloco_atual', 1)->assertHasErrors('valores.D02');
        expect(DrmLancamento::query()->count())->toBe(1);
    });

    it('B4 — impostos maiores que a receita bloqueiam', function () {
        $c = corrigindo($this->empresa)->set('valores.D02', '90.000,00')->call('proximo');

        $c->assertSet('bloco_atual', 1)->assertSeeHtml('dusk="drm-lancamento-bloqueio-B4"');
        $c->call('fechar')->assertNoRedirect();
        expect(DrmLancamento::query()->count())->toBe(1);
    });

    it('campo obrigatório em branco não deixa fechar', function () {
        $c = corrigindo($this->empresa)->set('valores.D01', '')->call('fechar');

        $c->assertHasErrors('valores.D01')->assertNoRedirect();
        expect(DrmLancamento::query()->count())->toBe(1);
    });

    it('alerta de prejuízo (A3) pede aceite e vai nas ressalvas da versão nova', function () {
        $c = corrigindo($this->empresa)->set('valores.D03', '80.000,00')->call('irParaBloco', 6);
        $c->assertSeeHtml('dusk="drm-lancamento-alerta-A3"');
        $c->call('fechar')->assertNoRedirect();
        expect(DrmLancamento::query()->count())->toBe(1);

        $c->call('aceitarAlertas')->call('fechar')->assertRedirect();
        $regras = collect(DrmLancamento::vigenteDaCompetencia($this->empresa, '2026-09-01')->alertas_aceitos)->pluck('regra');
        expect($regras)->toContain('A3');
    });

    it('B2 — competência futura nunca tem vigente, então nem abre', function () {
        $this->get(route('drm.corrigir', [$this->empresa, '2026-12']))->assertNotFound();
    });
});

describe('CA-4 — sinalização na demonstração', function () {
    it('versão 1 não tem selo nem rodapé de correção', function () {
        Livewire::test(Demonstracao::class, ['empresa' => $this->empresa, 'competencia' => '2026-09'])
            ->assertDontSeeHtml('dusk="drm-demonstracao-retificado"')
            ->assertDontSee('corrigido');
    });

    it('depois de retificar mostra o selo "Retificado", "corrigido 1×" e a data da última correção', function () {
        Carbon::setTestNow('2026-10-12 09:30:00');
        retificarPorFora($this->empresa, ['D02' => '6.000,00'], 1);

        Livewire::test(Demonstracao::class, ['empresa' => $this->empresa, 'competencia' => '2026-09'])
            ->assertSeeHtml('dusk="drm-demonstracao-retificado"')
            ->assertSee('Retificado')
            ->assertSee('corrigido 1× — última correção em 12/10/2026');
    });

    it('retificar de novo conta mais uma vez e a data é a da última', function () {
        Carbon::setTestNow('2026-10-12 09:30:00');
        retificarPorFora($this->empresa, ['D02' => '6.000,00'], 1);
        Carbon::setTestNow('2026-10-20 18:00:00');
        retificarPorFora($this->empresa, ['D02' => '6.100,00'], 2);

        Livewire::test(Demonstracao::class, ['empresa' => $this->empresa, 'competencia' => '2026-09'])
            ->assertSee('corrigido 2× — última correção em 20/10/2026');
    });

    it('a demonstração mostra a versão vigente: o número novo, não o antigo', function () {
        retificarPorFora($this->empresa, ['D02' => '6.000,00'], 1);

        Livewire::test(Demonstracao::class, ['empresa' => $this->empresa, 'competencia' => '2026-09'])
            ->assertSee('6.000,00')
            ->assertDontSee('5.058,00');
    });

    it('só o mês retificado leva o selo; os outros meses não', function () {
        DrmLancamento::factory()->create(['empresa_analisada_id' => $this->empresa->id, 'competencia' => '2026-08-01']);
        retificarPorFora($this->empresa, ['D02' => '6.000,00'], 1);

        Livewire::test(Demonstracao::class, ['empresa' => $this->empresa, 'competencia' => '2026-08'])
            ->assertDontSeeHtml('dusk="drm-demonstracao-retificado"');
    });
});

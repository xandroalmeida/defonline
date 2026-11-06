<?php

declare(strict_types=1);

use App\Domain\Drm\LancamentoCanonicalizer;
use App\Domain\Drm\Modo;
use App\Livewire\Drm\Lancamento;
use App\Models\DrmLancamento;
use App\Models\DrmPreferencia;
use App\Models\DrmRascunho;
use App\Models\EmpresaAnalisada;
use App\Models\Usuario;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/*
|-------------------------------------------------------------------------
| STORY-047 — fluxo do lançamento mensal do DRM, CA-1 a CA-10.
|
| "Hoje" é fixado em 2026-11-05: o mês anterior (default de D00) é outubro/2026.
|-------------------------------------------------------------------------
*/

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-11-05 10:00:00');
    Carbon::setTestNow('2026-11-05 10:00:00');
    $this->usuario = Usuario::factory()->create();
    $this->actingAs($this->usuario);
    $this->empresa = empresaDoSetor('comercio', $this->usuario);
});

afterEach(function () {
    CarbonImmutable::setTestNow();
    Carbon::setTestNow();
});

/** Os campos com texto: `valores` carrega todos os D01..D42, vazios inclusive (ver `Lancamento::dehydrate`). */
function preenchidos(array $valores): array
{
    return array_filter($valores, fn (?string $v): bool => trim((string) $v) !== '');
}

function empresaDoSetor(string $setor, Usuario $usuario): EmpresaAnalisada
{
    $cnae = ['industria' => '1091101', 'comercio' => '4711301', 'servicos' => '6920601'][$setor];

    return EmpresaAnalisada::factory()->create(['usuario_id' => $usuario->id, 'cnae' => $cnae, 'nome_fantasia' => 'Padaria do Bairro']);
}

function lancador(EmpresaAnalisada $empresa, ?string $competencia = null): Testable
{
    return Livewire::withQueryParams($competencia ? ['competencia' => $competencia] : [])
        ->test(Lancamento::class, ['empresa' => $empresa]);
}

/** Preenche os cinco blocos com valores simples e coerentes, no modo em que o componente está. */
function preencherTudo($c, array $sobrescrever = [])
{
    $base = ['D01' => '10.000,00', 'D02' => '600,00', 'D40' => '1.000,00'];
    foreach ($c->get('modos') as $grupo => $modo) {
        $total = ['custo' => 'D03', 'vendas' => 'D08', 'administrativas' => 'D15', 'financeiras' => 'D37'][$grupo];
        if ($modo === 'agregado') {
            $base[$total] = match ($grupo) {
                'custo' => '3.000,00', 'vendas' => '500,00', 'administrativas' => '2.000,00', default => '100,00',
            };
        }
    }
    foreach ([...$base, ...$sobrescrever] as $campo => $valor) {
        $c->set('valores.'.$campo, $valor);
    }

    return $c;
}

function avancarAte($c, int $bloco)
{
    while ($c->get('bloco_atual') < $bloco) {
        $c->call('proximo');
    }

    return $c;
}

function todosAgregados($c)
{
    foreach (['custo', 'vendas', 'administrativas', 'financeiras'] as $g) {
        $c->call('alternarModo', $g, 'agregado');
    }

    return $c;
}

describe('CA-1 — escopo da empresa', function () {
    it('abre para o dono da empresa e mostra empresa e setor como fato, sem perguntá-los', function () {
        $this->get(route('drm.lancar', $this->empresa))
            ->assertOk()
            ->assertSee('Lançar outubro/2026')
            ->assertSee('Padaria do Bairro · Comércio')
            ->assertDontSee('Setor de atividade')
            ->assertDontSee('Tipo do documento');
    });

    it('devolve 404 silente para a empresa de outro usuário (IDR-009)', function () {
        $outro = Usuario::factory()->create();
        $alheia = empresaDoSetor('comercio', $outro);

        $this->get(route('drm.lancar', $alheia))->assertNotFound();
    });

    it('exige login', function () {
        auth()->logout();

        $this->get(route('drm.lancar', $this->empresa))->assertRedirect(route('login'));
    });

    it('a empresa tem o ponto de entrada na sua tela', function () {
        $this->get(route('empresas.show', $this->empresa))
            ->assertSee('Raio X do Negócio')
            ->assertSee(route('drm.lancar', $this->empresa), false);
    });
});

describe('CA-2 — competência', function () {
    it('sugere o mês anterior ao corrente e não oferece meses futuros nem o corrente', function () {
        $c = lancador($this->empresa);

        $c->assertSet('competencia', '2026-10')
            ->assertSeeHtml('value="2026-10"')
            ->assertDontSeeHtml('value="2026-11"')
            ->assertDontSeeHtml('value="2027-01"');
    });

    it('B2 — competência futura por URL manipulada é bloqueada, com mensagem e sem avançar', function () {
        $c = lancador($this->empresa, '2027-03');
        preencherTudo($c);
        $c->call('proximo');

        $c->assertSet('bloco_atual', 1)
            ->assertSeeHtml('dusk="drm-lancamento-bloqueio-B2"')
            ->assertSee('Este mês ainda não terminou')
            ->assertSee('outubro/2026');
        expect(DrmRascunho::query()->count())->toBe(0);
    });

    it('B2 — o mês corrente também não terminou', function () {
        $c = lancador($this->empresa, '2026-11');
        $c->assertSeeHtml('dusk="drm-lancamento-bloqueio-B2"');
    });

    it('B1 — competência já lançada oferece abrir e corrigir, e não deixa avançar', function () {
        DrmLancamento::factory()->create(['empresa_analisada_id' => $this->empresa->id, 'competencia' => '2026-10-01']);
        $c = lancador($this->empresa);
        preencherTudo($c);
        $c->call('proximo');

        $c->assertSet('bloco_atual', 1)
            ->assertSee('Você já lançou outubro/2026 para esta empresa.')
            ->assertSee('Abrir outubro/2026')
            ->assertSee('Corrigir outubro/2026')
            ->assertSeeHtml(route('drm.mes', [$this->empresa, '2026-10']))
            ->assertSeeHtml('href="'.route('drm.corrigir', [$this->empresa, '2026-10']).'"');
    });

    it('trocar a competência para uma já lançada mostra B1 na hora', function () {
        DrmLancamento::factory()->create(['empresa_analisada_id' => $this->empresa->id, 'competencia' => '2026-08-01']);
        $c = lancador($this->empresa);

        $c->set('competencia', '2026-08')->assertSeeHtml('dusk="drm-lancamento-bloqueio-B1"');
        $c->set('competencia', '2026-07')->assertDontSeeHtml('dusk="drm-lancamento-bloqueio-B1"');
    });

    it('competência inválida na URL cai no default', function () {
        lancador($this->empresa, 'lixo')->assertSet('competencia', '2026-10');
    });
});

describe('CA-3 — adaptação por setor', function () {
    it('rotula o grupo de custo por setor e suprime campos', function (string $setor, string $rotulo, array $visiveis, array $ocultos) {
        $empresa = empresaDoSetor($setor, $this->usuario);
        $c = lancador($empresa);
        $c->set('valores.D01', '100')->set('valores.D02', '10');
        $c->call('proximo');

        $c->assertSet('bloco_atual', 2)->assertSee($rotulo);
        foreach ($visiveis as $campo) {
            $c->assertSeeHtml('dusk="drm-lancamento-campo-'.$campo.'"');
        }
        foreach ($ocultos as $campo) {
            $c->assertDontSeeHtml('dusk="drm-lancamento-campo-'.$campo.'"');
        }
    })->with([
        'indústria' => ['industria', 'Custo dos Produtos Vendidos (CPV)', ['D04', 'D05', 'D06', 'D07'], []],
        'comércio' => ['comercio', 'Custo das Mercadorias Vendidas (CMV)', ['D04', 'D06', 'D07'], ['D05']],
        'serviços' => ['servicos', 'Custo dos Serviços Prestados (CSP)', ['D06', 'D07'], ['D04', 'D05']],
    ]);

    it('o seletor conta os campos depois da supressão', function (string $setor, string $texto) {
        $c = lancador(empresaDoSetor($setor, $this->usuario));
        $c->set('valores.D01', '100')->set('valores.D02', '10')->call('proximo');

        $c->assertSee($texto);
    })->with([
        ['industria', '4 campos'], ['comercio', '3 campos'], ['servicos', '2 campos'],
    ]);

    it('grava nulos nos campos suprimidos', function () {
        $c = lancador(empresaDoSetor('servicos', $this->usuario));
        $empresa = EmpresaAnalisada::query()->where('cnae', '6920601')->first();
        $c = lancador($empresa);
        preencherTudo(todosAgregados($c));
        avancarAte($c, 6);
        $c->call('aceitarAlertas')->call('fechar');

        $p = DrmLancamento::query()->firstOrFail();
        expect($p->setor)->toBe('servicos')
            ->and($p->lancamento_payload['D04'])->toBeNull()
            ->and($p->lancamento_payload['D05'])->toBeNull();
    });

    it('nenhum campo pede valor em mil reais (CA-7)', function () {
        $c = lancador($this->empresa);

        $c->assertDontSee('mil reais')->assertDontSee('em R$ mil')->assertDontSee('(R$ mil)');
    });
});

describe('CA-4 — modo agregado e detalhado', function () {
    it('abre detalhado no primeiro lançamento (PDR-007)', function () {
        lancador($this->empresa)->assertSet('modos', [
            'custo' => 'detalhado', 'vendas' => 'detalhado', 'administrativas' => 'detalhado', 'financeiras' => 'detalhado',
        ]);
    });

    it('detalhado → agregado pré-preenche o total com a soma e guarda os itens', function () {
        $c = lancador($this->empresa);
        $c->set('valores.D01', '100')->set('valores.D02', '10')->call('proximo')->call('proximo');
        $c->set('valores.D09', '10,50')->set('valores.D10', '20,25')
            ->call('alternarModo', 'vendas', 'agregado');

        $c->assertSet('modos.vendas', 'agregado')
            ->assertSet('valores.D08', '30,75')
            ->assertSet('valores.D09', '10,50')
            ->assertSee('somamos os itens que você já tinha preenchido — pode ajustar');
    });

    it('voltar a detalhar sem ter mexido no total restaura os itens sem pedir confirmação', function () {
        $c = lancador($this->empresa);
        $c->set('valores.D01', '100')->set('valores.D02', '10')->call('proximo')->call('proximo');
        $c->set('valores.D09', '10,50')
            ->call('alternarModo', 'vendas', 'agregado')
            ->call('alternarModo', 'vendas', 'detalhado');

        $c->assertSet('modos.vendas', 'detalhado')
            ->assertSet('confirmando_detalhar', null)
            ->assertSet('valores.D09', '10,50')
            ->assertSet('valores.D08', '');
    });

    it('agregado → detalhado com total digitado é destrutivo: confirma inline e o rádio fica onde estava', function () {
        $c = lancador($this->empresa);
        $c->set('valores.D01', '100')->set('valores.D02', '10')->call('proximo')->call('proximo');
        $c->call('alternarModo', 'vendas', 'agregado')->set('valores.D08', '900,00')
            ->call('alternarModo', 'vendas', 'detalhado');

        $c->assertSet('modos.vendas', 'agregado')
            ->assertSet('confirmando_detalhar', 'vendas')
            ->assertSee('Detalhar os itens deste grupo?')
            ->assertSee('O total de R$ 900,00 que você informou será descartado, e os 6 itens começam vazios.');
        expect($c->html())->toMatch('/name="modo-vendas" value="agregado"\s+checked/')
            ->and($c->html())->not->toMatch('/name="modo-vendas" value="detalhado"\s+checked/');
    });

    it('confirmar descarta o total e esvazia os itens', function () {
        $c = lancador($this->empresa);
        $c->set('valores.D01', '100')->set('valores.D02', '10')->call('proximo')->call('proximo');
        $c->set('valores.D09', '5')->call('alternarModo', 'vendas', 'agregado')->set('valores.D08', '900,00')
            ->call('alternarModo', 'vendas', 'detalhado')->call('confirmarDetalhar');

        $c->assertSet('modos.vendas', 'detalhado')
            ->assertSet('valores.D08', '')
            ->assertSet('confirmando_detalhar', null)
            ->assertSet('valores.D09', null);
    });

    it('cancelar devolve o seletor intacto', function () {
        $c = lancador($this->empresa);
        $c->set('valores.D01', '100')->set('valores.D02', '10')->call('proximo')->call('proximo');
        $c->call('alternarModo', 'vendas', 'agregado')->set('valores.D08', '900,00')
            ->call('alternarModo', 'vendas', 'detalhado')->call('cancelarDetalhar');

        $c->assertSet('modos.vendas', 'agregado')->assertSet('valores.D08', '900,00')->assertSet('confirmando_detalhar', null);
    });

    it('agregado → detalhado com total em branco não pede confirmação (nada a perder)', function () {
        $c = lancador($this->empresa);
        $c->set('valores.D01', '100')->set('valores.D02', '10')->call('proximo')->call('proximo');
        $c->call('alternarModo', 'vendas', 'agregado')->set('valores.D08', '')->call('alternarModo', 'vendas', 'detalhado');

        $c->assertSet('modos.vendas', 'detalhado')->assertSet('confirmando_detalhar', null);
    });

    it('ignora grupo ou modo desconhecidos e troca para o mesmo modo', function () {
        $c = lancador($this->empresa);

        $c->call('alternarModo', 'inexistente', 'agregado')
            ->call('alternarModo', 'vendas', 'maluco')
            ->call('alternarModo', 'vendas', 'detalhado')
            ->call('confirmarDetalhar');

        $c->assertSet('modos.vendas', 'detalhado');
    });

    it('persiste o modo como preferência da empresa e o reaplica no mês seguinte', function () {
        $c = lancador($this->empresa);
        $c->set('valores.D01', '100')->set('valores.D02', '10')->call('proximo')
            ->call('alternarModo', 'custo', 'agregado')->set('valores.D03', '50')->call('proximo');

        expect(DrmPreferencia::query()->where('empresa_analisada_id', $this->empresa->id)->value('modo_custo'))->toBe('agregado');

        lancador($this->empresa, '2026-09')->assertSet('modos.custo', 'agregado')->assertSet('modos.vendas', 'detalhado');
    });

    it('o modo de cada grupo entra no lancamento_payload e no hash', function () {
        $c = lancador($this->empresa);
        preencherTudo(todosAgregados($c));
        avancarAte($c, 6);
        $c->call('aceitarAlertas')->call('fechar');

        $l = DrmLancamento::query()->firstOrFail();
        expect($l->lancamento_payload['modos'])->toEqual([
            'administrativas' => 'agregado', 'custo' => 'agregado', 'financeiras' => 'agregado', 'vendas' => 'agregado',
        ])->and($l->payload_hash)->toBe(LancamentoCanonicalizer::hash((array) $l->lancamento_payload));
    });
});

describe('CA-5 — pré-preenchimento', function () {
    function lancamentoDeSetembro($teste, array $modos = []): DrmLancamento
    {
        $c = lancador($teste->empresa, '2026-09');
        todosAgregados($c);
        preencherTudo($c, ['D01' => '84.300,00', 'D02' => '5.058,00', 'D03' => '41.200,00', 'D15' => '20.702,00']);
        avancarAte($c, 6);
        $c->call('aceitarAlertas')->call('fechar');

        return DrmLancamento::query()->where('competencia', '2026-09-01')->firstOrFail();
    }

    it('sem competência anterior os campos vêm vazios, sem marca, e mostra a orientação de primeira vez', function () {
        lancador($this->empresa)
            ->assertSet('valores', fn (array $v): bool => preenchidos($v) === [])
            ->assertSet('repetidos', [])
            ->assertSeeHtml('dusk="drm-lancamento-primeira-vez"')
            ->assertSee('Este é o primeiro mês desta empresa.')
            ->assertDontSeeHtml('dusk="drm-lancamento-conferir-tudo"');
    });

    it('com competência anterior, vem preenchido, marcado e com a oferta de conferir tudo', function () {
        lancamentoDeSetembro($this);
        $c = lancador($this->empresa);

        $c->assertSet('valores.D01', '84.300,00')
            ->assertSet('mes_origem', '2026-09')
            ->assertSeeHtml('dusk="drm-lancamento-herdado-D01"')
            ->assertSee('veio de setembro')
            ->assertSee('Setembro/2026 está inteiro aqui — 9 valores repetidos.')
            ->assertDontSeeHtml('dusk="drm-lancamento-primeira-vez"');
    });

    it('editar o campo apaga a marca dele', function () {
        lancamentoDeSetembro($this);
        $c = lancador($this->empresa);
        $c->set('valores.D01', '90.000,00');

        $c->assertDontSeeHtml('dusk="drm-lancamento-herdado-D01"')->assertSeeHtml('dusk="drm-lancamento-herdado-D02"');
    });

    it('"Conferi este bloco" apaga as marcas do bloco e só dele', function () {
        lancamentoDeSetembro($this);
        $c = lancador($this->empresa);
        $c->call('dispensarOferta')->call('conferirBloco');

        $c->assertSet('repetidos', array_values(array_diff($c->get('repetidos'), ['D01', 'D02'])));
        expect($c->get('repetidos'))->not->toContain('D01')->toContain('D03');
    });

    it('"Conferir tudo" vai ao bloco 6, limpa as marcas e NÃO fecha o mês', function () {
        lancamentoDeSetembro($this);
        $c = lancador($this->empresa);
        $c->call('conferirTudo');

        $c->assertSet('bloco_atual', 6)->assertSet('repetidos', []);
        expect(DrmLancamento::query()->count())->toBe(1); // só o de setembro
    });

    it('"Prefiro passar bloco a bloco" some com a oferta e as marcas ficam', function () {
        lancamentoDeSetembro($this);
        $c = lancador($this->empresa);
        $c->call('dispensarOferta');

        $c->assertDontSeeHtml('dusk="drm-lancamento-conferir-tudo"')->assertSeeHtml('dusk="drm-lancamento-herdado-D01"');
    });

    it('as marcas sobrevivem até a conferência para quem passou sem conferir', function () {
        lancamentoDeSetembro($this);
        $c = lancador($this->empresa);
        $c->call('dispensarOferta');
        avancarAte($c, 6);

        $c->assertSet('bloco_atual', 6)->assertSeeHtml('dusk="drm-lancamento-conferencia"')->assertSee('veio de setembro');
    });

    it('herança parcial: grupo estava agregado e agora detalhado começa vazio e informa o total anterior', function () {
        lancamentoDeSetembro($this);
        DrmPreferencia::gravarModos($this->empresa, [
            'custo' => Modo::Detalhado, 'vendas' => Modo::Agregado,
            'administrativas' => Modo::Agregado, 'financeiras' => Modo::Agregado,
        ]);
        $c = lancador($this->empresa);
        $c->call('dispensarOferta')->set('valores.D01', '1')->set('valores.D02', '1');
        $c->call('proximo');

        $c->assertSet('bloco_atual', 2)
            ->assertSet('valores.D04', null)
            ->assertSee('Em setembro você informou este grupo pelo total, então os itens começam vazios.')
            ->assertSee('O total de setembro era R$ 41.200,00.');
    });

    it('trocar a competência reaproveita a nova semente sem perder o que foi digitado', function () {
        lancamentoDeSetembro($this);
        $c = lancador($this->empresa);
        $c->set('valores.D01', '777,00')->set('competencia', '2026-10');

        $c->assertSet('valores.D01', '777,00')->assertSet('valores.D02', '5.058,00');
    });

    it('semeia sempre pelo lançamento anterior mais próximo, não por um posterior', function () {
        lancamentoDeSetembro($this);
        $c = lancador($this->empresa, '2026-08');

        $c->assertSet('valores', fn (array $v): bool => preenchidos($v) === [])->assertSet('mes_origem', '');
    });
});

describe('CA-6 — rascunho', function () {
    it('salva a cada bloco concluído e retoma no bloco em que parou, nomeando o bloco', function () {
        $c = lancador($this->empresa);
        $c->set('valores.D01', '100')->set('valores.D02', '10')->call('proximo')->call('proximo')->call('proximo');

        $rascunho = DrmRascunho::query()->firstOrFail();
        expect($rascunho->competencia->format('Y-m-d'))->toBe('2026-10-01')
            ->and($rascunho->origem)->toBe('inicial')
            ->and($rascunho->rascunho_payload['bloco'])->toBe(4)
            ->and($rascunho->expires_at->isSameDay(now()->addDays(90)))->toBeTrue();

        $outraSessao = lancador($this->empresa);
        $outraSessao->assertSet('bloco_atual', 4)
            ->assertSet('valores.D01', '100')
            ->assertSeeHtml('dusk="drm-lancamento-retomando"')
            ->assertSee('Retomando seu rascunho — você parou em Despesas Administrativas e Gerais.');
    });

    it('um bloco só é salvo quando concluído: sair sem avançar não grava', function () {
        $c = lancador($this->empresa);
        $c->set('valores.D01', '100');

        expect(DrmRascunho::query()->count())->toBe(0);
    });

    it('rascunho expirado: recomeça do início e avisa', function () {
        $c = lancador($this->empresa);
        $c->set('valores.D01', '100')->set('valores.D02', '10')->call('proximo');
        DrmRascunho::query()->update(['expires_at' => now()->subDay()]);

        lancador($this->empresa)->assertSet('bloco_atual', 1)
            ->assertSet('valores', fn (array $v): bool => preenchidos($v) === [])
            ->assertSee('Seu rascunho expirou. Vamos começar do início.');
    });

    it('um rascunho por competência: setembro e outubro convivem', function () {
        $a = lancador($this->empresa, '2026-09');
        $a->set('valores.D01', '1')->set('valores.D02', '0')->call('proximo');
        $b = lancador($this->empresa, '2026-10');
        $b->set('valores.D01', '2')->set('valores.D02', '0')->call('proximo');

        expect(DrmRascunho::query()->count())->toBe(2);
        lancador($this->empresa, '2026-09')->assertSet('valores.D01', '1');
    });

    it('abrir sem competência retoma a do rascunho mais recente', function () {
        $a = lancador($this->empresa, '2026-07');
        $a->set('valores.D01', '1')->set('valores.D02', '0')->call('proximo');

        lancador($this->empresa)->assertSet('competencia', '2026-07');
    });

    it('guarda os alertas já aceitos no rascunho', function () {
        $c = lancador($this->empresa);
        preencherTudo(todosAgregados($c), ['D40' => '0']);
        avancarAte($c, 6);
        $c->call('aceitarAlertas');

        lancador($this->empresa)->assertSet('alertas_aceitos', ['A5']);
    });

    it('fechar encerra o rascunho preservando a trilha (soft delete)', function () {
        $c = lancador($this->empresa);
        preencherTudo(todosAgregados($c));
        avancarAte($c, 6);
        $c->call('aceitarAlertas')->call('fechar');

        expect(DrmRascunho::query()->count())->toBe(0)
            ->and(DrmRascunho::withTrashed()->count())->toBe(1);
    });
});

describe('CA-7 e CA-8 — entrada em reais e regras bloqueantes', function () {
    it('substituir o array de valores inteiro (primeiro valor digitado) não quebra', function () {
        $c = lancador($this->empresa);
        $c->set('valores', ['D01' => '100,00', 'D02' => '10,00'])->assertHasNoErrors();

        $c->call('proximo')->assertSet('bloco_atual', 2);
    });

    it('B3 — valor negativo é rejeitado no campo, com a mensagem da regra', function () {
        $c = lancador($this->empresa);
        $c->set('valores.D01', '100')->set('valores.D02', '-5')->call('proximo');

        $c->assertSet('bloco_atual', 1)
            ->assertHasErrors('valores.D02')
            ->assertSeeHtml('dusk="drm-lancamento-erro-D02"')
            ->assertSee('Nenhum campo aceita valor negativo');
    });

    it('B3 também vale para itens de outros blocos, no fechamento', function () {
        $c = lancador($this->empresa);
        $c->set('valores.D01', '100')->set('valores.D02', '10')->call('proximo')
            ->set('valores.D06', '-1')->call('proximo');

        $c->assertSet('bloco_atual', 2)->assertHasErrors('valores.D06');
    });

    it('formato inválido é rejeitado', function () {
        $c = lancador($this->empresa);
        $c->set('valores.D01', 'abc')->set('valores.D02', '10')->call('proximo');

        $c->assertSet('bloco_atual', 1)->assertHasErrors('valores.D01')->assertSee('Informe um valor numérico válido');
    });

    it('obrigatórios em branco: D01 e D02 no bloco 1, D40 no bloco 5', function () {
        $c = lancador($this->empresa);
        $c->call('proximo');
        $c->assertHasErrors(['valores.D01', 'valores.D02'])->assertSee('Se não houve, informe 0,00.');

        $c->set('valores.D01', '1')->set('valores.D02', '0');
        avancarAte($c, 5);
        $c->call('proximo')->assertSet('bloco_atual', 5)->assertHasErrors('valores.D40');
    });

    it('corrigir o campo apaga o erro dele', function () {
        $c = lancador($this->empresa);
        $c->set('valores.D01', 'abc')->set('valores.D02', '10')->call('proximo')->assertHasErrors('valores.D01');
        $c->set('valores.D01', '100')->assertHasNoErrors('valores.D01');
    });

    it('B4 — impostos maiores que a receita bloqueiam o bloco 1, sem botão de seguir, com Corrigir', function () {
        $c = lancador($this->empresa);
        $c->set('valores.D01', '100,00')->set('valores.D02', '100,01')->call('proximo');

        $c->assertSet('bloco_atual', 1)
            ->assertSeeHtml('dusk="drm-lancamento-bloqueio-B4"')
            ->assertSee('Os impostos ficaram maiores que a venda')
            ->assertSee('R$ 100,01')
            ->assertSee('R$ 100,00')
            ->assertSeeHtml('dusk="drm-lancamento-b4-corrigir"');

        $c->set('valores.D02', '100,00')->call('proximo')->assertSet('bloco_atual', 2);
    });

    it('B4 só aparece depois de uma tentativa de avançar (não a cada dígito)', function () {
        $c = lancador($this->empresa);
        $c->set('valores.D01', '1')->set('valores.D02', '100');

        $c->assertDontSeeHtml('dusk="drm-lancamento-bloqueio-B4"');
    });

    it('no fechamento, o resumo de erros lista o problema com link para o campo', function () {
        $c = lancador($this->empresa);
        preencherTudo(todosAgregados($c));
        avancarAte($c, 6);
        $c->set('valores.D02', '99.999,00')->call('fechar');

        $c->assertSeeHtml('dusk="drm-lancamento-resumo-erros"')
            ->assertSee('Corrija antes de fechar o mês')
            ->assertSee('Você informou R$ 99.999,00 de impostos');
        expect(DrmLancamento::query()->count())->toBe(0);
    });

    it('irParaCampo leva ao bloco do campo e pede foco', function () {
        $c = lancador($this->empresa);
        $c->call('irParaCampo', 'D09')->assertSet('bloco_atual', 3)->assertDispatched('focar-campo', campo: 'D09');
        $c->call('irParaCampo', 'D40')->assertSet('bloco_atual', 5);
        $c->call('irParaCampo', 'D02')->assertSet('bloco_atual', 1);
        $c->call('irParaCampo', 'D07')->assertSet('bloco_atual', 2);
        $c->call('irParaCampo', 'D20')->assertSet('bloco_atual', 4);
        $c->call('irParaCampo', 'D00')->assertSet('bloco_atual', 1);
    });

    it('voltar não perde o que foi digitado', function () {
        $c = lancador($this->empresa);
        $c->set('valores.D01', '100')->set('valores.D02', '10')->call('proximo')->call('voltar');

        $c->assertSet('bloco_atual', 1)->assertSet('valores.D01', '100');
    });
});

describe('CA-9 — alertas com aceite', function () {
    it('sem alerta, fecha direto', function () {
        $c = lancador($this->empresa);
        preencherTudo(todosAgregados($c));
        avancarAte($c, 6);

        $c->assertDontSeeHtml('dusk="drm-lancamento-alertas"');
        $c->call('fechar');
        expect(DrmLancamento::query()->count())->toBe(1);
    });

    it('com alerta pendente o fechamento não acontece até o aceite explícito', function () {
        $c = lancador($this->empresa);
        preencherTudo(todosAgregados($c), ['D40' => '0']);
        avancarAte($c, 6);

        $c->assertSeeHtml('dusk="drm-lancamento-alerta-A5"')
            ->assertSee('Confira antes de fechar')
            ->assertSee('Você não lançou retirada neste mês')
            ->assertSee('Aceite os alertas acima para poder fechar o mês.')
            ->call('fechar');
        expect(DrmLancamento::query()->count())->toBe(0);

        $c->call('aceitarAlertas')->assertSee('Alertas aceitos.')->call('fechar');
        expect(DrmLancamento::query()->count())->toBe(1);
    });

    it('registra os alertas aceitos em coluna própria, fora do payload_hash', function () {
        $c = lancador($this->empresa);
        preencherTudo(todosAgregados($c), ['D01' => '0', 'D02' => '0', 'D03' => '0', 'D08' => '0', 'D15' => '300,00', 'D40' => '0']);
        avancarAte($c, 6);
        $c->call('aceitarAlertas')->call('fechar');

        $l = DrmLancamento::query()->firstOrFail();
        $codigos = array_column($l->alertas_aceitos, 'regra');
        expect($codigos)->toBe(['A1', 'A3', 'A5'])
            ->and($l->alertas_aceitos[0])->toHaveKeys(['regra', 'ocorrido_em', 'valor_envolvido'])
            ->and($l->alertas_aceitos[1]['valor_envolvido'])->toBe('400.00')
            ->and($l->lancamento_payload->getArrayCopy())->not->toHaveKey('alertas_aceitos')
            ->and($l->payload_hash)->toBe(LancamentoCanonicalizer::hash((array) $l->lancamento_payload));
    });

    it('dois lançamentos com os mesmos valores e alertas diferentes têm o mesmo hash', function () {
        $a = lancador($this->empresa, '2026-09');
        preencherTudo(todosAgregados($a), ['D40' => '0']);
        avancarAte($a, 6);
        $a->call('aceitarAlertas')->call('fechar');

        $b = lancador($this->empresa, '2026-10');
        preencherTudo(todosAgregados($b), ['D40' => '0']);
        avancarAte($b, 6);
        $b->call('aceitarAlertas')->call('fechar');

        $hashes = DrmLancamento::query()->pluck('payload_hash')->unique();
        expect($hashes)->toHaveCount(1);
    });

    it('mostra A2 e A3 com os valores formatados', function () {
        $c = lancador($this->empresa);
        preencherTudo(todosAgregados($c), ['D03' => '12.000,00']);
        avancarAte($c, 6);

        $c->assertSeeHtml('dusk="drm-lancamento-alerta-A2"')
            ->assertSee('R$ 12.000,00')
            ->assertSeeHtml('dusk="drm-lancamento-alerta-A3"');
    });

    it('um alerta novo depois do aceite exige novo aceite', function () {
        $c = lancador($this->empresa);
        preencherTudo(todosAgregados($c), ['D40' => '0']);
        avancarAte($c, 6);
        $c->call('aceitarAlertas');
        $c->call('irParaBloco', 2)->set('valores.D03', '99.000,00');
        avancarAte($c, 6);
        $c->call('fechar');

        expect(DrmLancamento::query()->count())->toBe(0);
        $c->assertSeeHtml('dusk="drm-lancamento-aceitar-alertas"');
    });
});

describe('STORY-095 — D-1: todo campo existe em `valores` desde o início', function () {
    it('lista D01..D42 vazios no primeiro estado, para a chave nunca ser nova no cliente', function () {
        $c = lancador($this->empresa);

        $c->assertSet('valores', fn (array $v): bool => array_keys($v) === App\Domain\Drm\Lancamento::todosOsCampos()
            && preenchidos($v) === []);
    });

    it('digitar um campo não perde nenhuma chave, e o vazio continua valendo como ausente no domínio', function () {
        $c = lancador($this->empresa);
        $c->set('valores.D01', '10.000,00')->set('valores.D02', '600,00')->call('proximo');

        $c->assertSet('valores', fn (array $v): bool => count($v) === 42 && $v['D01'] === '10.000,00' && $v['D03'] === '');
        expect(DrmRascunho::query()->firstOrFail()->rascunho_payload['valores'])
            ->toBe(['D01' => '10.000,00', 'D02' => '600,00']);   // o rascunho não grava os vazios
    });

    it('detalhar um grupo devolve os itens vazios sem apagar as chaves', function () {
        $c = lancador($this->empresa);
        $c->call('alternarModo', 'custo', 'agregado')->set('valores.D03', '500,00')
            ->call('alternarModo', 'custo', 'detalhado')->call('confirmarDetalhar');

        $c->assertSet('valores', fn (array $v): bool => array_key_exists('D04', $v) && $v['D04'] === '' && $v['D03'] === '');
    });

    it('os arquivos de rascunho antigos, sem as chaves vazias, hidratam normalmente', function () {
        $c = lancador($this->empresa);
        $c->set('valores.D01', '100')->set('valores.D02', '10')->call('proximo');
        DrmRascunho::query()->update(['rascunho_payload' => ['valores' => ['D01' => '100', 'D02' => '10'], 'bloco' => 2, 'modos' => []]]);

        lancador($this->empresa)->assertSet('valores.D01', '100')->assertSet('valores', fn (array $v): bool => count($v) === 42);
    });
});

describe('STORY-095 — O-1: o aceite do alerta vale para o valor que o gerou', function () {
    function ateOAlertaDePrejuizo($teste, string $custo): Testable
    {
        $c = lancador($teste->empresa);
        preencherTudo(todosAgregados($c), ['D03' => $custo, 'D40' => '1.000,00']);

        return avancarAte($c, 6);
    }

    it('mudar o valor que gerou o alerta derruba o aceite e o mês não fecha sem aceitar de novo', function () {
        $c = ateOAlertaDePrejuizo($this, '12.000,00');
        $c->call('aceitarAlertas')->assertSee('Alertas aceitos.');

        // Agrava o prejuízo: o texto do A3 e o do A2 mudam.
        $c->call('irParaBloco', 2)->set('valores.D03', '20.000,00');
        avancarAte($c, 6);
        $c->assertSeeHtml('dusk="drm-lancamento-aceitar-alertas"')->assertDontSee('Alertas aceitos.')->call('fechar');
        expect(DrmLancamento::query()->count())->toBe(0);

        $c->call('aceitarAlertas')->call('fechar');
        expect(DrmLancamento::query()->count())->toBe(1);
    });

    it('mudar o que não altera o alerta mantém o aceite', function () {
        $c = lancador($this->empresa);
        preencherTudo(todosAgregados($c), ['D40' => '0']);   // só A5 (sem retirada): não tem valor nem texto que mude
        avancarAte($c, 6);
        $c->call('aceitarAlertas');

        $c->call('irParaBloco', 1)->set('valores.D01', '10.500,00');
        avancarAte($c, 6);
        $c->assertSee('Alertas aceitos.')->call('fechar');
        expect(DrmLancamento::query()->count())->toBe(1);
    });

    it('o aceite guardado no rascunho carrega a assinatura, e um aceite antigo sem ela volta a pedir', function () {
        $c = ateOAlertaDePrejuizo($this, '12.000,00');
        $c->call('aceitarAlertas');

        $payload = DrmRascunho::query()->firstOrFail()->rascunho_payload;
        expect($payload['alertas_aceitos'])->not->toBeEmpty()
            ->and(array_keys($payload['alertas_assinaturas']))->toBe($payload['alertas_aceitos']);

        lancador($this->empresa)->assertSet('alertas_assinaturas', fn (array $a): bool => $a !== []);

        $antigo = $payload->getArrayCopy();
        unset($antigo['alertas_assinaturas']);
        DrmRascunho::query()->update(['rascunho_payload' => $antigo]);
        lancador($this->empresa)->assertSeeHtml('dusk="drm-lancamento-aceitar-alertas"');
    });
});

describe('CA-10 — persistência', function () {
    it('grava versao 1, vigente, com setor, motor e hash', function () {
        $c = lancador($this->empresa);
        preencherTudo(todosAgregados($c));
        avancarAte($c, 6);
        $c->call('aceitarAlertas')->call('fechar');

        $l = DrmLancamento::query()->firstOrFail();
        expect($l->versao)->toBe(1)
            ->and($l->substituido_por_id)->toBeNull()
            ->and($l->competencia->format('Y-m-d'))->toBe('2026-10-01')
            ->and($l->setor)->toBe('comercio')
            ->and($l->motor_drm_version)->toBe('1.0.0')
            ->and($l->usuario_id)->toBe($this->usuario->id)
            ->and($l->lancamento_payload['D01'])->toBe('10000.00')
            ->and($l->lancamento_payload['D05'])->toBeNull();
    });

    it('redireciona para o mês fechado', function () {
        $c = lancador($this->empresa);
        preencherTudo(todosAgregados($c));
        avancarAte($c, 6);
        $c->call('aceitarAlertas')->call('fechar')->assertRedirect(route('drm.mes', [$this->empresa, '2026-10']));
    });

    it('B1 pelo banco: se outra sessão lançou no meio, o fechamento vira "já existe" e não duplica', function () {
        $c = lancador($this->empresa);
        preencherTudo(todosAgregados($c));
        avancarAte($c, 6);

        // Outra aba fecha o mesmo mês entre o carregamento e o clique.
        DrmLancamento::factory()->create(['empresa_analisada_id' => $this->empresa->id, 'competencia' => '2026-10-01']);

        $c->call('aceitarAlertas')->call('fechar');

        $c->assertSet('competencia_ja_lancada', true)->assertSet('bloco_atual', 1)
            ->assertSee('Você já lançou outubro/2026 para esta empresa.');
        expect(DrmLancamento::query()->count())->toBe(1);
    });

    it('duplo submit do fechamento resulta em um único lançamento', function () {
        $c = lancador($this->empresa);
        preencherTudo(todosAgregados($c));
        avancarAte($c, 6);
        $c->call('aceitarAlertas')->call('fechar')->call('fechar');

        expect(DrmLancamento::query()->count())->toBe(1);
    });

    it('falha inesperada mostra mensagem honesta e não grava nada', function () {
        $c = lancador($this->empresa);
        preencherTudo(todosAgregados($c));
        avancarAte($c, 6);
        $c->call('aceitarAlertas');

        DrmLancamento::creating(fn () => throw new RuntimeException('boom'));

        $c->call('fechar')->assertSee('Algo deu errado do nosso lado.');
        expect(DrmLancamento::query()->count())->toBe(0);
    });

    it('não depende do rascunho existir para fechar', function () {
        $c = lancador($this->empresa);
        preencherTudo(todosAgregados($c));
        $c->set('bloco_atual', 6)->call('fechar');

        expect(DrmLancamento::query()->count())->toBe(1);
    });
});

describe('CA-12 — fluxo completo por setor e por modo', function () {
    it('fecha um mês completo em cada setor, agregado e detalhado', function (string $setor, bool $agregado, int $itensDoCusto) {
        $empresa = empresaDoSetor($setor, $this->usuario);
        $c = lancador($empresa);
        if ($agregado) {
            todosAgregados($c);
        }
        preencherTudo($c, $agregado ? [] : ['D06' => '100,00', 'D16' => '1.500,00', 'D40' => '500,00']);
        avancarAte($c, 6);
        $c->call('aceitarAlertas')->call('fechar')->assertRedirect();

        $l = DrmLancamento::query()->where('empresa_analisada_id', $empresa->id)->firstOrFail();
        $p = (array) $l->lancamento_payload;
        $coletados = array_filter($p, fn ($v, $k) => str_starts_with($k, 'D') && $v !== null, ARRAY_FILTER_USE_BOTH);

        expect($l->setor)->toBe($setor)
            ->and(count($coletados))->toBe($agregado ? 9 : ['industria' => 38, 'comercio' => 37, 'servicos' => 36][$setor]);
    })->with([
        'indústria agregada' => ['industria', true, 0],
        'indústria detalhada' => ['industria', false, 4],
        'comércio agregado' => ['comercio', true, 0],
        'comércio detalhado' => ['comercio', false, 3],
        'serviços agregado' => ['servicos', true, 0],
        'serviços detalhado' => ['servicos', false, 2],
    ]);
});

<?php

declare(strict_types=1);

use App\Domain\Drm\MotorDrm;
use App\Livewire\Drm\Demonstracao;
use App\Models\DrmLancamento;
use App\Models\DrmPreferencia;
use App\Models\EmpresaAnalisada;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;

/*
|-------------------------------------------------------------------------
| STORY-049 — a Demonstração do Mês lê o snapshot persistido; nunca o motor.
|
| Os meses vêm dos golden da STORY-048 (entrada congelada), gravados pela factory com o
| snapshot que o próprio motor produz, em UM mês de setembro/2026.
|-------------------------------------------------------------------------
*/

beforeEach(function () {
    $this->usuario = Usuario::factory()->create();
    $this->actingAs($this->usuario);
    $this->empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $this->usuario->id, 'nome_fantasia' => 'Padaria do Bairro']);
});

/** Grava um mês fechado a partir da entrada de um golden. */
function mesDoGolden(EmpresaAnalisada $empresa, string $cenario, array $sobrescrever = []): DrmLancamento
{
    $golden = json_decode((string) file_get_contents(base_path("tests/Fixtures/Drm/golden/{$cenario}.json")), true, 512, JSON_THROW_ON_ERROR);

    return DrmLancamento::factory()->create(array_merge([
        'empresa_analisada_id' => $empresa->id,
        'competencia' => '2026-09-01',
        'setor' => $golden['entrada']['setor'],
        'lancamento_payload' => $golden['entrada']['payload'],
        'gerado_em' => '2026-11-05 09:30:00+00',
    ], $sobrescrever));
}

function abrirMes(EmpresaAnalisada $empresa, string $competencia = '2026-09')
{
    return Livewire::test(Demonstracao::class, ['empresa' => $empresa, 'competencia' => $competencia]);
}

describe('CA-1 — rota e escopo', function () {
    it('abre a competência de uma empresa do usuário', function () {
        mesDoGolden($this->empresa, 'saudavel_comercio');

        $this->get(route('drm.mes', [$this->empresa, '2026-09']))
            ->assertOk()
            ->assertSee('Setembro de 2026')
            ->assertSee('Padaria do Bairro · Comércio')
            ->assertSeeLivewire(Demonstracao::class);
    });

    it('exige login', function () {
        mesDoGolden($this->empresa, 'saudavel_comercio');
        auth()->logout();

        $this->get(route('drm.mes', [$this->empresa, '2026-09']))->assertRedirect(route('login'));
    });

    it('empresa de outro usuário: 404 silente, sem vazar nada', function () {
        $alheia = EmpresaAnalisada::factory()->create(['usuario_id' => Usuario::factory()->create()->id]);
        mesDoGolden($alheia, 'saudavel_comercio');

        $this->get(route('drm.mes', [$alheia, '2026-09']))->assertNotFound();
    });

    it('competência sem lançamento: 404, e a de outro mês da mesma empresa também', function () {
        mesDoGolden($this->empresa, 'saudavel_comercio');

        $this->get(route('drm.mes', [$this->empresa, '2026-08']))->assertNotFound();
    });

    it('competência malformada não casa a rota', function () {
        $this->get('/empresas/'.$this->empresa->id.'/drm/2026-13')->assertNotFound();
        $this->get('/empresas/'.$this->empresa->id.'/drm/agosto')->assertNotFound();
    });

    it('lançamento de outro usuário na mesma competência não aparece para este', function () {
        $outro = Usuario::factory()->create();
        $empresaOutro = EmpresaAnalisada::factory()->create(['usuario_id' => $outro->id]);
        mesDoGolden($empresaOutro, 'saudavel_comercio');

        $this->get(route('drm.mes', [$this->empresa, '2026-09']))->assertNotFound();
    });

    it('mostra a versão vigente, não a substituída', function () {
        $v1 = mesDoGolden($this->empresa, 'saudavel_comercio', ['versao' => 1]);
        $v2 = mesDoGolden($this->empresa, 'prejuizo_comercio_agregado', ['versao' => 2, 'substituido_por_id' => null, 'competencia' => '2026-10-01']);
        // v1 substituída por uma versão 2 da MESMA competência (ordem de ADR-007: ponteiro, depois insert).
        $nova = (string) Str::uuid7();
        DB::table('drm_lancamentos')->where('id', $v1->id)->update(['substituido_por_id' => $nova]);
        $golden = json_decode((string) file_get_contents(base_path('tests/Fixtures/Drm/golden/prejuizo_comercio_agregado.json')), true);
        DrmLancamento::factory()->create([
            'id' => $nova, 'empresa_analisada_id' => $this->empresa->id, 'competencia' => '2026-09-01', 'versao' => 2,
            'setor' => $golden['entrada']['setor'], 'lancamento_payload' => $golden['entrada']['payload'],
        ]);

        abrirMes($this->empresa)->assertSee('Faltou depois da sua retirada');
    });

    it('a competência da URL não pode ser trocada pelo cliente', function () {
        mesDoGolden($this->empresa, 'saudavel_comercio');

        expect(fn () => abrirMes($this->empresa)->set('competencia', '2026-08'))->toThrow('Cannot update locked property');
    });
});

describe('CA-2/CA-3 — linhas, hierarquia, nominal e percentual', function () {
    it('lista as linhas do modo fora com o par nominal + percentual no padrão BR', function () {
        mesDoGolden($this->empresa, 'saudavel_comercio');

        abrirMes($this->empresa)
            ->assertSee('Receita Bruta')
            ->assertSee('R$ 100.000,00')
            ->assertSee('100,0%')
            ->assertSee('(–) Deduções da Receita Bruta')
            ->assertSee('= Receita Operacional Líquida')
            ->assertSee('= Lucro Bruto')
            ->assertSee('(–) Custo das Mercadorias Vendidas (CMV)')
            ->assertSee('= Resultado antes do pró-labore')
            ->assertSee('= Lucro Líquido do Mês')
            ->assertSee('(±) Outras receitas e despesas')
            ->assertDontSee('EBITDA')
            ->assertDontSeeHtml('dusk="drm-linha-L6c"');
    });

    it('as três faixas de setor renderizam, cada uma com o rótulo de custo do seu setor', function (string $cenario, string $setor, string $rotulo) {
        mesDoGolden($this->empresa, $cenario);

        abrirMes($this->empresa)->assertSee($setor)->assertSee($rotulo);
    })->with([
        ['saudavel_comercio', 'Comércio', 'Custo das Mercadorias Vendidas (CMV)'],
        ['saudavel_industria', 'Indústria', 'Custo dos Produtos Vendidos (CPV)'],
        ['saudavel_servicos_misto', 'Serviços', 'Custo dos Serviços Prestados (CSP)'],
    ]);

    it('hierarquia: totalizador de nível 1 destacado e subtotal de nível 2 recuado, sem ser item solto', function () {
        mesDoGolden($this->empresa, 'saudavel_comercio');

        $html = abrirMes($this->empresa)->html();

        // Linha totalizadora ganha o fundo neutro; o subtotal de nível 2 ganha o recuo.
        expect($html)->toMatch('/<tr class="bg-\[color:var\(--color-neutral\)\][^"]*" dusk="drm-linha-L3"/')
            ->and($html)->toMatch('/<tr class="" dusk="drm-linha-L6a"/')
            ->and($html)->toContain('pl-[var(--spacing-md)]');
    });

    it('tabela com semântica: caption, cabeçalhos de coluna e th de linha', function () {
        mesDoGolden($this->empresa, 'saudavel_comercio');

        $html = abrirMes($this->empresa)->html();

        expect($html)->toContain('<caption class="sr-only">')
            ->and($html)->toContain('scope="col"')
            ->and($html)->toContain('% da receita')
            ->and(substr_count($html, 'scope="row"'))->toBe(16);
    });

    it('cards do mobile: um por trecho da conta, com <dl>', function () {
        mesDoGolden($this->empresa, 'saudavel_comercio');

        $c = abrirMes($this->empresa);

        expect(substr_count($c->html(), 'dusk="drm-trecho"'))->toBe(5);
        $c->assertSee('Custo e lucro bruto')->assertSee('Despesas operacionais')->assertSeeHtml('<dl class="m-0">');
    });

    it('agregado: financeiras sem itens não mostram juros nem tarifas como linhas', function () {
        mesDoGolden($this->empresa, 'todos_agregados_industria');

        abrirMes($this->empresa)
            ->assertSee('Despesas Financeiras')
            ->assertDontSeeHtml('dusk="drm-linha-L8a"')
            ->assertDontSeeHtml('dusk="drm-linha-L8b"')
            ->assertSeeHtml('dusk="drm-linha-L6a"');
    });

    it('detalhado: os itens de financeiras aparecem como subtotais', function () {
        mesDoGolden($this->empresa, 'saudavel_comercio');

        abrirMes($this->empresa)->assertSee('Juros pagos')->assertSee('Tarifas bancárias');
    });

    it('L7b omitida quando D41 e D42 são zero e exibida quando há valor', function () {
        mesDoGolden($this->empresa, 'sem_prolabore_sem_nao_operacionais');
        abrirMes($this->empresa)->assertDontSee('Outras receitas e despesas');

        $outra = EmpresaAnalisada::factory()->create(['usuario_id' => $this->usuario->id]);
        mesDoGolden($outra, 'saudavel_comercio');
        abrirMes($outra)->assertSee('Outras receitas e despesas');
    });
});

describe('CA-3b — troca de modo do pró-labore', function () {
    it('abre em fora por padrão, com a frase do modo e o botão que oferece o outro', function () {
        mesDoGolden($this->empresa, 'saudavel_comercio');

        abrirMes($this->empresa)
            ->assertSet('posicao', 'fora')
            ->assertSee('Vendo com a sua retirada fora do resultado operacional.')
            ->assertSee('Ver com a retirada dentro')
            ->assertSee('Sobrou antes da sua retirada');
    });

    it('trocar mostra a outra leitura na hora: rótulos, linhas e ordem', function () {
        mesDoGolden($this->empresa, 'saudavel_comercio');

        $c = abrirMes($this->empresa)->call('trocarPosicao');

        $c->assertSet('posicao', 'dentro')
            ->assertSee('Vendo com a sua retirada dentro das despesas operacionais.')
            ->assertSee('Ver com a retirada fora')
            ->assertSee('Resultado Operacional (EBITDA)')
            ->assertSee('Resultado operacional (EBITDA)')
            ->assertSeeHtml('dusk="drm-linha-L6c"')
            ->assertDontSeeHtml('dusk="drm-linha-L9"')
            ->assertDontSeeHtml('dusk="drm-linha-L10"')
            ->assertDontSee('Resultado antes do pró-labore');

        $ordem = fn (string $html) => preg_match_all('/dusk="drm-linha-(L\w+)"/', $html, $m) ? $m[1] : [];
        expect($ordem($c->html()))->toBe(['L1', 'L2', 'L3', 'L4', 'L5', 'L6', 'L6a', 'L6b', 'L6c', 'L7', 'L7b', 'L8', 'L8a', 'L8b', 'L11']);

        $c->call('trocarPosicao')->assertSet('posicao', 'fora')->assertSeeHtml('dusk="drm-linha-L9"');
    });

    it('o lucro líquido exibido é o mesmo nos dois modos', function () {
        mesDoGolden($this->empresa, 'saudavel_comercio');

        $c = abrirMes($this->empresa);
        $fora = $c->html();
        $dentro = $c->call('trocarPosicao')->html();
        $lucro = fn (string $html) => preg_match('/dusk="drm-destaque-L11".*?dusk="drm-destaque-valor">(.*?)<\/p>/s', $html, $m) ? trim(strip_tags(preg_replace('/<!--.*?-->/s', '', $m[1]))) : null;

        expect($lucro($fora))->toBe('R$ 26.349,25')->and($lucro($dentro))->toBe($lucro($fora));
    });

    it('grava a preferência da empresa e não toca o lançamento nem recalcula', function () {
        $mes = mesDoGolden($this->empresa, 'saudavel_comercio');
        $antes = DB::table('drm_lancamentos')->where('id', $mes->id)->first();

        abrirMes($this->empresa)->call('trocarPosicao');

        expect(DrmPreferencia::query()->where('empresa_analisada_id', $this->empresa->id)->value('posicao_prolabore'))->toBe('dentro')
            ->and(DB::table('drm_lancamentos')->where('id', $mes->id)->first())->toEqual($antes);
    });

    it('a preferência vale para toda a série: outro mês da empresa abre já no modo escolhido', function () {
        mesDoGolden($this->empresa, 'saudavel_comercio');
        mesDoGolden($this->empresa, 'saudavel_comercio', ['competencia' => '2026-08-01']);
        abrirMes($this->empresa, '2026-09')->call('trocarPosicao');

        abrirMes($this->empresa, '2026-08')->assertSet('posicao', 'dentro')->assertSeeHtml('dusk="drm-linha-L6c"');
    });

    it('a preferência é por empresa: outra empresa continua em fora', function () {
        mesDoGolden($this->empresa, 'saudavel_comercio');
        $outra = EmpresaAnalisada::factory()->create(['usuario_id' => $this->usuario->id]);
        mesDoGolden($outra, 'saudavel_comercio');
        abrirMes($this->empresa)->call('trocarPosicao');

        abrirMes($outra)->assertSet('posicao', 'fora');
    });

    it('anuncia a mudança a leitores de tela (região aria-live com o número de linhas)', function () {
        mesDoGolden($this->empresa, 'saudavel_comercio');

        abrirMes($this->empresa)
            ->assertSeeHtml('aria-live="polite" dusk="drm-modo"')
            ->assertSee('16 linhas na demonstração.')
            ->call('trocarPosicao')
            ->assertSee('15 linhas na demonstração.');
    });
});

describe('CA-4 — os três números', function () {
    it('aparecem antes da conta, nesta ordem', function () {
        mesDoGolden($this->empresa, 'saudavel_comercio');

        $html = abrirMes($this->empresa)->html();

        expect(strpos($html, 'Quanto vendeu'))->toBeLessThan(strpos($html, 'Sobrou antes da sua retirada'))
            ->and(strpos($html, 'Sobrou antes da sua retirada'))->toBeLessThan(strpos($html, 'Sobrou depois da sua retirada'))
            ->and(strpos($html, 'Sobrou depois da sua retirada'))->toBeLessThan(strpos($html, 'dusk="drm-tabela"'));
    });
});

describe('CA-5 — sem faturamento', function () {
    it('percentuais indisponíveis com a mensagem semântica, valores presentes, sem tom de erro', function () {
        mesDoGolden($this->empresa, 'sem_faturamento_servicos');

        $c = abrirMes($this->empresa);

        $c->assertSeeHtml('dusk="drm-sem-faturamento"')
            ->assertSee('Sem faturamento neste mês — os percentuais sobre a receita não se aplicam.')
            ->assertSee('Os valores em reais continuam abaixo.')
            ->assertSee('Percentual indisponível: sem faturamento neste mês')
            ->assertSee('R$ 0,00')
            ->assertDontSeeHtml('role="alert"');
        expect($c->html())->not->toMatch('/\d,\d%/');
    });

    it('o segundo e o terceiro destaques continuam, com traço no percentual', function () {
        mesDoGolden($this->empresa, 'sem_faturamento_servicos');

        abrirMes($this->empresa)->assertSeeHtml('dusk="drm-destaque-L9"')->assertSeeHtml('dusk="drm-destaque-L11"');
    });

    it('com faturamento o aviso não existe', function () {
        mesDoGolden($this->empresa, 'saudavel_comercio');

        abrirMes($this->empresa)->assertDontSeeHtml('dusk="drm-sem-faturamento"');
    });
});

describe('CA-6 — prejuízo', function () {
    it('sinal de menos, verbo trocado e pill neutro; sem classe de erro nem de farol', function () {
        mesDoGolden($this->empresa, 'prejuizo_comercio_agregado');

        $c = abrirMes($this->empresa);

        $c->assertSee('Faltou depois da sua retirada')
            ->assertDontSee('Sobrou depois da sua retirada')
            ->assertSee('Prejuízo no mês')
            ->assertSee('−R$ ');
        expect($c->html())->not->toContain('destructive')->not->toContain('farol');
    });

    it('lucro não leva o pill', function () {
        mesDoGolden($this->empresa, 'saudavel_comercio');

        abrirMes($this->empresa)->assertDontSee('Prejuízo no mês')->assertDontSee('Faltou');
    });

    it('o pill aparece na linha final da tabela e do card, além do destaque', function () {
        mesDoGolden($this->empresa, 'prejuizo_comercio_agregado');

        expect(substr_count(abrirMes($this->empresa)->html(), 'Prejuízo no mês'))->toBe(3);
    });
});

describe('CA-7 — alertas aceitos', function () {
    it('viram ressalvas recolhidas, com contagem', function () {
        mesDoGolden($this->empresa, 'prejuizo_comercio_agregado', ['alertas_aceitos' => [
            ['regra' => 'A5', 'ocorrido_em' => '2026-11-05T09:30:00+00:00', 'valor_envolvido' => '0.00'],
            ['regra' => 'A3', 'ocorrido_em' => '2026-11-05T09:30:00+00:00', 'valor_envolvido' => '3240.00'],
        ]]);

        abrirMes($this->empresa)
            ->assertSee('Ressalvas registradas no lançamento (2)')
            ->assertSee('Este mês fechou com prejuízo de R$ 3.240,00.')
            ->assertSee('Nenhuma retirada (pró-labore) foi lançada neste mês.')
            ->assertSeeHtml('<details')
            ->assertDontSeeHtml('<details open');
    });

    it('sem alertas aceitos, o bloco não existe (nada de "Ressalvas (0)")', function () {
        mesDoGolden($this->empresa, 'saudavel_comercio');

        // O bloco de ressalvas é o <details dusk="drm-ressalvas">; os comparativos (STORY-051) também usam <details>.
        abrirMes($this->empresa)->assertDontSee('Ressalvas')->assertDontSeeHtml('dusk="drm-ressalvas"');
    });
});

describe('CA-8 — snapshot, não recálculo', function () {
    it('não invoca o motor nem ao abrir nem ao trocar de modo', function () {
        mesDoGolden($this->empresa, 'saudavel_comercio');
        // MotorDrm é final (não dá para espionar): se alguém o resolver do contêiner, o teste quebra.
        app()->bind(MotorDrm::class, fn () => throw new LogicException('A tela invocou o motor.'));

        abrirMes($this->empresa)->call('trocarPosicao')->call('trocarPosicao')->assertSee('R$ 100.000,00');
    });

    it('o componente nem conhece o motor', function () {
        $fonte = (string) file_get_contents(app_path('Livewire/Drm/Demonstracao.php')).(string) file_get_contents(app_path('Support/Drm/DemonstracaoVisao.php'));

        expect($fonte)->not->toContain('MotorDrm')->not->toContain('calcular(');
    });

    it('o rodapé mostra a versão e a data DO SNAPSHOT, não as da configuração atual', function () {
        $golden = json_decode((string) file_get_contents(base_path('tests/Fixtures/Drm/golden/saudavel_comercio.json')), true);
        $snapshot = $golden['esperado'];
        $snapshot['motor_drm_version'] = '0.9.0';
        $snapshot['gerado_em'] = '2026-09-04T14:22:00+00:00';
        mesDoGolden($this->empresa, 'saudavel_comercio', ['demonstracao' => $snapshot, 'motor_drm_version' => '0.9.0']);
        config(['drm.motor_drm_version' => '9.9.9']);

        abrirMes($this->empresa)->assertSee('motor v0.9.0 · gerado em 04/09/2026 14:22')->assertDontSee('9.9.9');
    });

    it('mostra o valor gravado mesmo que o cálculo de hoje desse outro', function () {
        $golden = json_decode((string) file_get_contents(base_path('tests/Fixtures/Drm/golden/saudavel_comercio.json')), true);
        $snapshot = $golden['esperado'];
        $snapshot['linhas']['fora'][0]['nominal'] = '123456.78';
        mesDoGolden($this->empresa, 'saudavel_comercio', ['demonstracao' => $snapshot]);

        abrirMes($this->empresa)->assertSee('R$ 123.456,78');
    });
});

describe('CA-8b — render genérico', function () {
    it('um snapshot de estrutura diferente da atual renderiza sem erro e sem hardcode de L1..L11', function () {
        $linha = fn (string $c, int $n, string $rotulo, string $tipo, string $nominal, string $pct, bool $dest = false) => [
            'codigo' => $c, 'nivel' => $n, 'rotulo' => $rotulo, 'tipo' => $tipo, 'destaque' => $dest,
            'nominal' => $nominal, 'percentual' => $pct, 'motivo' => null, 'mensagem' => null, 'motivo_nominal' => null, 'mensagem_nominal' => null,
        ];
        $futuro = [
            'motor_drm_version' => '2.0.0', 'competencia' => '2026-09', 'setor' => 'comercio', 'gerado_em' => '2026-11-05T09:30:00+00:00',
            'modos' => [],
            'linhas' => ['fora' => [
                $linha('X1', 1, 'Faturamento do mês', 'item', '1000.00', '100.0000', true),
                $linha('X2', 1, 'Impostos', 'deducao', '100.00', '10.0000'),
                $linha('X3', 1, 'Base', 'total', '900.00', '90.0000'),
                $linha('X3a', 3, 'Detalhe fino', 'folha', '300.00', '30.0000'),
                $linha('X4', 1, 'Sobra final', 'total', '800.00', '80.0000', true),
            ], 'dentro' => []],
        ];
        mesDoGolden($this->empresa, 'saudavel_comercio', ['demonstracao' => $futuro, 'motor_drm_version' => '2.0.0']);

        abrirMes($this->empresa)
            ->assertOk()
            ->assertSee('Faturamento do mês')
            ->assertSee('Detalhe fino')
            ->assertSee('= Sobra final')
            ->assertSee('R$ 800,00')
            ->assertSee('motor v2.0.0');
    });

    it('snapshot sem a posição pedida cai na que tem; sem linhas nenhuma, a tela abre vazia de conta', function () {
        mesDoGolden($this->empresa, 'saudavel_comercio', [
            'demonstracao' => ['motor_drm_version' => '3.0.0', 'linhas' => ['fora' => [], 'dentro' => []]],
            'motor_drm_version' => '3.0.0',
        ]);

        $this->get(route('drm.mes', [$this->empresa, '2026-09']))->assertOk()->assertSee('motor v3.0.0');
    });
});

describe('CA-9/CA-10/CA-11 — marcação, acessibilidade e navegação', function () {
    it('breadcrumb para Minhas Empresas e para a empresa; caminhos de volta e para o lançamento', function () {
        mesDoGolden($this->empresa, 'saudavel_comercio');

        $this->get(route('drm.mes', [$this->empresa, '2026-09']))
            ->assertSee(route('home'), false)
            ->assertSee(route('empresas.show', $this->empresa), false)
            ->assertSee(route('drm.lancar', $this->empresa), false)
            ->assertSee('aria-current="page"', false)
            ->assertSee('Voltar para a empresa')
            ->assertSee('Lançar outro mês');
    });

    it('troca de modo é <button>, não link; ausência de valor tem texto acessível, não só traço', function () {
        mesDoGolden($this->empresa, 'sem_faturamento_servicos');

        $html = abrirMes($this->empresa)->html();

        expect($html)->toMatch('/<button[^>]*wire:click="trocarPosicao"[^>]*dusk="drm-trocar-modo"/')
            ->and($html)->toContain('<span aria-hidden="true">—</span><span class="sr-only">Percentual indisponível')
            ->and($html)->toContain('btn btn--link');
    });

    it('a tela não tem cor literal nem usa vermelho de erro', function () {
        $blade = (string) file_get_contents(resource_path('views/livewire/drm/demonstracao.blade.php'));

        expect($blade)->not->toMatch('/#[0-9a-fA-F]{3,8}\b/')->not->toContain('destructive');
    });

    it('"Corrigir este mês" leva à retificação daquele mês (STORY-050)', function () {
        mesDoGolden($this->empresa, 'saudavel_comercio');

        abrirMes($this->empresa)
            ->assertSeeHtml('dusk="drm-corrigir"')
            ->assertSeeHtml('href="'.route('drm.corrigir', [$this->empresa, '2026-09']).'"')
            ->assertDontSeeHtml('disabled');
    });
});

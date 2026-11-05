<?php

declare(strict_types=1);

use App\Actions\Drm\RetificarLancamento;
use App\Domain\Cobranca\MotivoBloqueio;
use App\Domain\Drm\Competencia;
use App\Domain\Drm\Grupo;
use App\Domain\Drm\Lancamento;
use App\Domain\Drm\Modo;
use App\Domain\Drm\Setor;
use App\Models\CobrancaConfig;
use App\Models\DrmLancamento;
use App\Models\DrmPreferencia;
use App\Models\EmpresaAnalisada;
use App\Models\EventoProduto;
use App\Models\TrialConfig;
use App\Models\Usuario;
use App\Support\Cobranca\AcessoDrm;
use App\Support\Drm\PlanilhaDrm;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/*
|-------------------------------------------------------------------------
| STORY-055 / ADR-008 — exportação da Demonstração em XLSX.
| O teste RELÊ o arquivo gerado e confere célula a célula contra o snapshot, avaliando as fórmulas
| (CA-10): verificar só o status da resposta não prova nada, e fórmula errada é planilha que mente.
|-------------------------------------------------------------------------
*/

beforeEach(function () {
    DB::statement("SET LOCAL TIME ZONE 'UTC'");
    $this->usuario = Usuario::factory()->create();
    $this->actingAs($this->usuario);
    $this->empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $this->usuario->id, 'cnae' => '4711301']);
});

/** Baixa o XLSX pela rota e o abre de novo, como o contador faria. */
function xlsxBaixado(EmpresaAnalisada $empresa, string $competencia): Spreadsheet
{
    $resposta = test()->get(route('drm.exportar', [$empresa, $competencia]));
    $resposta->assertOk();
    $arquivo = tempnam(sys_get_temp_dir(), 'drm');
    file_put_contents($arquivo, $resposta->streamedContent());
    $planilha = IOFactory::load($arquivo);
    unlink($arquivo);

    return $planilha;
}

/** O lançamento do setor, tudo agregado, com o resultado dado pelos números. */
function lancamentoDoSetor(Setor $setor, array $texto = []): Lancamento
{
    $modos = array_fill_keys(array_map(fn (Grupo $g) => $g->value, Grupo::cases()), Modo::Agregado);

    return Lancamento::deTexto($setor, $modos, [
        'D01' => '84.300,00', 'D02' => '5.058,00', 'D03' => '41.200,00', 'D08' => '9.000,00',
        'D15' => '20.702,00', 'D37' => '1.100,00', 'D40' => '4.000,00', 'D41' => '300,00', 'D42' => '', ...$texto,
    ]);
}

function mesDoSetor(EmpresaAnalisada $empresa, string $competencia, Setor $setor, array $texto = [], array $extra = []): DrmLancamento
{
    return DrmLancamento::factory()->create([
        'empresa_analisada_id' => $empresa->id, 'competencia' => $competencia.'-01', 'setor' => $setor->value,
        'lancamento_payload' => lancamentoDoSetor($setor, $texto)->payload(), 'gerado_em' => '2026-11-01 10:00:00+00', ...$extra,
    ]);
}

/**
 * Cada linha do snapshot, na ordem, tem rótulo, valor e percentual iguais na aba "Mês". Devolve as linhas lidas.
 *
 * @return list<array{rotulo: string, formula: bool}>
 */
function conferirAbaMes(Worksheet $aba, DrmLancamento $l, string $posicao = 'fora'): array
{
    $esperadas = array_values(array_filter($l->demonstracao['linhas'][$posicao], fn ($x) => ! (
        (int) $x['nivel'] >= 2 && $x['motivo_nominal'] === 'indisponivel:grupo_agregado' && $x['nominal'] === null
    )));
    $lidas = [];
    foreach ($esperadas as $i => $e) {
        $r = 9 + $i;
        expect(trim(preg_replace('/^(= |\(–\) |\(±\) )/u', '', (string) $aba->getCell("A{$r}")->getValue())))->toBe($e['rotulo']);

        $valor = $aba->getCell("B{$r}")->getCalculatedValue();
        if ($e['nominal'] === null) {
            expect($valor)->toBeNull();
        } else {
            expect(round((float) $valor * 100))->toBe(round((float) $e['nominal'] * 100));
        }

        $pct = $aba->getCell("C{$r}")->getCalculatedValue();
        if ($e['percentual'] === null) {
            expect($pct)->toBeNull();
        } else {
            expect(abs((float) $pct * 100 - (float) $e['percentual']))->toBeLessThan(0.0002);
        }
        $lidas[] = ['rotulo' => $e['rotulo'], 'formula' => str_starts_with((string) $aba->getCell("B{$r}")->getValue(), '=')];
    }
    // Nada além da última linha: a próxima linha de dados é o rodapé, duas linhas abaixo.
    expect($aba->getCell('A'.(9 + count($esperadas)))->getValue())->toBeNull();

    return $lidas;
}

describe('CA-1, CA-2, CA-10 — conteúdo, célula a célula', function () {
    it('reproduz o snapshot nos três setores, com as linhas derivadas como fórmula', function (Setor $setor) {
        $l = mesDoSetor($this->empresa, '2026-09', $setor);

        $planilha = xlsxBaixado($this->empresa, '2026-09');
        $aba = $planilha->getSheetByName('Mês');
        $lidas = conferirAbaMes($aba, $l);

        $comFormula = array_column(array_filter($lidas, fn ($x) => $x['formula']), 'rotulo');
        expect($comFormula)->toContain('Receita Operacional Líquida')->toContain('Lucro Bruto')->toContain('Despesas Operacionais')
            ->and(array_column(array_filter($lidas, fn ($x) => ! $x['formula']), 'rotulo'))->toContain('Receita Bruta');
        expect($aba->getCell('B2')->getValue())->toBe($this->empresa->nomeExibicao())
            ->and($aba->getCell('B3')->getValue())->toBe('Setembro de 2026');
    })->with([Setor::Comercio, Setor::Servicos, Setor::Industria]);

    it('exporta também o modo "dentro" quando é a preferência da empresa', function () {
        $l = mesDoSetor($this->empresa, '2026-09', Setor::Comercio);
        DrmPreferencia::gravarPosicao($this->empresa, 'dentro');

        $lidas = conferirAbaMes(xlsxBaixado($this->empresa, '2026-09')->getSheetByName('Mês'), $l, 'dentro');

        expect(array_column($lidas, 'rotulo'))->toContain('Resultado Operacional (EBITDA)');
    });

    it('com grupos detalhados, a linha do grupo é a soma dos itens e a conta fecha', function () {
        $l = serieMes($this->empresa, '2026-09', '3.900,00', ['D16' => '1.200,00', 'D17' => '800,00'], detalhado: true);

        $lidas = conferirAbaMes(xlsxBaixado($this->empresa, '2026-09')->getSheetByName('Mês'), $l);

        expect(array_column(array_filter($lidas, fn ($x) => $x['formula']), 'rotulo'))->toContain('Despesas Operacionais');
    });

    it('a fórmula que não reproduz o snapshot é substituída pelo valor do snapshot', function () {
        $l = mesDoSetor($this->empresa, '2026-09', Setor::Comercio);
        // Um snapshot adulterado (L3 ≠ L1 − L2) não pode sair como fórmula que mente.
        $d = $l->demonstracao->getArrayCopy();
        $d['linhas']['fora'][2]['nominal'] = '90000.00';
        DB::table('drm_lancamentos')->where('id', $l->id)->update(['demonstracao' => json_encode($d)]);

        $aba = xlsxBaixado($this->empresa, '2026-09')->getSheetByName('Mês');

        expect((float) $aba->getCell('B11')->getValue())->toBe(90000.0)
            ->and($aba->getCell('B11')->getValue())->not->toBeString();
    });

    it('rótulo que começa com "=" é texto, nunca fórmula', function () {
        mesDoSetor($this->empresa, '2026-09', Setor::Comercio);

        $aba = xlsxBaixado($this->empresa, '2026-09')->getSheetByName('Mês');

        expect($aba->getCell('A11')->getDataType())->toBe('s')
            ->and($aba->getCell('A11')->getValue())->toStartWith('= ');
    });

    it('inclui o rodapé do snapshot (versão do motor e geração) e o nome sai previsível', function () {
        mesDoSetor($this->empresa, '2026-09', Setor::Comercio);

        $resposta = $this->get(route('drm.exportar', [$this->empresa, '2026-09']));
        $aba = xlsxBaixado($this->empresa, '2026-09')->getSheetByName('Mês');

        $resposta->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        expect($resposta->headers->get('Content-Disposition'))->toContain('drm_'.Str::slug($this->empresa->nomeExibicao()).'_2026-09.xlsx');
        $rodape = collect(range(9, 40))->map(fn ($r) => (string) $aba->getCell("A{$r}")->getValue())->first(fn ($t) => str_starts_with($t, 'motor v'));
        expect($rodape)->toMatch('/^motor v1\.0\.0 · gerado em \d{2}\/\d{2}\/\d{4} \d{2}:\d{2}$/');
    });
});

describe('CA-5 — fonte é o snapshot, retificação sinalizada', function () {
    it('exportar o mês retificado traz os valores retificados e diz que foi retificado', function () {
        $v1 = mesDoSetor($this->empresa, '2026-09', Setor::Comercio);
        app(RetificarLancamento::class)->execute(
            $this->empresa, Competencia::deTexto('2026-09'),
            lancamentoDoSetor(Setor::Comercio, ['D02' => '6.000,00']), [], 1,
        );
        $v2 = DrmLancamento::vigenteDaCompetencia($this->empresa, '2026-09-01');

        $aba = xlsxBaixado($this->empresa, '2026-09')->getSheetByName('Mês');
        conferirAbaMes($aba, $v2);

        expect($aba->getCell('B6')->getValue())->toStartWith('Retificado — versão 2 (corrigido 1× — última correção em ')
            ->and(round((float) $aba->getCell('B10')->getCalculatedValue(), 2))->toBe(6000.0)
            ->and($v1->demonstracao['linhas']['fora'][1]['nominal'])->toBe('5058.00');
    });

    it('o mês original diz que é o lançamento original', function () {
        mesDoSetor($this->empresa, '2026-09', Setor::Comercio);

        expect(xlsxBaixado($this->empresa, '2026-09')->getSheetByName('Mês')->getCell('B6')->getValue())->toBe('Lançamento original');
    });

    it('não recalcula: o arquivo sai do snapshot mesmo que o motor mude de versão', function () {
        $l = mesDoSetor($this->empresa, '2026-09', Setor::Comercio);
        config(['drm.motor_drm_version' => '9.9.9']);

        $aba = xlsxBaixado($this->empresa, '2026-09')->getSheetByName('Mês');

        conferirAbaMes($aba, $l);
        $textos = collect(range(9, 40))->map(fn ($r) => (string) $aba->getCell("A{$r}")->getValue())->implode('|');
        expect($textos)->toContain('motor v1.0.0')->not->toContain('9.9.9');
    });
});

describe('CA-7 — sem faturamento e prejuízo', function () {
    it('sem faturamento: valores em reais saem, percentuais ficam em branco com o motivo', function () {
        $l = mesDoSetor($this->empresa, '2026-09', Setor::Comercio, ['D01' => '0,00', 'D02' => '0,00', 'D03' => '0,00', 'D08' => '500,00', 'D15' => '1.000,00', 'D37' => '', 'D40' => '', 'D41' => '', 'D42' => '']);

        $aba = xlsxBaixado($this->empresa, '2026-09')->getSheetByName('Mês');
        conferirAbaMes($aba, $l);

        expect($aba->getCell('C9')->getValue())->toBeNull()
            ->and((string) $aba->getCell('D9')->getValue())->toContain('sem faturamento neste mês');
    });

    it('prejuízo: valor negativo no arquivo e a marca na linha, sem dramatizar', function () {
        $l = mesDoSetor($this->empresa, '2026-09', Setor::Comercio, ['D03' => '90.000,00']);

        $aba = xlsxBaixado($this->empresa, '2026-09')->getSheetByName('Mês');
        $lidas = conferirAbaMes($aba, $l);
        $ultima = 9 + count($lidas) - 1;

        expect((float) $aba->getCell("B{$ultima}")->getCalculatedValue())->toBeLessThan(0)
            ->and((string) $aba->getCell("D{$ultima}")->getValue())->toBe('Prejuízo no mês')
            ->and($aba->getStyle("B{$ultima}")->getFont()->getColor()->getRGB())->toBe('000000');
    });
});

describe('CA-4 — a série histórica na segunda aba', function () {
    it('uma coluna por mês; o mês sem lançamento é coluna em branco, nunca zero', function () {
        $jul = serieMes($this->empresa, '2026-07');
        serieMes($this->empresa, '2026-09', '4.900,00');

        $aba = xlsxBaixado($this->empresa, '2026-09')->getSheetByName('Série histórica');

        expect($aba->getCell('B5')->getValue())->toBe('julho/2026')
            ->and($aba->getCell('C5')->getValue())->toBe('agosto/2026')
            ->and($aba->getCell('D5')->getValue())->toBe('setembro/2026')
            ->and($aba->getCell('B6')->getValue())->toBe('Lançado')
            ->and($aba->getCell('C6')->getValue())->toBe('Não lançado')
            ->and($aba->getCell('E5')->getValue())->toBeNull();

        // Agosto inteiro em branco, do valor ao percentual.
        for ($r = 8; $r <= 60; $r++) {
            expect($aba->getCell("C{$r}")->getValue())->toBeNull();
        }
        // Julho e setembro conferem com os snapshots, fórmulas avaliadas.
        $linhas = array_values(array_filter($jul->demonstracao['linhas']['fora'], fn ($e) => ! ((int) $e['nivel'] >= 2 && $e['motivo_nominal'] === 'indisponivel:grupo_agregado')));
        foreach ($linhas as $i => $e) {
            expect(round((float) $aba->getCell('B'.(8 + $i))->getCalculatedValue() * 100))->toBe(round((float) $e['nominal'] * 100));
        }
        expect($aba->getCell('B10')->getValue())->toBeString()->toStartWith('=');
    });

    it('mês retificado é marcado com a versão; a série usa a vigente', function () {
        serieMes($this->empresa, '2026-08');
        mesDoSetor($this->empresa, '2026-09', Setor::Comercio);
        app(RetificarLancamento::class)->execute(
            $this->empresa, Competencia::deTexto('2026-09'),
            lancamentoDoSetor(Setor::Comercio, ['D02' => '6.000,00']), [], 1,
        );

        $aba = xlsxBaixado($this->empresa, '2026-09')->getSheetByName('Série histórica');

        expect($aba->getCell('C6')->getValue())->toBe('Retificado (v2)')
            ->and(round((float) $aba->getCell('C9')->getCalculatedValue(), 2))->toBe(6000.0);
    });

    it('os percentuais da série são fórmulas sobre a receita do próprio mês', function () {
        serieMes($this->empresa, '2026-08');

        $aba = xlsxBaixado($this->empresa, '2026-08')->getSheetByName('Série histórica');
        $primeira = collect(range(8, 60))->first(fn ($r) => str_contains((string) $aba->getCell("A{$r}")->getValue(), '% da receita'));

        expect($primeira)->not->toBeNull();
        $linhaReceitaPct = $primeira + 1;
        expect((float) $aba->getCell("B{$linhaReceitaPct}")->getCalculatedValue())->toBe(1.0);
    });
});

describe('CA-6 — escopo de acesso', function () {
    it('empresa de outro usuário e competência sem lançamento são o mesmo 404', function () {
        mesDoSetor($this->empresa, '2026-09', Setor::Comercio);
        $alheia = EmpresaAnalisada::factory()->create();
        DrmLancamento::factory()->create(['empresa_analisada_id' => $alheia->id, 'competencia' => '2026-09-01']);

        $this->get(route('drm.exportar', [$alheia, '2026-09']))->assertNotFound();
        $this->get(route('drm.exportar', [$this->empresa, '2026-08']))->assertNotFound();
    });

    it('sem login, vai para o login', function () {
        mesDoSetor($this->empresa, '2026-09', Setor::Comercio);
        auth()->logout();

        $this->get(route('drm.exportar', [$this->empresa, '2026-09']))->assertRedirect();
    });

    it('sem plano continua exportando: é leitura do histórico (STORY-084)', function () {
        TrialConfig::query()->firstOrCreate([], ['ativo' => true, 'dias' => 7, 'analises' => 1]);
        CobrancaConfig::atual()->update(['ativa' => true]);
        mesDoSetor($this->empresa, '2026-09', Setor::Comercio);

        expect(AcessoDrm::bloqueioPara($this->usuario->id)?->motivo)->toBe(MotivoBloqueio::SemNada);
        $this->get(route('drm.exportar', [$this->empresa, '2026-09']))->assertOk();
    });
});

describe('CA-9 — síncrono e sem retenção', function () {
    it('não grava arquivo em disco algum nem enfileira trabalho', function () {
        Storage::fake('local');
        Queue::fake();
        mesDoSetor($this->empresa, '2026-09', Setor::Comercio);

        $this->get(route('drm.exportar', [$this->empresa, '2026-09']))->assertOk()->streamedContent();

        expect(Storage::disk('local')->allFiles())->toBe([]);
        Queue::assertNothingPushed();
    });

    it('gerar não grava nada no banco (exportar é leitura)', function () {
        mesDoSetor($this->empresa, '2026-09', Setor::Comercio);
        $antes = DrmLancamento::query()->count();

        PlanilhaDrm::gerar($this->empresa, DrmLancamento::query()->firstOrFail(), 'fora');

        expect(DrmLancamento::query()->count())->toBe($antes)->and(EventoProduto::query()->count())->toBe(0);
    });
});

describe('CA-8 — nomenclatura', function () {
    it('drm_<empresa>_<AAAA-MM>.xlsx, com o nome normalizado', function () {
        $empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $this->usuario->id, 'nome_fantasia_informado' => 'Padaria São João & Filhos']);
        $competencia = Competencia::deTexto('2026-03');

        expect(PlanilhaDrm::nomeArquivo($empresa, $competencia))->toBe('drm_padaria-sao-joao-filhos_2026-03.xlsx');
    });
});

describe('CA-12 e CA-13 — camada de impressão', function () {
    it('a Demonstração oferece a planilha e a impressão interina, sem prometer PDF nosso', function () {
        mesDoSetor($this->empresa, '2026-09', Setor::Comercio);

        $this->get(route('drm.mes', [$this->empresa, '2026-09']))->assertOk()
            ->assertSee('Exportar planilha (XLSX)')
            ->assertSee(route('drm.exportar', [$this->empresa, '2026-09']), false)
            ->assertSee('Imprimir / Salvar em PDF')
            ->assertDontSee('Exportar PDF')
            ->assertDontSee('Baixar PDF')
            ->assertSee('drm-rodape-impressao', false)
            ->assertSee('motor v1.0.0 · conteúdo v1.0.0 · gerado em');
    });

    it('a folha de impressão não tem cor literal: só tokens (IDR-008)', function () {
        $css = (string) file_get_contents(resource_path('css/app.css'));
        $bloco = substr($css, (int) strpos($css, '@media print'));

        expect($bloco)->toContain('@page')->toContain('A4 portrait')
            ->and(preg_match('/#[0-9a-fA-F]{3,8}\b|rgba?\(/', $bloco))->toBe(0);
    });
});

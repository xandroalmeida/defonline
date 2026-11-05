<?php

declare(strict_types=1);

use App\Actions\Drm\RetificacaoSemMudanca;
use App\Actions\Drm\RetificarLancamento;
use App\Domain\Cobranca\Carteira;
use App\Domain\Drm\Competencia;
use App\Domain\Drm\Grupo;
use App\Domain\Drm\Lancamento as LancamentoDominio;
use App\Domain\Drm\Modo;
use App\Domain\Drm\Setor;
use App\Livewire\Drm\Demonstracao;
use App\Livewire\Drm\Lancamento;
use App\Models\CobrancaConfig;
use App\Models\DrmLancamento;
use App\Models\DrmRascunho;
use App\Models\EmpresaAnalisada;
use App\Models\EventoProduto;
use App\Models\TrialConfig;
use App\Models\Usuario;
use App\Observabilidade\EventLogger;
use App\Observabilidade\EventosDrm;
use App\Observabilidade\Excecoes\PiiEmEventoException;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;

/*
|-------------------------------------------------------------------------
| STORY-056 — eventos analíticos do DRM (ADR-004, PDR-003 decisão 8).
| Usa os ajudantes do fluxo de lançamento (`lancador`, `preencherTudo`…) de LancamentoTest.
|-------------------------------------------------------------------------
*/

beforeEach(function () {
    // O Postgres local roda em America/Sao_Paulo e a aplicação em UTC (IDR-018): sem isto, `created_at` lido do
    // banco e `now()` em memória diferem em 3 h e as durações dos eventos saem erradas. Produção é UTC.
    DB::statement("SET LOCAL TIME ZONE 'UTC'");
    CarbonImmutable::setTestNow('2026-11-05 10:00:00');
    Carbon::setTestNow('2026-11-05 10:00:00');
    $this->usuario = Usuario::factory()->create();
    $this->actingAs($this->usuario);
    $this->empresa = empresaDoSetor('comercio', $this->usuario);
});

afterEach(function () {
    CarbonImmutable::setTestNow();
    Carbon::setTestNow();
    EventoProduto::flushEventListeners(); // ganchos de teste não vazam para os outros arquivos.
});

/** @return list<EventoProduto> */
function eventosDrm(?string $nome = null)
{
    return EventoProduto::query()
        ->where('nome_evento', $nome === null ? 'like' : '=', $nome ?? 'drm_%')
        ->when($nome === null, fn ($q) => $q->where('nome_evento', 'like', 'drm\_%'))
        ->orderBy('id')
        ->get()
        ->all();
}

/** Lança um mês de ponta a ponta pelo formulário, agregado, e devolve o componente (já fechado). */
function lancarMesPeloFormulario(EmpresaAnalisada $empresa, string $competencia, array $valores = [])
{
    $c = lancador($empresa, $competencia);
    preencherTudo(todosAgregados($c), $valores);
    avancarAte($c, 6);
    $c->call('aceitarAlertas')->call('fechar');

    return $c;
}

function propsDo(EventoProduto $e): array
{
    return (array) $e->propriedades;
}

describe('CA-1, CA-3, CA-8 — lançamento concluído: recorrência, alcance e idempotência', function () {
    it('emite drm_lancamento_concluido uma vez, com o esquema exato e o setor', function () {
        lancarMesPeloFormulario($this->empresa, '2026-09');

        $eventos = eventosDrm('drm_lancamento_concluido');
        expect($eventos)->toHaveCount(1);
        $e = $eventos[0];
        $l = DrmLancamento::query()->firstOrFail();

        expect($e->usuario_id)->toBe($this->usuario->id)
            ->and($e->empresa_id)->toBe($this->empresa->id)
            ->and($e->request_id)->not->toBeEmpty()
            ->and(array_keys(propsDo($e)))->toEqualCanonicalizing([
                'lancamento_id', 'rascunho_id', 'competencia', 'setor', 'numero_lancamento', 'dias_desde_anterior', 'duracao_seg',
                'grupos_agregados', 'grupos_detalhados', 'campos_herdados', 'campos_herdados_aceitos', 'rascunho_retomado', 'qtd_alertas_aceitos',
            ])
            ->and(propsDo($e))->toMatchArray([
                'lancamento_id' => $l->id, 'competencia' => '2026-09', 'setor' => 'comercio', 'numero_lancamento' => 1,
                'dias_desde_anterior' => null, 'rascunho_retomado' => false,
            ]);
    });

    it('conta o ordinal do lançamento e a distância até o anterior (a base da métrica de 45 dias)', function () {
        lancarMesPeloFormulario($this->empresa, '2026-08');
        Carbon::setTestNow('2026-11-20 10:00:00'); // 15 dias depois
        CarbonImmutable::setTestNow('2026-11-20 10:00:00');
        lancarMesPeloFormulario($this->empresa, '2026-09');

        $segundo = eventosDrm('drm_lancamento_concluido')[1];

        expect(propsDo($segundo))->toMatchArray(['numero_lancamento' => 2, 'dias_desde_anterior' => 15, 'competencia' => '2026-09']);
    });

    it('reenviar o fechamento não duplica o evento nem o lançamento (CA-8)', function () {
        $c = lancarMesPeloFormulario($this->empresa, '2026-09');
        $c->call('fechar')->call('fechar');

        expect(eventosDrm('drm_lancamento_concluido'))->toHaveCount(1)
            ->and(DrmLancamento::query()->count())->toBe(1);
    });

    it('o evento nasce na transação do fato: se ele falha, o mês não fecha', function () {
        EventoProduto::creating(function (EventoProduto $e) {
            if ($e->nome_evento === 'drm_lancamento_concluido') {
                throw new RuntimeException('falha do evento');
            }
        });

        $c = lancador($this->empresa, '2026-09');
        preencherTudo(todosAgregados($c));
        avancarAte($c, 6);
        $c->call('aceitarAlertas')->call('fechar');

        expect(DrmLancamento::query()->count())->toBe(0)->and(eventosDrm('drm_lancamento_concluido'))->toBe([]);
        $c->assertSet('erro_geral', 'Algo deu errado do nosso lado. Já fomos notificados. Se continuar, fale com o suporte.');
        EventoProduto::flushEventListeners();
    });
});

describe('CA-2 — atrito: iniciado, blocos, duração e abandono', function () {
    it('iniciado nasce na primeira gravação do rascunho, uma vez; cada bloco concluído uma vez', function () {
        $c = lancador($this->empresa, '2026-09');
        todosAgregados($c);
        preencherTudo($c);
        expect(eventosDrm('drm_lancamento_iniciado'))->toBe([]); // abrir não é iniciar

        $c->call('proximo');
        Carbon::setTestNow('2026-11-05 10:05:00');
        $c->call('proximo')->call('voltar')->call('proximo'); // 2 → 3, volta, 2 → 3 de novo

        $iniciado = eventosDrm('drm_lancamento_iniciado');
        $blocos = collect(eventosDrm('drm_bloco_concluido'))->map(fn ($e) => propsDo($e)['bloco'])->all();

        expect($iniciado)->toHaveCount(1)
            ->and(array_keys(propsDo($iniciado[0])))->toEqualCanonicalizing(['rascunho_id', 'competencia', 'setor', 'origem', 'herdou_valores'])
            ->and(propsDo($iniciado[0]))->toMatchArray(['competencia' => '2026-09', 'setor' => 'comercio', 'origem' => 'inicial', 'herdou_valores' => false])
            ->and($blocos)->toBe([1, 2]); // o último bloco concluído é o ponto de abandono
        expect(array_keys(propsDo(eventosDrm('drm_bloco_concluido')[0])))->toEqualCanonicalizing(['rascunho_id', 'competencia', 'setor', 'origem', 'bloco']);
    });

    it('a duração vai do primeiro salvamento ao fechamento', function () {
        $c = lancador($this->empresa, '2026-09');
        preencherTudo(todosAgregados($c));
        $c->call('proximo'); // cria o rascunho às 10:00:00
        Carbon::setTestNow('2026-11-05 10:07:30');
        avancarAte($c, 6);
        $c->call('aceitarAlertas')->call('fechar');

        expect(propsDo(eventosDrm('drm_lancamento_concluido')[0])['duracao_seg'])->toBe(450);
    });

    it('conferir tudo registra só o bloco 1, o único validado', function () {
        $c = lancador($this->empresa, '2026-09');
        preencherTudo(todosAgregados($c));
        $c->call('conferirTudo');

        expect(collect(eventosDrm('drm_bloco_concluido'))->map(fn ($e) => propsDo($e)['bloco'])->all())->toBe([1]);
    });
});

describe('CA-4 — uso dos mecanismos anti-atrito', function () {
    it('conta grupos agregados e detalhados', function () {
        $c = lancador($this->empresa, '2026-09');
        $c->call('alternarModo', 'custo', 'agregado')->call('alternarModo', 'financeiras', 'agregado');
        preencherTudo($c);
        avancarAte($c, 6);
        $c->call('aceitarAlertas')->call('fechar');

        expect(propsDo(eventosDrm('drm_lancamento_concluido')[0]))->toMatchArray([
            'grupos_agregados' => ['custo', 'financeiras'],
            'grupos_detalhados' => ['vendas', 'administrativas'],
        ]);
    });

    it('mede o pré-preenchimento aceito sem alteração', function () {
        lancarMesPeloFormulario($this->empresa, '2026-08');
        $c = lancador($this->empresa, '2026-09'); // semeado com agosto
        preencherTudo($c, ['D02' => '700,00']);   // muda um campo herdado
        $herdados = count($c->get('repetidos'));
        avancarAte($c, 6);
        $c->call('aceitarAlertas')->call('fechar');

        $props = propsDo(eventosDrm('drm_lancamento_concluido')[1]);
        expect($props['campos_herdados'])->toBeGreaterThan(0)
            ->and($props['campos_herdados'])->toBeGreaterThanOrEqual($herdados)
            ->and($props['campos_herdados_aceitos'])->toBe($props['campos_herdados'] - 1);
        expect(propsDo(eventosDrm('drm_lancamento_iniciado')[1])['herdou_valores'])->toBeTrue();
    });

    it('sem mês anterior não há herança (0 e 0)', function () {
        lancarMesPeloFormulario($this->empresa, '2026-09');

        expect(propsDo(eventosDrm('drm_lancamento_concluido')[0]))->toMatchArray(['campos_herdados' => 0, 'campos_herdados_aceitos' => 0]);
    });

    it('rascunho retomado depois de uma pausa: um evento por rascunho, e o fechamento sabe', function () {
        $c = lancador($this->empresa, '2026-09');
        preencherTudo(todosAgregados($c));
        $c->call('proximo')->call('proximo'); // salvo às 10:00 no bloco 3
        $rascunho = DrmRascunho::query()->firstOrFail();

        Carbon::setTestNow('2026-11-05 10:10:00'); // 10 min: recarregar a página não é voltar
        lancador($this->empresa, '2026-09');
        expect(eventosDrm('drm_rascunho_retomado'))->toBe([]);

        Carbon::setTestNow('2026-11-06 10:00:00'); // voltou no dia seguinte
        $volta = lancador($this->empresa, '2026-09');
        lancador($this->empresa, '2026-09'); // outra abertura: mesmo rascunho, nenhum evento novo
        $retomado = eventosDrm('drm_rascunho_retomado');

        expect($retomado)->toHaveCount(1)
            ->and(array_keys(propsDo($retomado[0])))->toEqualCanonicalizing(['rascunho_id', 'competencia', 'setor', 'origem', 'bloco', 'dias_parado'])
            ->and(propsDo($retomado[0]))->toMatchArray(['rascunho_id' => $rascunho->id, 'bloco' => 3, 'dias_parado' => 1]);

        avancarAte($volta, 6);
        $volta->call('aceitarAlertas')->call('fechar');
        expect(propsDo(eventosDrm('drm_lancamento_concluido')[0]))->toMatchArray(['rascunho_retomado' => true, 'rascunho_id' => $rascunho->id]);
    });

    it('rascunho nunca retomado fecha com rascunho_retomado = false', function () {
        lancarMesPeloFormulario($this->empresa, '2026-09');

        expect(propsDo(eventosDrm('drm_lancamento_concluido')[0])['rascunho_retomado'])->toBeFalse();
    });
});

describe('CA-5 — qualidade do dado: retificação, alerta aceito e regra bloqueante', function () {
    it('retificar emite drm_lancamento_retificado com o que mudou, na mesma transação', function () {
        lancarMesPeloFormulario($this->empresa, '2026-09');
        Carbon::setTestNow('2026-11-09 10:00:00');

        $c = Livewire::test(Lancamento::class, ['empresa' => $this->empresa, 'competencia' => '2026-09']);
        $c->set('valores.D02', '777,00');
        avancarAte($c, 6);
        $c->call('aceitarAlertas')->call('fechar');

        $e = eventosDrm('drm_lancamento_retificado');
        expect($e)->toHaveCount(1)
            ->and(array_keys(propsDo($e[0])))->toEqualCanonicalizing(['lancamento_id', 'competencia', 'setor', 'versao', 'campos_alterados', 'dias_desde_lancamento'])
            ->and(propsDo($e[0]))->toMatchArray(['versao' => 2, 'campos_alterados' => 1, 'dias_desde_lancamento' => 4, 'competencia' => '2026-09'])
            ->and(propsDo(eventosDrm('drm_lancamento_iniciado')[1])['origem'])->toBe('retificacao');
        expect(eventosDrm('drm_lancamento_concluido'))->toHaveCount(1); // a correção não é um novo lançamento
    });

    it('retificar fora da tela também emite (a ação é a fonte), e sem mudança não emite', function () {
        $v1 = DrmLancamento::factory()->create(['empresa_analisada_id' => $this->empresa->id, 'competencia' => '2026-09-01']);
        $mesmo = LancamentoDominio::deTexto(Setor::Comercio, array_fill_keys(array_map(fn (Grupo $g) => $g->value, Grupo::cases()), Modo::Agregado), [
            'D01' => '84.300,00', 'D02' => '5.058,00', 'D03' => '41.200,00', 'D08' => '9.000,00', 'D15' => '20.702,00', 'D37' => '1.100,00', 'D40' => '4.000,00',
        ]);
        expect(fn () => app(RetificarLancamento::class)->execute($this->empresa, Competencia::deTexto('2026-09'), $mesmo, [], 1))
            ->toThrow(RetificacaoSemMudanca::class);
        expect(eventosDrm('drm_lancamento_retificado'))->toBe([]);

        $outro = LancamentoDominio::deTexto(Setor::Comercio, $mesmo->modos, ['D01' => '90.000,00', 'D02' => '5.058,00', 'D03' => '41.200,00', 'D08' => '9.000,00', 'D15' => '20.702,00', 'D37' => '1.100,00', 'D40' => '4.000,00']);
        app(RetificarLancamento::class)->execute($this->empresa, Competencia::deTexto('2026-09'), $outro, [], 1);

        expect(eventosDrm('drm_lancamento_retificado'))->toHaveCount(1);
        expect($v1->fresh()->versao)->toBe(1);
    });

    it('alerta aceito: um evento por regra, com a contagem de ocorrências', function () {
        $c = lancador($this->empresa, '2026-09');
        preencherTudo(todosAgregados($c), ['D40' => '0']); // A5: sem retirada
        avancarAte($c, 6);
        $c->call('aceitarAlertas')->call('fechar');

        $e = eventosDrm('drm_alerta_aceito');
        expect($e)->toHaveCount(1)
            ->and(array_keys(propsDo($e[0])))->toEqualCanonicalizing(['lancamento_id', 'competencia', 'setor', 'origem', 'regra', 'ocorrencias'])
            ->and(propsDo($e[0]))->toMatchArray(['regra' => 'A5', 'ocorrencias' => 1, 'origem' => 'inicial', 'competencia' => '2026-09'])
            ->and(propsDo(eventosDrm('drm_lancamento_concluido')[0])['qtd_alertas_aceitos'])->toBe(1);
    });

    it('regra bloqueante: uma vez por regra e campo em cada formulário, por mais que se tente', function () {
        $c = lancador($this->empresa, '2026-09');
        $c->set('valores.D01', '100,00')->set('valores.D02', '900,00'); // B4: impostos acima da receita
        $c->call('proximo')->call('proximo')->call('proximo');

        $e = eventosDrm('drm_regra_bloqueante_acionada');
        expect($e)->toHaveCount(1)
            ->and(array_keys(propsDo($e[0])))->toEqualCanonicalizing(['regra', 'campo', 'competencia', 'setor', 'origem', 'bloco'])
            ->and(propsDo($e[0]))->toMatchArray(['regra' => 'B4', 'campo' => 'D02', 'bloco' => 1, 'origem' => 'inicial']);

        // Outro formulário aberto depois é outro fato.
        $outro = lancador($this->empresa, '2026-09');
        $outro->set('valores.D01', '100,00')->set('valores.D02', '900,00')->call('proximo');
        expect(eventosDrm('drm_regra_bloqueante_acionada'))->toHaveCount(2);
    });

    it('B2 (mês que não terminou) e B1 (mês já lançado) também são registradas', function () {
        lancador($this->empresa, '2026-11')->set('valores.D01', '100,00')->set('valores.D02', '10,00')->call('proximo');
        lancarMesPeloFormulario($this->empresa, '2026-09');
        lancador($this->empresa, '2026-09')->call('proximo');

        $regras = collect(eventosDrm('drm_regra_bloqueante_acionada'))->map(fn ($e) => propsDo($e)['regra'])->all();
        expect($regras)->toContain('B2')->toContain('B1');
    });

    it('erro de preenchimento comum (campo vazio) não é regra bloqueante', function () {
        lancador($this->empresa, '2026-09')->call('proximo');

        expect(eventosDrm('drm_regra_bloqueante_acionada'))->toBe([]);
    });
});

describe('CA-6 — exportação e leitura', function () {
    beforeEach(function () {
        $this->mes = DrmLancamento::factory()->create(['empresa_analisada_id' => $this->empresa->id, 'competencia' => '2026-09-01']);
    });

    it('abrir a Demonstração emite drm_demonstracao_visualizada', function () {
        Livewire::test(Demonstracao::class, ['empresa' => $this->empresa, 'competencia' => '2026-09']);

        $e = eventosDrm('drm_demonstracao_visualizada');
        expect($e)->toHaveCount(1)
            ->and(array_keys(propsDo($e[0])))->toEqualCanonicalizing(['lancamento_id', 'competencia', 'setor', 'posicao', 'retificado'])
            ->and(propsDo($e[0]))->toMatchArray(['lancamento_id' => $this->mes->id, 'posicao' => 'fora', 'retificado' => false]);
    });

    it('baixar a planilha emite drm_exportado formato xlsx; imprimir emite formato impressao', function () {
        $this->get(route('drm.exportar', [$this->empresa, '2026-09']))->assertOk()->streamedContent();
        Livewire::test(Demonstracao::class, ['empresa' => $this->empresa, 'competencia' => '2026-09'])->call('registrarImpressao');

        $formatos = collect(eventosDrm('drm_exportado'))->map(fn ($e) => propsDo($e)['formato'])->all();
        expect($formatos)->toBe(['xlsx', 'impressao'])
            ->and(array_keys(propsDo(eventosDrm('drm_exportado')[0])))->toEqualCanonicalizing(['lancamento_id', 'competencia', 'setor', 'formato', 'retificado']);
    });

    it('reenviar o mesmo pedido (mesma requisição) não duplica o evento de exportação', function () {
        // Num teste todas as chamadas compartilham o request_id: é exatamente o "reenvio" do mesmo fato.
        $c = Livewire::test(Demonstracao::class, ['empresa' => $this->empresa, 'competencia' => '2026-09']);
        $c->call('registrarImpressao')->call('registrarImpressao');
        EventosDrm::exportado($this->mes, $this->empresa, 'impressao');

        expect(eventosDrm('drm_exportado'))->toHaveCount(1);
    });

    it('empresa alheia não gera evento nenhum (404 antes de qualquer coisa)', function () {
        $alheia = EmpresaAnalisada::factory()->create();
        DrmLancamento::factory()->create(['empresa_analisada_id' => $alheia->id, 'competencia' => '2026-09-01']);

        $this->get(route('drm.exportar', [$alheia, '2026-09']))->assertNotFound();

        expect(eventosDrm())->toBe([]);
    });
});

describe('STORY-084 CA-7 — acesso do plano e eventos', function () {
    function ligarCobrancaSemPlano(): void
    {
        TrialConfig::query()->firstOrCreate([], ['ativo' => true, 'dias' => 7, 'analises' => 1]);
        CobrancaConfig::atual()->update(['ativa' => true]);
    }

    it('tentativa bloqueada na entrada não gera evento algum', function () {
        ligarCobrancaSemPlano();

        $this->get(route('drm.lancar', $this->empresa))->assertOk()->assertSee('O Raio X do Negócio faz parte dos planos');
        DrmLancamento::factory()->create(['empresa_analisada_id' => $this->empresa->id, 'competencia' => '2026-09-01']);
        $this->get(route('drm.corrigir', [$this->empresa, '2026-09']))->assertOk()->assertSee('faz parte dos planos');

        expect(eventosDrm())->toBe([]);
    });

    it('perdeu o acesso com a página aberta: fechar não grava nem emite lançamento concluído', function () {
        $c = lancador($this->empresa, '2026-09');
        preencherTudo(todosAgregados($c));
        avancarAte($c, 6);
        $c->call('aceitarAlertas');
        $antes = count(eventosDrm());

        ligarCobrancaSemPlano(); // o plano acabou enquanto a tela estava aberta
        $c->call('fechar');

        expect(DrmLancamento::query()->count())->toBe(0)
            ->and(eventosDrm('drm_lancamento_concluido'))->toBe([])
            ->and(eventosDrm('drm_alerta_aceito'))->toBe([])
            ->and(count(eventosDrm()))->toBe($antes);
    });

    it('perdeu o acesso: avançar de bloco e bater em regra bloqueante também não geram evento', function () {
        $c = lancador($this->empresa, '2026-09');
        preencherTudo(todosAgregados($c));
        ligarCobrancaSemPlano();

        $c->call('proximo');
        $c->set('valores.D01', '100,00')->set('valores.D02', '900,00')->call('proximo');

        expect(eventosDrm())->toBe([]);
    });

    it('com plano ativo os eventos fluem normalmente', function () {
        ligarCobrancaSemPlano();
        app(Carteira::class)->concederCota($this->usuario->id, 3, now()->subDays(2), now()->addDays(28));

        lancarMesPeloFormulario($this->empresa, '2026-09');

        expect(eventosDrm('drm_lancamento_concluido'))->toHaveCount(1);
    });

    it('sem plano, ler e exportar o histórico continuam gerando os eventos de leitura (STORY-084 CA-3)', function () {
        DrmLancamento::factory()->create(['empresa_analisada_id' => $this->empresa->id, 'competencia' => '2026-09-01']);
        ligarCobrancaSemPlano();

        Livewire::test(Demonstracao::class, ['empresa' => $this->empresa, 'competencia' => '2026-09']);
        $this->get(route('drm.exportar', [$this->empresa, '2026-09']))->assertOk()->streamedContent();

        expect(eventosDrm('drm_demonstracao_visualizada'))->toHaveCount(1)->and(eventosDrm('drm_exportado'))->toHaveCount(1);
    });
});

describe('CA-7, CA-9 — sem dado pessoal e canal único', function () {
    it('nenhum evento de um fluxo completo carrega valor, nome ou documento', function () {
        $c = lancador($this->empresa, '2026-09');
        preencherTudo(todosAgregados($c), ['D01' => '12.345,67', 'D40' => '0']);
        avancarAte($c, 6);
        $c->call('aceitarAlertas')->call('fechar');
        $this->get(route('drm.exportar', [$this->empresa, '2026-09']))->assertOk()->streamedContent();

        $eventos = eventosDrm();
        expect(count($eventos))->toBeGreaterThan(8);
        foreach ($eventos as $e) {
            $json = json_encode(propsDo($e), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            expect($json)->not->toContain('12345')->not->toContain('12.345')->not->toContain('1234567')
                ->not->toContain($this->empresa->razao_social)->not->toContain('Padaria do Bairro')
                ->not->toContain($this->usuario->email)->not->toContain((string) $this->empresa->documento);
            foreach (array_keys(propsDo($e)) as $chave) {
                expect($chave)->not->toMatch('/^(faturamento|balanco|receita|custo)(_.*)?$/')
                    ->not->toBeIn(['cpf', 'cnpj', 'email', 'telefone', 'nome_completo', 'endereco']);
            }
        }
    });

    it('o EventLogger continua barrando valor financeiro em tempo de execução', function () {
        expect(fn () => EventLogger::emitOnce('k', 'drm_x', ['receita_mensal' => 100]))->toThrow(PiiEmEventoException::class)
            ->and(fn () => EventLogger::emitOnce('k', 'drm_x', ['grupos' => ['custo' => 'agregado']]))->toThrow(PiiEmEventoException::class);
    });

    it('todo evento de lançamento tem o setor (CA-3) e nenhum fica sem usuário e empresa', function () {
        $industria = empresaDoSetor('industria', $this->usuario);
        lancarMesPeloFormulario($this->empresa, '2026-09');
        $c = lancador($industria, '2026-09');
        preencherTudo(todosAgregados($c), ['D03' => '3.000,00']);
        avancarAte($c, 6);
        $c->call('aceitarAlertas')->call('fechar');

        foreach (eventosDrm() as $e) {
            expect(propsDo($e)['setor'] ?? null)->toBeIn(['comercio', 'industria', 'servicos'])
                ->and($e->usuario_id)->toBe($this->usuario->id)->and($e->empresa_id)->not->toBeNull();
        }
        expect(collect(eventosDrm('drm_lancamento_concluido'))->map(fn ($e) => propsDo($e)['setor'])->sort()->values()->all())->toBe(['comercio', 'industria']);
    });
});

describe('EventLogger::emitOnce — idempotência do canal (CA-8)', function () {
    it('o mesmo fato grava uma vez; o mesmo fato em outro evento é outro registro', function () {
        $primeiro = EventLogger::emitOnce('fato:1', 'drm_a', ['x' => 1], $this->usuario->id);
        $repetido = EventLogger::emitOnce('fato:1', 'drm_a', ['x' => 2], $this->usuario->id);
        $outroNome = EventLogger::emitOnce('fato:1', 'drm_b', ['x' => 1], $this->usuario->id);

        expect($primeiro)->not->toBeNull()->and($repetido)->toBeNull()->and($outroNome)->not->toBeNull()
            ->and(EventoProduto::query()->where('nome_evento', 'drm_a')->count())->toBe(1)
            ->and((array) EventoProduto::query()->where('nome_evento', 'drm_a')->first()->propriedades)->toMatchArray(['x' => 1]);
    });

    it('o índice único recusa a duplicata no banco, e a corrida contra a consulta prévia não derruba a transação de fora', function () {
        $linha = fn () => DB::table('evento_produto')->insert([
            'evento_id' => (string) Str::uuid7(), 'nome_evento' => 'drm_corrida', 'chave_idempotencia' => 'k', 'propriedades' => '{}',
        ]);
        expect(fn () => DB::transaction(function () use ($linha) {
            $linha();
            $linha();
        }))->toThrow(UniqueConstraintViolationException::class);

        // O concorrente grava entre a consulta prévia e o INSERT: o INSERT falha, emitOnce devolve null e a transação segue.
        EventoProduto::creating(function (EventoProduto $e) use ($linha) {
            if ($e->nome_evento === 'drm_corrida') {
                $linha();
            }
        });

        $resultado = DB::transaction(function () {
            $r = EventLogger::emitOnce('k', 'drm_corrida');
            DB::select('select 1'); // a transação de fora segue utilizável

            return $r;
        });

        expect($resultado)->toBeNull();
        EventoProduto::flushEventListeners();
    });

    it('evento sem chave segue como antes: emit() não é idempotente', function () {
        EventLogger::emit('drm_livre');
        EventLogger::emit('drm_livre');

        expect(EventoProduto::query()->where('nome_evento', 'drm_livre')->count())->toBe(2);
    });
});

describe('DoD — as três métricas do épico são calculáveis a partir dos eventos', function () {
    it('recorrência, atrito e alcance saem de consultas sobre evento_produto', function () {
        $servicos = empresaDoSetor('servicos', $this->usuario);

        // Comércio: 2 lançamentos em 15 dias. Serviços: 1 lançamento, e outro mês iniciado e abandonado no bloco 2.
        lancarMesPeloFormulario($this->empresa, '2026-08');
        Carbon::setTestNow('2026-11-20 10:00:00');
        lancarMesPeloFormulario($this->empresa, '2026-09');
        lancarMesPeloFormulario($servicos, '2026-09', ['D03' => '3.000,00']);
        $abandono = lancador($servicos, '2026-08');
        preencherTudo(todosAgregados($abandono), ['D03' => '3.000,00']);
        $abandono->call('proximo')->call('proximo');

        // Recorrência: empresas com 2º lançamento a ≤ 45 dias do 1º / empresas com 1º lançamento.
        $recorrencia = DB::selectOne("
            select count(*) filter (where segundo.empresa_id is not null) as com_segundo, count(*) as com_primeiro
            from evento_produto primeiro
            left join evento_produto segundo
              on segundo.empresa_id = primeiro.empresa_id and segundo.nome_evento = 'drm_lancamento_concluido'
             and (segundo.propriedades->>'numero_lancamento')::int = 2
             and (segundo.propriedades->>'dias_desde_anterior')::int <= 45
            where primeiro.nome_evento = 'drm_lancamento_concluido' and (primeiro.propriedades->>'numero_lancamento')::int = 1");
        // Distribuição de lançamentos por empresa.
        $distribuicao = collect(DB::select("
            select max((propriedades->>'numero_lancamento')::int) as lancamentos, count(*) as empresas
            from (select empresa_id, propriedades from evento_produto where nome_evento = 'drm_lancamento_concluido') t
            group by empresa_id"))->pluck('lancamentos')->sort()->values()->all();
        // Atrito: concluídos / iniciados; ponto de abandono = último bloco concluído do rascunho sem conclusão.
        $atrito = DB::selectOne("
            select count(distinct i.propriedades->>'rascunho_id') as iniciados,
                   count(distinct c.propriedades->>'rascunho_id') as concluidos
            from evento_produto i
            left join evento_produto c on c.nome_evento = 'drm_lancamento_concluido' and c.propriedades->>'rascunho_id' = i.propriedades->>'rascunho_id'
            where i.nome_evento = 'drm_lancamento_iniciado' and i.propriedades->>'origem' = 'inicial'");
        $abandonos = DB::select("
            select max((b.propriedades->>'bloco')::int) as ultimo_bloco
            from evento_produto i
            join evento_produto b on b.nome_evento = 'drm_bloco_concluido' and b.propriedades->>'rascunho_id' = i.propriedades->>'rascunho_id'
            where i.nome_evento = 'drm_lancamento_iniciado'
              and not exists (select 1 from evento_produto c where c.nome_evento = 'drm_lancamento_concluido' and c.propriedades->>'rascunho_id' = i.propriedades->>'rascunho_id')
            group by i.propriedades->>'rascunho_id'");
        // Alcance: participação por setor.
        $porSetor = collect(DB::select("select propriedades->>'setor' as setor, count(*) as n from evento_produto where nome_evento = 'drm_lancamento_concluido' group by 1"))->pluck('n', 'setor')->all();

        expect((int) $recorrencia->com_segundo)->toBe(1)->and((int) $recorrencia->com_primeiro)->toBe(2)
            ->and($distribuicao)->toBe([1, 2])
            ->and((int) $atrito->iniciados)->toBe(4)->and((int) $atrito->concluidos)->toBe(3)
            ->and(array_map(fn ($a) => (int) $a->ultimo_bloco, $abandonos))->toBe([2])
            ->and(array_map('intval', $porSetor))->toBe(['comercio' => 2, 'servicos' => 1]);
    });
});

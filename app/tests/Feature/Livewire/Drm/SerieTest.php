<?php

declare(strict_types=1);

use App\Actions\Drm\RetificarLancamento;
use App\Domain\Drm\Competencia;
use App\Livewire\Drm\Demonstracao;
use App\Livewire\Drm\Serie;
use App\Models\DrmLancamento;
use App\Models\DrmRascunho;
use App\Models\EmpresaAnalisada;
use App\Models\Usuario;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/*
|-------------------------------------------------------------------------
| STORY-051 — Série histórica: estados, ausência ≠ zero, comparativos e retificação.
|
| "Hoje" é 2026-11-05: o último mês fechado é outubro/2026. Cada mês é gravado pela factory
| (o snapshot é o que o motor produz) a partir de um lançamento real.
|-------------------------------------------------------------------------
*/

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-11-05 10:00:00');
    Carbon::setTestNow('2026-11-05 10:00:00');
    $this->usuario = Usuario::factory()->create();
    $this->actingAs($this->usuario);
    $this->empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $this->usuario->id, 'cnae' => '4711301', 'nome_fantasia' => 'Padaria do Bairro']);
});

afterEach(function () {
    CarbonImmutable::setTestNow();
    Carbon::setTestNow();
});

function serieAbrir(EmpresaAnalisada $empresa)
{
    return Livewire::test(Serie::class, ['empresa' => $empresa]);
}

/** O texto visível de um trecho marcado com `dusk`, sem etiquetas e com espaços normalizados. */
function serieTexto($teste, string $dusk): string
{
    preg_match('/dusk="'.preg_quote($dusk, '/').'"[^>]*>(.*?)<\/(?:p|td|li|span|div)>/s', preg_replace('/<!--.*?-->/s', '', $teste->html()), $m);

    return trim((string) preg_replace('/\s+/', ' ', strip_tags($m[1] ?? '')));
}

describe('rota e escopo', function () {
    it('abre para o dono da empresa', function () {
        $this->get(route('drm.serie', $this->empresa))
            ->assertOk()
            ->assertSee('Todos os meses')
            ->assertSee('Padaria do Bairro · Comércio')
            ->assertSeeLivewire(Serie::class);
    });

    it('devolve 404 silente para a empresa de outro usuário (IDR-009)', function () {
        $alheia = EmpresaAnalisada::factory()->create(['usuario_id' => Usuario::factory()->create()->id]);

        $this->get(route('drm.serie', $alheia))->assertNotFound();
    });

    it('exige login', function () {
        auth()->logout();

        $this->get(route('drm.serie', $this->empresa))->assertRedirect(route('login'));
    });

    it('não vaza os meses de outra empresa do mesmo usuário', function () {
        $outra = EmpresaAnalisada::factory()->create(['usuario_id' => $this->usuario->id, 'cnae' => '4711301']);
        serieMes($outra, '2026-10');

        serieAbrir($this->empresa)->assertSeeHtml('dusk="drm-serie-vazio"')->assertDontSeeHtml('drm-serie-competencia-2026-10');
    });
});

describe('CA-13 — série vazia, 1, 3, 4 e 13 competências', function () {
    it('vazia: promessa do primeiro lançamento e o CTA para o mês fechado mais recente', function () {
        $c = serieAbrir($this->empresa);

        $c->assertSeeHtml('dusk="drm-serie-vazio"')
            ->assertSee('Você ainda não lançou nenhum mês para esta empresa.')
            ->assertSee('Do segundo em diante, a maior parte já vem preenchida.')
            ->assertSee('Lançar outubro/2026')
            ->assertSeeHtml(route('drm.lancar', [$this->empresa, 'competencia' => '2026-10']))
            ->assertDontSeeHtml('dusk="drm-serie-lacunas"');
    });

    it('com 1 competência: nada a comparar, e dito em palavras', function () {
        serieMes($this->empresa, '2026-10');

        $c = serieAbrir($this->empresa);

        expect(serieTexto($c, 'drm-serie-vs-anterior-2026-10'))->toBe('primeiro mês lançado')
            ->and(serieTexto($c, 'drm-serie-vs-media-2026-10'))->toBe('média disponível a partir do 4º mês lançado');
        $c->assertSee('R$ 1.000,00')
            ->assertDontSee('em relação')   // nenhuma variação inventada: nem 0%, nem N/A
            ->assertDontSeeHtml('dusk="drm-serie-lancar"');   // o mês fechado mais recente já está lançado: nada em aberto
    });

    it('com 3 competências: a variação sobre o mês anterior existe, a média ainda não', function () {
        serieMes($this->empresa, '2026-08', '2.900,00');   // lucro 2.000,00
        serieMes($this->empresa, '2026-09', '3.400,00');   // 1.500,00
        serieMes($this->empresa, '2026-10', '3.150,00');   // 1.750,00

        $c = serieAbrir($this->empresa);

        expect(serieTexto($c, 'drm-serie-vs-anterior-2026-09'))->toBe('−25,0% em relação a agosto')
            ->and(serieTexto($c, 'drm-serie-vs-anterior-2026-10'))->toBe('+16,7% em relação a setembro')
            ->and(serieTexto($c, 'drm-serie-vs-media-2026-10'))->toBe('média disponível a partir do 4º mês lançado');
    });

    it('com 4 competências: a média dos três anteriores aparece no 4º', function () {
        serieMes($this->empresa, '2026-07', '2.900,00');   // 2.000
        serieMes($this->empresa, '2026-08', '3.400,00');   // 1.500
        serieMes($this->empresa, '2026-09', '3.400,00');   // 1.500
        serieMes($this->empresa, '2026-10', '2.400,00');   // 2.500

        $c = serieAbrir($this->empresa);

        // média (2.000 + 1.500 + 1.500) / 3 = 1.666,67; (2.500 − 1.666,67) / 1.666,67 = +50,0%
        expect(serieTexto($c, 'drm-serie-vs-media-2026-10'))->toBe('+50,0% em relação à média dos meses anteriores')
            ->and(serieTexto($c, 'drm-serie-vs-media-2026-09'))->toBe('média disponível a partir do 4º mês lançado');
    });

    it('com 13 competências: agrupa por ano, mais recente primeiro, e a média olha só 12 meses', function () {
        // out/2025 (lucro 4.900) ... out/2026 (lucro 1.000); out/2025 está a 12 meses de out/2026: ainda dentro da janela.
        $c = Competencia::deTexto('2025-10');
        for ($i = 0; $i < 13; $i++) {
            serieMes($this->empresa, $c->texto(), $i === 0 ? '0,00' : '3.900,00');
            $c = Competencia::deData($c->data->addMonthNoOverflow());
        }

        $t = serieAbrir($this->empresa);
        $html = preg_replace('/<!--.*?-->/s', '', $t->html());

        expect(substr_count($html, 'dusk="drm-serie-competencia-'))->toBe(13)
            ->and(strpos($html, 'drm-serie-ano-2026'))->toBeLessThan(strpos($html, 'drm-serie-ano-2025'));
        // Rótulos: outubro no ano corrente sem ano; dezembro/2025 com ano.
        $t->assertSee('dezembro/2025')->assertSee('2026');
        // 12 anteriores a out/2026: out/2025 (4.900) + 11 meses de 1.000 → média 1.325; out/2026 (1.000) fica abaixo.
        expect(serieTexto($t, 'drm-serie-vs-media-2026-10'))->not->toBe('+0,0% em relação à média dos meses anteriores')
            ->and(serieTexto($t, 'drm-serie-vs-media-2026-10'))->toStartWith('−');
    });
});

describe('CA-1/CA-2 — estados distintos e ausência ≠ zero', function () {
    it('mês sem lançamento é uma linha de ausência: sem valor, sem comparativo, com ação de lançar', function () {
        serieMes($this->empresa, '2026-08');
        serieMes($this->empresa, '2026-10');

        $c = serieAbrir($this->empresa);

        expect(serieTexto($c, 'drm-serie-ausencia-2026-09'))->toBe('Sem lançamento para este mês.')
            ->and(serieTexto($c, 'drm-serie-estado-2026-09'))->toBe('não lançado');
        $c->assertSeeHtml('dusk="drm-serie-acao-2026-09"')
            ->assertSee('Lançar setembro de 2026')
            ->assertSeeHtml(route('drm.lancar', [$this->empresa, 'competencia' => '2026-09']))
            ->assertDontSeeHtml('dusk="drm-serie-valor-2026-09"')
            ->assertDontSeeHtml('dusk="drm-serie-vs-anterior-2026-09"');
        // O mês seguinte à lacuna NÃO se compara com um mês zerado.
        expect(serieTexto($c, 'drm-serie-vs-anterior-2026-10'))->toBe('setembro não foi lançado, então não dá para comparar');
    });

    it('a lacuna nunca vira "R$ 0,00": zero só aparece onde o mês foi lançado e fechou em zero', function () {
        serieMes($this->empresa, '2026-08', '4.900,00');   // lucro exatamente zero: 4.900 − 4.900
        serieMes($this->empresa, '2026-10');

        $c = serieAbrir($this->empresa);

        expect(serieTexto($c, 'drm-serie-valor-2026-08'))->toBe('R$ 0,00')
            ->and(serieTexto($c, 'drm-serie-estado-2026-08'))->toBe('lançado')
            ->and(serieTexto($c, 'drm-serie-ausencia-2026-09'))->not->toContain('R$');
    });

    it('um mês lançado depois de um zero real diz que a base é zero, não inventa variação', function () {
        serieMes($this->empresa, '2026-09', '4.900,00');
        serieMes($this->empresa, '2026-10');

        expect(serieTexto(serieAbrir($this->empresa), 'drm-serie-vs-anterior-2026-10'))
            ->toBe('o mês de comparação ficou em zero, então a variação não se aplica');
    });

    it('rascunho em aberto: estado próprio, sem valor, com "você parou em" e a ação de continuar', function () {
        serieMes($this->empresa, '2026-08');
        DrmRascunho::query()->create([
            'usuario_id' => $this->usuario->id, 'empresa_analisada_id' => $this->empresa->id, 'competencia' => '2026-10-01',
            'origem' => 'inicial', 'rascunho_payload' => ['bloco' => 3, 'valores' => []], 'expires_at' => now()->addDays(30),
        ]);

        $c = serieAbrir($this->empresa);

        expect(serieTexto($c, 'drm-serie-estado-2026-10'))->toBe('rascunho')
            ->and(serieTexto($c, 'drm-serie-ausencia-2026-10'))->toBe('Você parou em despesas com vendas.');
        $c->assertSeeHtml('dusk="drm-serie-rascunho-2026-10"')
            ->assertSee('Continuar o rascunho de outubro de 2026')
            ->assertDontSeeHtml('dusk="drm-serie-valor-2026-10"');
    });

    it('rascunho expirado ou de retificação não cria estado de rascunho', function () {
        serieMes($this->empresa, '2026-10');
        DrmRascunho::query()->create([
            'usuario_id' => $this->usuario->id, 'empresa_analisada_id' => $this->empresa->id, 'competencia' => '2026-10-01',
            'origem' => 'retificacao', 'base_versao' => 1, 'rascunho_payload' => ['bloco' => 2], 'expires_at' => now()->addDays(30),
        ]);
        DrmRascunho::query()->create([
            'usuario_id' => $this->usuario->id, 'empresa_analisada_id' => $this->empresa->id, 'competencia' => '2026-08-01',
            'origem' => 'inicial', 'rascunho_payload' => ['bloco' => 2], 'expires_at' => now()->subDay(),
        ]);

        $c = serieAbrir($this->empresa);

        $c->assertDontSeeHtml('dusk="drm-serie-rascunho-2026-10"')->assertDontSeeHtml('drm-serie-competencia-2026-08');
        expect(serieTexto($c, 'drm-serie-estado-2026-10'))->toBe('lançado');
    });

    it('rascunho sem bloco registrado ainda é rascunho', function () {
        DrmRascunho::query()->create([
            'usuario_id' => $this->usuario->id, 'empresa_analisada_id' => $this->empresa->id, 'competencia' => '2026-10-01',
            'origem' => 'inicial', 'rascunho_payload' => [], 'expires_at' => now()->addDays(30),
        ]);

        $c = serieAbrir($this->empresa);

        expect(serieTexto($c, 'drm-serie-ausencia-2026-10'))->toBe('Você começou a lançar este mês.');
        $c->assertDontSeeHtml('dusk="drm-serie-vazio"');
    });

    it('lançamento apagado (deleted_at) conta como ausente, nunca como zero', function () {
        serieMes($this->empresa, '2026-08');
        serieMes($this->empresa, '2026-09')->delete();
        serieMes($this->empresa, '2026-10');

        $c = serieAbrir($this->empresa);

        expect(serieTexto($c, 'drm-serie-estado-2026-09'))->toBe('não lançado')
            ->and(serieTexto($c, 'drm-serie-vs-anterior-2026-10'))->toBe('setembro não foi lançado, então não dá para comparar');
    });

    it('o CTA do topo aponta o mês fechado mais recente sem lançamento', function () {
        serieMes($this->empresa, '2026-08');

        serieAbrir($this->empresa)
            ->assertSee('Lançar outubro/2026')
            ->assertSeeHtml(route('drm.lancar', [$this->empresa, 'competencia' => '2026-10']));
    });

    it('as linhas de ação têm nome acessível completo e o mês lançado é link para a demonstração', function () {
        serieMes($this->empresa, '2026-10');

        serieAbrir($this->empresa)
            ->assertSeeHtml('aria-label="Corrigir outubro de 2026"')
            ->assertSeeHtml('aria-label="Abrir outubro de 2026"')
            ->assertSeeHtml('href="'.route('drm.mes', [$this->empresa, '2026-10']).'"')
            ->assertSeeHtml('href="'.route('drm.corrigir', [$this->empresa, '2026-10']).'"');
    });
});

describe('aviso de lacunas (A6 na série)', function () {
    it('com 2 ou mais meses sem lançamento entre o primeiro e o último, avisa com a contagem', function () {
        serieMes($this->empresa, '2026-05');
        serieMes($this->empresa, '2026-09');   // faltam jun, jul, ago

        serieAbrir($this->empresa)
            ->assertSeeHtml('dusk="drm-serie-lacunas"')
            ->assertSee('Você tem 3 meses sem lançamento entre maio/2026 e setembro/2026.');
    });

    it('com 1 mês em falta, só a linha de ausência — sem aviso', function () {
        serieMes($this->empresa, '2026-08');
        serieMes($this->empresa, '2026-10');

        serieAbrir($this->empresa)->assertDontSeeHtml('dusk="drm-serie-lacunas"');
    });

    it('o rascunho não é lançamento: continua contando como mês sem lançamento', function () {
        serieMes($this->empresa, '2026-06');
        serieMes($this->empresa, '2026-09');
        DrmRascunho::query()->create([
            'usuario_id' => $this->usuario->id, 'empresa_analisada_id' => $this->empresa->id, 'competencia' => '2026-07-01',
            'origem' => 'inicial', 'rascunho_payload' => ['bloco' => 1], 'expires_at' => now()->addDays(30),
        ]);

        serieAbrir($this->empresa)->assertSee('Você tem 2 meses sem lançamento entre junho/2026 e setembro/2026.');
    });
});

describe('CA-5 — mês atípico (A4) legível como explicação', function () {
    /** Três meses de 13º em zero e o quarto com 13º: atípico. Administrativas detalhada para D21 existir. */
    function serieComDecimoTerceiro(EmpresaAnalisada $empresa, string $d21): void
    {
        foreach (['2026-07', '2026-08', '2026-09'] as $mes) {
            serieMes($empresa, $mes, '3.900,00', ['D21' => '0,00'], true);
        }
        serieMes($empresa, '2026-10', '3.900,00', ['D21' => $d21], true);
    }

    it('marca o mês com pill neutro e uma frase de explicação no fluxo de leitura', function () {
        serieComDecimoTerceiro($this->empresa, '5.000,00');

        $c = serieAbrir($this->empresa);

        $c->assertSeeHtml('dusk="drm-serie-atipico-2026-10"')
            ->assertSee('Mês atípico');
        expect(serieTexto($c, 'drm-serie-atipico-explicacao-2026-10'))->toStartWith('Outubro teve décimo-terceiro salário — por isso o resultado do mês caiu.')
            ->and($c->html())->not->toContain('farol');
        $c->assertDontSeeHtml('drm-serie-atipico-2026-09');
    });

    it('o mês atípico ENTRA nos comparativos (CA-7): a variação é calculada, não omitida', function () {
        serieComDecimoTerceiro($this->empresa, '5.000,00');

        // Administrativas só com D21: lucro = 6.900 − 3.900 − D21. Meses normais: 3.000; outubro: −2.000 → (−2.000 − 3.000) / 3.000.
        expect(serieTexto(serieAbrir($this->empresa), 'drm-serie-vs-media-2026-10'))->toBe('−166,7% em relação à média dos meses anteriores');
    });

    it('antes do 4º lançamento a regra não vale (nenhum mês é marcado)', function () {
        foreach (['2026-08', '2026-09'] as $mes) {
            serieMes($this->empresa, $mes, '3.900,00', ['D21' => '0,00'], true);
        }
        serieMes($this->empresa, '2026-10', '3.900,00', ['D21' => '5.000,00'], true);

        serieAbrir($this->empresa)->assertDontSeeHtml('dusk="drm-serie-atipico-');
    });

    it('rubrica dentro do normal não marca nada', function () {
        serieComDecimoTerceiro($this->empresa, '0,00');

        serieAbrir($this->empresa)->assertDontSeeHtml('dusk="drm-serie-atipico-');
    });
});

describe('CA-8 — granularidades mistas convivem na série', function () {
    it('mês agregado e mês detalhado aparecem juntos e o comparativo de nível 1 está sempre disponível', function () {
        serieMes($this->empresa, '2026-09', '3.900,00');                                        // administrativas agregada: lucro 1.000
        serieMes($this->empresa, '2026-10', '3.400,00', ['D21' => '0,00'], true);               // detalhada: 6.900 − 3.400 = 3.500

        $c = serieAbrir($this->empresa);

        expect(serieTexto($c, 'drm-serie-vs-anterior-2026-10'))->toBe('+250,0% em relação a setembro');
        $c->assertSeeHtml('drm-serie-competencia-2026-09')->assertSeeHtml('drm-serie-competencia-2026-10');
    });
});

describe('CA-9 e STORY-050 CA-5 — retificação, na leitura real', function () {
    it('mês retificado é exibido como tal', function () {
        $v1 = serieMes($this->empresa, '2026-10');
        app(RetificarLancamento::class)->execute($this->empresa, Competencia::deTexto('2026-10'), serieLancamento('3.400,00'), [], $v1->versao);

        $c = serieAbrir($this->empresa);

        $c->assertSeeHtml('dusk="drm-serie-retificado-2026-10"')->assertSee('Retificado')->assertSee('corrigido 1×');
        expect(serieTexto($c, 'drm-serie-valor-2026-10'))->toBe('R$ 1.500,00');   // o valor VIGENTE (custo 3.400), não o da versão 1 (3.900)
    });

    it('retificar um mês muda o comparativo do mês seguinte, sem tocar nele', function () {
        $setembro = serieMes($this->empresa, '2026-09', '3.900,00');    // lucro 1.000
        $outubro = serieMes($this->empresa, '2026-10', '3.400,00');     // lucro 1.500: +50,0% sobre setembro
        $hashOutubro = $outubro->payload_hash;
        $demonstracaoOutubro = $outubro->fresh()->demonstracao->getArrayCopy();

        expect(serieTexto(serieAbrir($this->empresa), 'drm-serie-vs-anterior-2026-10'))->toBe('+50,0% em relação a setembro');

        // Retifica SETEMBRO: o lucro cai para 500 (custo 4.400).
        app(RetificarLancamento::class)->execute($this->empresa, Competencia::deTexto('2026-09'), serieLancamento('4.400,00'), [], 1);

        // Leitura real: outubro, que ninguém tocou, agora compara com o setembro retificado: (1.500 − 500) / 500 = +200,0%.
        $c = serieAbrir($this->empresa);
        expect(serieTexto($c, 'drm-serie-vs-anterior-2026-10'))->toBe('+200,0% em relação a setembro')
            ->and(serieTexto($c, 'drm-serie-valor-2026-09'))->toBe('R$ 500,00');

        // E o mesmo na Demonstração do Mês de outubro: o comparativo por linha mudou também.
        $d = Livewire::test(Demonstracao::class, ['empresa' => $this->empresa, 'competencia' => '2026-10']);
        $d->assertSee('+200,0% em relação a setembro');

        // O snapshot de outubro continua byte a byte o mesmo: comparativo nunca é persistido (ADR-007).
        $depois = DrmLancamento::query()->where('competencia', '2026-10-01')->vigente()->firstOrFail();
        expect($depois->id)->toBe($outubro->id)
            ->and($depois->versao)->toBe(1)
            ->and($depois->payload_hash)->toBe($hashOutubro)
            ->and($depois->demonstracao->getArrayCopy())->toBe($demonstracaoOutubro);
    });

    it('a versão substituída nunca entra na série (só snapshots vigentes)', function () {
        $v1 = serieMes($this->empresa, '2026-10', '3.900,00');
        app(RetificarLancamento::class)->execute($this->empresa, Competencia::deTexto('2026-10'), serieLancamento('3.400,00'), [], $v1->versao);

        $c = serieAbrir($this->empresa);

        expect(substr_count(preg_replace('/<!--.*?-->/s', '', $c->html()), 'dusk="drm-serie-competencia-2026-10"'))->toBe(1);
        $c->assertDontSee('R$ 1.000,00');
    });
});

describe('CA-11 — desempenho com 24 competências', function () {
    it('carrega a série em poucas consultas e bem abaixo de 2s', function () {
        $c = Competencia::deTexto('2024-11');
        for ($i = 0; $i < 24; $i++) {
            serieMes($this->empresa, $c->texto(), number_format(3000 + $i * 20, 2, ',', '.'));
            $c = Competencia::deData($c->data->addMonthNoOverflow());
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $inicio = microtime(true);
        $this->get(route('drm.serie', $this->empresa))->assertOk()->assertSee('outubro');
        $segundos = microtime(true) - $inicio;
        $consultas = count(DB::getQueryLog());
        DB::disableQueryLog();

        expect($segundos)->toBeLessThan(2.0)
            ->and($consultas)->toBeLessThan(15);   // não cresce com o número de meses: nada de N+1
    });
});

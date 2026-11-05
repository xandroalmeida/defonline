<?php

declare(strict_types=1);

use App\Actions\CalcularDiagnostico;
use App\Models\Diagnostico;
use App\Models\EmpresaAnalisada;
use App\Models\Usuario;
use App\Support\DataExibicao;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Relatório minimalista `/diagnosticos/{diagnostico}` — STORY-029.
 *   - CA-1 (auth + IDR-009 404 cross-tenant via Global Scope)
 *   - CA-2 (breadcrumb, app shell)
 *   - CA-3 (7 indicadores + valor/farol/mensagem; null = "Indisponível" cinza sem farol)
 *   - CA-4 (NCG abs card separado)
 *   - CA-5 (glossário inline)
 *   - CA-8 (acessibilidade básica)
 *   - CA-9 (rodapé com motor_version / matrix_version / data)
 */
function payloadSaudavel(): array
{
    return [
        'Q01' => 1, 'Q02' => '50000', 'Q03' => '20000', 'Q04' => '10000',
        'Q05' => '300000', 'Q06' => '40000', 'Q07' => '80000', 'Q08' => '50000',
        'Q09' => '100000', 'Q10' => 90, 'Q11' => 15, 'Q12' => 15, 'Q13' => '2',
        'Q14' => '20000', 'Q15' => '8000', 'Q16' => '2000',
    ];
}

function payloadIndisponivel(): array
{
    // Vendas e demais Qs ausentes → 7 indicadores essenciais ficam indisponíveis.
    return [
        'Q01' => 1, 'Q02' => null, 'Q03' => '20000', 'Q04' => '10000',
        'Q05' => null, 'Q06' => null, 'Q07' => null, 'Q08' => null, 'Q09' => null,
        'Q10' => null, 'Q11' => null, 'Q12' => null, 'Q13' => null,
        'Q14' => null, 'Q15' => null, 'Q16' => null,
    ];
}

function gerarDiagnostico(Usuario $dono, ?array $payload = null): Diagnostico
{
    Auth::login($dono);
    $empresa = EmpresaAnalisada::factory()->create([
        'usuario_id' => $dono->id,
        'razao_social' => 'Indústria Teste LTDA',
        'nome_fantasia' => 'Indústria Teste',
    ]);
    $diag = app(CalcularDiagnostico::class)->execute($empresa, $payload ?? payloadSaudavel());
    Auth::logout();

    return $diag;
}

it('exige autenticação (redirect para /login)', function () {
    $dono = Usuario::factory()->create();
    $diag = gerarDiagnostico($dono);

    $this->get("/diagnosticos/{$diag->id}")->assertRedirect('/login');
});

it('renderiza o relatório do próprio Usuário (CA-1, CA-2, CA-3)', function () {
    $dono = Usuario::factory()->create();
    $diag = gerarDiagnostico($dono);

    $response = $this->actingAs($dono)->get("/diagnosticos/{$diag->id}");

    $response->assertOk()
        ->assertSee('Indústria Teste')
        ->assertSee('Minhas Empresas')
        ->assertSee('Versão MVP')
        // 7 indicadores essenciais.
        ->assertSee('Margem Bruta')
        ->assertSee('Margem Líquida')
        ->assertSee('Dívida Líquida / EBITDA')
        ->assertSee('NCG / Vendas')
        ->assertSee('PMR')
        ->assertSee('PMC')
        ->assertSee('Ciclo Financeiro');
});

it('devolve 404 silente (IDR-009) quando outro Usuário tenta acessar', function () {
    $dono = Usuario::factory()->create();
    $intruso = Usuario::factory()->create();
    $diag = gerarDiagnostico($dono);

    $this->actingAs($intruso)
        ->get("/diagnosticos/{$diag->id}")
        ->assertNotFound();
});

it('devolve 404 quando o diagnóstico não existe', function () {
    $usuario = Usuario::factory()->create();

    $this->actingAs($usuario)
        ->get('/diagnosticos/00000000-0000-0000-0000-000000000000')
        ->assertNotFound();
});

it('exibe texto da matriz dez-2025 (Anexo F Indústria) no lugar do placeholder (STORY-032)', function () {
    $dono = Usuario::factory()->create();
    $diag = gerarDiagnostico($dono);

    $response = $this->actingAs($dono)->get("/diagnosticos/{$diag->id}");

    // payloadSaudavel → Margem Bruta 50% → verde → texto F.1 verde da matriz dez-2025.
    $response->assertOk()
        ->assertSee('Buscar manter a margem atual.')
        ->assertDontSee('Faixa verde.')
        ->assertDontSee('Faixa amarela.')
        ->assertDontSee('Faixa vermelha.');
});

it('exibe NCG absoluto em card separado, sem farol verde/amarelo/vermelho (CA-4)', function () {
    $dono = Usuario::factory()->create();
    $diag = gerarDiagnostico($dono);

    $response = $this->actingAs($dono)->get("/diagnosticos/{$diag->id}");

    $response->assertOk()
        ->assertSee('Necessidade de Capital de Giro', escape: false)
        ->assertSee('Indicador informativo')
        ->assertSee('data-codigo="ncg_absoluto"', escape: false);
});

it('exibe glossário inline com termos do Anexo I (CA-5)', function () {
    $dono = Usuario::factory()->create();
    $diag = gerarDiagnostico($dono);

    $response = $this->actingAs($dono)->get("/diagnosticos/{$diag->id}");

    $response->assertOk()
        ->assertSee('Glossário')
        ->assertSee('Necessidade de Capital de Giro (NCG)', escape: false)
        ->assertSee('Ciclo Financeiro (CF)', escape: false)
        ->assertSee('Indisponível')
        ->assertSee('Balanço Adaptado / DRE Adaptada', escape: false);
});

it('exibe rodapé com motor_version, matrix_version e data (CA-9)', function () {
    $dono = Usuario::factory()->create();
    $diag = gerarDiagnostico($dono);

    $response = $this->actingAs($dono)->get("/diagnosticos/{$diag->id}");

    $response->assertOk()
        ->assertSee('motor v'.$diag->motor_version)
        ->assertSee('matriz '.$diag->matrix_version)
        ->assertSee('gerado em '.DataExibicao::dataHora($diag->gerado_em));
});

it('rodapé reflete a versão do snapshot, não a do config atual', function () {
    $dono = Usuario::factory()->create();
    Auth::login($dono);
    $empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $dono->id]);

    // Cria diagnóstico via factory direto (não via Action), simulando registro
    // antigo com motor_version diferente do config atual.
    $diag = Diagnostico::factory()->paraEmpresa($empresa)->create([
        'motor_version' => '0.9.0',
        'matrix_version' => 'jul-2025',
    ]);
    Auth::logout();

    $this->actingAs($dono)
        ->get("/diagnosticos/{$diag->id}")
        ->assertOk()
        ->assertSee('motor v0.9.0')
        ->assertSee('matriz jul-2025');
});

it('renderiza linha cinza "Indisponível" sem farol quando valor é null (CA-3)', function () {
    $dono = Usuario::factory()->create();
    $diag = gerarDiagnostico($dono, payloadIndisponivel());

    $response = $this->actingAs($dono)->get("/diagnosticos/{$diag->id}");

    $response->assertOk()->assertSee('Indisponível');

    // Confirma que pelo menos um indicador essencial veio com valor null.
    $snapshot = (array) $diag->indicadores_calculados;
    $indisponiveis = array_filter(
        ['margem_bruta', 'margem_liquida', 'pmr', 'pmc', 'ciclo_financeiro'],
        fn (string $codigo) => ($snapshot[$codigo]['valor'] ?? null) === null,
    );
    expect($indisponiveis)->not->toBeEmpty();
});

it('contém estrutura semântica básica (<main>, <h1>, breadcrumb com aria) — CA-8', function () {
    $dono = Usuario::factory()->create();
    $diag = gerarDiagnostico($dono);

    $response = $this->actingAs($dono)->get("/diagnosticos/{$diag->id}");

    $html = $response->getContent();
    $response->assertOk();
    expect($html)->toContain('<main');
    expect($html)->toContain('<h1');
    expect($html)->toContain('aria-label="breadcrumb"');
    expect($html)->toContain('aria-label="Farol:');
});

it('botão "Voltar" segue o Referer interno (Selecionar Empresa)', function () {
    $dono = Usuario::factory()->create();
    $diag = gerarDiagnostico($dono);
    $previousUrl = url('/diagnosticos/novo');

    $response = $this->actingAs($dono)
        ->withHeader('referer', $previousUrl)
        ->get("/diagnosticos/{$diag->id}");

    $response->assertOk();
    expect($response->getContent())
        ->toContain('dusk="diagnostico-voltar"')
        ->toContain('href="'.$previousUrl.'"');
});

it('botão "Voltar" segue o Referer interno (Minhas Empresas)', function () {
    $dono = Usuario::factory()->create();
    $diag = gerarDiagnostico($dono);
    $previousUrl = url('/home');

    $response = $this->actingAs($dono)
        ->withHeader('referer', $previousUrl)
        ->get("/diagnosticos/{$diag->id}");

    expect($response->getContent())->toContain('href="'.$previousUrl.'"');
});

it('botão "Voltar" cai em /home quando não há Referer (URL direta/bookmark)', function () {
    $dono = Usuario::factory()->create();
    $diag = gerarDiagnostico($dono);

    $response = $this->actingAs($dono)->get("/diagnosticos/{$diag->id}");

    expect($response->getContent())->toContain('href="'.url('/home').'"');
});

it('botão "Voltar" cai em /home quando Referer aponta para fora do app (anti-open-redirect)', function () {
    $dono = Usuario::factory()->create();
    $diag = gerarDiagnostico($dono);

    $response = $this->actingAs($dono)
        ->withHeader('referer', 'https://atacante.example/phish')
        ->get("/diagnosticos/{$diag->id}");

    $html = $response->getContent();
    expect($html)->toContain('href="'.url('/home').'"');
    expect($html)->not->toContain('atacante.example');
});

it('botão "Voltar" cai em /home quando Referer é a própria página (refresh)', function () {
    $dono = Usuario::factory()->create();
    $diag = gerarDiagnostico($dono);
    $urlAtual = url("/diagnosticos/{$diag->id}");

    $response = $this->actingAs($dono)
        ->withHeader('referer', $urlAtual)
        ->get("/diagnosticos/{$diag->id}");

    expect($response->getContent())->toContain('href="'.url('/home').'"');
});

it('PDR-015 — a tela do relatório não mostra o Resumo Executivo em nenhum caso', function (array $resumo) {
    $dono = Usuario::factory()->create();
    Auth::login($dono);
    $empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $dono->id]);
    $diag = Diagnostico::factory()->paraEmpresa($empresa)->create([
        'motor_version' => '1.2.0',
        'resumo_executivo' => $resumo,
    ]);
    Auth::logout();

    $response = $this->actingAs($dono)->get("/diagnosticos/{$diag->id}");

    $response->assertOk();
    expect($response->getContent())
        ->toContain('data-testid="indicadores-tabela"')
        ->not->toContain('resumo-executivo')
        ->not->toContain('Resumo executivo')
        ->not->toContain('Veredito de teste')
        ->not->toContain('Destaque de teste')
        ->not->toContain('Mensagem de fallback de teste');
})->with([
    'normal' => [[
        'veredito' => 'precisa_atencao',
        'veredito_texto' => 'Veredito de teste',
        'destaques_negativos' => [['codigo' => 'ncg_vendas', 'texto' => 'Destaque de teste']],
        'destaque_positivo' => null,
        'linha_fixa' => 'Linha fixa',
        'fallback_acionado' => false,
    ]],
    'fallback' => [[
        'veredito' => 'fallback',
        'mensagem_fallback' => 'Mensagem de fallback de teste',
        'fallback_acionado' => true,
    ]],
    'legado sem veredito' => [['pendente_story' => 'STORY-031', 'fallback_acionado' => false]],
]);

it('exibe ambos os layouts (tabela em md+ e cards em <md) para mobile-first — CA-6', function () {
    $dono = Usuario::factory()->create();
    $diag = gerarDiagnostico($dono);

    $response = $this->actingAs($dono)->get("/diagnosticos/{$diag->id}");

    $html = $response->getContent();
    $response->assertOk();
    expect($html)->toContain('data-testid="indicadores-tabela"');
    expect($html)->toContain('data-testid="indicadores-cards"');
    // table com thead semântico (CA-8).
    expect($html)->toContain('<th scope="col"');
});

// ============================================================================
// STORY-042 — glossário: ordem dos itens (CA-8) + default vencido da pendência
// ============================================================================

/**
 * Termos do glossário na ordem em que aparecem no DOM.
 *
 * A ordem é o objeto do CA-8, e `assertSee` é cego a ela — passa com o item em
 * qualquer posição. Por isso extraímos os `<summary>` do bloco do glossário e
 * comparamos posições de verdade.
 *
 * @return list<string>
 */
function termosDoGlossario(string $html): array
{
    $glossario = (string) Str::of($html)->after('data-testid="glossario"')->before('</section>');

    preg_match_all('/<summary[^>]*>\s*<span>(.*?)<\/span>/s', $glossario, $m);

    return array_map(fn (string $t): string => html_entity_decode(trim($t), ENT_QUOTES), $m[1]);
}

it('CA-8 — o item do EBITDA é o 2º da lista do glossário', function () {
    $dono = Usuario::factory()->create();
    $diag = gerarDiagnostico($dono);

    $html = $this->actingAs($dono)->get("/diagnosticos/{$diag->id}")->assertOk()->getContent();

    $termos = termosDoGlossario($html);

    expect($termos)->not->toBeEmpty()
        ->and($termos[1])->toContain('EBITDA')
        ->and($termos[1])->toContain('Margem Operacional');
});

it('CA-8 (borda) — reordenar não pode ter mexido no 1º item nem no total de termos', function () {
    // O default vencido da pendência com o Edvan manda **manter** o glossário
    // como está (nenhuma remoção). Uma reordenação feita com corta-e-cola erra
    // exatamente por aqui: perde ou duplica um item.
    $dono = Usuario::factory()->create();
    $diag = gerarDiagnostico($dono);

    $html = $this->actingAs($dono)->get("/diagnosticos/{$diag->id}")->assertOk()->getContent();

    $termos = termosDoGlossario($html);

    expect($termos)->toHaveCount(17)
        ->and($termos[0])->toContain('Margem Bruta')
        ->and(array_unique($termos))->toHaveCount(17);
});

it('o glossário define Despesas Financeiras / EBITDA, Fontes de Recursos, Giro do Ativo, PME e Inadimplência', function (string $termo, string $definicao) {
    $dono = Usuario::factory()->create();
    $diag = gerarDiagnostico($dono);

    $html = $this->actingAs($dono)->get("/diagnosticos/{$diag->id}")->assertOk()->getContent();

    expect(termosDoGlossario($html))->toContain($termo)
        ->and(html_entity_decode($html))->toContain($definicao);
})->with([
    ['Relação Despesas Financeiras / EBITDA', 'Representa a participação dos bancos no lucro.'],
    ['Relação entre Fontes de Recursos (RFR)', 'dependência de recursos externos'],
    ['Giro do Ativo (GA)', 'Vendas ÷ Ativo Total'],
    ['PME — Prazo Médio de Permanência dos Estoques', 'Prazo médio de permanência em estoque, em dias.'],
    ['Inadimplência', 'Percentual médio de atraso de clientes de vendas a prazo.'],
]);

it('a tela do relatório não imprime: só a orientação de usar o PDF', function () {
    $dono = Usuario::factory()->create();
    $diag = gerarDiagnostico($dono);

    $html = $this->actingAs($dono)->get("/diagnosticos/{$diag->id}")->assertOk()->getContent();

    $doc = new DOMDocument;
    @$doc->loadHTML('<?xml encoding="utf-8"?>'.$html);
    $shell = (new DOMXPath($doc))->query('//body/div[@x-data]')->item(0);

    expect($shell->getAttribute('class'))->toContain('print:hidden')
        ->and($html)->toContain('dusk="impressao-bloqueada"')
        ->and($html)->toContain('use o botão "Baixar PDF"');
});

it('as outras telas do app continuam imprimíveis', function () {
    $dono = Usuario::factory()->create();

    $this->actingAs($dono)->get('/home')->assertOk()->assertDontSee('impressao-bloqueada', escape: false);
});

it('CA-9 (borda) — o item anterior a "Farol / Semáforo" continua no glossário (pendência diferida)', function () {
    // Edvan pediu "excluir o item que está antes do Farol/Semáforo" sem nomear
    // qual. Sem resposta até 2026-06-04 → manter. Este teste trava o default:
    // uma remoção futura sem decisão do PO derruba o build.
    $dono = Usuario::factory()->create();
    $diag = gerarDiagnostico($dono);

    $html = $this->actingAs($dono)->get("/diagnosticos/{$diag->id}")->assertOk()->getContent();

    $termos = termosDoGlossario($html);
    $posFarol = array_search('Farol / Semáforo', $termos, strict: true);

    expect($posFarol)->toBeInt()->toBeGreaterThan(0)
        ->and($termos[$posFarol - 1])->toBe('Necessidade de Capital de Giro (NCG)');
});

it('o cabeçalho e o menu do app não saem na impressão da tela', function () {
    $dono = Usuario::factory()->create();
    $diag = gerarDiagnostico($dono);

    $html = $this->actingAs($dono)->get("/diagnosticos/{$diag->id}")->assertOk()->getContent();

    $doc = new DOMDocument;
    @$doc->loadHTML('<?xml encoding="utf-8"?>'.$html);
    $xpath = new DOMXPath($doc);

    foreach (['app-header', 'app-nav'] as $testid) {
        $classes = $xpath->query("//*[@data-testid='{$testid}']")->item(0)?->getAttribute('class');

        expect($classes)->toContain('print:hidden');
    }
});

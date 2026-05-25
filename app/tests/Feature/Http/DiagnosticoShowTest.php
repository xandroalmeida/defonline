<?php

declare(strict_types=1);

use App\Actions\CalcularDiagnostico;
use App\Models\Diagnostico;
use App\Models\EmpresaAnalisada;
use App\Models\Usuario;
use Illuminate\Support\Facades\Auth;

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
        ->assertSee('gerado em '.$diag->gerado_em->format('d/m/Y H:i'));
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

it('renderiza o bloco "Resumo executivo" no topo do relatório (STORY-031)', function () {
    $dono = Usuario::factory()->create();
    $diag = gerarDiagnostico($dono);

    $response = $this->actingAs($dono)->get("/diagnosticos/{$diag->id}");

    $html = $response->getContent();
    $response->assertOk();
    expect($html)->toContain('data-testid="resumo-executivo"');
    expect($html)->toContain('aria-label="Resumo executivo"');
    expect($html)->toContain('data-veredito="');
    // Linha fixa do passo 4.
    expect($html)->toContain('Veja a tabela abaixo para análise detalhada');
    // Bloco aparece ANTES da tabela de indicadores.
    $posResumo = mb_strpos($html, 'data-testid="resumo-executivo"');
    $posTabela = mb_strpos($html, 'data-testid="indicadores-tabela"');
    expect($posResumo)->toBeLessThan($posTabela);
});

it('resumo executivo entra em modo fallback quando ≥70% indicadores indisponíveis', function () {
    $dono = Usuario::factory()->create();
    $diag = gerarDiagnostico($dono, payloadIndisponivel());

    $response = $this->actingAs($dono)->get("/diagnosticos/{$diag->id}");

    $response->assertOk();
    $html = $response->getContent();
    expect($html)->toContain('data-veredito="fallback"');
    expect($html)->toContain('Não foi possível calcular indicadores suficientes');
});

it('snapshot legado em motor_version < 1.2.0 (placeholder) não quebra a view', function () {
    // Diagnóstico antigo persistido com resumo_executivo no formato placeholder
    // — o componente deve ser silenciado em vez de quebrar.
    $dono = Usuario::factory()->create();
    Auth::login($dono);
    $empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $dono->id]);
    $diag = Diagnostico::factory()->paraEmpresa($empresa)->create([
        'motor_version' => '1.1.0',
        'resumo_executivo' => ['pendente_story' => 'STORY-031', 'fallback_acionado' => false],
    ]);
    Auth::logout();

    $response = $this->actingAs($dono)->get("/diagnosticos/{$diag->id}");

    $response->assertOk();
    expect($response->getContent())->not->toContain('data-testid="resumo-executivo"');
});

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

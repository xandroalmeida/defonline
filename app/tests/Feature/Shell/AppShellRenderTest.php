<?php

declare(strict_types=1);

use App\Models\EmpresaAnalisada;
use App\Models\Usuario;

/**
 * STORY-019 CA-1 / CA-2 / CA-3 / CA-4 / CA-5 / CA-16 / CA-17 — render do app shell.
 *
 * Verifica que cada rota autenticada e cada rota auth renderiza dentro do shell
 * correto, com os data-testid esperados, o item ativo da sidebar e os 4 sinais
 * de identificação de tela ativa (sidebar + breadcrumb + H1 + title).
 */
beforeEach(function () {
    $this->usuario = Usuario::factory()->create(['nome' => 'Roberto Souza']);
});

it('CA-1 — /home renderiza dentro do shell APP (header + nav + footer)', function () {
    $response = $this->actingAs($this->usuario)->get('/home');

    $response->assertOk()
        ->assertSee('data-testid="app-header"', escape: false)
        ->assertSee('data-testid="app-nav"', escape: false)
        ->assertSee('data-testid="app-footer"', escape: false)
        ->assertSee('data-testid="app-main"', escape: false);
});

it('CA-1 — /empresas/nova renderiza dentro do shell APP', function () {
    $response = $this->actingAs($this->usuario)->get('/empresas/nova');

    $response->assertOk()
        ->assertSee('data-testid="app-header"', escape: false)
        ->assertSee('data-testid="app-nav"', escape: false)
        ->assertSee('data-testid="app-footer"', escape: false);
});

it('CA-1 — /empresas/{id} renderiza dentro do shell APP', function () {
    $empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $this->usuario->id]);

    $response = $this->actingAs($this->usuario)->get("/empresas/{$empresa->id}");

    $response->assertOk()
        ->assertSee('data-testid="app-header"', escape: false)
        ->assertSee('data-testid="app-nav"', escape: false)
        ->assertSee('data-testid="app-footer"', escape: false);
});

it('CA-1 — /cadastro renderiza dentro do shell AUTH (sem nav)', function () {
    $response = $this->get('/cadastro');

    $response->assertOk()
        ->assertSee('data-testid="auth-header"', escape: false)
        ->assertSee('data-testid="auth-main"', escape: false)
        ->assertSee('data-testid="app-footer"', escape: false)
        ->assertDontSee('data-testid="app-nav"', escape: false);
});

it('CA-1 — /login renderiza dentro do shell AUTH (sem nav)', function () {
    $response = $this->get('/login');

    $response->assertOk()
        ->assertSee('data-testid="auth-header"', escape: false)
        ->assertSee('data-testid="auth-main"', escape: false)
        ->assertDontSee('data-testid="app-nav"', escape: false);
});

it('CA-2 — header autenticado mostra nome do usuário', function () {
    $response = $this->actingAs($this->usuario)->get('/home');

    $response->assertOk()
        ->assertSee('Olá, Roberto');
});

it('CA-3 — sidebar marca "Minhas Empresas" como ativo em /home', function () {
    $response = $this->actingAs($this->usuario)->get('/home');

    expect($response->getContent())->toMatch(
        '/dusk="app-nav-minhas-empresas"[\s\S]{0,400}is-active|is-active[\s\S]{0,400}dusk="app-nav-minhas-empresas"/',
    );
    expect($response->getContent())->toContain('aria-current="page"');
});

it('CA-3 — sidebar marca "Adicionar Empresa" como ativo em /empresas/nova', function () {
    $response = $this->actingAs($this->usuario)->get('/empresas/nova');

    $html = $response->getContent();
    expect($html)->toContain('dusk="app-nav-adicionar-empresa"');
    expect($html)->toMatch('/dusk="app-nav-adicionar-empresa"[\s\S]{0,400}is-active|is-active[\s\S]{0,400}dusk="app-nav-adicionar-empresa"/');
});

it('CA-3 — sidebar mantém "Minhas Empresas" ativo em /empresas/{id}', function () {
    $empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $this->usuario->id]);

    $response = $this->actingAs($this->usuario)->get("/empresas/{$empresa->id}");

    expect($response->getContent())->toMatch(
        '/dusk="app-nav-minhas-empresas"[\s\S]{0,400}is-active|is-active[\s\S]{0,400}dusk="app-nav-minhas-empresas"/',
    );
});

it('CA-3 — itens Diagnósticos, Histórico e Conta aparecem desabilitados com tooltip', function () {
    $response = $this->actingAs($this->usuario)->get('/home');
    $html = $response->getContent();

    foreach (['app-nav-diagnosticos', 'app-nav-historico', 'app-nav-conta'] as $dusk) {
        // Extrai o bloco do span que tem este dusk (até o próximo `>` do mesmo span)
        // e checa que tem aria-disabled e o tooltip.
        expect($html)->toMatch("/dusk=\"{$dusk}\"/", "Item {$dusk} deveria existir");
        expect($html)->toMatch(
            "/<span[^>]*(?:aria-disabled=\"true\"[\s\S]{0,400}title=\"Em breve — Onda 2\"|title=\"Em breve — Onda 2\"[\s\S]{0,400}aria-disabled=\"true\"|dusk=\"{$dusk}\"[\s\S]{0,400}aria-disabled=\"true\"[\s\S]{0,400}title=\"Em breve — Onda 2\")/",
            "Item {$dusk} deveria ter aria-disabled e tooltip 'Em breve — Onda 2'",
        );
    }
});

it('CA-4 — breadcrumb não aparece em /home (raiz)', function () {
    $response = $this->actingAs($this->usuario)->get('/home');

    $response->assertDontSee('aria-label="breadcrumb"', escape: false);
});

it('CA-4 — breadcrumb aparece em /empresas/nova com pai "Minhas Empresas"', function () {
    $response = $this->actingAs($this->usuario)->get('/empresas/nova');

    $response->assertSee('aria-label="breadcrumb"', escape: false)
        ->assertSee('Minhas Empresas')
        ->assertSee('Nova empresa');
});

it('CA-4 — breadcrumb aparece em /empresas/{id} com pai "Minhas Empresas" e nome da empresa', function () {
    $empresa = EmpresaAnalisada::factory()->create([
        'usuario_id' => $this->usuario->id,
        'razao_social' => 'Fábrica de Teste LTDA',
        'nome_fantasia' => 'Fábrica Teste',
    ]);

    $response = $this->actingAs($this->usuario)->get("/empresas/{$empresa->id}");

    $response->assertSee('aria-label="breadcrumb"', escape: false)
        ->assertSee('Fábrica Teste');
});

it('CA-5 — footer mostra termo, política, copyright e versão', function () {
    $response = $this->actingAs($this->usuario)->get('/home');

    $response->assertOk()
        ->assertSee('Termo de Adesão')
        ->assertSee('Política de Privacidade')
        ->assertSee('DEFOnline 2026')
        ->assertSee('dusk="footer-version"', escape: false);
});

it('CA-16 — footer-version mostra "v0.4.2-test · local" em ambiente testing custom', function () {
    config(['app.version' => 'v0.4.2-test']);
    config(['app.env' => 'local']);

    $response = $this->actingAs($this->usuario)->get('/home');

    $response->assertSee('v0.4.2-test · local');
});

it('CA-16 — footer-version mostra só versão em produção (sem sufixo)', function () {
    config(['app.version' => 'v0.4.2']);
    config(['app.env' => 'production']);

    $response = $this->actingAs($this->usuario)->get('/home');

    $response->assertSee('v0.4.2')->assertDontSee('v0.4.2 · ');
});

it('CA-17 — H1 e <title> de /home seguem o mapeamento canônico', function () {
    $response = $this->actingAs($this->usuario)->get('/home');

    $response->assertSee('<title>Minhas Empresas · DEFOnline</title>', escape: false)
        ->assertSee('<h1', escape: false)
        ->assertSee('Minhas Empresas');
});

it('CA-17 — H1 e <title> de /empresas/nova seguem o mapeamento canônico', function () {
    $response = $this->actingAs($this->usuario)->get('/empresas/nova');

    $response->assertSee('<title>Cadastrar empresa · DEFOnline</title>', escape: false)
        ->assertSee('Cadastrar empresa');
});

it('CA-17 — H1 e <title> de /empresas/{id} seguem o mapeamento canônico (razão social)', function () {
    $empresa = EmpresaAnalisada::factory()->create([
        'usuario_id' => $this->usuario->id,
        'razao_social' => 'ACME Empreendimentos S.A.',
    ]);

    $response = $this->actingAs($this->usuario)->get("/empresas/{$empresa->id}");

    $response->assertSee('<title>ACME Empreendimentos S.A. · DEFOnline</title>', escape: false);
});

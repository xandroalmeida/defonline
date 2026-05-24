<?php

declare(strict_types=1);

use App\Domain\FonteEnriquecimento;
use App\Domain\TipoDocumento;
use App\Models\AuditLog;
use App\Models\EmpresaAnalisada;
use App\Models\Usuario;
use Database\Factories\EmpresaAnalisadaFactory;

/**
 * STORY-016 CA-1, CA-2, CA-5 — tela "Minhas Empresas" e respeito ao multi-tenant.
 */
beforeEach(function () {
    $this->usuario = Usuario::factory()->create(['nome' => 'Roberto Souza']);
});

it('redireciona para /login quando acessa /home sem sessão', function () {
    $this->get('/home')->assertRedirect('/login');
});

it('renderiza estado vazio com CTA para /empresas/nova quando não há empresas (CA-2)', function () {
    $this->actingAs($this->usuario)
        ->get('/home')
        ->assertOk()
        ->assertSee('Olá, Roberto')
        ->assertSee('Cadastre sua primeira Empresa para começar')
        ->assertSee('Cadastrar primeira Empresa')
        ->assertSee('/empresas/nova', escape: false);
});

it('lista as empresas do usuário com nome, documento mascarado, município/UF e badge (CA-1)', function () {
    $cnpj = EmpresaAnalisadaFactory::gerarCnpjValido();
    $empresa = EmpresaAnalisada::factory()->create([
        'usuario_id' => $this->usuario->id,
        'tipo_documento' => TipoDocumento::Cnpj,
        'documento' => $cnpj,
        'razao_social' => 'Marcenaria Roberto LTDA',
        'nome_fantasia' => 'Marcenaria Roberto',
        'municipio' => 'Curitiba',
        'uf' => 'PR',
        'fonte_enriquecimento' => FonteEnriquecimento::Rfb,
    ]);

    $response = $this->actingAs($this->usuario)
        ->get('/home')
        ->assertOk()
        ->assertSee('Marcenaria Roberto')
        ->assertSee('Curitiba')
        ->assertSee('PR')
        ->assertSee('Receita Federal');

    // Documento mascarado: primeiros 2 dígitos + filial visíveis, resto oculto.
    $esperado = substr($cnpj, 0, 2).'.***.***/'.substr($cnpj, 8, 4).'-**';
    $response->assertSee($esperado);

    // Documento bruto NÃO pode aparecer renderizado.
    $response->assertDontSee($cnpj);
});

it('exibe badge "Manual" para empresa de fonte manual (CA-1)', function () {
    EmpresaAnalisada::factory()->create([
        'usuario_id' => $this->usuario->id,
        'tipo_documento' => TipoDocumento::Cpf,
        'documento' => EmpresaAnalisadaFactory::gerarCpfValido(),
        'razao_social' => 'Joana — Costureira Autônoma',
        'nome_fantasia' => null,
        'fonte_enriquecimento' => FonteEnriquecimento::Manual,
    ]);

    $this->actingAs($this->usuario)
        ->get('/home')
        ->assertOk()
        ->assertSee('Joana — Costureira Autônoma')
        ->assertSee('Manual');
});

it('botão "Iniciar diagnóstico" aparece desabilitado (CA-1)', function () {
    EmpresaAnalisada::factory()->create([
        'usuario_id' => $this->usuario->id,
    ]);

    $response = $this->actingAs($this->usuario)
        ->get('/home')
        ->assertOk()
        ->assertSee('Iniciar diagnóstico');

    expect($response->getContent())
        ->toMatch('/<button[^>]*\bdisabled\b[^>]*>\s*Iniciar diagnóstico/');
});

it('não vaza empresas de outros usuários — multi-tenancy via Global Scope (CA-5)', function () {
    $outro = Usuario::factory()->create();
    EmpresaAnalisada::factory()->create([
        'usuario_id' => $outro->id,
        'razao_social' => 'Empresa Do Outro LTDA',
        'nome_fantasia' => 'Outro Stuff',
    ]);

    $this->actingAs($this->usuario)
        ->get('/home')
        ->assertOk()
        ->assertDontSee('Empresa Do Outro LTDA')
        ->assertDontSee('Outro Stuff');
});

it('não grava entrada em audit_logs ao acessar /home (CA-5)', function () {
    EmpresaAnalisada::factory()->create(['usuario_id' => $this->usuario->id]);

    $auditAntes = AuditLog::count();

    $this->actingAs($this->usuario)->get('/home')->assertOk();

    expect(AuditLog::count())->toBe($auditAntes);
});

it('botão "Adicionar empresa" não aparece nesta onda (epic — fora do escopo)', function () {
    EmpresaAnalisada::factory()->create(['usuario_id' => $this->usuario->id]);

    $this->actingAs($this->usuario)
        ->get('/home')
        ->assertOk()
        ->assertDontSee('Adicionar empresa');
});

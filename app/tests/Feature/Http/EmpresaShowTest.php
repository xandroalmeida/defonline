<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\EmpresaAnalisada;
use App\Models\Usuario;

/**
 * Tela read-only `/empresas/{empresa}` + multi-tenancy 403 (CA-4, CA-5, CA-6).
 */
it('exige autenticação', function () {
    $empresa = EmpresaAnalisada::factory()->create();

    $this->get("/empresas/{$empresa->id}")->assertRedirect('/login');
});

it('mostra os dados da empresa do próprio Usuário em read-only (CA-5)', function () {
    $usuario = Usuario::factory()->create();
    $empresa = EmpresaAnalisada::factory()->create([
        'usuario_id' => $usuario->id,
        'razao_social' => 'Marcenaria Roberto LTDA',
        'municipio' => 'São Paulo',
        'uf' => 'SP',
    ]);

    $this->actingAs($usuario)
        ->get("/empresas/{$empresa->id}")
        ->assertOk()
        ->assertSee('Marcenaria Roberto LTDA')
        ->assertSee('São Paulo')
        ->assertSee('SP')
        ->assertSee('Preenchimento manual')               // badge da fonte
        ->assertSee('Voltar para Minhas Empresas');
});

it('devolve 403 quando outro Usuário tenta acessar a empresa (CA-4)', function () {
    $usuarioA = Usuario::factory()->create();
    $empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $usuarioA->id]);

    $usuarioB = Usuario::factory()->create();

    $this->actingAs($usuarioB)
        ->get("/empresas/{$empresa->id}")
        ->assertForbidden();
});

it('grava audit_log "empresa.acesso_negado" no cross-tenant (CA-6)', function () {
    $usuarioA = Usuario::factory()->create();
    $empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $usuarioA->id]);

    $usuarioB = Usuario::factory()->create();
    $this->actingAs($usuarioB)->get("/empresas/{$empresa->id}");

    $audit = AuditLog::where('action', 'empresa.acesso_negado')->first();
    expect($audit)->not->toBeNull();
    expect($audit->subject_type)->toBe('EmpresaAnalisada');
    expect($audit->subject_id)->toBe($empresa->id);
    expect($audit->usuario_id)->toBe($usuarioB->id);
    expect($audit->actor_id)->toBe($usuarioB->id);
});

it('devolve 404 quando a empresa não existe', function () {
    $usuario = Usuario::factory()->create();

    $this->actingAs($usuario)
        ->get('/empresas/00000000-0000-0000-0000-000000000000')
        ->assertNotFound();
});

it('não inclui o documento no HTML mascarável apenas formatado', function () {
    $usuario = Usuario::factory()->create();
    $empresa = EmpresaAnalisada::factory()->create([
        'usuario_id' => $usuario->id,
        'documento' => '11222333000181',
    ]);

    $this->actingAs($usuario)
        ->get("/empresas/{$empresa->id}")
        ->assertOk()
        ->assertSee('11.222.333/0001-81');             // documento formatado, não cru
});

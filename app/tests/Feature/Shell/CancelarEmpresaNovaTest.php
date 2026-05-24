<?php

declare(strict_types=1);

use App\Models\EmpresaAnalisada;
use App\Models\Usuario;

/**
 * STORY-019 CA-18 — política Cancelar/Voltar em /empresas/nova.
 *
 * Tem botão Cancelar (secondary) ao lado de Cadastrar empresa que navega para
 * /home via GET (sem confirmação modal — fica para depois).
 */
beforeEach(function () {
    $this->usuario = Usuario::factory()->create();
});

it('CA-18 — /empresas/nova tem botão Cancelar com link para /home', function () {
    $response = $this->actingAs($this->usuario)->get('/empresas/nova');

    $response->assertOk()
        ->assertSee('dusk="empresa-cancelar"', escape: false)
        ->assertSee('Cancelar')
        ->assertSee('href="'.route('home').'"', escape: false);
});

it('CA-18 — /empresas/nova NÃO tem botão "Voltar" (só Cancelar; nunca os dois)', function () {
    $response = $this->actingAs($this->usuario)->get('/empresas/nova');

    $response->assertOk()
        ->assertDontSee('Voltar para Minhas Empresas');
});

it('CA-18 — /empresas/{id}/show tem botão "Voltar para Minhas Empresas" (não Cancelar)', function () {
    $empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $this->usuario->id]);

    $response = $this->actingAs($this->usuario)->get("/empresas/{$empresa->id}");

    $response->assertOk()
        ->assertSee('dusk="empresa-show-voltar"', escape: false)
        ->assertSee('Voltar para Minhas Empresas')
        ->assertDontSee('dusk="empresa-cancelar"', escape: false);
});

<?php

declare(strict_types=1);

use App\Models\Usuario;

/**
 * CA-4 da STORY-011.
 */
it('redireciona para /login quando acessa /home sem sessão', function () {
    $this->get('/home')->assertRedirect('/login');
});

it('renderiza home autenticada com primeiro nome do usuário (CA-4)', function () {
    $usuario = Usuario::factory()->create(['nome' => 'Roberto Carlos Souza']);

    $this->actingAs($usuario)
        ->get('/home')
        ->assertOk()
        ->assertSee('Olá, Roberto')
        ->assertDontSee('Carlos Souza,')
        ->assertSee('Sair');
});

it('faz logout via POST /logout, invalida sessão e redireciona para /login (CA-4)', function () {
    $usuario = Usuario::factory()->create();

    $this->actingAs($usuario);
    expect(auth()->check())->toBeTrue();

    $this->withSession(['_token' => 'test-token'])
        ->post('/logout', ['_token' => 'test-token'])
        ->assertRedirect('/login');
    expect(auth()->check())->toBeFalse();
});

it('rejeita logout via GET (precisa de POST)', function () {
    $usuario = Usuario::factory()->create();

    $this->actingAs($usuario)
        ->get('/logout')
        ->assertStatus(405);
});

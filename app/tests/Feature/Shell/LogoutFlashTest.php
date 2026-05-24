<?php

declare(strict_types=1);

use App\Models\Usuario;

/**
 * STORY-019 CA-19 — logout via dropdown Conta redireciona para /login com
 * flash de sucesso. Backend (HomeController::logout) já existia; aqui só
 * formalizamos o flash novo `logout_sucesso`.
 */
it('CA-19 — POST /logout redireciona para /login com flash logout_sucesso', function () {
    $usuario = Usuario::factory()->create();

    $this->actingAs($usuario)
        ->withSession(['_token' => 'tok'])
        ->post('/logout', ['_token' => 'tok'])
        ->assertRedirect('/login')
        ->assertSessionHas('logout_sucesso', 'Você saiu da conta com sucesso.');

    expect(auth()->check())->toBeFalse();
});

it('CA-19 — /login mostra o flash logout_sucesso no HTML quando vem do logout', function () {
    $response = $this->withSession(['logout_sucesso' => 'Você saiu da conta com sucesso.'])
        ->get('/login');

    $response->assertOk()
        ->assertSee('Você saiu da conta com sucesso.')
        ->assertSee('dusk="logout-sucesso"', escape: false);
});

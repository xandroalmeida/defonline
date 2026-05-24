<?php

declare(strict_types=1);

use App\Models\Usuario;

/**
 * STORY-011 CA-4 — logout via POST.
 *
 * Antes da STORY-016 estes casos viviam em HomeTest. A renderização de
 * "/home" agora é coberta por MinhasEmpresasTest (Livewire).
 */
it('faz logout via POST /logout, invalida sessão e redireciona para /login', function () {
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

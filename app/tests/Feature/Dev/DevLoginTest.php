<?php

declare(strict_types=1);

use App\Models\Usuario;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;

/**
 * Login rápido sem senha: só existe no ambiente local. Trocar o ambiente desliga a dispensa de CSRF
 * que o Laravel dá aos testes, por isso o middleware sai explicitamente.
 */
beforeEach(fn () => $this->withoutMiddleware(PreventRequestForgery::class));

function _comoLocal(): void
{
    app()->detectEnvironment(fn () => 'local');
}

it('fora do ambiente local, a lista e a entrada respondem 404', function (string $ambiente) {
    app()->detectEnvironment(fn () => $ambiente);
    $usuario = Usuario::factory()->create();

    $this->get('/dev/entrar')->assertNotFound();
    $this->post('/dev/entrar/'.$usuario->id)->assertNotFound();
    $this->assertGuest();
})->with(['testing', 'production', 'homolog']);

it('fora do ambiente local, a tela de login não mostra o atalho', function () {
    $this->get('/login')->assertOk()->assertDontSee('Entrar sem senha');
});

it('em local, lista as contas e a tela de login mostra o atalho', function () {
    _comoLocal();
    Usuario::factory()->create(['nome' => 'Carla Captação', 'email' => 'carla@exemplo.com.br']);

    $this->get('/login')->assertOk()->assertSee('Entrar sem senha (só local)');
    $this->get('/dev/entrar')->assertOk()->assertSee('Carla Captação')->assertSee('carla@exemplo.com.br');
});

it('em local, a busca filtra por nome ou e-mail', function () {
    _comoLocal();
    Usuario::factory()->create(['nome' => 'Carla Captação']);
    Usuario::factory()->create(['nome' => 'Bruno Outro']);

    $this->get('/dev/entrar?q=carla')->assertOk()->assertSee('Carla Captação')->assertDontSee('Bruno Outro');
});

it('em local, entra na conta escolhida sem senha e troca de conta', function () {
    _comoLocal();
    $a = Usuario::factory()->create();
    $b = Usuario::factory()->create();

    $this->post('/dev/entrar/'.$a->id)->assertRedirect('/home');
    $this->assertAuthenticatedAs($a);

    $this->post('/dev/entrar/'.$b->id)->assertRedirect('/home');
    $this->assertAuthenticatedAs($b);
});

it('em local, conta inexistente responde 404', function () {
    _comoLocal();

    $this->post('/dev/entrar/019a0000-0000-7000-8000-000000000000')->assertNotFound();
    $this->assertGuest();
});

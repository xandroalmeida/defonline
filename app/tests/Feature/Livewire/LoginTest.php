<?php

declare(strict_types=1);

use App\Livewire\Login;
use App\Models\AuditLog;
use App\Models\Usuario;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

/**
 * CA-3, CA-6 da STORY-011.
 */
beforeEach(function () {
    $this->usuario = Usuario::factory()->create([
        'email' => 'roberto@exemplo.com.br',
        'senha_hash' => Hash::make('Senha1234'),
    ]);
});

it('exibe formulário de login na rota /login', function () {
    $this->get('/login')
        ->assertOk()
        ->assertSee('Entrar')
        ->assertSee('Email');
});

it('autentica com credenciais válidas e redireciona para /home (CA-3)', function () {
    Livewire::test(Login::class)
        ->set('email', 'roberto@exemplo.com.br')
        ->set('senha', 'Senha1234')
        ->call('submit')
        ->assertRedirect('/home');

    expect(auth()->check())->toBeTrue();
    expect(auth()->id())->toBe($this->usuario->id);
});

it('grava audit_log "usuario.login_sucesso" em login bem-sucedido (CA-6)', function () {
    Livewire::test(Login::class)
        ->set('email', 'roberto@exemplo.com.br')
        ->set('senha', 'Senha1234')
        ->call('submit');

    $audit = AuditLog::where('action', 'usuario.login_sucesso')->first();
    expect($audit)->not->toBeNull();
    expect($audit->usuario_id)->toBe($this->usuario->id);
});

it('rejeita senha errada com mensagem genérica (CA-3)', function () {
    $component = Livewire::test(Login::class)
        ->set('email', 'roberto@exemplo.com.br')
        ->set('senha', 'senha-errada-1')
        ->call('submit')
        ->assertHasErrors(['email'])
        ->assertNoRedirect();

    expect($component->errors()->get('email')[0])->toBe('Credenciais inválidas.');
    expect(auth()->check())->toBeFalse();
});

it('rejeita email inexistente com a mesma mensagem genérica (CA-3)', function () {
    $component = Livewire::test(Login::class)
        ->set('email', 'naoexiste@exemplo.com.br')
        ->set('senha', 'qualquer1234')
        ->call('submit')
        ->assertHasErrors(['email']);

    expect($component->errors()->get('email')[0])->toBe('Credenciais inválidas.');
});

it('exige campos obrigatórios', function () {
    Livewire::test(Login::class)
        ->set('email', '')
        ->set('senha', '')
        ->call('submit')
        ->assertHasErrors(['email', 'senha']);
});

it('não grava audit_log em falha de login', function () {
    Livewire::test(Login::class)
        ->set('email', 'roberto@exemplo.com.br')
        ->set('senha', 'errada')
        ->call('submit');

    expect(AuditLog::where('action', 'usuario.login_sucesso')->count())->toBe(0);
});

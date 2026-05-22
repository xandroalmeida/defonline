<?php

declare(strict_types=1);

use App\Jobs\EnviarEmailConfirmacao;
use App\Livewire\Cadastro;
use App\Livewire\Login;
use App\Models\Usuario;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

/**
 * STORY-013 — fluxo cruzando Cadastro (CA-2) e Login (CA-4).
 */
it('cadastro enfileira EnviarEmailConfirmacao (CA-2)', function () {
    Queue::fake();

    Livewire::test(Cadastro::class)
        ->set('cpf', '529.982.247-25')
        ->set('nome', 'Roberto Souza')
        ->set('email', 'roberto@exemplo.com.br')
        ->set('senha', 'Senha1234')
        ->set('senha_confirmation', 'Senha1234')
        ->set('telefone', '11988887777')
        ->set('aceite_termo_adesao', true)
        ->set('aceite_lgpd', true)
        ->call('submit')
        ->assertRedirect('/login');

    $usuario = Usuario::firstWhere('email', 'roberto@exemplo.com.br');
    expect($usuario)->not->toBeNull();
    expect($usuario->email_confirmed_at)->toBeNull();

    Queue::assertPushed(EnviarEmailConfirmacao::class, fn ($j) => $j->usuarioId === $usuario->id);
});

it('flash de sucesso menciona email de confirmação após o cadastro (CA-2)', function () {
    Queue::fake();

    Livewire::test(Cadastro::class)
        ->set('cpf', '529.982.247-25')
        ->set('nome', 'Roberto Souza')
        ->set('email', 'roberto@exemplo.com.br')
        ->set('senha', 'Senha1234')
        ->set('senha_confirmation', 'Senha1234')
        ->set('telefone', '11988887777')
        ->set('aceite_termo_adesao', true)
        ->set('aceite_lgpd', true)
        ->call('submit');

    expect(session('cadastro_sucesso'))
        ->toContain('link de confirmação')
        ->toContain('confirme antes de fazer login');
});

it('login bloqueia usuário com email_confirmed_at = null e mostra email mascarado (CA-4)', function () {
    Usuario::factory()->unconfirmed()->create([
        'email' => 'roberto@exemplo.com.br',
        'senha_hash' => Hash::make('Senha1234'),
    ]);

    $component = Livewire::test(Login::class)
        ->set('email', 'roberto@exemplo.com.br')
        ->set('senha', 'Senha1234')
        ->call('submit')
        ->assertHasErrors(['email'])
        ->assertNoRedirect();

    $erro = $component->errors()->get('email')[0];
    expect($erro)->toContain('Confirme seu email antes de fazer login');
    expect($erro)->toContain('r******@*******.br');     // mascarado
    expect($erro)->not->toContain('roberto@exemplo');   // PII pleno NÃO

    // Não deixa sessão autenticada por trás.
    expect(auth()->check())->toBeFalse();
});

it('login autentica normalmente se o email já foi confirmado (CA-4)', function () {
    Usuario::factory()->create([
        'email' => 'ok@exemplo.com.br',
        'senha_hash' => Hash::make('Senha1234'),
    ]);

    Livewire::test(Login::class)
        ->set('email', 'ok@exemplo.com.br')
        ->set('senha', 'Senha1234')
        ->call('submit')
        ->assertRedirect('/home');

    expect(auth()->check())->toBeTrue();
});

it('login com senha errada NÃO leak info sobre confirmação (CA-4 + security-discipline)', function () {
    Usuario::factory()->unconfirmed()->create([
        'email' => 'pendente@exemplo.com.br',
        'senha_hash' => Hash::make('Senha1234'),
    ]);

    $component = Livewire::test(Login::class)
        ->set('email', 'pendente@exemplo.com.br')
        ->set('senha', 'senha-errada')
        ->call('submit')
        ->assertHasErrors(['email']);

    // Senha errada cai no caminho genérico — não revela que a conta existe nem
    // que o email está pendente.
    expect($component->errors()->get('email')[0])->toBe('Credenciais inválidas.');
});

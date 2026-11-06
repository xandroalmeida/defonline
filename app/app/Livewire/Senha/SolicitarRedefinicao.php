<?php

declare(strict_types=1);

namespace App\Livewire\Senha;

use App\Mail\RedefinicaoSenhaMessage;
use App\Models\Usuario;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * STORY-100 — pede o link de redefinição. A resposta é sempre a mesma, exista a conta ou não, e o e-mail sai
 * pela fila, para que nem o texto nem o tempo de resposta revelem quem tem cadastro.
 */
#[Layout('components.layouts.auth', ['title' => 'Esqueci minha senha'])]
final class SolicitarRedefinicao extends Component
{
    private const PEDIDOS = 5;

    private const JANELA_SEGUNDOS = 900;

    public string $email = '';

    public bool $enviado = false;

    public function enviar(): void
    {
        $this->validate(
            ['email' => ['required', 'string', 'email:rfc', 'max:255']],
            ['email.required' => 'Informe o e-mail.', 'email.email' => 'Informe um e-mail válido.', 'email.max' => 'Informe um e-mail válido.'],
        );

        $email = trim($this->email);
        $chave = 'senha-esqueci:'.sha1(mb_strtolower($email).'|'.request()->ip());

        if (RateLimiter::attempt($chave, self::PEDIDOS, fn (): bool => true, self::JANELA_SEGUNDOS)) {
            Password::broker()->sendResetLink(['email' => $email], function (Usuario $usuario, string $token): void {
                Mail::to($usuario->email)->queue(new RedefinicaoSenhaMessage(
                    $usuario->primeiroNome(),
                    route('senha.redefinir', ['usuario' => $usuario->id, 'token' => $token]),
                    (int) config('auth.passwords.users.expire'),
                ));
            });
        }

        $this->enviado = true;
    }

    public function render(): View
    {
        return view('livewire.senha.solicitar-redefinicao');
    }
}

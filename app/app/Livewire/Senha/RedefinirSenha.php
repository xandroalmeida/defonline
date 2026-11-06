<?php

declare(strict_types=1);

namespace App\Livewire\Senha;

use App\Models\Usuario;
use App\Observabilidade\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password as Senhas;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** STORY-100 — grava a nova senha pelo link do e-mail, derruba as sessões abertas e invalida o link. */
#[Layout('components.layouts.auth', ['title' => 'Redefinir senha'])]
final class RedefinirSenha extends Component
{
    #[Locked]
    public string $usuarioId = '';

    #[Locked]
    public string $token = '';

    public string $novaSenha = '';

    public string $novaSenha_confirmation = '';

    public bool $linkInvalido = false;

    public function mount(string $usuario, string $token): void
    {
        $this->usuarioId = $usuario;
        $this->token = $token;
        $this->linkInvalido = ! $this->linkValido();
    }

    public function salvar(): mixed
    {
        $this->validate(
            ['novaSenha' => ['required', 'confirmed', Password::min(8)->letters()->numbers()]],
            [
                'novaSenha.required' => 'Informe a nova senha.',
                'novaSenha.confirmed' => 'A confirmação não é igual à nova senha.',
                'novaSenha.min' => 'A nova senha precisa ter ao menos 8 caracteres.',
                'novaSenha.letters' => 'A nova senha precisa ter letras e números.',
                'novaSenha.numbers' => 'A nova senha precisa ter letras e números.',
            ],
        );

        $usuario = Usuario::query()->find($this->usuarioId);
        $status = $usuario === null ? Senhas::INVALID_USER : Senhas::broker()->reset(
            ['email' => $usuario->email, 'token' => $this->token, 'password' => $this->novaSenha],
            function (Usuario $u, string $senha): void {
                $u->forceFill(['senha_hash' => $senha, 'remember_token' => Str::random(60)])->save();
                $this->encerrarSessoes($u);
            },
        );

        if ($status !== Senhas::PASSWORD_RESET) {
            $this->linkInvalido = true;
            $this->reset('novaSenha', 'novaSenha_confirmation');

            return null;
        }

        AuditLogger::log(
            action: 'usuario.senha_redefinida',
            subjectType: 'Usuario',
            subjectId: $this->usuarioId,
            actorType: 'user',
            actorId: $this->usuarioId,
            usuarioId: $this->usuarioId,
            context: [],
        );

        session()->flash('senha_redefinida', true);

        return $this->redirectRoute('login');
    }

    public function render(): View
    {
        return view('livewire.senha.redefinir-senha');
    }

    private function linkValido(): bool
    {
        $usuario = Usuario::query()->find($this->usuarioId);

        return $usuario !== null && Senhas::broker()->tokenExists($usuario, $this->token);
    }

    private function encerrarSessoes(Usuario $usuario): void
    {
        if (config('session.driver') === 'database') {
            DB::table((string) config('session.table', 'sessions'))->where('user_id', $usuario->id)->delete();
        }
    }
}

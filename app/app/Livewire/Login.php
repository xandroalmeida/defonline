<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Usuario;
use App\Observabilidade\AuditLogger;
use App\Observabilidade\LogSanitizer;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Login do Usuário (STORY-011 CA-3, CA-4, CA-6).
 *
 * Auth padrão Laravel (ADR-001) com mensagem genérica em falha — sem distinguir
 * email-inexistente vs. senha-errada (security-discipline.md). Em sucesso, gera
 * sessão (Auth::login), regenera o session id e grava audit_log + log estruturado.
 */
#[Layout('layouts.app')]
final class Login extends Component
{
    public string $email = '';

    public string $senha = '';

    public function submit(): mixed
    {
        $this->validate([
            'email' => ['required', 'string', 'email:rfc'],
            'senha' => ['required', 'string'],
        ], [
            'email.required' => 'Informe o email.',
            'email.email' => 'Informe um email válido.',
            'senha.required' => 'Informe a senha.',
        ]);

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->senha])) {
            Log::info('usuario.login_falha', [
                'email' => $this->email,
                'module' => 'login',
                'action' => 'tentativa',
            ]);

            throw ValidationException::withMessages(['email' => 'Credenciais inválidas.']);
        }

        $usuario = Auth::user();
        if (! $usuario instanceof Usuario) {
            // Defesa: provider mal configurado.
            Auth::logout();
            throw ValidationException::withMessages(['email' => 'Credenciais inválidas.']);
        }

        // STORY-013 CA-4 — bloqueia login enquanto email não foi confirmado.
        if (! $usuario->emailConfirmado()) {
            Auth::logout();

            Log::info('usuario.login_bloqueado_email_nao_confirmado', [
                'usuario_id' => $usuario->id,
                'email' => $usuario->email,
                'module' => 'login',
                'action' => 'bloqueio_email_nao_confirmado',
            ]);

            $mascarado = LogSanitizer::maskByCategory($usuario->email, 'email');

            throw ValidationException::withMessages([
                'email' => "Confirme seu email antes de fazer login. Verifique sua caixa de entrada — enviamos um link para {$mascarado}.",
            ]);
        }

        session()->regenerate();

        AuditLogger::log(
            action: 'usuario.login_sucesso',
            subjectType: 'Usuario',
            subjectId: $usuario->id,
            actorType: 'user',
            actorId: $usuario->id,
            usuarioId: $usuario->id,
            context: [
                'ip' => request()->ip(),
                'user_agent' => substr((string) request()->userAgent(), 0, 255),
            ],
        );

        Log::info('usuario.login_sucesso', [
            'usuario_id' => $usuario->id,
            'email' => $usuario->email,
            'module' => 'login',
            'action' => 'autenticar',
        ]);

        return $this->redirect('/home', navigate: false);
    }

    public function render(): View
    {
        return view('livewire.login');
    }
}

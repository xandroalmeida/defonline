<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\EnviarEmailConfirmacao;
use App\Models\Usuario;
use App\Observabilidade\AuditLogger;
use App\Observabilidade\EventLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * STORY-013 CA-3 / CA-5 — confirmação de email e reenvio.
 *
 * - `confirmar` valida assinatura via middleware `signed` (rota), marca
 *   `email_confirmed_at = now()`, grava `audit_logs` e redireciona.
 * - `reenviar` aceita email (sem login), re-enfileira o job e responde
 *   genericamente (anti-enumeração). Throttling: 3/hora por hash do email.
 */
final class EmailConfirmacaoController extends Controller
{
    public function confirmar(Request $request, Usuario $usuario): RedirectResponse
    {
        // Middleware `signed` na rota já garantiu integridade da URL e TTL.
        if ($usuario->emailConfirmado()) {
            return redirect()
                ->route('email.confirmar-erro')
                ->with('email_confirmar_erro_motivo', 'ja_confirmado');
        }

        DB::transaction(function () use ($usuario, $request): void {
            $usuario->forceFill(['email_confirmed_at' => now()])->save();

            AuditLogger::log(
                action: 'usuario.email_confirmado',
                subjectType: 'Usuario',
                subjectId: $usuario->id,
                actorType: 'user',
                actorId: $usuario->id,
                usuarioId: $usuario->id,
                after: [
                    'email' => $usuario->email,
                    'email_confirmed_at' => $usuario->email_confirmed_at?->toAtomString(),
                ],
                context: [
                    'ip' => $request->ip(),
                    'user_agent' => substr((string) $request->userAgent(), 0, 255),
                ],
            );

            // STORY-016 CA-3 — emissão do evento `usuario_cadastrado` após
            // confirmação de email (ADR-004 §2.2: o "cadastro" só é considerado
            // realizado quando o Usuário confirma a posse do email; antes, a
            // conta está inativa e não conta para north star). Propriedades sem
            // PII — `plano_inicial` documenta o plano default em onda 1.
            EventLogger::emit(
                nomeEvento: 'usuario_cadastrado',
                propriedades: ['plano_inicial' => 'basico_beta'],
                usuarioId: $usuario->id,
            );
        });

        Log::info('usuario.email_confirmado', [
            'usuario_id' => $usuario->id,
            'email' => $usuario->email,
            'module' => 'cadastro',
            'action' => 'confirmar_email',
        ]);

        return redirect()->route('email.confirmado');
    }

    public function reenviar(Request $request): RedirectResponse
    {
        $email = trim((string) $request->input('email', ''));

        // Throttle anti-spam: 3 tentativas/hora por email — chave anonimizada.
        // Resposta genérica em todos os casos (sucesso, throttle, email inexistente)
        // para não permitir enumeração.
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false) {
            $chave = 'email-confirm:'.hash('sha256', strtolower($email));

            if (RateLimiter::tooManyAttempts($chave, 3)) {
                Log::info('email_reenvio_throttled', [
                    'email' => $email,
                    'module' => 'cadastro',
                    'action' => 'reenviar_confirmacao',
                ]);

                return $this->respostaGenerica();
            }

            RateLimiter::hit($chave, 3600);

            $usuario = Usuario::firstWhere('email', $email);
            if ($usuario instanceof Usuario && ! $usuario->emailConfirmado()) {
                EnviarEmailConfirmacao::dispatch($usuario->id);

                AuditLogger::log(
                    action: 'usuario.email_reenvio_solicitado',
                    subjectType: 'Usuario',
                    subjectId: $usuario->id,
                    actorType: 'user',
                    actorId: $usuario->id,
                    usuarioId: $usuario->id,
                    context: [
                        'ip' => $request->ip(),
                        'user_agent' => substr((string) $request->userAgent(), 0, 255),
                    ],
                );

                Log::info('email_reenvio_enfileirado', [
                    'usuario_id' => $usuario->id,
                    'email' => $usuario->email,
                    'module' => 'cadastro',
                    'action' => 'reenviar_confirmacao',
                ]);
            } else {
                Log::info('email_reenvio_solicitado_invalido', [
                    'email' => $email,
                    'module' => 'cadastro',
                    'action' => 'reenviar_confirmacao',
                ]);
            }
        }

        return $this->respostaGenerica();
    }

    private function respostaGenerica(): RedirectResponse
    {
        return back()->with(
            'email_reenvio_aviso',
            'Se este email estiver cadastrado, enviamos um novo link de confirmação.',
        );
    }
}

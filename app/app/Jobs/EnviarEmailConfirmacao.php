<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\EmailConfirmacaoMessage;
use App\Models\Usuario;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Throwable;

/**
 * STORY-013 CA-2 — envia o email de confirmação de cadastro.
 *
 * Roda no worker (`queue:work`), driver `database` (ADR-002). 3 tentativas com
 * backoff exponencial padrão Laravel. Em sucesso/falha emite `business_metrics`
 * (`email_confirmacao_enviado` / `email_confirmacao_falhou` — ADR-004 §1.2).
 *
 * Link assinado via `URL::temporarySignedRoute('email.confirmar', now()+60min)`.
 * TTL é fixado no momento do envio — refletir mudança no Mailable significa
 * gerar novo link via reenvio.
 */
final class EnviarEmailConfirmacao extends BaseJob
{
    public int $tries = 3;

    public function __construct(public readonly string $usuarioId)
    {
        parent::__construct();
    }

    protected function handleJob(): void
    {
        $inicio = microtime(true);

        $usuario = Usuario::find($this->usuarioId);
        if (! $usuario instanceof Usuario) {
            // Sumiu entre dispatch e execução (delete manual em teste, etc).
            Log::warning('email_confirmacao_usuario_nao_encontrado', [
                'usuario_id' => $this->usuarioId,
            ]);

            return;
        }

        if ($usuario->emailConfirmado()) {
            // Reenvio enfileirado após confirmação concorrente — nada a fazer.
            Log::info('email_confirmacao_ja_confirmado', [
                'usuario_id' => $usuario->id,
                'email' => $usuario->email,
            ]);

            return;
        }

        try {
            $link = URL::temporarySignedRoute(
                'email.confirmar',
                Carbon::now()->addMinutes(60),
                ['usuario' => $usuario->id],
            );

            Mail::to($usuario->email)->send(new EmailConfirmacaoMessage(
                nome: $usuario->primeiroNome(),
                link: $link,
            ));

            Log::info('email_confirmacao_enviado', [
                'usuario_id' => $usuario->id,
                'email' => $usuario->email,
            ]);

            $this->recordBusinessMetric('email_confirmacao_enviado', true, (int) round((microtime(true) - $inicio) * 1000));
        } catch (Throwable $e) {
            Log::error('email_confirmacao_envio_falhou', [
                'usuario_id' => $usuario->id,
                'email' => $usuario->email,
                'error' => $e->getMessage(),
                'tentativa' => $this->attempts(),
            ]);

            $this->recordBusinessMetric('email_confirmacao_envio_erro', false, (int) round((microtime(true) - $inicio) * 1000));

            throw $e;
        }
    }

    /**
     * Hook chamado depois de esgotar `$tries` (3). Registra `email_confirmacao_falhou`
     * em `business_metrics` para o painel/alerta enxergar.
     */
    public function failed(?Throwable $e): void
    {
        Log::error('email_confirmacao_falhou_total', [
            'usuario_id' => $this->usuarioId,
            'error' => $e?->getMessage(),
        ]);

        $this->recordBusinessMetric('email_confirmacao_falhou', false, null);
    }

    private function recordBusinessMetric(string $tipo, bool $sucesso, ?int $duracaoMs): void
    {
        try {
            DB::table('business_metrics')->insert([
                'request_id' => $this->meta['request_id'] ?? request_id(),
                'tipo' => $tipo,
                'sucesso' => $sucesso,
                'duracao_ms' => $duracaoMs,
                'meta' => json_encode([
                    'job' => 'EnviarEmailConfirmacao',
                    'usuario_id' => $this->usuarioId,
                ], JSON_THROW_ON_ERROR),
                'inserido_em' => now(),
            ]);
        } catch (Throwable $e) {
            Log::warning('business_metrics_insert_failed', ['error' => $e->getMessage()]);
        }
    }
}

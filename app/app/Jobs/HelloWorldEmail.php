<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\HelloWorldMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Job demo do hello world — exercita os 3 processos da ADR-002 (web enfileira;
 * worker consome; envia ao Mailpit) e a propagação de `request_id` cross-process.
 *
 * Grava linha em `business_metrics` (tipo `email_enviado`) — valida ADR-004 §1.2.
 */
final class HelloWorldEmail extends BaseJob
{
    public function __construct(public readonly string $destinatario)
    {
        parent::__construct();
    }

    protected function handleJob(): void
    {
        $inicio = microtime(true);
        $sucesso = false;

        try {
            Mail::to($this->destinatario)->send(new HelloWorldMessage(
                requestId: $this->meta['request_id'] ?? request_id(),
            ));
            $sucesso = true;
            Log::info('hello_world_email_enviado', ['destinatario' => $this->destinatario]);
        } catch (Throwable $e) {
            Log::error('hello_world_email_falhou', [
                'destinatario' => $this->destinatario,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        } finally {
            $this->recordBusinessMetric($sucesso, (int) round((microtime(true) - $inicio) * 1000));
        }
    }

    private function recordBusinessMetric(bool $sucesso, int $duracaoMs): void
    {
        try {
            DB::table('business_metrics')->insert([
                'request_id' => $this->meta['request_id'] ?? request_id(),
                'tipo' => 'email_enviado',
                'sucesso' => $sucesso,
                'duracao_ms' => $duracaoMs,
                'meta' => json_encode([
                    'job' => 'HelloWorldEmail',
                    'destinatario_dominio' => substr(strrchr($this->destinatario, '@') ?: '@?', 1),
                ], JSON_THROW_ON_ERROR),
                'inserido_em' => now(),
            ]);
        } catch (Throwable $e) {
            Log::warning('business_metrics_insert_failed', ['error' => $e->getMessage()]);
        }
    }
}

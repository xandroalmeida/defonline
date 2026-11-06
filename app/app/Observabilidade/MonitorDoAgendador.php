<?php

declare(strict_types=1);

namespace App\Observabilidade;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * STORY-101 — conciliação e reprocessamento dependem do agendador, então ele não pode ser vigiado por si
 * mesmo. Ele registra um batimento por minuto, e o /health, chamado pelo healthcheck do contêiner web a cada
 * 10 s, confere. O alerta sai pelo ouvinte de log da cobrança, com o silêncio de 30 minutos dele.
 */
final class MonitorDoAgendador
{
    private const CHAVE = 'agendador:ultimo-batimento';

    private const LIMITE_SEGUNDOS = 600;

    public function registrarBatimento(): void
    {
        Cache::forever(self::CHAVE, now()->getTimestamp());
    }

    public function conferir(): void
    {
        try {
            // Sem batimento ainda (cache limpo, primeira subida): começa a contar agora.
            Cache::add(self::CHAVE, now()->getTimestamp());
            $ultimo = Cache::get(self::CHAVE);

            if (is_int($ultimo) && now()->getTimestamp() - $ultimo > self::LIMITE_SEGUNDOS) {
                Log::error('cobranca.agendador_parado', ['module' => 'cobranca', 'action' => 'monitorar_agendador', 'prioridade' => 'P1']);
            }
        } catch (Throwable $e) {
            // A liveness já respondeu; banco fora é outro alerta.
            Log::warning('agendador.monitor_sem_cache', ['exception' => $e::class]);
        }
    }
}

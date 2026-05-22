<?php

declare(strict_types=1);

namespace App\Observabilidade\Listeners;

use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Listener que popula `job_metrics` (ADR-004 §1.2).
 *
 * Registrado no `EventServiceProvider`. Mede duração entre `JobProcessing` e
 * `JobProcessed`/`JobFailed`.
 */
final class CollectJobMetrics
{
    /** @var array<string, float> */
    private array $startedAt = [];

    public function onProcessing(JobProcessing $event): void
    {
        $this->startedAt[(string) $event->job->getJobId()] = microtime(true);
    }

    public function onProcessed(JobProcessed $event): void
    {
        $this->insert((string) $event->job->getJobId(), $event->job->resolveName(), $event->job->getQueue(), 'ok', $event->job->attempts());
    }

    public function onFailed(JobFailed $event): void
    {
        $this->insert((string) $event->job->getJobId(), $event->job->resolveName(), $event->job->getQueue(), 'failed', $event->job->attempts());
    }

    private function insert(string $jobId, string $jobClass, ?string $queue, string $status, int $tentativas): void
    {
        $startedAt = $this->startedAt[$jobId] ?? null;
        unset($this->startedAt[$jobId]);

        $duration = $startedAt !== null ? (int) round((microtime(true) - $startedAt) * 1000) : 0;

        try {
            DB::table('job_metrics')->insert([
                'request_id' => request_id(),
                'job_class' => $jobClass,
                'queue' => $queue ?? 'default',
                'status' => $status,
                'duration_ms' => $duration,
                'tentativas' => $tentativas,
                'inserido_em' => now(),
            ]);
        } catch (Throwable $e) {
            Log::warning('job_metrics_insert_failed', [
                'error' => $e->getMessage(),
                'job_class' => $jobClass,
            ]);
        }
    }
}

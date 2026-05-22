<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Support\RequestId;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Base class de todos os jobs (ADR-002 §Estratégia de trace).
 *
 * Propaga o `request_id` do contexto que disparou o job para o worker — preserva
 * a correlação cross-process. Aplica `Log::withContext()` automaticamente no handle().
 *
 * Subclasses devem implementar `handleJob()` em vez de `handle()`.
 */
abstract class BaseJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** @var array<string, mixed> */
    public array $meta = [];

    public function __construct()
    {
        $this->meta['request_id'] = RequestId::get();
    }

    final public function handle(): void
    {
        $requestId = $this->meta['request_id'] ?? RequestId::generate();
        RequestId::set($requestId);

        Log::withContext([
            'request_id' => $requestId,
            'job_class' => static::class,
            'process' => config('app.process', 'worker'),
        ]);

        $this->handleJob();
    }

    abstract protected function handleJob(): void;
}

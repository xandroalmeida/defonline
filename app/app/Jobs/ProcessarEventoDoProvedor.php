<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Cobranca\ProcessarEvento;

/** STORY-076 / ADR-010 §3 — aplica um webhook já gravado em `provider_events`. */
final class ProcessarEventoDoProvedor extends BaseJob
{
    public int $tries = 5;

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 30, 120, 600];
    }

    public function __construct(public readonly string $providerEventId)
    {
        parent::__construct();
    }

    protected function handleJob(): void
    {
        app(ProcessarEvento::class)->executar($this->providerEventId);
    }
}

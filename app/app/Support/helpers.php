<?php

declare(strict_types=1);

use App\Support\RequestId;

if (! function_exists('request_id')) {
    /**
     * Retorna o request_id atual (UUID v7 ou `sched:<uuid>`).
     *
     * Fonte única de correlação cross-process (ADR-002). Disponível em qualquer ponto
     * do request, do job ou da task cron.
     */
    function request_id(): string
    {
        return RequestId::get();
    }
}

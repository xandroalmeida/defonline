<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Mede latência de cada request e grava em `request_metrics` (ADR-004 §1.2).
 *
 * Insere via `terminate()` (após a resposta ter sido enviada) — não bloqueia latência
 * percebida pelo usuário. Falha silenciosa (logada): se a métrica não pode ser
 * gravada, o request já foi atendido e o usuário não percebe.
 *
 * Rotas isentas: `/health` (RNF §6 — liveness é leve por design).
 */
final class MeasureRequest
{
    private const ATTR = '_measure_started_at';

    public function handle(Request $request, Closure $next): Response
    {
        // Storage no `attributes` do request — sobrevive entre instâncias do middleware
        // (Laravel pode instanciar separadamente para handle() e terminate()).
        $request->attributes->set(self::ATTR, microtime(true));

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if ($request->is('health')) {
            return;
        }

        $startedAt = $request->attributes->get(self::ATTR);
        if (! is_float($startedAt)) {
            return;
        }

        try {
            DB::table('request_metrics')->insert([
                'request_id' => request_id(),
                'path' => substr($request->path() === '' ? '/' : '/'.$request->path(), 0, 255),
                'method' => $request->method(),
                'status' => $response->getStatusCode(),
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'usuario_id' => Auth::id(),
                'empresa_id' => null,
                'inserido_em' => now(),
            ]);
        } catch (Throwable $e) {
            Log::warning('request_metrics_insert_failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}

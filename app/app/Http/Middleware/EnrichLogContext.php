<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enriquecimento global do contexto de log (ADR-002 + ADR-004).
 *
 * Roda DEPOIS de AssignRequestId — depende do `request_id()` já ter sido fixado.
 * Adiciona ao contexto do logger: `request_id`, `user_id`, `process`.
 * Módulos específicos podem adicionar `module`/`action` via `Log::withContext()` local.
 */
final class EnrichLogContext
{
    public function handle(Request $request, Closure $next): Response
    {
        Log::withContext([
            'request_id' => request_id(),
            'user_id' => Auth::id(),
            'process' => config('app.process', 'web'),
        ]);

        return $next($request);
    }
}

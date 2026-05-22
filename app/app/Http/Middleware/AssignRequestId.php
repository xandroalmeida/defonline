<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\RequestId;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Injeta `X-Request-Id` (UUID v7) em toda requisição entrante (ADR-002).
 *
 * Se a request já vier com o header (de um reverse proxy à frente — Caddy/Cloudflare),
 * respeita o valor recebido. Caso contrário, gera um UUID v7.
 *
 * O ID fica disponível via `request_id()` helper, ou via RequestId::get(), em qualquer
 * ponto do request. A response sempre carrega `X-Request-Id` para facilitar diagnóstico.
 */
final class AssignRequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $candidate = (string) $request->headers->get('X-Request-Id', '');

        $requestId = (RequestId::isValid($candidate) && $candidate !== '')
            ? $candidate
            : RequestId::generate();

        RequestId::set($requestId);
        $request->headers->set('X-Request-Id', $requestId);

        $response = $next($request);
        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }
}

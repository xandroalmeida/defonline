<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cabeçalhos de segurança de toda resposta (ADR-009).
 *
 * No ZalkVPS o Caddy do host só faz TLS e proxy — HSTS, `X-Frame-Options` etc.
 * são responsabilidade da aplicação. Antes (ADR-005) o Caddyfile próprio do
 * DEFOnline os injetava. HSTS só vai em request HTTPS (via `X-Forwarded-Proto`
 * confiável), para não travar o `http://localhost` do dev local.
 */
final class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }

        return $response;
    }
}

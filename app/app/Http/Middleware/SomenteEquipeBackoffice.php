<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Usuario;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * STORY-065 CA-1 — só a equipe da allowlist (`config/backoffice.php`) entra no
 * backoffice. Quem não está na lista recebe 404, não 403 (IDR-009): a área não
 * existe para quem não é da equipe. Roda depois de `auth`.
 */
final class SomenteEquipeBackoffice
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        if (! $usuario instanceof Usuario || ! $usuario->ehEquipeBackoffice()) {
            abort(404);
        }

        return $next($request);
    }
}

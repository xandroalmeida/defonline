<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Usuario;
use App\Support\PermissaoAdmin;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * STORY-065 / STORY-067 — cada rota do backoffice exige uma permissão de admin.
 * Quem não a tem recebe 404, não 403 (IDR-009): a área não existe para ele.
 * Roda depois de `auth`.
 */
final class SomenteEquipeBackoffice
{
    public function handle(Request $request, Closure $next, string $permissao): Response
    {
        $usuario = $request->user();

        if (! $usuario instanceof Usuario || ! $usuario->podeUsarBackoffice(PermissaoAdmin::from($permissao))) {
            abort(404);
        }

        return $next($request);
    }
}

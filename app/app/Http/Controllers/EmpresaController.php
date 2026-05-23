<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\EmpresaAnalisada;
use App\Models\Scopes\BelongsToUsuarioScope;
use App\Observabilidade\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Visualização read-only da Empresa Analisada (STORY-014 CA-5).
 *
 * Edição/exclusão são FORA do MVP (epic declara). A rota só renderiza.
 *
 * Acesso cross-tenant: a estória prescreve 403 + audit log `empresa.acesso_negado`
 * (CA-4 + CA-6 + DoD). Sigo a estória como contrato local, e por isso bypasso o
 * Global Scope no resolve do route binding (senão a query devolveria 404
 * silencioso). Divergência registrada nas Notas do agente para o PO alinhar com
 * ADR-003 §Decisão 1 / NRF §4.3 (que prescrevem 404).
 */
final class EmpresaController extends Controller
{
    public function show(Request $request, string $empresa): View
    {
        // Resolve sem o Global Scope para distinguir "não existe" (404) de
        // "existe e não é seu" (403 + audit). Veja docblock.
        $empresaModel = EmpresaAnalisada::withoutGlobalScope(BelongsToUsuarioScope::class)
            ->find($empresa);

        if ($empresaModel === null) {
            throw new NotFoundHttpException;
        }

        $usuarioId = (string) Auth::id();

        if ($empresaModel->usuario_id !== $usuarioId) {
            AuditLogger::log(
                action: 'empresa.acesso_negado',
                subjectType: 'EmpresaAnalisada',
                subjectId: $empresaModel->id,
                actorType: 'user',
                actorId: $usuarioId,
                usuarioId: $usuarioId,
                context: [
                    'ip' => $request->ip(),
                    'user_agent' => substr((string) $request->userAgent(), 0, 255),
                ],
            );

            throw new AuthorizationException;
        }

        // Mesmo a policy gate é redundante aqui (já chequei `usuario_id`), mas
        // mantém o ponto de extensão e cobre o caso "controller futuro esquece
        // o check" — defesa em profundidade.
        if ($request->user()?->cannot('view', $empresaModel)) {
            throw new AuthorizationException;
        }

        return view('empresa.show', ['empresa' => $empresaModel]);
    }
}

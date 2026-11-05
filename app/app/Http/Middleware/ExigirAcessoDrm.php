<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\DrmLancamento;
use App\Models\DrmRascunho;
use App\Models\EmpresaAnalisada;
use App\Support\Cobranca\AcessoDrm;
use App\Support\Cobranca\TextoBloqueio;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * STORY-084 — nas rotas que gravam o DRM (lançar e retificar), quem não tem plano ou trial vê o
 * bloqueio no lugar do formulário (spec STORY-070 §4, variante Raio X). O rascunho não é tocado (CA-6)
 * e a demonstração e o histórico ficam fora destas rotas (CA-3). A empresa já vem resolvida pelo
 * route binding (outra conta = 404 antes de chegar aqui).
 */
final class ExigirAcessoDrm
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();
        $bloqueio = $usuario === null ? null : AcessoDrm::bloqueioPara((string) $usuario->getAuthIdentifier());

        if ($bloqueio === null) {
            return $next($request);
        }

        $empresa = $request->route('empresa');
        $empresa = $empresa instanceof EmpresaAnalisada ? $empresa : null;
        $ultimo = $empresa === null ? null : DrmLancamento::query()->where('empresa_analisada_id', $empresa->id)->vigente()->orderByDesc('competencia')->first();

        return response()->view('drm.bloqueio', [
            ...TextoBloqueio::paraDrm($bloqueio),
            'rascunho' => $empresa !== null && DrmRascunho::maisRecente($empresa) !== null,
            'empresa' => $empresa,
            'ultimoMes' => $ultimo?->competencia->format('Y-m'),
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Drm\Competencia;
use App\Models\DrmLancamento;
use App\Models\EmpresaAnalisada;
use Illuminate\Contracts\View\View;

/**
 * Destino do lançamento fechado. Hoje só confirma que o mês foi gravado — a
 * Demonstração do Mês é a STORY-049, que substitui esta view.
 *
 * Empresa de outro usuário e competência sem lançamento são o mesmo 404 (IDR-009).
 */
final class DrmMesController extends Controller
{
    public function show(EmpresaAnalisada $empresa, string $competencia): View
    {
        $lancamento = DrmLancamento::vigenteDaCompetencia($empresa, Competencia::deTexto($competencia)->dia01());
        abort_if($lancamento === null, 404);

        return view('drm.mes', [
            'empresa' => $empresa,
            'lancamento' => $lancamento,
            'competencia' => Competencia::deTexto($competencia),
        ]);
    }
}

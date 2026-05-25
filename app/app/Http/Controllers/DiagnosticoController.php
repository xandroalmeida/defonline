<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Diagnostico;
use Illuminate\Contracts\View\View;

/**
 * Visualização read-only do Diagnóstico — relatório minimalista (STORY-029).
 *
 * Renderiza o snapshot já calculado (`indicadores_calculados`) — não invoca o motor
 * (IDR-010 §sub-decisão 4: snapshot é a fonte da verdade). O controller é magro:
 * o Route Model Binding resolve `$diagnostico` e o Global Scope
 * (`BelongsToUsuarioScope`) garante 404 silente cross-tenant (IDR-009).
 */
final class DiagnosticoController extends Controller
{
    public function show(Diagnostico $diagnostico): View
    {
        return view('diagnosticos.show', ['diagnostico' => $diagnostico]);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Diagnostico;
use App\Support\Relatorio\PdfDiagnostico;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;

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

    /**
     * PDF do mesmo snapshot (capa + resumo + indicadores), gerado
     * na request e devolvido como download — nada fica gravado no servidor.
     * Cross-tenant cai no 404 do Global Scope, igual ao `show`.
     */
    public function pdf(Diagnostico $diagnostico): Response
    {
        $gerador = PdfDiagnostico::padrao();

        return response($gerador->gerar($diagnostico), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$gerador->nomeArquivo($diagnostico).'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}

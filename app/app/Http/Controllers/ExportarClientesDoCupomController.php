<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Cupom;
use App\Observabilidade\AuditLogger;
use App\Services\Cobranca\RelatorioClientesDoCupom;
use App\Support\DataExibicao;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Planilha (CSV para Excel: `;`, BOM UTF-8) dos clientes que vieram por um cupom, para enviar ao
 * parceiro. Sem retenção: gerada na requisição. Exportar é auditado, porque leva dado pessoal.
 */
final class ExportarClientesDoCupomController extends Controller
{
    public function __invoke(Cupom $cupom, RelatorioClientesDoCupom $relatorio): StreamedResponse
    {
        $linhas = $relatorio->linhas($cupom);

        $atorId = (string) Auth::id();
        AuditLogger::log(
            action: 'backoffice.cupom_clientes_exportados',
            subjectType: 'Cupom',
            subjectId: $cupom->id,
            actorType: 'user',
            actorId: $atorId,
            usuarioId: $atorId,
            context: ['linhas' => count($linhas)],
        );

        $nome = 'clientes-cupom-'.Str::slug($cupom->codigo).'-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($linhas): void {
            $saida = fopen('php://output', 'w');
            fwrite($saida, "\xEF\xBB\xBF");
            fputcsv($saida, ['Nome', 'E-mail', 'WhatsApp', 'Situação', 'Desde', 'Desconto (R$)', 'Plano atual', 'Empresas', 'Diagnósticos', 'Último diagnóstico'], ';');
            foreach ($linhas as $l) {
                fputcsv($saida, [
                    $l['nome'], $l['email'], $l['telefone'] ?? '', $l['situacao'],
                    $l['desde'] ? DataExibicao::data($l['desde']) : '',
                    number_format($l['desconto_centavos'] / 100, 2, ',', ''),
                    $l['plano'], $l['empresas'], $l['diagnosticos'], $l['ultimo_diagnostico'] ?? '',
                ], ';');
            }
            fclose($saida);
        }, $nome, ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }
}

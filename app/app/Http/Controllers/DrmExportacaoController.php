<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Drm\Competencia;
use App\Models\DrmLancamento;
use App\Models\DrmPreferencia;
use App\Models\EmpresaAnalisada;
use App\Observabilidade\EventosDrm;
use App\Support\Drm\PlanilhaDrm;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Exportação da Demonstração em XLSX (STORY-055, ADR-008).
 *
 * Síncrona e sem retenção (CA-9): `streamDownload`, nada em disco, sem fila nem URL assinada. A fonte é o
 * snapshot da versão vigente — o motor não participa. É leitura do histórico, por isso não passa por
 * `drm.acesso` (STORY-084): quem já lançou continua podendo levar o resultado ao contador. Empresa alheia
 * e competência sem lançamento são o mesmo 404 (IDR-009, Global Scope).
 */
final class DrmExportacaoController extends Controller
{
    public function __invoke(EmpresaAnalisada $empresa, string $competencia): StreamedResponse
    {
        $alvo = Competencia::deTexto($competencia);
        $lancamento = DrmLancamento::vigenteDaCompetencia($empresa, $alvo->dia01());
        abort_if($lancamento === null, 404);

        EventosDrm::exportado($lancamento, $empresa, EventosDrm::FORMATO_XLSX); // STORY-056 CA-6.

        $planilha = PlanilhaDrm::gerar($empresa, $lancamento, DrmPreferencia::daEmpresa($empresa)->posicao_prolabore);

        return response()->streamDownload(
            static function () use ($planilha): void {
                (new Xlsx($planilha))->save('php://output');
            },
            PlanilhaDrm::nomeArquivo($empresa, $alvo),
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'private, no-store',
            ],
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Rfb\RfbAlerter;
use App\Support\RequestId;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Monitor de taxa de erro da consulta RFB (STORY-015 CA-5; NRF §3.1; ADR-004).
 *
 * Roda a cada 5 min via scheduler. Para cada `provider` distinto em
 * `business_metrics` (tipo=`rfb_consulta`) na janela de 10 min, calcula
 *
 *     taxa = erros_provedor / total_consultas
 *
 * onde `erros_provedor` cobre apenas {timeout, erro_5xx, erro_rede} — usuário
 * digitar CNPJ inexistente é UX, não problema operacional, então não infla a
 * taxa. Dispara alerta para o {@see RfbAlerter} quando total ≥ 5 E taxa > 5%
 * (limiares fixados pela NRF §3.1).
 */
final class MonitorarRfbErrorRate extends Command
{
    /** @var string */
    protected $signature = 'rfb:monitorar-error-rate
        {--janela-min=10 : Janela (em minutos) considerada para o cálculo}
        {--min-consultas=5 : Mínimo de consultas para acionar o alerta}
        {--limiar=0.05 : Limiar de taxa de erro do provedor (0..1)}';

    /** @var string */
    protected $description = 'Aciona alerta se a taxa de erro da consulta RFB ultrapassar o limiar por provedor (STORY-015 CA-5)';

    public function handle(RfbAlerter $alerter): int
    {
        // request_id dedicado pra correlacionar o run no log estruturado.
        RequestId::set('sched:'.RequestId::generate());

        $janela = max(1, (int) $this->option('janela-min'));
        $minConsultas = max(1, (int) $this->option('min-consultas'));
        $limiar = (float) $this->option('limiar');

        $linhas = DB::select(
            <<<'SQL'
            SELECT
                coalesce(meta->>'provider', 'desconhecido')  AS provider,
                count(*)                                      AS total,
                count(*) FILTER (
                    WHERE meta->>'status' IN ('timeout', 'erro_5xx', 'erro_rede')
                )                                             AS erros_provedor,
                count(*) FILTER (WHERE sucesso)               AS sucessos,
                count(*) FILTER (
                    WHERE meta->>'status' = 'cnpj_inexistente'
                )                                             AS inexistentes
            FROM business_metrics
            WHERE tipo = 'rfb_consulta'
              AND inserido_em >= NOW() - (? || ' minutes')::interval
            GROUP BY provider
            SQL,
            [(string) $janela],
        );

        $alertasDisparados = 0;

        foreach ($linhas as $linha) {
            $total = (int) $linha->total;
            $erros = (int) $linha->erros_provedor;
            $provider = (string) $linha->provider;
            $taxa = $total > 0 ? $erros / $total : 0.0;

            $this->line(sprintf(
                'provider=%s total=%d erros_provedor=%d taxa=%.3f',
                $provider, $total, $erros, $taxa,
            ));

            if ($total < $minConsultas || $taxa <= $limiar) {
                continue;
            }

            $alerter->enviar(
                titulo: "[DEFOnline][RFB] Taxa de erro alta — provider={$provider}",
                mensagem: sprintf(
                    'Janela de %d min: %d/%d consultas falharam no provedor (%.1f%%), acima do limiar de %.1f%%.',
                    $janela, $erros, $total, $taxa * 100, $limiar * 100,
                ),
                contexto: [
                    'provider' => $provider,
                    'janela_minutos' => $janela,
                    'total_consultas' => $total,
                    'erros_provedor' => $erros,
                    'sucessos' => (int) $linha->sucessos,
                    'cnpj_inexistente' => (int) $linha->inexistentes,
                    'taxa' => round($taxa, 4),
                    'limiar' => $limiar,
                ],
            );

            $alertasDisparados++;
        }

        $this->info("rfb:monitorar-error-rate concluído — alertas={$alertasDisparados}");

        return self::SUCCESS;
    }
}

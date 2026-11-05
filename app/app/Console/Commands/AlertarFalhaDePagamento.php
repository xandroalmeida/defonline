<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\CobrancaConfig;
use App\Observabilidade\AlertaDaEquipe;
use App\Observabilidade\MetricasDeCobranca;
use App\Support\RequestId;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * STORY-086 CA-4 / NRF §6.4 — taxa de falha de pagamento acima de 10% em 1 hora gera alerta P2.
 *
 * Conta os fatos `pagamento_confirmado` e `pagamento_recusado` (um por cobrança) da janela. Abaixo do
 * mínimo de tentativas não alerta: 1 recusa em 1 tentativa é 100% e não é incidente. O alerta sai uma
 * vez por silêncio (30 min, ADR-004) para uma hora ruim não gerar uma mensagem a cada execução. Sem
 * dado pessoal: só contagens e a taxa.
 */
final class AlertarFalhaDePagamento extends Command
{
    /** @var string */
    protected $signature = 'cobranca:alertar-falha-de-pagamento';

    /** @var string */
    protected $description = 'Alerta P2 se mais de 10% dos pagamentos da última hora falharam (STORY-086 CA-4)';

    public function handle(MetricasDeCobranca $metricas, AlertaDaEquipe $alerta): int
    {
        RequestId::set('sched:'.RequestId::generate());

        if (! CobrancaConfig::ligada()) {
            $this->info('Cobrança desligada: nada a medir.');

            return self::SUCCESS;
        }

        $limite = (float) config('cobranca.observabilidade.falha_de_pagamento.limite', 0.10);
        $janela = (int) config('cobranca.observabilidade.falha_de_pagamento.janela_minutos', 60);
        $minimo = (int) config('cobranca.observabilidade.falha_de_pagamento.minimo_de_tentativas', 5);

        $m = $metricas->falhaDePagamento(now()->subMinutes($janela));

        $this->line(sprintf('janela=%dmin tentativas=%d recusados=%d taxa=%s limite=%.1f%%', $janela, $m['total'], $m['recusados'], $m['taxa'] === null ? '-' : sprintf('%.1f%%', $m['taxa'] * 100), $limite * 100));

        if ($m['total'] < $minimo || $m['taxa'] === null || $m['taxa'] <= $limite) {
            return self::SUCCESS;
        }

        if (! Cache::add('alerta-falha-de-pagamento', 1, now()->addMinutes((int) config('cobranca.observabilidade.silencio_minutos', 30)))) {
            $this->line('Alerta já enviado há pouco: em silêncio.');

            return self::SUCCESS;
        }

        $alerta->enviar(
            sprintf('[P2] Falha de pagamento acima de %.0f%% na última hora', $limite * 100),
            sprintf('%d de %d pagamentos foram recusados em %d min (%.1f%%). Veja a tela Financeiro e o provedor de pagamento.', $m['recusados'], $m['total'], $janela, $m['taxa'] * 100),
            ['janela_minutos' => $janela, 'tentativas' => $m['total'], 'recusados' => $m['recusados'], 'taxa' => round($m['taxa'], 4), 'limite' => $limite],
        );

        $this->warn('Alerta P2 enviado.');

        return self::SUCCESS;
    }
}

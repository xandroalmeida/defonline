<?php

declare(strict_types=1);

namespace App\Console\Concerns;

use Illuminate\Console\Command;

/**
 * Progresso de comando agendado com fase, n/total, %, decorrido, taxa e ETA (preferência do projeto:
 * rotina que pode demorar nunca fica muda). `iniciarProgresso()` marca o início (o total chega no primeiro `progresso()`, depois de contado); `progresso()` imprime.
 *
 * @mixin Command
 */
trait RelataProgresso
{
    private float $progressoInicio = 0.0;

    private function iniciarProgresso(string $fase): void
    {
        $this->progressoInicio = microtime(true);
        $this->line("[{$fase}] contando o que há a processar...");
    }

    private function progresso(string $fase, int $feitas, int $total, string $detalhe = ''): void
    {
        $decorrido = max(0.001, microtime(true) - $this->progressoInicio);
        $taxa = $feitas / $decorrido;
        $eta = $taxa > 0 ? (int) round(($total - $feitas) / $taxa) : 0;
        $pct = $total > 0 ? (int) round($feitas * 100 / $total) : 100;

        $this->line(sprintf(
            '[%s] %d/%d (%d%%) decorrido %ds · %.1f/s · ETA %ds%s',
            $fase,
            $feitas,
            $total,
            $pct,
            (int) round($decorrido),
            $taxa,
            $eta,
            $detalhe === '' ? '' : " · {$detalhe}",
        ));
    }
}

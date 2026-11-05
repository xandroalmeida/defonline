<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\CobrancaConfig;
use App\Services\Cobranca\AvisarFimDoDesconto;
use Illuminate\Console\Command;

/** STORY-083 CA-8 — avisa por e-mail quem está a 7 dias do fim do desconto do cupom. */
final class AvisarFimDoDescontoCommand extends Command
{
    protected $signature = 'cupons:avisar-fim-do-desconto';

    protected $description = 'Avisa 7 dias antes do fim do desconto de cupom (STORY-083 CA-8)';

    public function handle(AvisarFimDoDesconto $avisar): int
    {
        if (! CobrancaConfig::ligada()) {
            $this->info('Cobrança desligada: nada a enviar.');

            return self::SUCCESS;
        }

        $inicio = microtime(true);
        $r = $avisar->executar(function (int $n, int $total, int $enviados, int $falhas) use ($inicio): void {
            $decorrido = microtime(true) - $inicio;
            $eta = $n > 0 ? (int) round(($total - $n) * $decorrido / $n) : 0;
            $this->line(sprintf('  %d/%d (%d%%) · enviados %d · falhas %d · %.1fs · ETA %ds', $n, $total, (int) round($n / max(1, $total) * 100), $enviados, $falhas, $decorrido, $eta));
        });

        $this->info("Concluído: {$r['enviados']} avisos de {$r['candidatas']} candidatas ({$r['falhas']} falhas).");

        return $r['falhas'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}

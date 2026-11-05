<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\CobrancaConfig;
use App\Services\Cobranca\AvisarRenovacaoPix;
use App\Services\Cobranca\EncerrarPixVencidas;
use Illuminate\Console\Command;

/**
 * STORY-080 CA-4 e CA-5 — rotina horária do plano anual pago com Pix: avisa quem está a 7 dias do fim
 * (um aviso por ciclo) e encerra quem chegou ao fim sem pagar a renovação. Idempotente. Com a cobrança
 * desligada não faz nada (STORY-073 CA-1).
 */
final class CicloDoPixAnual extends Command
{
    /** @var string */
    protected $signature = 'assinaturas:pix-anual';

    /** @var string */
    protected $description = 'Avisa 7 dias antes do fim e encerra os planos anuais Pix que venceram sem renovação (STORY-080)';

    public function handle(AvisarRenovacaoPix $avisar, EncerrarPixVencidas $encerrar): int
    {
        if (! CobrancaConfig::ligada()) {
            $this->info('Cobrança desligada: nada a fazer.');

            return self::SUCCESS;
        }

        $inicio = microtime(true);
        $this->info('Fase 1/2 · aviso de renovação (7 dias antes do fim)');
        $r = $avisar->executar(function (int $n, int $total, int $enviados, int $falhas) use ($inicio): void {
            $decorrido = microtime(true) - $inicio;
            $taxa = $decorrido > 0 ? $n / $decorrido : 0;
            $eta = $taxa > 0 ? (int) round(($total - $n) / $taxa) : 0;
            $this->line(sprintf('  %d/%d (%d%%) · enviados %d · falhas %d · %.1fs · ETA %ds', $n, $total, (int) round($n / max(1, $total) * 100), $enviados, $falhas, $decorrido, $eta));
        });

        $this->info('Fase 2/2 · encerramento dos planos vencidos sem renovação');
        $encerradas = $encerrar->executar();

        $this->info("Concluído: {$r['enviados']} avisos ({$r['falhas']} falhas), {$encerradas} planos encerrados.");

        return $r['falhas'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}

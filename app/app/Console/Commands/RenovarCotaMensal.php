<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Cobranca\Carteira;
use Illuminate\Console\Command;

/**
 * STORY-077 CA-6 — no ciclo anual a cota volta cheia todo mês, no dia de aniversário da assinatura.
 * Idempotente: rodar duas vezes no mesmo dia não renova de novo.
 */
final class RenovarCotaMensal extends Command
{
    protected $signature = 'assinaturas:renovar-cota';

    protected $description = 'Renova a cota mensal das assinaturas anuais cujo aniversário chegou';

    public function handle(Carteira $carteira): int
    {
        $renovadas = $carteira->renovarCotasMensais();
        $this->line("Cotas renovadas: {$renovadas}");

        return self::SUCCESS;
    }
}

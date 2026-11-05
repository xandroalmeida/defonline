<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Cobranca\TrocarCartao;
use Illuminate\Console\Command;

/** STORY-081 — termina o cancelamento da assinatura antiga depois de uma troca de cartão confirmada. */
final class CancelarAssinaturasAntigas extends Command
{
    protected $signature = 'assinaturas:cancelar-antigas';

    protected $description = 'Cancela no provedor a assinatura antiga das trocas de cartão já confirmadas';

    public function handle(TrocarCartao $trocas): int
    {
        $this->line('Assinaturas antigas canceladas: '.$trocas->cancelarAntigas());

        return self::SUCCESS;
    }
}

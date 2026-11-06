<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Cobranca\ConferirWebhook;
use Illuminate\Console\Command;

final class ConferirWebhookCommand extends Command
{
    protected $signature = 'cobranca:conferir-webhook';

    protected $description = 'Alerta e retoma a fila quando o webhook de pagamentos está pausado no provedor (STORY-101)';

    public function handle(ConferirWebhook $conferir): int
    {
        $resultado = $conferir->executar();
        $this->info("Webhook: {$resultado}.");

        return $resultado === ConferirWebhook::FALHOU ? self::FAILURE : self::SUCCESS;
    }
}

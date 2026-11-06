<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Observabilidade\MonitorDoAgendador;
use Illuminate\Console\Command;

final class BatimentoDoAgendadorCommand extends Command
{
    protected $signature = 'agendador:batimento';

    protected $description = 'Registra que o agendador está rodando (STORY-101)';

    public function handle(MonitorDoAgendador $monitor): int
    {
        $monitor->registrarBatimento();

        return self::SUCCESS;
    }
}

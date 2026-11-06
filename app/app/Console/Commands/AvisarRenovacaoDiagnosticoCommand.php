<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Concerns\RelataProgresso;
use App\Services\Diagnostico\AvisarRenovacaoDeDiagnostico;
use Illuminate\Console\Command;

final class AvisarRenovacaoDiagnosticoCommand extends Command
{
    use RelataProgresso;

    protected $signature = 'diagnosticos:avisar-renovacao';

    protected $description = 'Envia o aviso de prazo vencido e o lembrete para renovar o diagnóstico (STORY-099)';

    public function handle(AvisarRenovacaoDeDiagnostico $avisos): int
    {
        $this->iniciarProgresso('aviso-renovacao-diagnostico');

        $r = $avisos->executar(fn (int $feitas, int $total, int $enviados) => $this->progresso('aviso-renovacao-diagnostico', $feitas, $total, "enviados={$enviados}"));

        $this->info("Concluído: {$r['enviados']} enviados, {$r['falhas']} falhas.");

        return $r['falhas'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}

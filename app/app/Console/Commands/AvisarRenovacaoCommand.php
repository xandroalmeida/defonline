<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Concerns\RelataProgresso;
use App\Services\Cobranca\AvisarRenovacao;
use Illuminate\Console\Command;

/** STORY-079 CA-1 — lembrete de renovação (D-7). Cada ciclo recebe o aviso uma vez. */
final class AvisarRenovacaoCommand extends Command
{
    use RelataProgresso;

    protected $signature = 'assinaturas:avisar-renovacao';

    protected $description = 'Envia o lembrete de renovação a quem está a 7 dias ou menos do fim do ciclo (STORY-079 CA-1)';

    public function handle(AvisarRenovacao $avisos): int
    {
        $this->iniciarProgresso('aviso-renovacao');

        $r = $avisos->executar(fn (int $feitas, int $total, int $enviados) => $this->progresso('aviso-renovacao', $feitas, $total, "enviados {$enviados}"));

        $this->info("Concluído: {$r['enviados']} enviados, {$r['falhas']} falhas.");

        return $r['falhas'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}

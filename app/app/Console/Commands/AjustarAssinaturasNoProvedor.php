<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Cobranca\TrocarDePlano;
use Illuminate\Console\Command;

/** STORY-082 — repete o ajuste de valor da assinatura no provedor que falhou na hora da troca de plano. */
final class AjustarAssinaturasNoProvedor extends Command
{
    protected $signature = 'assinaturas:ajustar-no-provedor';

    protected $description = 'Reenvia ao provedor o valor e o ciclo das assinaturas cuja troca de plano ainda não foi refletida lá';

    public function handle(TrocarDePlano $trocas): int
    {
        $this->line('Assinaturas ajustadas: '.$trocas->reenviarAjustesPendentes());

        return self::SUCCESS;
    }
}

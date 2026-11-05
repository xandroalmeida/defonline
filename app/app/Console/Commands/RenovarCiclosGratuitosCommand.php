<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\CobrancaConfig;
use App\Services\Cobranca\AjustarValorDaAssinatura;
use App\Services\Cobranca\CiclosGratuitos;
use Illuminate\Console\Command;

/**
 * STORY-083 CA-6/CA-7 — renova os ciclos sem cobrança do desconto, encerra o que ficou sem cartão quando o
 * desconto acabou e termina o ajuste de valor no provedor que falhou na hora.
 */
final class RenovarCiclosGratuitosCommand extends Command
{
    protected $signature = 'assinaturas:ciclos-gratuitos';

    protected $description = 'Renova ciclos gratuitos de cupom, encerra descontos sem cartão e ajusta o valor no provedor (STORY-083)';

    public function handle(CiclosGratuitos $gratuitos, AjustarValorDaAssinatura $ajustes): int
    {
        if (! CobrancaConfig::ligada()) {
            $this->info('Cobrança desligada: nada a fazer.');

            return self::SUCCESS;
        }

        $this->info('Fase 1/2 · ciclos gratuitos vencidos: '.$gratuitos->executar());
        $this->info('Fase 2/2 · valores a devolver ao preço cheio no provedor: '.$ajustes->pendentes());

        return self::SUCCESS;
    }
}

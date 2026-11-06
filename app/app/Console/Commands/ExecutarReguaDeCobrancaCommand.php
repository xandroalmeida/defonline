<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Concerns\RelataProgresso;
use App\Console\Concerns\SemProvedorNaoAge;
use App\Services\Cobranca\ExecutarReguaDeCobranca;
use Illuminate\Console\Command;

/**
 * STORY-079 — roda a régua de cobrança (retentativas a cada 48 h, encerramento e cancelamento no
 * provedor). Idempotente: a tentativa é reservada antes de pedir ao provedor, então rodar de novo no
 * mesmo prazo não pede de novo.
 */
final class ExecutarReguaDeCobrancaCommand extends Command
{
    use RelataProgresso;
    use SemProvedorNaoAge;

    protected $signature = 'cobranca:regua';

    protected $description = 'Retenta cobranças recusadas de assinaturas em atraso, encerra as que esgotaram as tentativas (STORY-079)';

    public function handle(ExecutarReguaDeCobranca $regua): int
    {
        if ($this->semProvedorECobrancaDesligada()) {
            return self::SUCCESS;
        }

        $this->iniciarProgresso('regua');

        $c = $regua->executar(fn (int $feitas, int $total, array $c) => $this->progresso(
            'regua',
            $feitas,
            $total,
            sprintf('retentadas %d · restabelecidas %d · encerradas %d · provedor fora %d', $c['retentadas'], $c['restabelecidas'], $c['encerradas'], $c['indisponivel']),
        ));

        $this->info(sprintf(
            'Concluído: retentadas %d, restabelecidas %d, encerradas %d, aguardando %d, provedor indisponível %d, avisos reenviados %d, recorrências canceladas %d.',
            $c['retentadas'], $c['restabelecidas'], $c['encerradas'], $c['aguardando'], $c['indisponivel'], $c['avisos'], $c['cancelamentos'],
        ));

        return $c['indisponivel'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}

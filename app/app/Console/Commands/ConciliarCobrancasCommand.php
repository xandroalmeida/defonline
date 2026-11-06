<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Concerns\RelataProgresso;
use App\Console\Concerns\SemProvedorNaoAge;
use App\Services\Cobranca\ConciliarCobrancas;
use Illuminate\Console\Command;

/**
 * STORY-079 CA-6 / ADR-010 §3 — conciliação com o provedor. `--modo=horaria` confere o que está
 * pendente; `--modo=diaria` varre 14 dias. Idempotente: o que já foi aplicado não se aplica de novo.
 */
final class ConciliarCobrancasCommand extends Command
{
    use RelataProgresso;
    use SemProvedorNaoAge;

    protected $signature = 'cobranca:conciliar {--modo=horaria : horaria ou diaria}';

    protected $description = 'Concilia pagamentos e assinaturas com o provedor, recuperando webhooks perdidos (STORY-079)';

    public function handle(ConciliarCobrancas $conciliar): int
    {
        if ($this->semProvedorECobrancaDesligada()) {
            return self::SUCCESS;
        }

        $modo = (string) $this->option('modo');

        if (! in_array($modo, [ConciliarCobrancas::HORARIA, ConciliarCobrancas::DIARIA], true)) {
            $this->error('Use --modo=horaria ou --modo=diaria.');

            return self::INVALID;
        }

        $this->iniciarProgresso("conciliacao-{$modo}");

        $c = $conciliar->executar($modo, fn (int $feitas, int $total, array $c) => $this->progresso(
            "conciliacao-{$modo}",
            $feitas,
            $total,
            sprintf('corrigidas %d · provedor fora %d · erros %d', $c['corrigidas'], $c['indisponivel'], $c['erros']),
        ));

        $this->info(sprintf(
            'Concluído (%s): %d verificadas, %d corrigidas, %d com provedor indisponível, %d erros%s.',
            $modo, $c['verificadas'], $c['corrigidas'], $c['indisponivel'], $c['erros'], $c['abortada'] ? ', rodada abortada por falhas seguidas do provedor' : '',
        ));

        return $c['indisponivel'] > 0 || $c['erros'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}

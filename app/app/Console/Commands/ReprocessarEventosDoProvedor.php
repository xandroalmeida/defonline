<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Concerns\SemProvedorNaoAge;
use App\Jobs\ProcessarEventoDoProvedor;
use App\Models\ProviderEvent;
use Illuminate\Console\Command;
use Throwable;

/**
 * STORY-076 / ADR-010 §3 — retoma webhooks gravados que não chegaram a ser aplicados (o processo caiu
 * entre gravar e processar, ou o processamento falhou). Idempotente: evento já tratado não reentra.
 */
final class ReprocessarEventosDoProvedor extends Command
{
    use SemProvedorNaoAge;

    protected $signature = 'cobranca:reprocessar-eventos {--minutos=2 : só eventos parados há mais que isto}';

    protected $description = 'Reprocessa eventos de pagamento recebidos e ainda não aplicados';

    public function handle(): int
    {
        if ($this->semProvedorECobrancaDesligada()) {
            return self::SUCCESS;
        }

        $eventos = ProviderEvent::query()
            ->whereIn('status', ['recebido', 'erro'])
            ->whereRaw('recebido_em < now() - make_interval(mins => ?)', [(int) $this->option('minutos')])
            ->orderBy('recebido_em')
            ->get();

        $total = $eventos->count();
        $this->line("Eventos a reprocessar: {$total}");

        $falhas = 0;

        foreach ($eventos as $i => $evento) {
            try {
                ProcessarEventoDoProvedor::dispatchSync($evento->id);
            } catch (Throwable $e) {
                $falhas++;
                $this->error('Falhou '.$evento->id.': '.$e::class);
            }

            $this->line(sprintf('[%d/%d] %d%% evento %s', $i + 1, $total, (int) round(($i + 1) * 100 / $total), $evento->id));
        }

        $this->line("Concluído: {$total} evento(s), {$falhas} falha(s).");

        return $falhas === 0 ? self::SUCCESS : self::FAILURE;
    }
}

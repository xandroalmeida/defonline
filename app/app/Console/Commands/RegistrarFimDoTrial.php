<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Cobranca\StatusAssinatura;
use App\Models\Assinatura;
use App\Observabilidade\EventosCobranca;
use App\Support\RequestId;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * STORY-086 CA-1 — registra `trial_encerrado` (motivo `prazo`) dos testes cujo prazo passou. Quem
 * contratou durante o teste já teve o evento no ato da contratação (motivo `contratou`); a chave de
 * idempotência é a mesma, então os dois nunca se duplicam. Não muda a assinatura: o fim do trial por
 * prazo já vale pela data (a carteira não dá acesso a ciclo vencido), isto só deixa o fato registrado.
 */
final class RegistrarFimDoTrial extends Command
{
    /** @var string */
    protected $signature = 'trial:registrar-encerramento';

    /** @var string */
    protected $description = 'Registra o evento trial_encerrado dos testes grátis cujo prazo acabou (STORY-086 CA-1)';

    public function handle(): int
    {
        RequestId::set('sched:'.RequestId::generate());

        $trials = Assinatura::query()
            ->where('trial', true)
            ->where('status', StatusAssinatura::Ativa->value)
            ->where('proximo_vencimento', '<=', now())
            ->whereNotExists(fn ($q) => $q
                ->select(DB::raw(1))
                ->from('evento_produto')
                ->where('nome_evento', EventosCobranca::TRIAL_ENCERRADO)
                ->whereRaw("chave_idempotencia = 'trial:' || assinaturas.id::text || ':encerrado'"))
            ->orderBy('proximo_vencimento')
            ->get();

        $total = $trials->count();
        $this->info("Testes vencidos sem evento: {$total}");

        foreach ($trials as $i => $trial) {
            EventosCobranca::trialEncerrado($trial, EventosCobranca::FIM_POR_PRAZO);
            $this->line(sprintf('%d/%d registrados', $i + 1, $total));
        }

        $this->info("Concluído: {$total} registrados.");

        return self::SUCCESS;
    }
}

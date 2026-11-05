<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Cobranca\StatusAssinatura;
use App\Mail\FimDoTrialMessage;
use App\Models\Assinatura;
use App\Models\CobrancaConfig;
use App\Models\Usuario;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * STORY-074 CA-6 — e-mail de convite para assinar em D-2 e em D-0 do fim do trial (espec §4.12).
 *
 * Roda de hora em hora: cada trial recebe cada aviso uma vez (as colunas `aviso_d2_em` e `aviso_d0_em`
 * só são preenchidas depois do envio, então uma falha tenta de novo na hora seguinte). Os dias contam
 * em Brasília. Não avisa no dia da própria ativação (trial curto demais) nem depois do vencimento, e
 * com a cobrança desligada não faz nada (STORY-073 CA-1).
 */
final class AvisarFimDoTrial extends Command
{
    /** @var string */
    protected $signature = 'trial:avisar-fim';

    /** @var string */
    protected $description = 'Envia o convite para assinar a quem está a 2 dias (D-2) ou no último dia (D-0) do teste grátis (STORY-074 CA-6)';

    private const FUSO = 'America/Sao_Paulo';

    public function handle(): int
    {
        if (! CobrancaConfig::ligada()) {
            $this->info('Cobrança desligada: nada a enviar.');

            return self::SUCCESS;
        }

        $agora = now();
        $hoje = $agora->copy()->setTimezone(self::FUSO)->startOfDay();

        $trials = Assinatura::query()
            ->where('trial', true)
            ->where('status', StatusAssinatura::Ativa->value)
            ->where('proximo_vencimento', '>', $agora)
            ->where('proximo_vencimento', '<=', $agora->copy()->addDays(3))
            ->get();

        $this->info("Trials perto do fim: {$trials->count()}");

        $enviados = 0;
        $falhas = 0;

        foreach ($trials as $i => $trial) {
            $fim = $trial->proximo_vencimento->copy()->setTimezone(self::FUSO);
            $dias = (int) $hoje->diffInDays($fim->copy()->startOfDay());
            $iniciouAntesDeHoje = $trial->data_inicio->copy()->setTimezone(self::FUSO)->startOfDay()->lessThan($hoje);

            $coluna = match (true) {
                ! $iniciouAntesDeHoje => null,
                $dias === 2 && $trial->aviso_d2_em === null => 'aviso_d2_em',
                $dias === 0 && $trial->aviso_d0_em === null => 'aviso_d0_em',
                default => null,
            };

            if ($coluna === null) {
                continue;
            }

            $usuario = Usuario::query()->find($trial->usuario_id);

            if ($usuario === null) {
                continue;
            }

            try {
                Mail::to($usuario->email)->send(new FimDoTrialMessage($usuario->primeiroNome(), $fim->format('d/m/Y'), $dias, route('planos')));
                $trial->forceFill([$coluna => $agora])->save();
                $enviados++;
            } catch (Throwable $e) {
                $falhas++;
                Log::error('trial.aviso_fim.falha', ['assinatura_id' => $trial->id, 'aviso' => $coluna, 'exception' => $e::class, 'message' => $e->getMessage()]);
            }

            $this->line(sprintf('%d/%d · enviados %d · falhas %d', $i + 1, $trials->count(), $enviados, $falhas));
        }

        $this->info("Concluído: {$enviados} enviados, {$falhas} falhas.");

        return $falhas > 0 ? self::FAILURE : self::SUCCESS;
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Cobranca;

use App\Models\Assinatura;
use App\Models\TrialAtivacao;
use App\Models\TrialConfig;
use App\Models\Usuario;
use App\Observabilidade\AlertaDaEquipe;
use App\Observabilidade\EventosCobranca;
use App\Support\DataExibicao;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * STORY-074 — ativação do trial: uma assinatura com `trial = true` e sem plano, concedida pela
 * {@see Carteira} com os dias e as análises que a configuração tinha NESTE instante (a assinatura
 * guarda cota e vencimento, então mudar a configuração depois não mexe em trial em andamento, CA-3).
 *
 * Uma ativação por CPF (índice único no HMAC do CPF: duas abas ao mesmo tempo também caem aqui).
 * O IP é só registrado: mais ativações do mesmo IP que o limite em 30 dias geram alerta, nunca
 * bloqueio, porque escritórios compartilham IP (CA-7). Não é `final` só para a tela poder ser testada
 * contra uma falha inesperada.
 */
class Trial
{
    public function __construct(
        private readonly Carteira $carteira,
        private readonly ElegibilidadeTrial $elegibilidade,
        private readonly AlertaDaEquipe $alerter,
    ) {}

    /** @throws TrialIndisponivel */
    public function ativar(string $usuarioId, ?string $ip): Assinatura
    {
        $motivo = $this->elegibilidade->motivo($usuarioId);

        if ($motivo !== null) {
            throw new TrialIndisponivel($motivo);
        }

        $config = TrialConfig::atual();
        $usuario = Usuario::query()->findOrFail($usuarioId);
        $agora = now();

        try {
            $assinatura = DB::transaction(function () use ($usuario, $config, $ip, $agora): Assinatura {
                // Conta anonimizada não tem CPF: só a regra por conta (assinatura com trial) vale.
                $cpf = (string) $usuario->cpf;

                if ($cpf !== '') {
                    TrialAtivacao::query()->create([
                        'usuario_id' => $usuario->id,
                        'cpf_hash' => DocumentoHash::de($cpf),
                        'ip' => $ip,
                        'ativado_em' => $agora,
                    ]);
                }

                $trial = $this->carteira->concederCota($usuario->id, $config->analises, $agora, DataExibicao::fimDoDia($agora->copy()->addDays($config->dias)), trial: true);
                EventosCobranca::trialAtivado($trial);

                return $trial;
            });
        } catch (UniqueConstraintViolationException|AssinaturaVigenteJaExiste) {
            // Corrida: outra ativação do mesmo CPF (ou da mesma conta) chegou primeiro.
            throw new TrialIndisponivel(MotivoInelegivelTrial::CpfJaUsou);
        }

        $this->alertarSeIpAcimaDoLimite($ip, $config->limite_ip_30d);

        return $assinatura;
    }

    private function alertarSeIpAcimaDoLimite(?string $ip, int $limite): void
    {
        if ($ip === null || $ip === '') {
            return;
        }

        $total = TrialAtivacao::query()->where('ip', $ip)->where('ativado_em', '>=', now()->subDays(30))->count();

        if ($total > $limite) {
            // O alerta vai para um canal de mensagens: IP é dado pessoal, então só sai com o último trecho oculto.
            $mascarado = AlertaDaEquipe::mascararIp($ip);

            $this->alerter->enviar(
                '[P2] Trials em excesso para um mesmo IP',
                "Um IP ({$mascarado}) ativou {$total} trials em 30 dias (limite configurado: {$limite}). Nada foi bloqueado; confira em trial_ativacoes se é abuso ou um escritório compartilhando IP.",
                ['ip_mascarado' => $mascarado, 'total_30d' => $total, 'limite' => $limite],
            );
        }
    }
}

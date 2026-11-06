<?php

declare(strict_types=1);

namespace App\Domain\Cobranca;

use App\Models\Assinatura;
use App\Models\TrialAtivacao;
use App\Models\TrialConfig;
use App\Models\Usuario;
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
 * Não é `final` só para a tela poder ser testada contra uma falha inesperada.
 */
class Trial
{
    public function __construct(
        private readonly Carteira $carteira,
        private readonly ElegibilidadeTrial $elegibilidade,
    ) {}

    /** @throws TrialIndisponivel */
    public function ativar(string $usuarioId): Assinatura
    {
        $motivo = $this->elegibilidade->motivo($usuarioId);

        if ($motivo !== null) {
            throw new TrialIndisponivel($motivo);
        }

        $config = TrialConfig::atual();
        $usuario = Usuario::query()->findOrFail($usuarioId);
        $agora = now();

        try {
            $assinatura = DB::transaction(function () use ($usuario, $config, $agora): Assinatura {
                // Conta anonimizada não tem CPF: só a regra por conta (assinatura com trial) vale.
                $cpf = (string) $usuario->cpf;

                if ($cpf !== '') {
                    TrialAtivacao::query()->create([
                        'usuario_id' => $usuario->id,
                        'cpf_hash' => DocumentoHash::de($cpf),
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

        return $assinatura;
    }
}

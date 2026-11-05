<?php

declare(strict_types=1);

namespace App\Domain\Cobranca;

use App\Models\Assinatura;
use App\Models\TrialAtivacao;
use App\Models\TrialConfig;
use App\Models\Usuario;

/**
 * STORY-074 CA-1, CA-2 e CA-4 — o Usuário pode ativar o trial? Só consulta, sem efeito colateral;
 * quem ativa é o {@see Trial}. A regra do CNPJ (uma análise de trial por empresa) é outra, aplicada
 * no consumo pela {@see Carteira}. Não é `final` só para o teste simular a corrida entre duas abas.
 */
class ElegibilidadeTrial
{
    /** Nulo quando pode; senão o motivo. */
    public function motivo(string $usuarioId): ?MotivoInelegivelTrial
    {
        $config = TrialConfig::query()->first();

        if ($config === null || ! $config->ativo) {
            return MotivoInelegivelTrial::Desativado;
        }

        if (Assinatura::query()->where('usuario_id', $usuarioId)->where('trial', true)->exists()) {
            return MotivoInelegivelTrial::CpfJaUsou;
        }

        $cpf = Usuario::query()->whereKey($usuarioId)->value('cpf');

        if (is_string($cpf) && $cpf !== '' && TrialAtivacao::query()->where('cpf_hash', DocumentoHash::de($cpf))->exists()) {
            return MotivoInelegivelTrial::CpfJaUsou;
        }

        $vigente = Assinatura::query()
            ->where('usuario_id', $usuarioId)
            ->whereIn('status', [StatusAssinatura::Ativa->value, StatusAssinatura::EmAtraso->value])
            ->exists();

        return $vigente ? MotivoInelegivelTrial::JaTemAssinatura : null;
    }
}

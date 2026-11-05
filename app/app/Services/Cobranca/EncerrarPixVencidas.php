<?php

declare(strict_types=1);

namespace App\Services\Cobranca;

use App\Domain\Cobranca\Carteira;
use App\Domain\Cobranca\StatusAssinatura;
use App\Models\Assinatura;
use App\Observabilidade\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * STORY-080 CA-5 — plano anual pago com Pix que chegou ao fim sem a renovação paga passa a `expirada`:
 * sem retentativa (nada falhou, não há cobrança a repetir), histórico e diagnósticos preservados, e a
 * vaga de assinatura do Usuário é liberada para ele contratar de novo. O bloqueio das funções pagas já
 * vale na hora do vencimento (a carteira não dá acesso a ciclo vencido); isto só fecha o estado.
 */
final class EncerrarPixVencidas
{
    public function __construct(private readonly Carteira $carteira) {}

    public function executar(): int
    {
        $ids = Assinatura::query()
            ->where('trial', false)
            ->where('status', StatusAssinatura::Ativa->value)
            ->where('meio_pagamento', 'pix')
            ->where('proximo_vencimento', '<=', now())
            ->pluck('id');

        $encerradas = 0;

        foreach ($ids as $id) {
            $encerradas += DB::transaction(function () use ($id): int {
                $a = Assinatura::query()->lockForUpdate()->find($id);

                // Uma renovação paga entre a consulta e o lock empurrou o vencimento.
                if ($a === null || $a->status !== StatusAssinatura::Ativa || $a->proximo_vencimento->greaterThan(now())) {
                    return 0;
                }

                $this->carteira->mudarStatus($a->id, StatusAssinatura::Expirada);

                AuditLogger::log(
                    action: 'cobranca.assinatura_pix_encerrada',
                    subjectType: 'Assinatura',
                    subjectId: $a->id,
                    actorType: 'system',
                    usuarioId: $a->usuario_id,
                    after: ['proximo_vencimento' => $a->proximo_vencimento->toIso8601String()],
                );

                return 1;
            });
        }

        return $encerradas;
    }
}

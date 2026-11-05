<?php

declare(strict_types=1);

namespace App\Services\Cobranca;

use App\Domain\Cobranca\CicloCobranca;
use App\Domain\Cobranca\PaymentProvider;
use App\Domain\Cobranca\ProvedorIndisponivel;
use App\Domain\Cobranca\ReferenciaProvedor;
use App\Domain\Cobranca\StatusAssinatura;
use App\Models\Assinatura;
use App\Observabilidade\AuditLogger;
use Illuminate\Support\Facades\Log;

/**
 * STORY-083 CA-7 / ADR-010 4.7 — quando o último ciclo com desconto é pago, o valor da recorrência no provedor
 * volta ao preço cheio (`changeSubscriptionValue`). A assinatura sai da transação do pagamento com
 * `valor_pendente_no_provedor`; aqui se tenta na hora e, se o provedor falhar, a rotina horária repete
 * (mesmo desenho do cancelamento da assinatura antiga na troca de cartão, IDR-021).
 */
final class AjustarValorDaAssinatura
{
    public function __construct(private readonly PaymentProvider $provedor) {}

    /** Tenta o ajuste de uma assinatura. Verdadeiro se não há mais nada pendente. */
    public function ajustar(string $assinaturaId): bool
    {
        $a = Assinatura::query()->find($assinaturaId);

        if ($a === null || ! $a->valor_pendente_no_provedor) {
            return true;
        }

        // Recorrência encerrada ou sem cartão no provedor: não há valor a mudar.
        if ($a->id_externo === null || $a->provedor === null || ! in_array($a->status, [StatusAssinatura::Ativa, StatusAssinatura::EmAtraso], true) || $a->preco_cheio_centavos === null) {
            $a->forceFill(['valor_pendente_no_provedor' => false])->save();

            return true;
        }

        try {
            $this->provedor->changeSubscriptionValue(
                new ReferenciaProvedor($a->provedor, $a->id_externo),
                $a->preco_cheio_centavos,
                CicloCobranca::from((string) $a->ciclo),
                $a->proximo_vencimento->toDateTimeImmutable(),
            );
        } catch (ProvedorIndisponivel $e) {
            Log::error('cobranca.ajuste_de_valor_falhou', [
                'module' => 'cobranca',
                'action' => 'ajustar_valor_da_assinatura',
                'assinatura_id' => $a->id,
                'motivo' => $e->getMessage(),
            ]);

            return false;
        }

        $a->forceFill(['valor_pendente_no_provedor' => false])->save();

        AuditLogger::log(
            action: 'cobranca.valor_da_assinatura_ajustado',
            subjectType: 'Assinatura',
            subjectId: $a->id,
            actorType: 'system',
            usuarioId: $a->usuario_id,
            after: ['valor_centavos' => $a->preco_cheio_centavos],
        );

        return true;
    }

    /** @return int quantas assinaturas ficaram em dia com o provedor */
    public function pendentes(): int
    {
        $feitas = 0;

        foreach (Assinatura::query()->where('valor_pendente_no_provedor', true)->pluck('id') as $id) {
            $feitas += $this->ajustar($id) ? 1 : 0;
        }

        return $feitas;
    }
}

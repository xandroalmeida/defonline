<?php

declare(strict_types=1);

namespace App\Services\Cobranca;

use App\Models\Assinatura;
use App\Models\Invoice;

/**
 * STORY-083 CA-7, CA-9 — passa o desconto da Invoice paga para a assinatura (retrato; desativar o cupom
 * depois não o muda) e deixa `preco_snapshot_centavos` com o valor da PRÓXIMA cobrança.
 */
final class DescontoNaAssinatura
{
    /**
     * @param  int  $periodo  o ciclo que esta Invoice pagou (1 = contratação)
     */
    public static function gravar(Assinatura $assinatura, Invoice $invoice, int $periodo): void
    {
        $desconto = $assinatura->desconto();

        if ($invoice->cupom_id !== null && $invoice->desconto_centavos > 0 && $invoice->preco_cheio_centavos !== null) {
            $assinatura->forceFill([
                'cupom_id' => $invoice->cupom_id,
                'preco_cheio_centavos' => $invoice->preco_cheio_centavos,
                'desconto_centavos' => $invoice->desconto_centavos,
                'desconto_periodos' => $invoice->cupom_ciclos === null ? null : ($periodo - 1) + $invoice->cupom_ciclos,
                'desconto_aviso_em' => null,
            ]);
            $desconto = $assinatura->desconto();
        }

        if ($desconto === null) {
            return;
        }

        $assinatura->forceFill([
            'preco_snapshot_centavos' => $desconto->valorDoPeriodo($periodo + 1),
            'valor_pendente_no_provedor' => $desconto->acabaApos($periodo) && $assinatura->id_externo !== null,
        ]);
    }
}

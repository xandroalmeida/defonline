<?php

declare(strict_types=1);

namespace App\Services\Cobranca;

use App\Domain\Cobranca\EstadoCobranca;
use App\Domain\Cobranca\PaymentProvider;
use App\Domain\Cobranca\PedidoCheckout;
use App\Domain\Cobranca\ProvedorIndisponivel;
use App\Models\Invoice;
use Illuminate\Support\Facades\Log;

/**
 * Abre o checkout hospedado de uma Invoice já gravada (o id dela é a chave de idempotência) e guarda o
 * link e o prazo. Se o provedor falha, a Invoice vira `falhou` (motivo `provedor`, para a tela não
 * confundir com recusa do cliente), o alerta operacional sai em `Log::error` e a exceção sobe.
 * Compartilhado por créditos (076/078), assinatura (077/080) e renovação por Pix (080).
 */
final class AbrirCheckoutDaInvoice
{
    public function __construct(private readonly PaymentProvider $provedor) {}

    /** @throws ProvedorIndisponivel */
    public function executar(Invoice $invoice, PedidoCheckout $pedido, string $acao): void
    {
        try {
            $sessao = $this->provedor->startCheckout($pedido);
        } catch (ProvedorIndisponivel $e) {
            $invoice->forceFill([
                'status' => EstadoCobranca::Falhou,
                'motivo_encerramento' => 'provedor',
                'falha_motivo' => mb_substr($e->getMessage(), 0, 200),
            ])->save();

            // Alvo do alerta operacional: nível error, módulo e ação estáveis. Sem corpo nem chave.
            Log::error('cobranca.provedor_falhou', [
                'module' => 'cobranca',
                'action' => $acao,
                'invoice_id' => $invoice->id,
                'provedor' => $this->provedor->nome(),
                'motivo' => $e->getMessage(),
            ]);

            throw $e;
        }

        $invoice->forceFill([
            'checkout_id_externo' => $sessao->referencia->id,
            'checkout_url' => $sessao->url,
            'checkout_expira_em' => $sessao->expiraEm,
        ])->save();
    }
}

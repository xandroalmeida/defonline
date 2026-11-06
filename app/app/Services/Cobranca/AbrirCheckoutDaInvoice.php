<?php

declare(strict_types=1);

namespace App\Services\Cobranca;

use App\Domain\Cobranca\EstadoCobranca;
use App\Domain\Cobranca\PaymentProvider;
use App\Domain\Cobranca\PedidoCheckout;
use App\Domain\Cobranca\ProvedorIndisponivel;
use App\Domain\Cobranca\ReferenciaProvedor;
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

        $this->cancelarAnteriores($invoice);
    }

    /**
     * STORY-092 CA-2 — abrir um checkout novo do mesmo item (mesma compra de créditos, mesmo plano e ciclo, mesma
     * renovação, mesma diferença de troca) cancela no provedor o que ficou pendente antes: dois links pagáveis do
     * mesmo item são a origem do pagamento em dobro. O provedor que não deixa cancelar (fora do ar, ou o
     * checkout já foi pago) não impede o novo: vira aviso, e o estado local do antigo só muda pelo webhook.
     */
    private function cancelarAnteriores(Invoice $nova): void
    {
        $anteriores = Invoice::query()
            ->where('usuario_id', $nova->usuario_id)
            ->where('tipo', $nova->tipo)
            ->where('renovacao', (bool) $nova->renovacao)
            ->where('meio', $nova->meio)
            ->where('provedor', $nova->provedor)
            ->where('status', EstadoCobranca::Pendente->value)
            ->whereNotNull('checkout_id_externo')
            ->where('id', '!=', $nova->id)
            ->where(fn ($q) => $nova->pacote_credito_id === null ? $q->whereNull('pacote_credito_id') : $q->where('pacote_credito_id', $nova->pacote_credito_id))
            ->where(fn ($q) => $nova->plano_id === null ? $q->whereNull('plano_id') : $q->where('plano_id', $nova->plano_id))
            ->where(fn ($q) => $nova->ciclo === null ? $q->whereNull('ciclo') : $q->where('ciclo', $nova->ciclo))
            ->where(fn ($q) => $nova->assinatura_id === null ? $q->whereNull('assinatura_id') : $q->where('assinatura_id', $nova->assinatura_id))
            ->get();

        foreach ($anteriores as $anterior) {
            try {
                $this->provedor->cancelCheckout(new ReferenciaProvedor($anterior->provedor, (string) $anterior->checkout_id_externo));
            } catch (ProvedorIndisponivel $e) {
                Log::warning('cobranca.checkout_anterior_nao_cancelado', [
                    'module' => 'cobranca',
                    'action' => 'cancelar_checkout_anterior',
                    'invoice_id' => $anterior->id,
                    'nova_invoice_id' => $nova->id,
                    'motivo' => $e->getMessage(),
                ]);
            }
        }
    }
}

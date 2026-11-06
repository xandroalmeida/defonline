<?php

declare(strict_types=1);

namespace App\Services\Cobranca;

use App\Domain\Cobranca\CobrancaNoProvedor;
use App\Domain\Cobranca\EstadoCobranca;
use App\Models\Invoice;
use App\Observabilidade\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

/**
 * STORY-092 CA-1 (D-08) — o provedor confirmou o pagamento, mas não há o que conceder (a base da troca de plano
 * mudou, a renovação do ano já estava paga, o checkout já tinha sido cancelado). O dinheiro entrou: a Invoice
 * fica `paga` e marcada com o motivo da devolução, o que a coloca na lista "a devolver" do Financeiro
 * (STORY-085), e sai um alerta para a equipe. Nada é concedido. Chamar dentro da transação do pagamento.
 */
final class PagamentoSemEfeito
{
    public const TROCA_SEM_EFEITO = 'troca_sem_efeito';

    public const RENOVACAO_REPETIDA = 'renovacao_repetida';

    public const CHECKOUT_CANCELADO = 'checkout_cancelado';

    /** Rótulos para a tela do Financeiro. */
    public const ROTULOS = [
        self::TROCA_SEM_EFEITO => 'Diferença de troca de plano paga sem efeito (o plano já tinha mudado)',
        self::RENOVACAO_REPETIDA => 'Renovação anual paga duas vezes pelo mesmo período',
        self::CHECKOUT_CANCELADO => 'Pago em checkout que já estava cancelado',
    ];

    private const ALERTAS = [
        self::TROCA_SEM_EFEITO => 'cobranca.troca_paga_sem_efeito',
        self::RENOVACAO_REPETIDA => 'cobranca.renovacao_paga_sem_efeito',
        self::CHECKOUT_CANCELADO => 'cobranca.pagamento_de_checkout_cancelado',
    ];

    /** @return array{0: 'processado', 1: string} */
    public static function registrar(Invoice $invoice, CobrancaNoProvedor $cobranca, string $motivo): array
    {
        $pagoEm = $cobranca->pagoEm !== null ? CarbonImmutable::instance($cobranca->pagoEm) : CarbonImmutable::now();

        $invoice->forceFill([
            'status' => EstadoCobranca::Paga,
            'id_externo' => $cobranca->referencia->id,
            'checkout_id_externo' => $invoice->checkout_id_externo ?? $cobranca->checkoutId,
            'pago_em' => $pagoEm,
            'cartao_bandeira' => $cobranca->cartaoBandeira,
            'cartao_ultimos4' => $cobranca->cartaoUltimos4,
            'devolucao_motivo' => $motivo,
        ])->save();

        Log::error(self::ALERTAS[$motivo], [
            'module' => 'cobranca',
            'action' => 'processar_evento',
            'invoice_id' => $invoice->id,
            'assinatura_id' => $invoice->assinatura_id,
            'usuario_id' => $invoice->usuario_id,
            'motivo' => $motivo,
        ]);

        AuditLogger::log(
            action: 'cobranca.pagamento_sem_efeito',
            subjectType: 'Invoice',
            subjectId: $invoice->id,
            actorType: 'system',
            usuarioId: $invoice->usuario_id,
            after: ['motivo' => $motivo, 'valor_centavos' => $invoice->valor_centavos, 'tipo' => $invoice->tipo, 'provedor' => $invoice->provedor],
        );

        return ['processado', $invoice->id];
    }
}

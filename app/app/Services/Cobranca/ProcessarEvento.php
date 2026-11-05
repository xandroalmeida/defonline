<?php

declare(strict_types=1);

namespace App\Services\Cobranca;

use App\Domain\Cobranca\Carteira;
use App\Domain\Cobranca\CobrancaNoProvedor;
use App\Domain\Cobranca\EstadoCobranca;
use App\Domain\Cobranca\PaymentProvider;
use App\Domain\Cobranca\ReferenciaProvedor;
use App\Domain\Cobranca\TipoEvento;
use App\Models\Invoice;
use App\Models\ProviderEvent;
use App\Observabilidade\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * STORY-076 CA-3, CA-4, CA-5 — aplica um evento já gravado. Regras do ADR-010 §3:
 *
 * - o crédito só nasce de `cobranca.paga`, e só depois de conferir o estado ATUAL no provedor
 *   (`getCharge`); o que o payload diz é só um aviso;
 * - a mesma Invoice paga duas vezes credita uma vez (lock da linha + índice único em
 *   `lotes_credito.invoice_id`);
 * - o valor que o provedor cobrou tem que ser o da Invoice, senão não credita e alerta;
 * - estado final (estornada, contestada, cancelada) nunca vira `paga`. Já `vencida` (Pix que
 *   passou do prazo) ainda aceita a confirmação do banco;
 * - evento sem efeito vira `ignorado`, não erro;
 * - pagamento de assinatura (ativação e renovação) é de {@see ConfirmarAssinatura} (STORY-077).
 */
final class ProcessarEvento
{
    public function __construct(
        private readonly PaymentProvider $provedor,
        private readonly Carteira $carteira,
        private readonly ConfirmarAssinatura $assinaturas,
    ) {}

    public function executar(string $providerEventId): void
    {
        $registro = ProviderEvent::query()->findOrFail($providerEventId);

        if (in_array($registro->status, ['processado', 'ignorado'], true)) {
            return;
        }

        try {
            [$status, $invoiceId] = $this->aplicar($registro);
        } catch (Throwable $e) {
            $registro->forceFill(['status' => 'erro', 'erro' => mb_substr($e::class.': '.$e->getMessage(), 0, 500)])->save();

            Log::error('cobranca.evento_com_erro', [
                'module' => 'cobranca',
                'action' => 'processar_evento',
                'provider_event_id' => $registro->id,
                'tipo' => $registro->tipo,
                'erro' => $e::class,
            ]);

            throw $e;
        }

        $registro->forceFill([
            'status' => $status,
            'invoice_id' => $invoiceId,
            'erro' => null,
            'processado_em' => now(),
        ])->save();
    }

    /** @return array{0: 'processado'|'ignorado', 1: string|null} */
    private function aplicar(ProviderEvent $evento): array
    {
        return match (TipoEvento::from($evento->tipo)) {
            TipoEvento::CobrancaPaga => $this->aplicarPagamento($evento),
            TipoEvento::CheckoutExpirado => $this->encerrarCheckout($evento, EstadoCobranca::Vencida, 'expirado'),
            TipoEvento::CheckoutCancelado => $this->encerrarCheckout($evento, EstadoCobranca::Cancelada, 'cancelado'),
            TipoEvento::CobrancaFalhou => $this->encerrarCheckout($evento, EstadoCobranca::Falhou, 'recusado'),
            default => ['ignorado', null],
        };
    }

    /** @return array{0: 'processado'|'ignorado', 1: string|null} */
    private function aplicarPagamento(ProviderEvent $evento): array
    {
        if ($evento->cobranca_id_externo === null) {
            return $this->semInvoice($evento, 'pagamento sem id de cobrança');
        }

        $cobranca = $this->provedor->getCharge(new ReferenciaProvedor($evento->provedor, $evento->cobranca_id_externo));
        $invoice = $this->localizar($evento, $cobranca);

        // Sem Invoice, só uma cobrança de assinatura nossa (renovação) ainda pode ter destino.
        if ($invoice === null && $cobranca->assinaturaId === null) {
            return $this->semInvoice($evento, 'pagamento sem invoice correspondente');
        }

        if ($cobranca->estado !== EstadoCobranca::Paga) {
            return ['ignorado', $invoice?->id];
        }

        // Daqui em diante, sem assinatura na cobrança, a Invoice existe (a guarda acima já devolveu o resto).
        if ($cobranca->assinaturaId !== null || $invoice->tipo === 'assinatura') {
            return $this->assinaturas->aplicar($invoice, $cobranca, $evento->provedor);
        }

        return DB::transaction(function () use ($invoice, $cobranca): array {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);

            if (! in_array($invoice->status, [EstadoCobranca::Pendente, EstadoCobranca::Vencida, EstadoCobranca::Falhou], true)) {
                return ['ignorado', $invoice->id];
            }

            if ($cobranca->valorCentavos !== $invoice->valor_centavos) {
                Log::error('cobranca.valor_divergente', [
                    'module' => 'cobranca',
                    'action' => 'processar_evento',
                    'invoice_id' => $invoice->id,
                    'esperado_centavos' => $invoice->valor_centavos,
                    'recebido_centavos' => $cobranca->valorCentavos,
                ]);

                throw new \UnexpectedValueException('Valor pago diferente do valor da invoice.');
            }

            $pagoEm = $cobranca->pagoEm !== null ? CarbonImmutable::instance($cobranca->pagoEm) : CarbonImmutable::now();
            $invoice->forceFill([
                'status' => EstadoCobranca::Paga,
                'id_externo' => $cobranca->referencia->id,
                'checkout_id_externo' => $invoice->checkout_id_externo ?? $cobranca->checkoutId,
                'pago_em' => $pagoEm,
            ])->save();

            $this->carteira->concederCreditos(
                $invoice->usuario_id,
                (int) $invoice->quantidade_creditos,
                $pagoEm->addMonthsNoOverflow((int) $invoice->validade_meses),
                $invoice->id,
                $pagoEm,
            );

            AuditLogger::log(
                action: 'cobranca.paga',
                subjectType: 'Invoice',
                subjectId: $invoice->id,
                actorType: 'system',
                usuarioId: $invoice->usuario_id,
                after: ['valor_centavos' => $invoice->valor_centavos, 'creditos' => $invoice->quantidade_creditos, 'provedor' => $invoice->provedor],
            );

            return ['processado', $invoice->id];
        });
    }

    /** @return array{0: 'processado'|'ignorado', 1: string|null} */
    private function encerrarCheckout(ProviderEvent $evento, EstadoCobranca $novo, string $motivo): array
    {
        $invoice = $this->localizar($evento, null);

        if ($invoice === null) {
            return $this->semInvoice($evento, 'evento de checkout sem invoice correspondente');
        }

        return DB::transaction(function () use ($invoice, $novo, $motivo): array {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);

            if (! $invoice->status->podeIrPara($novo)) {
                return ['ignorado', $invoice->id];
            }

            $invoice->forceFill(['status' => $novo, 'motivo_encerramento' => $motivo])->save();

            return ['processado', $invoice->id];
        });
    }

    private function localizar(ProviderEvent $evento, ?CobrancaNoProvedor $cobranca): ?Invoice
    {
        $referencia = $cobranca->referenciaInterna ?? $evento->referencia_externa;
        $checkout = $cobranca->checkoutId ?? $evento->checkout_id_externo;

        if ($referencia !== null && Str::isUuid($referencia)) {
            $porId = Invoice::query()->where('provedor', $evento->provedor)->find($referencia);

            if ($porId !== null) {
                return $porId;
            }
        }

        if ($checkout !== null) {
            $porCheckout = Invoice::query()->where('provedor', $evento->provedor)->where('checkout_id_externo', $checkout)->first();

            if ($porCheckout !== null) {
                return $porCheckout;
            }
        }

        return $evento->cobranca_id_externo === null ? null : Invoice::query()
            ->where('provedor', $evento->provedor)
            ->where('id_externo', $evento->cobranca_id_externo)
            ->first();
    }

    /** @return array{0: 'ignorado', 1: null} */
    private function semInvoice(ProviderEvent $evento, string $motivo): array
    {
        // Dinheiro que não casa com nenhuma compra: precisa de olhar humano.
        Log::error('cobranca.evento_sem_invoice', [
            'module' => 'cobranca',
            'action' => 'processar_evento',
            'provider_event_id' => $evento->id,
            'tipo' => $evento->tipo,
            'motivo' => $motivo,
        ]);

        return ['ignorado', null];
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Cobranca;

use App\Domain\Cobranca\PaymentProvider;
use App\Domain\Cobranca\WebhookBruto;
use App\Domain\Cobranca\WebhookInvalido;
use App\Jobs\ProcessarEventoDoProvedor;
use App\Models\ProviderEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * STORY-076 CA-4 / ADR-010 §3 — porta de entrada dos webhooks.
 *
 * Autentica (via adaptador), grava o evento com `ON CONFLICT DO NOTHING` em
 * `UNIQUE (provedor, evento_id)` e só então enfileira o processamento. Evento repetido já
 * tratado é ignorado; repetido mas ainda não tratado (falhou antes) é reenfileirado.
 */
final class ReceberWebhook
{
    public function __construct(private readonly PaymentProvider $provedor) {}

    /**
     * @return 'novo'|'duplicado'|'reenfileirado'
     *
     * @throws WebhookInvalido
     */
    public function tratar(WebhookBruto $requisicao): string
    {
        $evento = $this->provedor->parseWebhook($requisicao);

        $id = (string) Str::uuid7();
        $inseridos = DB::table('provider_events')->insertOrIgnore([
            'id' => $id,
            'provedor' => $evento->provedor,
            'evento_id' => $evento->eventoId,
            'tipo' => $evento->tipo->value,
            'cobranca_id_externo' => $evento->cobrancaId,
            'checkout_id_externo' => $evento->checkoutId,
            'referencia_externa' => $evento->referenciaInterna,
            'assinatura_id_externo' => $evento->assinaturaId,
            'payload' => json_encode($evento->payload, JSON_THROW_ON_ERROR),
            'status' => 'recebido',
            'recebido_em' => now()->format('Y-m-d H:i:sP'),
        ]);

        if ($inseridos === 1) {
            $this->enfileirar($id);

            return 'novo';
        }

        $existente = ProviderEvent::query()
            ->where('provedor', $evento->provedor)
            ->where('evento_id', $evento->eventoId)
            ->firstOrFail();

        if (in_array($existente->status, ['recebido', 'erro'], true)) {
            $this->enfileirar($existente->id);

            return 'reenfileirado';
        }

        return 'duplicado';
    }

    /**
     * O evento já está salvo: se o processamento falhar aqui, o webhook continua respondendo 200
     * (o Asaas não precisa reenviar) e `cobranca:reprocessar-eventos` retoma o que ficou.
     */
    private function enfileirar(string $eventoId): void
    {
        try {
            ProcessarEventoDoProvedor::dispatch($eventoId);
        } catch (Throwable $e) {
            Log::error('cobranca.evento_nao_processado', [
                'module' => 'cobranca',
                'action' => 'receber_webhook',
                'provider_event_id' => $eventoId,
                'erro' => $e::class,
            ]);
        }
    }
}

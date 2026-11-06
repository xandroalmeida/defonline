<?php

declare(strict_types=1);

namespace App\Services\Cobranca;

use App\Domain\Cobranca\EstadoCobranca;
use App\Domain\Cobranca\ProvedorIndisponivel;
use App\Models\Invoice;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * STORY-101 — com o webhook fora, quem pagou não pode esperar a conciliação horária olhando "Aguardando o
 * pagamento". A tela de acompanhamento pede esta conferência a cada ciclo do polling: depois de 1 minuto
 * sem resposta, pergunta ao provedor no máximo a cada 30 s por cobrança. O pagamento encontrado passa pelo
 * mesmo caminho da conciliação, que aplica uma vez só.
 */
final class ConferirPagamentoAtrasado
{
    private const ESPERA_SEGUNDOS = 60;

    private const INTERVALO_SEGUNDOS = 30;

    public function __construct(private readonly ConciliarCobrancas $conciliar) {}

    public function executar(Invoice $invoice): void
    {
        if ($invoice->status !== EstadoCobranca::Pendente
            || $invoice->checkout_id_externo === null
            || $invoice->created_at === null
            || $invoice->created_at->greaterThan(now()->subSeconds(self::ESPERA_SEGUNDOS))
            || ! Cache::add("conferir-pagamento:{$invoice->id}", 1, now()->addSeconds(self::INTERVALO_SEGUNDOS))) {
            return;
        }

        try {
            $this->conciliar->conciliarInvoice($invoice);
        } catch (ProvedorIndisponivel) {
            // O webhook e a conciliação seguem como rede de segurança; a tela continua esperando.
            Log::warning('cobranca.conferencia_sem_resposta', ['module' => 'cobranca', 'action' => 'conferir_pagamento', 'invoice_id' => $invoice->id]);

            return;
        }

        // Pagou e o aviso do provedor não tinha chegado: a equipe precisa saber.
        if ($invoice->fresh()?->status === EstadoCobranca::Paga) {
            Log::error('cobranca.webhook_nao_chegou', ['module' => 'cobranca', 'action' => 'conferir_pagamento', 'invoice_id' => $invoice->id]);
        }
    }
}

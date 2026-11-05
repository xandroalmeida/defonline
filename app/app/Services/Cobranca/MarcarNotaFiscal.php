<?php

declare(strict_types=1);

namespace App\Services\Cobranca;

use App\Domain\Cobranca\EstadoCobranca;
use App\Models\Invoice;
use App\Models\Usuario;
use App\Observabilidade\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * STORY-085 CA-6 e CA-7 — controle da nota fiscal. A emissão é fora do código no MVP (espec §2.4): o admin emite
 * onde emite e aqui só marca a cobrança paga como "nota emitida", com quem marcou, quando e o número (opcional).
 */
final class MarcarNotaFiscal
{
    public const NUMERO_MAX = 40;

    /** @throws ReembolsoNaoRealizado cobrança que não está paga, ou nota já marcada */
    public function marcar(Invoice $invoice, Usuario $admin, ?string $numero): void
    {
        DB::transaction(function () use ($invoice, $admin, $numero): void {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);

            if ($invoice->status !== EstadoCobranca::Paga) {
                throw new ReembolsoNaoRealizado('Só cobrança paga recebe nota fiscal.');
            }

            if ($invoice->nf_emitida_em !== null) {
                throw new ReembolsoNaoRealizado('A nota fiscal desta cobrança já foi marcada como emitida.');
            }

            $invoice->forceFill([
                'nf_emitida_em' => CarbonImmutable::now(),
                'nf_emitida_por_id' => $admin->id,
                'nf_numero' => $numero,
            ])->save();

            AuditLogger::log(
                action: 'cobranca.nota_fiscal_emitida',
                subjectType: 'Invoice',
                subjectId: $invoice->id,
                actorType: 'user',
                actorId: $admin->id,
                usuarioId: $invoice->usuario_id,
                after: ['numero' => $numero, 'valor_centavos' => $invoice->valor_centavos],
            );
        });
    }
}

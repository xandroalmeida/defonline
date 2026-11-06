<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * STORY-076 / ADR-010 §3 — um webhook recebido. `UNIQUE (provedor, evento_id)` é a idempotência
 * de entrada. O payload é o corpo do provedor sem dado de cartão.
 *
 * @property string $id
 * @property string $provedor
 * @property string $evento_id
 * @property string $tipo
 * @property string|null $cobranca_id_externo
 * @property string|null $checkout_id_externo
 * @property string|null $referencia_externa
 * @property string|null $assinatura_id_externo
 * @property string|null $invoice_id
 * @property array<string, mixed> $payload
 * @property int|null $valor_estornado_centavos
 * @property string $status recebido|processado|ignorado|erro
 * @property string|null $erro
 * @property Carbon $recebido_em
 * @property Carbon|null $processado_em
 */
final class ProviderEvent extends Model
{
    use HasUuids;

    public $timestamps = false;

    /** Com offset (IDR-025): a sessão do banco já é UTC; o offset explícito é só defesa contra outra sessão. */
    protected $dateFormat = 'Y-m-d H:i:sP';

    protected $table = 'provider_events';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'recebido_em' => 'datetime',
            'processado_em' => 'datetime',
        ];
    }
}

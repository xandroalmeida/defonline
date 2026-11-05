<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Cobranca\StatusLote;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * STORY-073 — lote de créditos avulsos do Usuário (consumo FIFO por vencimento, espec §4.3).
 *
 * @property string $id
 * @property string $usuario_id
 * @property string|null $invoice_id
 * @property int $quantidade_comprada
 * @property int $quantidade_restante
 * @property StatusLote $status
 * @property Carbon $data_compra
 * @property Carbon $data_expiracao
 */
#[Fillable(['usuario_id', 'invoice_id', 'quantidade_comprada', 'quantidade_restante', 'status', 'data_compra', 'data_expiracao'])]
final class LoteCredito extends Model
{
    use HasUuids;

    /** Com offset: o banco local roda em America/Sao_Paulo e a aplicação em UTC (mesmo ajuste de Recomendacao). */
    protected $dateFormat = 'Y-m-d H:i:sP';

    protected $table = 'lotes_credito';

    protected function casts(): array
    {
        return [
            'status' => StatusLote::class,
            'data_compra' => 'datetime',
            'data_expiracao' => 'datetime',
        ];
    }
}

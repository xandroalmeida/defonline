<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * STORY-072 — item coberto por um cupom: um plano em um ciclo, ou um pacote de créditos.
 *
 * @property string $id
 * @property string $cupom_id
 * @property string|null $plano_id
 * @property string|null $ciclo
 * @property string|null $pacote_credito_id
 * @property Carbon|null $created_at
 */
#[Fillable(['cupom_id', 'plano_id', 'ciclo', 'pacote_credito_id'])]
final class CupomItem extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    public const CICLOS = ['mensal', 'anual'];

    protected $table = 'cupom_itens';

    /** @return BelongsTo<Plano, $this> */
    public function plano(): BelongsTo
    {
        return $this->belongsTo(Plano::class);
    }

    /** @return BelongsTo<PacoteCredito, $this> */
    public function pacote(): BelongsTo
    {
        return $this->belongsTo(PacoteCredito::class, 'pacote_credito_id');
    }

    /** Chave usada no formulário: "plano:{id}:mensal" ou "pacote:{id}". */
    public function chave(): string
    {
        return $this->plano_id !== null
            ? "plano:{$this->plano_id}:{$this->ciclo}"
            : "pacote:{$this->pacote_credito_id}";
    }
}

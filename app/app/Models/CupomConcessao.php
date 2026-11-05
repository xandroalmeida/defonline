<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * STORY-072 — concessão direta de um cupom a uma conta (o benefício do beta). O checkout
 * (STORY-083) aplica as concessões automaticamente.
 *
 * @property string $id
 * @property string $cupom_id
 * @property string $usuario_id
 * @property int|null $ciclos_restantes
 * @property string|null $concedida_por_usuario_id
 * @property Carbon $concedida_em
 */
#[Fillable(['cupom_id', 'usuario_id', 'ciclos_restantes', 'concedida_por_usuario_id', 'concedida_em'])]
final class CupomConcessao extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'cupom_concessoes';

    protected function casts(): array
    {
        return ['concedida_em' => 'datetime'];
    }

    /** @return BelongsTo<Usuario, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class)->withTrashed();
    }
}

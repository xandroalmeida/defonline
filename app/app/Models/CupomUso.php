<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * STORY-072 — uso efetivo de um cupom por um usuário. Gravado pelo checkout (STORY-083).
 *
 * @property string $id
 * @property string $cupom_id
 * @property string $usuario_id
 * @property int $desconto_centavos
 * @property int|null $ciclos_restantes
 * @property Carbon $usado_em
 */
#[Fillable(['cupom_id', 'usuario_id', 'desconto_centavos', 'ciclos_restantes', 'usado_em'])]
final class CupomUso extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'cupom_usos';

    protected function casts(): array
    {
        return ['usado_em' => 'datetime'];
    }

    /** @return BelongsTo<Usuario, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class)->withTrashed();
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * STORY-103 — linha do tempo da demanda de captação: nota da equipe ou registro automático de
 * mudança de etapa ou de responsável. Só se acrescenta; nada é editado nem apagado.
 *
 * @property string $id
 * @property string $demanda_captacao_id
 * @property string|null $autor_id
 * @property string $tipo
 * @property string $texto
 * @property Carbon $created_at
 * @property-read Usuario|null $autor
 */
#[Fillable(['demanda_captacao_id', 'autor_id', 'tipo', 'texto'])]
final class DemandaCaptacaoEvento extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    public const NOTA = 'nota';

    public const STATUS = 'status';

    public const RESPONSAVEL = 'responsavel';

    protected $table = 'demanda_captacao_eventos';

    /** @return BelongsTo<Usuario, $this> */
    public function autor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'autor_id')->withTrashed();
    }
}

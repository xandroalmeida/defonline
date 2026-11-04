<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * STORY-067 — termo do glossário do relatório, mantido no backoffice.
 *
 * @property string $id
 * @property string $termo
 * @property string $definicao
 * @property int $ordem
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['termo', 'definicao', 'ordem'])]
final class GlossarioTermo extends Model
{
    use HasUuids;

    protected $table = 'glossario_termos';
}

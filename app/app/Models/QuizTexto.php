<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * STORY-106 — texto de uma pergunta do quiz alterado pela equipe (só existe se foi alterado).
 *
 * @property string $campo
 * @property string $rotulo
 * @property string $auxiliar
 * @property string $ajuda
 * @property string|null $alterado_por_usuario_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['campo', 'rotulo', 'auxiliar', 'ajuda', 'alterado_por_usuario_id'])]
final class QuizTexto extends Model
{
    protected $table = 'quiz_textos';

    protected $primaryKey = 'campo';

    protected $keyType = 'string';

    public $incrementing = false;
}

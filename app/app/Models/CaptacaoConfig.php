<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * STORY-102 / PDR-021 — e-mail da equipe que recebe as demandas de captação (linha única).
 * Sem e-mail, a demanda fica registrada e a equipe não recebe e-mail.
 *
 * @property string $id
 * @property string|null $email
 * @property string|null $alterado_por_usuario_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['email', 'alterado_por_usuario_id'])]
final class CaptacaoConfig extends Model
{
    use HasUuids;

    protected $table = 'captacao_config';

    public static function atual(): self
    {
        return self::query()->first() ?? self::query()->create(['email' => null]);
    }

    /** O e-mail da equipe, ou null quando não há um cadastrado. */
    public static function emailDaEquipe(): ?string
    {
        $email = trim((string) self::query()->value('email'));

        return $email === '' ? null : $email;
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * STORY-071 / PDR-020 — configuração do trial (linha única), mantida no backoffice.
 *
 * @property string $id
 * @property bool $ativo
 * @property int $dias
 * @property int $analises
 * @property int $limite_ip_30d
 * @property string|null $alterado_por_usuario_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['ativo', 'dias', 'analises', 'limite_ip_30d', 'alterado_por_usuario_id'])]
final class TrialConfig extends Model
{
    use HasUuids;

    protected $table = 'trial_config';

    protected function casts(): array
    {
        return ['ativo' => 'boolean'];
    }

    /** A linha única criada pela migration. */
    public static function atual(): self
    {
        return self::query()->firstOrFail();
    }
}

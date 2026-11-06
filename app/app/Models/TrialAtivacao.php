<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * STORY-074 CA-2 e CA-7 — uma ativação de trial: o CPF (em HMAC, único).
 *
 * @property string $id
 * @property string|null $usuario_id
 * @property string $cpf_hash
 * @property Carbon $ativado_em
 */
#[Fillable(['usuario_id', 'cpf_hash', 'ativado_em'])]
final class TrialAtivacao extends Model
{
    use HasUuids;

    protected $table = 'trial_ativacoes';

    protected function casts(): array
    {
        return ['ativado_em' => 'datetime'];
    }
}

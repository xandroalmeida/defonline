<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * STORY-074 CA-2 — um CNPJ (em HMAC, único) que já recebeu análise de trial, e a conta que a recebeu.
 *
 * @property string $id
 * @property string $cnpj_hash
 * @property string|null $usuario_id
 */
#[Fillable(['cnpj_hash', 'usuario_id'])]
final class TrialEmpresa extends Model
{
    use HasUuids;

    protected $table = 'trial_empresas';
}

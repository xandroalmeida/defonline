<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * STORY-071 / PDR-020 — plano de assinatura do catálogo, mantido no backoffice.
 *
 * @property string $id
 * @property string $codigo
 * @property string $nome
 * @property int $cota_mensal
 * @property int $preco_mensal_centavos
 * @property int $preco_anual_centavos
 * @property bool $ativo
 * @property int $ordem
 * @property string|null $alterado_por_usuario_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['codigo', 'nome', 'cota_mensal', 'preco_mensal_centavos', 'preco_anual_centavos', 'ativo', 'ordem', 'alterado_por_usuario_id'])]
final class Plano extends Model
{
    use HasUuids;

    protected $table = 'planos';

    protected function casts(): array
    {
        return ['ativo' => 'boolean'];
    }
}

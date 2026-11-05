<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * STORY-071 / PDR-020 — pacote de créditos avulsos do catálogo, mantido no backoffice.
 *
 * @property string $id
 * @property string $codigo
 * @property string $nome
 * @property int $quantidade_creditos
 * @property int $preco_centavos
 * @property int $validade_meses
 * @property bool $ativo
 * @property int $ordem
 * @property string|null $alterado_por_usuario_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['codigo', 'nome', 'quantidade_creditos', 'preco_centavos', 'validade_meses', 'ativo', 'ordem', 'alterado_por_usuario_id'])]
final class PacoteCredito extends Model
{
    use HasUuids;

    protected $table = 'pacotes_credito';

    protected function casts(): array
    {
        return ['ativo' => 'boolean'];
    }
}

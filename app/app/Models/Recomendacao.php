<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * STORY-068 / PDR-016 — texto da matriz por segmento, indicador e faixa do farol.
 *
 * @property string $id
 * @property string $setor
 * @property string $indicador
 * @property string $farol
 * @property string $faixa
 * @property string $texto
 * @property Carbon|null $editado_em
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['texto', 'editado_em'])]
final class Recomendacao extends Model
{
    use HasUuids;

    protected $table = 'recomendacoes';

    /**
     * Grava com o fuso (IDR-025): a sessão do banco já é UTC; o offset explícito é só defesa
     * contra outra sessão, que poderia deslocar a versão da matriz (mês da edição).
     */
    protected $dateFormat = 'Y-m-d H:i:sP';

    protected function casts(): array
    {
        return ['editado_em' => 'datetime'];
    }
}

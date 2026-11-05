<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Cobranca\Origem;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * STORY-073 CA-8 — o registro de cada análise cobrada: qual Diagnóstico, qual Empresa Analisada
 * e de onde saiu o saldo (cota ou crédito).
 *
 * @property string $id
 * @property string $usuario_id
 * @property string|null $diagnostico_id
 * @property string|null $empresa_analisada_id
 * @property Origem $origem
 * @property string|null $assinatura_id
 * @property string|null $lote_credito_id
 * @property string|null $quiz_id
 * @property Carbon $consumido_em
 */
#[Fillable(['usuario_id', 'diagnostico_id', 'empresa_analisada_id', 'origem', 'assinatura_id', 'lote_credito_id', 'quiz_id', 'consumido_em'])]
final class ConsumoAnalise extends Model
{
    use HasUuids;

    protected $table = 'consumos_analise';

    protected function casts(): array
    {
        return [
            'origem' => Origem::class,
            'consumido_em' => 'datetime',
        ];
    }
}

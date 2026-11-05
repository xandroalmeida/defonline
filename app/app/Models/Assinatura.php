<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Cobranca\Carteira;
use App\Domain\Cobranca\StatusAssinatura;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * STORY-073 — assinatura do Usuário, inclusive o trial (`trial` verdadeiro e `plano_id` nulo).
 * Cota e vencimento são o que foi concedido; quem concede é a {@see Carteira}.
 *
 * @property string $id
 * @property string $usuario_id
 * @property string|null $plano_id
 * @property bool $trial
 * @property StatusAssinatura $status
 * @property string|null $ciclo
 * @property Carbon $data_inicio
 * @property Carbon $proximo_vencimento
 * @property Carbon|null $data_cancelamento
 * @property int $cota_snapshot
 * @property int $cota_restante_ciclo
 * @property Carbon|null $aviso_d2_em
 * @property Carbon|null $aviso_d0_em
 */
#[Fillable(['usuario_id', 'plano_id', 'trial', 'status', 'ciclo', 'data_inicio', 'proximo_vencimento', 'data_cancelamento', 'cota_snapshot', 'cota_restante_ciclo', 'aviso_d2_em', 'aviso_d0_em'])]
final class Assinatura extends Model
{
    use HasUuids;

    protected $table = 'assinaturas';

    protected function casts(): array
    {
        return [
            'trial' => 'boolean',
            'status' => StatusAssinatura::class,
            'data_inicio' => 'datetime',
            'proximo_vencimento' => 'datetime',
            'data_cancelamento' => 'datetime',
            'aviso_d2_em' => 'datetime',
            'aviso_d0_em' => 'datetime',
        ];
    }
}

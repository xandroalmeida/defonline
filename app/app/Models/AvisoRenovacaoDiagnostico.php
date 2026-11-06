<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * STORY-099 — o que já aconteceu no aviso para renovar um diagnóstico. Uma linha por diagnóstico.
 *
 * @property string $id
 * @property string $diagnostico_id
 * @property Carbon|null $aviso_em
 * @property Carbon|null $lembrete_em
 * @property bool $sem_lembrete
 * @property Carbon|null $alerta_dispensado_em
 */
#[Fillable(['diagnostico_id', 'aviso_em', 'lembrete_em', 'sem_lembrete', 'alerta_dispensado_em'])]
final class AvisoRenovacaoDiagnostico extends Model
{
    use HasUuids;

    protected $table = 'avisos_renovacao_diagnostico';

    protected function casts(): array
    {
        return [
            'aviso_em' => 'datetime',
            'lembrete_em' => 'datetime',
            'sem_lembrete' => 'boolean',
            'alerta_dispensado_em' => 'datetime',
        ];
    }
}

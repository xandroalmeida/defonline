<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Captacao\PrazoRetorno;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * STORY-102 / PDR-021 — demanda de captação registrada ao concluir um diagnóstico com Q17 = sim.
 * Uma por diagnóstico. Lida sem o Global Scope de tenant do Diagnostico/EmpresaAnalisada, porque
 * quem consulta a lista é a equipe no backoffice.
 *
 * @property string $id
 * @property string $diagnostico_id
 * @property string $usuario_id
 * @property string $empresa_analisada_id
 * @property string|null $valor_captar
 * @property bool $autoriza_contato
 * @property Carbon|null $equipe_avisada_em
 * @property Carbon|null $cliente_avisado_em
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Usuario $usuario
 * @property-read EmpresaAnalisada $empresa
 */
#[Fillable(['diagnostico_id', 'usuario_id', 'empresa_analisada_id', 'valor_captar', 'autoriza_contato', 'equipe_avisada_em', 'cliente_avisado_em'])]
final class DemandaCaptacao extends Model
{
    use HasUuids;

    protected $table = 'demandas_captacao';

    protected function casts(): array
    {
        return [
            'valor_captar' => 'decimal:2',
            'autoriza_contato' => 'boolean',
            'equipe_avisada_em' => 'datetime',
            'cliente_avisado_em' => 'datetime',
        ];
    }

    /** @return BelongsTo<Usuario, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id')->withTrashed();
    }

    /**
     * Sem o Global Scope de tenant (quem lê é a equipe) e com as excluídas.
     *
     * @return BelongsTo<EmpresaAnalisada, $this>
     */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(EmpresaAnalisada::class, 'empresa_analisada_id')->withoutGlobalScopes();
    }

    public function prazoRetorno(): CarbonImmutable
    {
        return PrazoRetorno::aPartirDe(CarbonImmutable::instance($this->created_at ?? now()));
    }
}

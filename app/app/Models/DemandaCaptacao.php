<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Captacao\PrazoRetorno;
use App\Domain\Captacao\StatusDemanda;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
 * @property StatusDemanda $status
 * @property string|null $responsavel_id
 * @property Carbon|null $status_alterado_em
 * @property-read Usuario $usuario
 * @property-read Usuario|null $responsavel
 * @property-read EmpresaAnalisada $empresa
 */
#[Fillable(['diagnostico_id', 'usuario_id', 'empresa_analisada_id', 'valor_captar', 'autoriza_contato', 'equipe_avisada_em', 'cliente_avisado_em', 'status', 'responsavel_id', 'status_alterado_em'])]
final class DemandaCaptacao extends Model
{
    use HasUuids;

    protected $table = 'demandas_captacao';

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'nova'];

    protected function casts(): array
    {
        return [
            'valor_captar' => 'decimal:2',
            'autoriza_contato' => 'boolean',
            'equipe_avisada_em' => 'datetime',
            'cliente_avisado_em' => 'datetime',
            'status' => StatusDemanda::class,
            'status_alterado_em' => 'datetime',
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

    /** @return BelongsTo<Usuario, $this> */
    public function responsavel(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'responsavel_id')->withTrashed();
    }

    /** @return HasMany<DemandaCaptacaoEvento, $this> */
    public function eventos(): HasMany
    {
        return $this->hasMany(DemandaCaptacaoEvento::class, 'demanda_captacao_id');
    }

    /** Em aberto e com o prazo de retorno já passado (o dia limite ainda conta como no prazo). */
    public function atrasada(?CarbonImmutable $agora = null): bool
    {
        $hoje = ($agora ?? CarbonImmutable::now())->setTimezone((string) config('app.display_timezone'))->startOfDay();

        return $this->autoriza_contato && $this->status->emAberto() && $this->prazoRetorno()->lt($hoje);
    }

    public function prazoRetorno(): CarbonImmutable
    {
        return PrazoRetorno::aPartirDe(CarbonImmutable::instance($this->created_at ?? now()));
    }
}

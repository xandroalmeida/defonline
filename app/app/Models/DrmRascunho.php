<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\RegistraPersonificacao;
use App\Models\Scopes\BelongsToUsuarioScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\ArrayObject;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Rascunho do lançamento mensal (ADR-007): mesmo padrão de {@see QuizRascunho}, com
 * uma chave a mais (a competência — setembro pode estar em rascunho enquanto se
 * começa outubro). Vive aqui até virar lançamento (soft delete, preservando a
 * trilha) ou expirar (90 dias, renovado a cada save).
 *
 * @property string $id
 * @property string $usuario_id
 * @property string $empresa_analisada_id
 * @property Carbon $competencia
 * @property string $origem
 * @property int|null $base_versao
 * @property ArrayObject<string, mixed> $rascunho_payload
 * @property Carbon $expires_at
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'usuario_id',
    'empresa_analisada_id',
    'competencia',
    'origem',
    'base_versao',
    'rascunho_payload',
    'expires_at',
])]
#[ScopedBy([BelongsToUsuarioScope::class])]
final class DrmRascunho extends Model
{
    use HasUuids, RegistraPersonificacao, SoftDeletes;

    public const TTL_DIAS = 90;

    protected $table = 'drm_rascunhos';

    protected $casts = [
        'competencia' => 'date',
        'base_versao' => 'integer',
        'rascunho_payload' => AsArrayObject::class,
        'expires_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * @param  Builder<self>  $query
     */
    public function scopeAtivos(Builder $query): void
    {
        $query->where('expires_at', '>', now());
    }

    public static function paraCompetencia(EmpresaAnalisada $empresa, string $competenciaDia01): ?self
    {
        return self::query()
            ->where('empresa_analisada_id', $empresa->id)
            ->where('competencia', $competenciaDia01)
            ->ativos()
            ->first();
    }

    /** O rascunho ativo mais recentemente salvo da empresa, qualquer que seja a competência. */
    public static function maisRecente(EmpresaAnalisada $empresa): ?self
    {
        return self::query()
            ->where('empresa_analisada_id', $empresa->id)
            ->ativos()
            ->latest('updated_at')
            ->first();
    }
}

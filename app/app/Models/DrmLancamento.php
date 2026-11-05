<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\RegistraPersonificacao;
use App\Models\Scopes\BelongsToUsuarioScope;
use Database\Factories\DrmLancamentoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\ArrayObject;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Uma VERSÃO de uma competência do DRM (ADR-007) — append-only quanto ao conteúdo.
 *
 * Imutáveis após o `INSERT`: competência, versão, setor, payload, hash, demonstração,
 * versão do motor e `gerado_em`. Mutáveis, por serem ciclo de vida e nunca conteúdo:
 * `substituido_por_id` (uma única vez, de `NULL` para um id) e `deleted_at`. Retificar
 * é inserir a versão N+1 e apontar a N para ela (STORY-050).
 *
 * `alertas_aceitos` fica FORA do `payload_hash`: é ato do usuário, não insumo de cálculo.
 *
 * @property string $id
 * @property string $usuario_id
 * @property string $empresa_analisada_id
 * @property Carbon $competencia
 * @property int $versao
 * @property string|null $substituido_por_id
 * @property string $setor
 * @property ArrayObject<string, mixed> $lancamento_payload
 * @property string $payload_hash
 * @property ArrayObject<string, mixed> $demonstracao
 * @property list<array<string, mixed>> $alertas_aceitos
 * @property string $motor_drm_version
 * @property Carbon $gerado_em
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'usuario_id',
    'empresa_analisada_id',
    'competencia',
    'versao',
    'substituido_por_id',
    'setor',
    'lancamento_payload',
    'payload_hash',
    'demonstracao',
    'alertas_aceitos',
    'motor_drm_version',
    'gerado_em',
])]
#[ScopedBy([BelongsToUsuarioScope::class])]
final class DrmLancamento extends Model
{
    /** @use HasFactory<DrmLancamentoFactory> */
    use HasFactory, HasUuids, RegistraPersonificacao, SoftDeletes;

    /** Colunas de conteúdo: gravadas uma vez, nunca alteradas. */
    public const IMUTAVEIS = [
        'usuario_id', 'empresa_analisada_id', 'competencia', 'versao', 'setor', 'lancamento_payload',
        'payload_hash', 'demonstracao', 'alertas_aceitos', 'motor_drm_version', 'gerado_em',
    ];

    protected $table = 'drm_lancamentos';

    protected $casts = [
        'competencia' => 'date',
        'versao' => 'integer',
        'lancamento_payload' => AsArrayObject::class,
        'demonstracao' => AsArrayObject::class,
        'alertas_aceitos' => 'array',
        'gerado_em' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        self::updating(function (self $lancamento): void {
            $alteradas = array_intersect(self::IMUTAVEIS, array_keys($lancamento->getDirty()));
            if ($alteradas !== []) {
                throw new LogicException('Lançamento do DRM é imutável (ADR-007); colunas alteradas: '.implode(', ', $alteradas));
            }

            // O ponteiro de vigência só transiciona de NULL para um id, jamais de volta.
            if ($lancamento->isDirty('substituido_por_id') && $lancamento->getOriginal('substituido_por_id') !== null) {
                throw new LogicException('O ponteiro de vigência não pode ser alterado depois de definido (ADR-007).');
            }
        });
    }

    /**
     * @return BelongsTo<EmpresaAnalisada, $this>
     */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(EmpresaAnalisada::class, 'empresa_analisada_id');
    }

    /**
     * A versão vigente: a que nenhuma outra substituiu.
     *
     * @param  Builder<self>  $query
     */
    public function scopeVigente(Builder $query): void
    {
        $query->whereNull('substituido_por_id');
    }

    public static function vigenteDaCompetencia(EmpresaAnalisada $empresa, string $competenciaDia01): ?self
    {
        return self::query()
            ->vigente()
            ->where('empresa_analisada_id', $empresa->id)
            ->where('competencia', $competenciaDia01)
            ->first();
    }

    /** O lançamento vigente mais recente de uma competência anterior à dada — a fonte do pré-preenchimento. */
    public static function anteriorA(EmpresaAnalisada $empresa, string $competenciaDia01): ?self
    {
        return self::query()
            ->vigente()
            ->where('empresa_analisada_id', $empresa->id)
            ->where('competencia', '<', $competenciaDia01)
            ->orderByDesc('competencia')
            ->first();
    }

    protected static function newFactory(): DrmLancamentoFactory
    {
        return DrmLancamentoFactory::new();
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Scopes\BelongsToUsuarioScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\ArrayObject;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Rascunho parcial do quiz de diagnóstico (STORY-027 CA-6).
 *
 * Tabela separada de `diagnosticos` por decisão da IDR-010 — `diagnosticos` é
 * snapshot imutável; rascunho vive aqui até virar Diagnóstico (no submit final)
 * ou expirar (90 dias default — espec §6.4).
 *
 * Multi-tenancy via Global Scope (ADR-003 §Decisão 1).
 *
 * @property string $id
 * @property string $usuario_id
 * @property string $empresa_analisada_id
 * @property ArrayObject<string, mixed> $quiz_payload
 * @property int $ultimo_bloco_preenchido
 * @property Carbon $expires_at
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'usuario_id',
    'empresa_analisada_id',
    'quiz_payload',
    'ultimo_bloco_preenchido',
    'expires_at',
])]
#[ScopedBy([BelongsToUsuarioScope::class])]
final class QuizRascunho extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'quiz_rascunhos';

    protected $casts = [
        'quiz_payload' => AsArrayObject::class,
        'ultimo_bloco_preenchido' => 'integer',
        'expires_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Usuario, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    /**
     * @return BelongsTo<EmpresaAnalisada, $this>
     */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(EmpresaAnalisada::class, 'empresa_analisada_id');
    }

    /**
     * Rascunhos ainda válidos (não expirados).
     *
     * @param  Builder<self>  $query
     */
    public function scopeAtivos(Builder $query): void
    {
        $query->where('expires_at', '>', now());
    }

    /**
     * Recupera o rascunho ativo do Usuário autenticado para uma Empresa Analisada,
     * ou `null` se não existir / estiver expirado.
     *
     * Reusa o Global Scope — chamadores não precisam filtrar por `usuario_id`.
     */
    public static function paraEmpresa(EmpresaAnalisada $empresa): ?self
    {
        return self::query()
            ->where('empresa_analisada_id', $empresa->id)
            ->ativos()
            ->first();
    }
}

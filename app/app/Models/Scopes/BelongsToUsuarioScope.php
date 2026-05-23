<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * Global Scope multi-tenancy (ADR-003 §Decisão 1).
 *
 * Filtra `WHERE usuario_id = Auth::id()` em toda query de modelos tenant-scoped
 * (a partir de STORY-014: `EmpresaAnalisada`). Quando não há Usuário autenticado
 * (jobs em fila, comandos, seeds, tinker), o scope se desativa — assim código
 * de sistema continua funcionando.
 *
 * Bypass explícito: `Model::withoutGlobalScope(BelongsToUsuarioScope::class)`.
 *
 * @implements Scope<Model>
 */
final class BelongsToUsuarioScope implements Scope
{
    /**
     * @param  Builder<covariant Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        $usuarioId = Auth::id();

        if ($usuarioId === null) {
            return;
        }

        $builder->where($model->qualifyColumn('usuario_id'), $usuarioId);
    }
}

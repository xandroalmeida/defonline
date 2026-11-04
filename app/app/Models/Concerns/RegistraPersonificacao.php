<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Usuario;
use App\Support\Personificacao;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * STORY-066 CA-6 — `personificado_por_id` guarda o admin que fez a última gravação
 * pelo "Logar como". Volta a null quando o próprio usuário grava depois; o histórico
 * completo fica em `audit_logs`.
 */
trait RegistraPersonificacao
{
    protected static function bootRegistraPersonificacao(): void
    {
        static::saving(function (Model $registro): void {
            $registro->setAttribute('personificado_por_id', Personificacao::ativa()['admin_id'] ?? null);
        });
    }

    /** @return BelongsTo<Usuario, $this> */
    public function personificadoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'personificado_por_id')->withTrashed();
    }
}

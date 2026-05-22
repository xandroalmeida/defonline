<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UsuarioFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Usuário (ADR-003 §Agregado Usuario): pessoa física raiz do tenant.
 *
 * Mapeia a tabela `usuarios` criada na STORY-007 (Phase 1). PK UUID v7 (ADR-003 §Decisão 3).
 * Sobrescreve `getAuthPasswordName()` para a coluna `senha_hash` (ADR-003 §ER) — Laravel
 * já lida com `password` no array de credenciais via mapeamento automático.
 *
 * Soft delete + anonimização diferida (ADR-003 §Decisão 5) já têm colunas, mas a cascata
 * e o job entram em estória futura — aqui apenas declaramos o trait.
 */
#[Fillable(['cpf', 'nome', 'email', 'senha_hash', 'telefone'])]
#[Hidden(['senha_hash', 'remember_token'])]
final class Usuario extends Authenticatable
{
    /** @use HasFactory<UsuarioFactory> */
    use HasFactory, HasUuids, Notifiable, SoftDeletes;

    protected $table = 'usuarios';

    protected $casts = [
        'deleted_at' => 'datetime',
        'anonimizado_at' => 'datetime',
        'senha_hash' => 'hashed',
    ];

    public function getAuthPasswordName(): string
    {
        return 'senha_hash';
    }

    public function primeiroNome(): string
    {
        return explode(' ', trim($this->nome))[0] ?? $this->nome;
    }

    protected static function newFactory(): UsuarioFactory
    {
        return UsuarioFactory::new();
    }
}

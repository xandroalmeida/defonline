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
use Illuminate\Support\Carbon;

/**
 * Usuário (ADR-003 §Agregado Usuario): pessoa física raiz do tenant.
 *
 * Mapeia a tabela `usuarios` criada na STORY-007 (Phase 1). PK UUID v7 (ADR-003 §Decisão 3).
 * Sobrescreve `getAuthPasswordName()` para a coluna `senha_hash` (ADR-003 §ER) — Laravel
 * já lida com `password` no array de credenciais via mapeamento automático.
 *
 * Soft delete + anonimização diferida (ADR-003 §Decisão 5) já têm colunas, mas a cascata
 * e o job entram em estória futura — aqui apenas declaramos o trait.
 *
 * @property string $id
 * @property string $cpf
 * @property string $nome
 * @property string $email
 * @property string $senha_hash
 * @property string $telefone
 * @property Carbon|null $email_confirmed_at
 * @property Carbon|null $anonimizado_at
 * @property Carbon|null $deleted_at
 */
#[Fillable(['cpf', 'nome', 'email', 'senha_hash', 'telefone', 'email_confirmed_at'])]
#[Hidden(['senha_hash', 'remember_token'])]
final class Usuario extends Authenticatable
{
    /** @use HasFactory<UsuarioFactory> */
    use HasFactory, HasUuids, Notifiable, SoftDeletes;

    protected $table = 'usuarios';

    protected $casts = [
        'deleted_at' => 'datetime',
        'anonimizado_at' => 'datetime',
        'email_confirmed_at' => 'datetime',
        'senha_hash' => 'hashed',
    ];

    public function getAuthPasswordName(): string
    {
        return 'senha_hash';
    }

    public function primeiroNome(): string
    {
        return explode(' ', trim($this->nome))[0];
    }

    public function emailConfirmado(): bool
    {
        return $this->email_confirmed_at !== null;
    }

    /**
     * STORY-065 CA-1 — membro da equipe com acesso ao backoffice (PDR-011).
     */
    public function ehEquipeBackoffice(): bool
    {
        /** @var list<string> $equipe */
        $equipe = config('backoffice.equipe_emails', []);

        return in_array(mb_strtolower((string) $this->email), $equipe, true);
    }

    /**
     * STORY-065 CA-2 — CPF com só os 6 dígitos do meio visíveis: `***.456.789-**`.
     */
    public function cpfMascarado(): string
    {
        $digitos = preg_replace('/\D+/', '', (string) $this->cpf) ?? '';

        if (strlen($digitos) !== 11) {
            return '—';
        }

        return '***.'.substr($digitos, 3, 3).'.'.substr($digitos, 6, 3).'-**';
    }

    public function anonimizado(): bool
    {
        return $this->anonimizado_at !== null;
    }

    /** STORY-066 — colega da equipe fica de fora: assumir outro admin embaralharia a trilha. */
    public function podeSerPersonificadoPor(self $admin): bool
    {
        return $admin->ehEquipeBackoffice()
            && ! $this->ehEquipeBackoffice()
            && ! $this->trashed()
            && ! $this->anonimizado();
    }

    protected static function newFactory(): UsuarioFactory
    {
        return UsuarioFactory::new();
    }
}

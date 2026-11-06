<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\PermissaoAdmin;
use App\Support\Personificacao;
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
 * @property Carbon|null $aviso_renovacao_descadastrado_em
 * @property list<string>|null $permissoes_admin
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
        'permissoes_admin' => 'array',
        'email_confirmed_at' => 'datetime',
        'aviso_renovacao_descadastrado_em' => 'datetime',
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
     * STORY-065 / STORY-067 — tem acesso à área de administração.
     */
    public function ehEquipeBackoffice(): bool
    {
        return $this->ehAdminPorConfiguracao() || ! empty($this->permissoes_admin);
    }

    /**
     * STORY-067 — quem está em BACKOFFICE_EMAILS tem todas as permissões. Garante que
     * sempre há quem administre, mesmo sem nenhuma linha no banco.
     */
    public function ehAdminPorConfiguracao(): bool
    {
        /** @var list<string> $equipe */
        $equipe = config('backoffice.equipe_emails', []);

        return in_array(mb_strtolower((string) $this->email), $equipe, true);
    }

    public function temPermissao(PermissaoAdmin $permissao): bool
    {
        return $this->ehAdminPorConfiguracao()
            || in_array($permissao->value, $this->permissoes_admin ?? [], true);
    }

    /** Sessão de "Logar como" nunca usa o backoffice, seja quem for o usuário assumido. */
    public function podeUsarBackoffice(PermissaoAdmin $permissao): bool
    {
        return $this->temPermissao($permissao) && Personificacao::ativa() === null;
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

    /** PDR-012 §3 — quem também pode "Logar como" fica de fora, e o admin não assume a si mesmo. */
    public function podeSerPersonificadoPor(self $admin): bool
    {
        return $admin->temPermissao(PermissaoAdmin::LogarComo)
            && ! $this->temPermissao(PermissaoAdmin::LogarComo)
            && ! $this->trashed()
            && ! $this->anonimizado();
    }

    protected static function newFactory(): UsuarioFactory
    {
        return UsuarioFactory::new();
    }
}

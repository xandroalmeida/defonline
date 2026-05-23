<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Cnpj;
use App\Domain\Cpf;
use App\Domain\FonteEnriquecimento;
use App\Domain\SituacaoCadastral;
use App\Domain\TipoDocumento;
use App\Domain\Uf;
use App\Models\Scopes\BelongsToUsuarioScope;
use Database\Factories\EmpresaAnalisadaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Empresa Analisada — entidade alvo do diagnóstico (espec V2 §1.5.2).
 *
 * STORY-014 entrega o cadastro manual; STORY-015 vai usar o mesmo modelo para
 * gravar dados vindos da RFB (`fonte_enriquecimento='rfb'`).
 *
 * Multi-tenancy via Global Scope (ADR-003). Toda query autenticada filtra por
 * `auth()->id()`. Bypass explícito disponível via `withoutGlobalScope(...)`.
 *
 * @property string $id
 * @property string $usuario_id
 * @property TipoDocumento $tipo_documento
 * @property string $documento
 * @property string $razao_social
 * @property string|null $nome_fantasia
 * @property string|null $cnae
 * @property string $municipio
 * @property string $uf
 * @property SituacaoCadastral $situacao_cadastral
 * @property Carbon|null $data_fundacao
 * @property FonteEnriquecimento $fonte_enriquecimento
 * @property Carbon|null $enriquecido_at
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'usuario_id',
    'tipo_documento',
    'documento',
    'razao_social',
    'nome_fantasia',
    'cnae',
    'municipio',
    'uf',
    'situacao_cadastral',
    'data_fundacao',
    'fonte_enriquecimento',
    'enriquecido_at',
])]
#[ScopedBy([BelongsToUsuarioScope::class])]
final class EmpresaAnalisada extends Model
{
    /** @use HasFactory<EmpresaAnalisadaFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'empresas_analisadas';

    protected $casts = [
        'tipo_documento' => TipoDocumento::class,
        'situacao_cadastral' => SituacaoCadastral::class,
        'fonte_enriquecimento' => FonteEnriquecimento::class,
        'data_fundacao' => 'date',
        'enriquecido_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Usuario, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function ufEnum(): Uf
    {
        return Uf::from($this->uf);
    }

    public function documentoFormatado(): string
    {
        return match ($this->tipo_documento) {
            TipoDocumento::Cnpj => Cnpj::formatar($this->documento),
            TipoDocumento::Cpf => Cpf::formatar($this->documento),
        };
    }

    protected static function newFactory(): EmpresaAnalisadaFactory
    {
        return EmpresaAnalisadaFactory::new();
    }
}

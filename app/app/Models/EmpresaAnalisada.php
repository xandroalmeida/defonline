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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    /**
     * @return HasMany<Diagnostico, $this>
     */
    public function diagnosticos(): HasMany
    {
        return $this->hasMany(Diagnostico::class, 'empresa_analisada_id');
    }

    /**
     * Anexa `ultimo_diagnostico_id` (UUID|null) à query — usado pela tela
     * "Minhas Empresas" e pelo seletor de empresa do menu Diagnósticos para
     * oferecer um atalho de leitura ao Roberto sem refazer o quiz.
     *
     * Implementado como **subquery select** em vez de `hasOne()->latestOfMany()`
     * porque Postgres não suporta `MAX(uuid)` como tiebreaker — e o Eloquent
     * `latestOfMany` injeta esse aggregate na PK automaticamente. A subquery
     * é mais simples e a `Diagnostico::select()` herda o Global Scope
     * `BelongsToUsuarioScope`, mantendo defesa em profundidade no multi-tenant.
     *
     * @param  Builder<EmpresaAnalisada>  $query
     */
    public function scopeWithUltimoDiagnosticoId(Builder $query): void
    {
        $query->addSelect([
            'ultimo_diagnostico_id' => Diagnostico::query()
                ->select('id')
                ->whereColumn('empresa_analisada_id', 'empresas_analisadas.id')
                ->latest('gerado_em')
                ->limit(1),
        ]);
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

    /**
     * Documento mascarado para exibição em listagem (STORY-016 CA-1).
     *
     * CNPJ: `AA dot estrelas barra FFFF dash estrelas` — primeiros 2 dígitos +
     * filial visíveis, restantes ocultos.
     * CPF: 3 dígitos centrais visíveis, restantes ocultos.
     */
    public function documentoMascarado(): string
    {
        $d = $this->documento;

        return match ($this->tipo_documento) {
            TipoDocumento::Cnpj => strlen($d) === 14
                ? substr($d, 0, 2).'.***.***/'.substr($d, 8, 4).'-**'
                : $d,
            TipoDocumento::Cpf => strlen($d) === 11
                ? '***.'.substr($d, 3, 3).'.***-**'
                : $d,
        };
    }

    /**
     * Rótulo curto da fonte para badge — "Receita Federal" ou "Manual"
     * (STORY-016 CA-1; o rótulo longo de `FonteEnriquecimento::rotulo()`
     * permanece para o detalhe da empresa).
     */
    public function fonteBadge(): string
    {
        return match ($this->fonte_enriquecimento) {
            FonteEnriquecimento::Rfb => 'Receita Federal',
            FonteEnriquecimento::Manual => 'Manual',
        };
    }

    protected static function newFactory(): EmpresaAnalisadaFactory
    {
        return EmpresaAnalisadaFactory::new();
    }
}

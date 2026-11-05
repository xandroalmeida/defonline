<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Cnpj;
use App\Domain\Cpf;
use App\Domain\TipoDocumento;
use App\Models\Concerns\RegistraPersonificacao;
use App\Models\Scopes\BelongsToUsuarioScope;
use Database\Factories\DiagnosticoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Casts\ArrayObject;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Diagnóstico — snapshot imutável de uma execução do motor de cálculo (espec V2.5 §4.5/§4.7).
 *
 * Persistência decidida em IDR-010 (versionamento motor + persistência idempotente):
 *   - JSON imutável em quiz_payload / indicadores_calculados / resumo_executivo.
 *   - motor_version (semver) + matrix_version (datado) carimbados na emissão.
 *   - Idempotência verificada por payload_hash (SHA-256 do quiz_payload canonicalizado).
 *
 * STORY-026 entrega o esqueleto. STORY-028 implementa o motor que popula este model.
 *
 * Multi-tenancy via Global Scope (ADR-003) — Diagnostico::query() já filtra por auth()->id().
 *
 * @property string $id
 * @property string $usuario_id
 * @property string $empresa_analisada_id
 * @property string $motor_version
 * @property string $matrix_version
 * @property string $setor
 * @property ArrayObject<string, mixed> $quiz_payload
 * @property string $payload_hash
 * @property ArrayObject<string, array{valor: float|int|null, farol: string, motivo: ?string, mensagem: string}> $indicadores_calculados
 * @property ArrayObject<string, mixed> $resumo_executivo
 * @property array{tipo_documento: string, documento: string, razao_social: string, nome_fantasia: string|null, municipio: string, uf: string} $empresa_snapshot
 * @property Carbon $gerado_em
 * @property Carbon|null $deleted_at
 *
 * @see defonline-docs/project-state/decisions/idr/IDR-010-versionamento-motor-persistencia-diagnostico.md
 */
#[Fillable([
    'usuario_id',
    'empresa_analisada_id',
    'motor_version',
    'matrix_version',
    'setor',
    'quiz_payload',
    'payload_hash',
    'indicadores_calculados',
    'resumo_executivo',
    'empresa_snapshot',
    'gerado_em',
])]
#[ScopedBy([BelongsToUsuarioScope::class])]
final class Diagnostico extends Model
{
    /** @use HasFactory<DiagnosticoFactory> */
    use HasFactory, HasUuids, RegistraPersonificacao, SoftDeletes;

    protected $table = 'diagnosticos';

    protected $casts = [
        'quiz_payload' => AsArrayObject::class,
        'indicadores_calculados' => AsArrayObject::class,
        'resumo_executivo' => AsArrayObject::class,
        'empresa_snapshot' => 'array',
        'gerado_em' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Nome da empresa no relatório. A ordem é a da PDR-017: o nome informado pelo
     * usuário (lido ao vivo — os já emitidos mostram o nome novo), o fantasia da
     * Receita e a razão social, estes dois congelados na emissão. Em CPF o nome
     * comercial é do próprio usuário e também é lido ao vivo.
     */
    public function nomeExibicao(): string
    {
        $empresa = $this->empresa;
        $snapshot = $this->empresa_snapshot;
        $fantasia = $snapshot['tipo_documento'] === TipoDocumento::Cpf->value
            ? $empresa?->nome_fantasia
            : $snapshot['nome_fantasia'];

        return ($empresa?->nome_fantasia_informado ?: $fantasia) ?: $snapshot['razao_social'];
    }

    /** Razão social (congelada) para a capa; null quando ela já é o próprio nome. */
    public function razaoSocialComplementar(): ?string
    {
        $razao = $this->empresa_snapshot['razao_social'];

        return $this->nomeExibicao() !== $razao ? $razao : null;
    }

    public function rotuloDocumento(): string
    {
        return $this->empresa_snapshot['tipo_documento'] === TipoDocumento::Cpf->value ? 'CPF' : 'CNPJ';
    }

    public function documentoFormatado(): string
    {
        $documento = $this->empresa_snapshot['documento'];

        return match (true) {
            $documento === '' => '—',
            $this->empresa_snapshot['tipo_documento'] === TipoDocumento::Cpf->value => Cpf::formatar($documento),
            default => Cnpj::formatar($documento),
        };
    }

    /** "Município / UF" como estavam na emissão. */
    public function localizacao(): string
    {
        $municipio = $this->empresa_snapshot['municipio'];

        return $municipio === '' ? '—' : $municipio.' / '.$this->empresa_snapshot['uf'];
    }

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
     * Helper de idempotência (IDR-010 sub-decisão 3).
     *
     * Dois diagnósticos têm os mesmos inputs sse:
     *   - mesma versão de motor;
     *   - mesma versão de matriz;
     *   - mesmo setor;
     *   - mesmo hash do quiz_payload canonicalizado.
     *
     * Se este método retorna `true`, o motor deve produzir saída bit-exata para
     * ambos — propriedade verificada pelos golden hashes em
     * `app/tests/Domain/Motor/GoldenHashesTest.php` (a ser criado em STORY-028).
     *
     * Não compara `indicadores_calculados` aqui — esse é o **efeito** da idempotência,
     * não a chave dela. Comparação direta de saída fica nos testes.
     */
    public function hasSameInputsAs(self $other): bool
    {
        return $this->motor_version === $other->motor_version
            && $this->matrix_version === $other->matrix_version
            && $this->setor === $other->setor
            && $this->payload_hash === $other->payload_hash;
    }
}

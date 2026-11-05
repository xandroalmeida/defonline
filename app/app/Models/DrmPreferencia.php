<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Drm\Grupo;
use App\Domain\Drm\Modo;
use App\Models\Concerns\RegistraPersonificacao;
use App\Models\Scopes\BelongsToUsuarioScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Parâmetros do DRM por Empresa Analisada (Anexo K §K.5-A): o modo de cada grupo e a
 * posição do pró-labore. É preferência — o que o formulário abre; o modo de cada mês
 * é fato e vive dentro do `lancamento_payload`. Criada de forma preguiçosa, sem soft delete
 * (configuração, sem PII).
 *
 * @property string $id
 * @property string $usuario_id
 * @property string $empresa_analisada_id
 * @property string $posicao_prolabore
 * @property string $modo_custo
 * @property string $modo_vendas
 * @property string $modo_administrativas
 * @property string $modo_financeiras
 */
#[Fillable([
    'usuario_id',
    'empresa_analisada_id',
    'posicao_prolabore',
    'modo_custo',
    'modo_vendas',
    'modo_administrativas',
    'modo_financeiras',
])]
#[ScopedBy([BelongsToUsuarioScope::class])]
final class DrmPreferencia extends Model
{
    use HasUuids, RegistraPersonificacao;

    protected $table = 'drm_preferencias';

    /** A preferência da empresa; sem registro ainda, os defaults (nada é gravado só por ler). */
    public static function daEmpresa(EmpresaAnalisada $empresa): self
    {
        return self::query()->where('empresa_analisada_id', $empresa->id)->first()
            ?? new self([
                'usuario_id' => $empresa->usuario_id,
                'empresa_analisada_id' => $empresa->id,
                'posicao_prolabore' => 'fora',
                'modo_custo' => Modo::Detalhado->value,
                'modo_vendas' => Modo::Detalhado->value,
                'modo_administrativas' => Modo::Detalhado->value,
                'modo_financeiras' => Modo::Detalhado->value,
            ]);
    }

    public function modo(Grupo $grupo): Modo
    {
        return Modo::from((string) $this->getAttribute('modo_'.$grupo->value));
    }

    /**
     * @return array<string, Modo> indexado pelo valor do Grupo
     */
    public function modos(): array
    {
        $modos = [];
        foreach (Grupo::cases() as $grupo) {
            $modos[$grupo->value] = $this->modo($grupo);
        }

        return $modos;
    }

    /**
     * Grava os modos escolhidos (cria a linha na primeira vez).
     *
     * @param  array<string, Modo>  $modos
     */
    public static function gravarModos(EmpresaAnalisada $empresa, array $modos): self
    {
        $dados = [];
        foreach (Grupo::cases() as $grupo) {
            $dados['modo_'.$grupo->value] = ($modos[$grupo->value] ?? Modo::Detalhado)->value;
        }

        return self::query()->updateOrCreate(
            ['empresa_analisada_id' => $empresa->id],
            ['usuario_id' => $empresa->usuario_id, ...$dados],
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Actions\Drm;

use App\Domain\Drm\Competencia;
use App\Domain\Drm\Dinheiro;
use App\Domain\Drm\Lancamento;
use App\Domain\Drm\LancamentoCanonicalizer;
use App\Domain\Drm\MotorDrm;
use App\Domain\Drm\Ocorrencia;
use App\Models\DrmLancamento;
use App\Models\DrmPreferencia;
use App\Models\DrmRascunho;
use App\Models\EmpresaAnalisada;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Fecha o lançamento: grava a versão 1 da competência (ADR-007), encerra o rascunho
 * preservando a trilha (soft delete, como o Quiz) e fixa os modos como preferência.
 *
 * **A regra B1 é do banco.** Não há `exists()` antes do insert: o índice único parcial
 * `drm_lancamentos_vigente_unique` resolve a corrida, e a violação vira
 * {@see CompetenciaJaLancada} — o chamador oferece abrir ou corrigir o existente.
 *
 * **O snapshot nasce junto (STORY-048, ADR-007).** O motor roda sobre o payload canônico —
 * o mesmo que vira o `payload_hash` — e a `demonstracao` é gravada na mesma transação, com
 * o mesmo carimbo de `gerado_em` que o motor recebeu. A coluna é imutável: não existe
 * "calcular depois". `alertas_aceitos` vai em coluna própria, fora do `payload_hash`.
 */
final class FecharLancamento
{
    public function __construct(private readonly MotorDrm $motor) {}

    /**
     * @param  list<Ocorrencia>  $alertasAceitos
     *
     * @throws CompetenciaJaLancada
     */
    public function execute(
        EmpresaAnalisada $empresa,
        Competencia $competencia,
        Lancamento $lancamento,
        array $alertasAceitos,
        ?DrmRascunho $rascunho = null,
    ): DrmLancamento {
        $payload = LancamentoCanonicalizer::canonicalizar($lancamento->payload());
        $agora = now();
        $demonstracao = $this->motor->calcular($payload, $lancamento->setor, $competencia, $agora);

        try {
            return DB::transaction(function () use ($empresa, $competencia, $lancamento, $alertasAceitos, $rascunho, $payload, $demonstracao, $agora): DrmLancamento {
                $registro = DrmLancamento::query()->create([
                    'usuario_id' => $empresa->usuario_id,
                    'empresa_analisada_id' => $empresa->id,
                    'competencia' => $competencia->dia01(),
                    'versao' => 1,
                    'setor' => $lancamento->setor->value,
                    'lancamento_payload' => $payload,
                    'payload_hash' => LancamentoCanonicalizer::hash($payload),
                    'demonstracao' => $demonstracao,
                    'alertas_aceitos' => array_map(
                        static fn (Ocorrencia $o): array => [
                            'regra' => $o->codigo,
                            'ocorrido_em' => $agora->toIso8601String(),
                            'valor_envolvido' => $o->valorEnvolvido === null ? null : Dinheiro::decimal($o->valorEnvolvido),
                        ],
                        $alertasAceitos,
                    ),
                    'motor_drm_version' => $demonstracao['motor_drm_version'],
                    'gerado_em' => $agora,
                ]);

                $rascunho?->delete();
                DrmPreferencia::gravarModos($empresa, $lancamento->modos);

                return $registro;
            });
        } catch (UniqueConstraintViolationException) {
            throw new CompetenciaJaLancada("Competência {$competencia->texto()} já lançada.");
        }
    }
}

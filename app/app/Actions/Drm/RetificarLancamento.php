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
use App\Models\DrmRascunho;
use App\Models\EmpresaAnalisada;
use App\Observabilidade\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Retifica uma competência já fechada: grava a versão N+1 e encerra a N, sem apagar nada (ADR-007, STORY-050).
 *
 * A transação segue o ADR-007 com a ordem corrigida pelo IDR-016 (o índice único parcial de vigência
 * não é adiável, a FK do ponteiro é):
 *
 *  1. `SELECT … FOR UPDATE` da vigente (quem perde a corrida volta vazio e vira conflito, ver abaixo);
 *  2. `base_versao` precisa ser a versão vigente (concorrência otimista) → {@see RetificacaoConflitante};
 *  3. o `payload_hash` precisa ter mudado → {@see RetificacaoSemMudanca};
 *  4. `UPDATE` do ponteiro da N para o id da N+1, e só então `INSERT` da N+1;
 *  5. audit `drm.lancamento.retificado` com `{versao, payload_hash}` antes/depois — nunca o payload financeiro.
 *
 * Só a linha da competência é tocada: nenhuma outra competência é recalculada (CA-3). O motor roda
 * com a versão vigente HOJE e carimba o snapshot (IDR-010), por isso a cadeia pode misturar versões.
 */
final class RetificarLancamento
{
    public function __construct(private readonly MotorDrm $motor) {}

    /**
     * @param  list<Ocorrencia>  $alertasAceitos
     *
     * @throws CompetenciaNaoLancada
     * @throws RetificacaoConflitante
     * @throws RetificacaoSemMudanca
     */
    public function execute(
        EmpresaAnalisada $empresa,
        Competencia $competencia,
        Lancamento $lancamento,
        array $alertasAceitos,
        int $baseVersao,
        ?DrmRascunho $rascunho = null,
    ): DrmLancamento {
        $payload = LancamentoCanonicalizer::canonicalizar($lancamento->payload());
        $hash = LancamentoCanonicalizer::hash($payload);
        $agora = now();
        $demonstracao = $this->motor->calcular($payload, $lancamento->setor, $competencia, $agora);

        return DB::transaction(function () use ($empresa, $competencia, $lancamento, $alertasAceitos, $baseVersao, $rascunho, $payload, $hash, $demonstracao, $agora): DrmLancamento {
            $vigente = DrmLancamento::query()
                ->vigente()
                ->where('empresa_analisada_id', $empresa->id)
                ->where('competencia', $competencia->dia01())
                ->lockForUpdate()
                ->first();

            if ($vigente === null) {
                // Em READ COMMITTED, quem esperava o lock reavalia o WHERE sobre a linha que o concorrente acabou de
                // substituir: `substituido_por_id IS NULL` deixa de valer e o SELECT volta vazio. Existir versão
                // é então conflito (a base ficou velha), não "nunca lançado".
                $existe = DrmLancamento::query()
                    ->where('empresa_analisada_id', $empresa->id)
                    ->where('competencia', $competencia->dia01())
                    ->exists();
                if ($existe) {
                    throw new RetificacaoConflitante("Competência {$competencia->texto()} foi corrigida por outra sessão (base {$baseVersao}).");
                }

                throw new CompetenciaNaoLancada("Competência {$competencia->texto()} não tem lançamento para retificar.");
            }

            if ($vigente->versao !== $baseVersao) {
                throw new RetificacaoConflitante("Competência {$competencia->texto()} está na versão {$vigente->versao}, não na {$baseVersao}.");
            }

            if ($vigente->payload_hash === $hash) {
                throw new RetificacaoSemMudanca("Competência {$competencia->texto()}: nenhum valor mudou.");
            }

            $novaId = (string) Str::uuid7();

            // Ponteiro primeiro, versão nova depois (IDR-016): a FK adiada só é conferida no COMMIT.
            $vigente->update(['substituido_por_id' => $novaId]);

            $nova = new DrmLancamento([
                'usuario_id' => $empresa->usuario_id,
                'empresa_analisada_id' => $empresa->id,
                'competencia' => $competencia->dia01(),
                'versao' => $vigente->versao + 1,
                'setor' => $lancamento->setor->value,
                'lancamento_payload' => $payload,
                'payload_hash' => $hash,
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
            $nova->id = $novaId;
            $nova->save();

            $rascunho?->delete();

            $autor = auth()->id();
            AuditLogger::log(
                action: 'drm.lancamento.retificado',
                subjectType: 'DrmLancamento',
                subjectId: $nova->id,
                actorType: 'user',
                actorId: $autor === null ? null : (string) $autor,
                usuarioId: $empresa->usuario_id,
                before: ['versao' => $vigente->versao, 'payload_hash' => $vigente->payload_hash],
                after: ['versao' => $nova->versao, 'payload_hash' => $nova->payload_hash],
                context: [
                    'origem' => 'web',
                    'empresa_analisada_id' => $empresa->id,
                    'competencia' => $competencia->texto(),
                ],
            );

            return $nova;
        });
    }
}

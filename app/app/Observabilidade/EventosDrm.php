<?php

declare(strict_types=1);

namespace App\Observabilidade;

use App\Domain\Drm\Competencia;
use App\Domain\Drm\Grupo;
use App\Domain\Drm\Lancamento;
use App\Domain\Drm\Modo;
use App\Domain\Drm\PreenchimentoInicial;
use App\Domain\Drm\Setor;
use App\Models\DrmLancamento;
use App\Models\DrmRascunho;
use App\Models\EmpresaAnalisada;
use App\Models\EventoProduto;
use App\Support\RequestId;

/**
 * Catálogo dos eventos analíticos do DRM (STORY-056, ADR-004 §Decisão 2, PDR-003 decisão 8).
 *
 * Tudo sai por {@see EventLogger} (canal único, CA-9). Cada método declara o esquema exato do seu
 * evento e a chave de idempotência do FATO que ele registra (CA-8): reenviar o mesmo fato não grava
 * de novo, e o índice único parcial de `evento_produto` garante isso mesmo em corrida.
 *
 * **Sem dado pessoal nem valor absoluto (CA-7).** Só identificadores opacos, contagens, faixas de
 * tempo, o setor e a competência (mês). Nenhuma chave começa com `receita`, `custo`, `faturamento`
 * ou `balanco` — o `EventLogger` recusa em tempo de execução; por isso os grupos viajam como
 * VALORES de lista (`grupos_agregados: ["custo", …]`), nunca como chaves.
 *
 * As métricas do épico saem destes eventos (consultas de exemplo nas notas da STORY-056):
 *  - recorrência: `drm_lancamento_concluido.numero_lancamento` e `dias_desde_anterior`;
 *  - atrito: `drm_lancamento_iniciado` × `drm_lancamento_concluido`, `duracao_seg`, último `drm_bloco_concluido`;
 *  - alcance: `setor` em todos os eventos de lançamento;
 *  - mecanismos anti-atrito: modos por grupo, `campos_herdados*`, `drm_rascunho_retomado`;
 *  - qualidade: `drm_lancamento_retificado`, `drm_alerta_aceito`, `drm_regra_bloqueante_acionada`;
 *  - exportação e leitura: `drm_exportado.formato`, `drm_demonstracao_visualizada`.
 */
final class EventosDrm
{
    public const FORMATO_XLSX = 'xlsx';

    public const FORMATO_IMPRESSAO = 'impressao';

    /** O formulário abriu o rascunho de um mês (primeira gravação) — o lançamento "iniciado". */
    public static function lancamentoIniciado(DrmRascunho $rascunho, EmpresaAnalisada $empresa, Setor $setor, bool $herdouValores): void
    {
        EventLogger::emitOnce(
            "rascunho:{$rascunho->id}",
            'drm_lancamento_iniciado',
            [
                'rascunho_id' => $rascunho->id,
                'competencia' => Competencia::deData($rascunho->competencia)->texto(),
                'setor' => $setor->value,
                'origem' => $rascunho->origem,
                'herdou_valores' => $herdouValores,
            ],
            (string) $rascunho->usuario_id,
            $empresa->id,
        );
    }

    /** Um bloco foi concluído e validado: o último de um rascunho sem `drm_lancamento_concluido` é o ponto de abandono. */
    public static function blocoConcluido(DrmRascunho $rascunho, EmpresaAnalisada $empresa, Setor $setor, int $bloco): void
    {
        EventLogger::emitOnce(
            "rascunho:{$rascunho->id}:bloco:{$bloco}",
            'drm_bloco_concluido',
            [
                'rascunho_id' => $rascunho->id,
                'competencia' => Competencia::deData($rascunho->competencia)->texto(),
                'setor' => $setor->value,
                'origem' => $rascunho->origem,
                'bloco' => $bloco,
            ],
            (string) $rascunho->usuario_id,
            $empresa->id,
        );
    }

    /** O usuário voltou a um rascunho que já existia (uma vez por rascunho). */
    public static function rascunhoRetomado(DrmRascunho $rascunho, EmpresaAnalisada $empresa, Setor $setor, int $bloco): void
    {
        EventLogger::emitOnce(
            "rascunho:{$rascunho->id}",
            'drm_rascunho_retomado',
            [
                'rascunho_id' => $rascunho->id,
                'competencia' => Competencia::deData($rascunho->competencia)->texto(),
                'setor' => $setor->value,
                'origem' => $rascunho->origem,
                'bloco' => $bloco,
                'dias_parado' => (int) $rascunho->updated_at->diffInDays(now(), true),
            ],
            (string) $rascunho->usuario_id,
            $empresa->id,
        );
    }

    /**
     * Uma regra bloqueante (B1–B4) impediu de avançar. Uma vez por regra e campo em cada abertura do
     * formulário (`$formulario` é o id do componente): tentar de novo o mesmo erro não conta de novo.
     */
    public static function regraBloqueanteAcionada(string $formulario, EmpresaAnalisada $empresa, Setor $setor, string $competencia, string $origem, int $bloco, string $regra, ?string $campo): void
    {
        EventLogger::emitOnce(
            "formulario:{$formulario}:{$regra}:".($campo ?? '-'),
            'drm_regra_bloqueante_acionada',
            [
                'regra' => $regra,
                'campo' => $campo,
                'competencia' => $competencia,
                'setor' => $setor->value,
                'origem' => $origem,
                'bloco' => $bloco,
            ],
            (string) $empresa->usuario_id,
            $empresa->id,
        );
    }

    /**
     * O mês foi fechado (versão 1). Chamado dentro da transação do fechamento: se o evento falha, o
     * lançamento não é gravado (ADR-004 §2.3). Carrega a recorrência, o atrito e o uso dos mecanismos.
     */
    public static function lancamentoConcluido(DrmLancamento $registro, EmpresaAnalisada $empresa, Lancamento $lancamento, ?DrmRascunho $rascunho): void
    {
        $anterior = DrmLancamento::anteriorA($empresa, $registro->competencia->format('Y-m-d'));
        [$herdados, $aceitos] = self::heranca($lancamento, $anterior);
        [$agregados, $detalhados] = self::gruposPorModo($lancamento);

        EventLogger::emitOnce(
            "lancamento:{$registro->id}",
            'drm_lancamento_concluido',
            [
                'lancamento_id' => $registro->id,
                'rascunho_id' => $rascunho?->id,
                'competencia' => Competencia::deData($registro->competencia)->texto(),
                'setor' => $registro->setor,
                // Recorrência: o ordinal do mês na história da empresa e a distância até o anterior.
                'numero_lancamento' => DrmLancamento::query()->where('empresa_analisada_id', $empresa->id)->where('versao', 1)->count(),
                'dias_desde_anterior' => $anterior === null ? null : (int) $anterior->created_at->diffInDays($registro->created_at, true),
                // Atrito: do primeiro salvamento ao fechamento.
                'duracao_seg' => $rascunho === null ? null : max(0, (int) $rascunho->created_at->diffInSeconds($registro->created_at, true)),
                // Mecanismos anti-atrito (PDR-003): modo por grupo, pré-preenchimento aceito, rascunho retomado.
                'grupos_agregados' => $agregados,
                'grupos_detalhados' => $detalhados,
                'campos_herdados' => $herdados,
                'campos_herdados_aceitos' => $aceitos,
                'rascunho_retomado' => $rascunho !== null && self::foiRetomado($rascunho),
                'qtd_alertas_aceitos' => count($registro->alertas_aceitos),
            ],
            (string) $empresa->usuario_id,
            $empresa->id,
        );

        self::alertasAceitos($registro, $empresa, 'inicial');
    }

    /** Uma correção foi gravada (versão ≥ 2): o numerador da taxa de retificação do PDR-003. */
    public static function lancamentoRetificado(DrmLancamento $nova, DrmLancamento $anterior, EmpresaAnalisada $empresa): void
    {
        EventLogger::emitOnce(
            "lancamento:{$nova->id}",
            'drm_lancamento_retificado',
            [
                'lancamento_id' => $nova->id,
                'competencia' => Competencia::deData($nova->competencia)->texto(),
                'setor' => $nova->setor,
                'versao' => $nova->versao,
                'campos_alterados' => self::camposAlterados($anterior->lancamento_payload->getArrayCopy(), $nova->lancamento_payload->getArrayCopy()),
                'dias_desde_lancamento' => (int) $anterior->created_at->diffInDays($nova->created_at, true),
            ],
            (string) $empresa->usuario_id,
            $empresa->id,
        );

        self::alertasAceitos($nova, $empresa, 'retificacao');
    }

    /** Leitura do resultado: a Demonstração foi aberta. */
    public static function demonstracaoVisualizada(DrmLancamento $lancamento, EmpresaAnalisada $empresa, string $posicao): void
    {
        EventLogger::emitOnce(
            'visualizacao:'.RequestId::get(),
            'drm_demonstracao_visualizada',
            [
                'lancamento_id' => $lancamento->id,
                'competencia' => Competencia::deData($lancamento->competencia)->texto(),
                'setor' => $lancamento->setor,
                'posicao' => $posicao,
                'retificado' => $lancamento->foiRetificado(),
            ],
            (string) $empresa->usuario_id,
            $empresa->id,
        );
    }

    /** O resultado saiu do produto, por formato (`xlsx` ou `impressao`). Uma vez por formato em cada requisição. */
    public static function exportado(DrmLancamento $lancamento, EmpresaAnalisada $empresa, string $formato): void
    {
        EventLogger::emitOnce(
            'exportacao:'.RequestId::get().":{$formato}",
            'drm_exportado',
            [
                'lancamento_id' => $lancamento->id,
                'competencia' => Competencia::deData($lancamento->competencia)->texto(),
                'setor' => $lancamento->setor,
                'formato' => $formato,
                'retificado' => $lancamento->foiRetificado(),
            ],
            (string) $empresa->usuario_id,
            $empresa->id,
        );
    }

    // ------------------------------------------------------------------ derivados

    /** Um evento por regra de alerta aceita (A4 pode vir por campo: `ocorrencias` conta quantos). */
    private static function alertasAceitos(DrmLancamento $registro, EmpresaAnalisada $empresa, string $origem): void
    {
        $porRegra = [];
        foreach ($registro->alertas_aceitos as $aceito) {
            $regra = (string) ($aceito['regra'] ?? '');
            if ($regra !== '') {
                $porRegra[$regra] = ($porRegra[$regra] ?? 0) + 1;
            }
        }

        foreach ($porRegra as $regra => $ocorrencias) {
            EventLogger::emitOnce(
                "lancamento:{$registro->id}:alerta:{$regra}",
                'drm_alerta_aceito',
                [
                    'lancamento_id' => $registro->id,
                    'competencia' => Competencia::deData($registro->competencia)->texto(),
                    'setor' => $registro->setor,
                    'origem' => $origem,
                    'regra' => $regra,
                    'ocorrencias' => $ocorrencias,
                ],
                (string) $empresa->usuario_id,
                $empresa->id,
            );
        }
    }

    /**
     * Quanto do pré-preenchimento foi aceito sem alteração: dos campos que o formulário herdou do mês
     * anterior, quantos fecharam com o mesmo valor. Sem mês anterior não há herança (0 e 0).
     *
     * @return array{0: int, 1: int}
     */
    private static function heranca(Lancamento $lancamento, ?DrmLancamento $anterior): array
    {
        if ($anterior === null) {
            return [0, 0];
        }

        $anteriorPayload = $anterior->lancamento_payload->getArrayCopy();
        $herdados = PreenchimentoInicial::de($lancamento->setor, $lancamento->modos, $anteriorPayload)->repetidos;
        $atual = $lancamento->payload();
        $aceitos = 0;
        foreach ($herdados as $campo) {
            if (($atual[$campo] ?? null) === ($anteriorPayload[$campo] ?? null)) {
                $aceitos++;
            }
        }

        return [count($herdados), $aceitos];
    }

    /**
     * @return array{0: list<string>, 1: list<string>} grupos em modo agregado e em modo detalhado
     */
    private static function gruposPorModo(Lancamento $lancamento): array
    {
        $agregados = [];
        $detalhados = [];
        foreach (Grupo::cases() as $grupo) {
            if (($lancamento->modos[$grupo->value] ?? Modo::Detalhado) === Modo::Agregado) {
                $agregados[] = $grupo->value;
            } else {
                $detalhados[] = $grupo->value;
            }
        }

        return [$agregados, $detalhados];
    }

    private static function foiRetomado(DrmRascunho $rascunho): bool
    {
        return EventoProduto::query()
            ->where('nome_evento', 'drm_rascunho_retomado')
            ->where('chave_idempotencia', "rascunho:{$rascunho->id}")
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $antes
     * @param  array<string, mixed>  $depois
     */
    private static function camposAlterados(array $antes, array $depois): int
    {
        $alterados = 0;
        foreach (Lancamento::todosOsCampos() as $campo) {
            if (($antes[$campo] ?? null) !== ($depois[$campo] ?? null)) {
                $alterados++;
            }
        }

        return $alterados;
    }
}

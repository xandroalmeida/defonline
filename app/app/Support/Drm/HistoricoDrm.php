<?php

declare(strict_types=1);

namespace App\Support\Drm;

use App\Domain\Drm\Competencia;
use App\Domain\Drm\Serie\MesDaSerie;
use App\Domain\Drm\Serie\SerieDrm;
use App\Models\DrmLancamento;
use App\Models\EmpresaAnalisada;
use Illuminate\Support\Collection;

/**
 * Lê as versões VIGENTES de uma empresa e as entrega ao domínio da série (STORY-051).
 *
 * Uma consulta só, sem `COALESCE`: mês sem linha vigente (nunca lançado, ou lançamento apagado
 * — `deleted_at` fica de fora pelo soft delete) simplesmente não vem, e a série o trata como
 * ausência. Versões substituídas nunca entram: retificar muda o que os comparativos enxergam.
 */
final class HistoricoDrm
{
    /**
     * @return Collection<int, DrmLancamento>
     */
    public static function vigentes(EmpresaAnalisada $empresa): Collection
    {
        return DrmLancamento::query()
            ->vigente()
            ->where('empresa_analisada_id', $empresa->id)
            ->orderBy('competencia')
            ->get(['id', 'empresa_analisada_id', 'usuario_id', 'competencia', 'versao', 'lancamento_payload', 'demonstracao']);
    }

    public static function serie(EmpresaAnalisada $empresa, string $posicao): SerieDrm
    {
        return new SerieDrm(self::vigentes($empresa)->map(
            static fn (DrmLancamento $l): MesDaSerie => MesDaSerie::doSnapshot(
                Competencia::deData($l->competencia),
                $l->versao,
                $l->demonstracao->getArrayCopy(),
                $l->lancamento_payload->getArrayCopy(),
                $posicao,
            ),
        )->all());
    }

    /**
     * O histórico que os alertas A4 e A6 precisam: só payload, sem o snapshot.
     *
     * @return array{campos: array<string, array<string, int|null>>, lancadas: list<string>}
     */
    public static function paraAlertas(EmpresaAnalisada $empresa): array
    {
        $campos = [];
        $lancadas = [];
        $linhas = DrmLancamento::query()
            ->vigente()
            ->where('empresa_analisada_id', $empresa->id)
            ->get(['id', 'empresa_analisada_id', 'usuario_id', 'competencia', 'lancamento_payload']);

        foreach ($linhas as $l) {
            $texto = Competencia::deData($l->competencia)->texto();
            $lancadas[] = $texto;
            $campos[$texto] = MesDaSerie::camposDoPayload($l->lancamento_payload->getArrayCopy());
        }
        sort($lancadas);

        return ['campos' => $campos, 'lancadas' => $lancadas];
    }
}

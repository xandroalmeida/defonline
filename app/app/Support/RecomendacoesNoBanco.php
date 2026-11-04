<?php

declare(strict_types=1);

namespace App\Support;

use App\Domain\Motor\FonteRecomendacoes;
use App\Models\Recomendacao;
use Illuminate\Support\Carbon;

/**
 * STORY-068 / PDR-016 — matriz vigente na tabela `recomendacoes`.
 *
 * A versão de um segmento é o mês da sua última edição. Sem nenhuma edição, é a
 * versão de origem (`motor.matrix_version`, dez-2025).
 */
final class RecomendacoesNoBanco implements FonteRecomendacoes
{
    private const MESES = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];

    public function texto(string $setor, string $indicador, string $farol): ?string
    {
        return Recomendacao::query()
            ->where(['setor' => $setor, 'indicador' => $indicador, 'farol' => $farol])
            ->value('texto');
    }

    public function versao(string $setor): string
    {
        $ultimaEdicao = Recomendacao::query()->where('setor', $setor)->max('editado_em');

        if ($ultimaEdicao === null) {
            return (string) config('motor.matrix_version');
        }

        $data = Carbon::parse($ultimaEdicao)->setTimezone('America/Sao_Paulo');

        return self::MESES[$data->month - 1].'-'.$data->year;
    }
}

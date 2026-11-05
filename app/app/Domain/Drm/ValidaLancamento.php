<?php

declare(strict_types=1);

namespace App\Domain\Drm;

use Carbon\CarbonImmutable;

/**
 * Regras bloqueantes B2, B3 e B4 do Anexo K §K.6.
 *
 * **B1 (competência duplicada) não está aqui, de propósito**: quem arbitra é o
 * índice único parcial do banco (ADR-007) — um `exists()` antes teria janela de
 * corrida. A aplicação trata a violação de unicidade como "já existe".
 */
final class ValidaLancamento
{
    /**
     * @return list<Ocorrencia>
     */
    public static function bloqueios(Lancamento $lancamento, Competencia $competencia, CarbonImmutable $hoje): array
    {
        $ocorrencias = [];

        if ($competencia->ehFutura($hoje)) {
            $ocorrencias[] = new Ocorrencia('B2', 'D00', [
                'competencia' => $competencia->rotulo(),
                'ultimo_mes_fechado' => Competencia::padrao($hoje)->rotulo(),
            ]);
        }

        foreach ($lancamento->erros as $campo => $erro) {
            if ($erro === Dinheiro::NEGATIVO) {
                $ocorrencias[] = new Ocorrencia('B3', $campo);
            }
        }

        if ($lancamento->impostos() > $lancamento->receita() && ! isset($lancamento->erros['D01']) && ! isset($lancamento->erros['D02'])) {
            $ocorrencias[] = new Ocorrencia('B4', 'D02', [
                'valor_impostos' => Dinheiro::brl($lancamento->impostos()),
                'valor_receita' => Dinheiro::brl($lancamento->receita()),
            ]);
        }

        return $ocorrencias;
    }
}

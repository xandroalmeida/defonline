<?php

declare(strict_types=1);

namespace App\Domain\Drm\Alertas;

use App\Domain\Drm\Competencia;
use App\Domain\Drm\Lancamento;
use App\Domain\Drm\Ocorrencia;

/** A1 — D01 = 0 com qualquer despesa lançada. */
final class SemFaturamento implements RegraDeAlerta
{
    public function codigo(): string
    {
        return 'A1';
    }

    public function avaliar(Lancamento $lancamento, Competencia $competencia): ?Ocorrencia
    {
        if ($lancamento->receita() !== 0 || $lancamento->despesas() <= 0) {
            return null;
        }

        return new Ocorrencia('A1', 'D01', ['competencia' => $competencia->rotulo()], $lancamento->despesas());
    }
}

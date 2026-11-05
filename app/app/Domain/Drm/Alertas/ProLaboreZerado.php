<?php

declare(strict_types=1);

namespace App\Domain\Drm\Alertas;

use App\Domain\Drm\Competencia;
use App\Domain\Drm\Lancamento;
use App\Domain\Drm\Ocorrencia;

/** A5 — D40 = 0. Comum e legítimo, mas muitas vezes é esquecimento. */
final class ProLaboreZerado implements RegraDeAlerta
{
    public function codigo(): string
    {
        return 'A5';
    }

    public function avaliar(Lancamento $lancamento, Competencia $competencia): ?Ocorrencia
    {
        if ($lancamento->valor('D40') !== 0) {
            return null;
        }

        return new Ocorrencia('A5', 'D40', [], 0);
    }
}

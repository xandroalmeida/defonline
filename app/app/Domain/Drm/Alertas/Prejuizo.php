<?php

declare(strict_types=1);

namespace App\Domain\Drm\Alertas;

use App\Domain\Drm\Competencia;
use App\Domain\Drm\Dinheiro;
use App\Domain\Drm\Lancamento;
use App\Domain\Drm\Ocorrencia;

/** A3 — resultado final do mês negativo. Informa; não questiona o dado. */
final class Prejuizo implements RegraDeAlerta
{
    public function codigo(): string
    {
        return 'A3';
    }

    public function avaliar(Lancamento $lancamento, Competencia $competencia): ?Ocorrencia
    {
        $lucro = $lancamento->lucroLiquido();
        if ($lucro >= 0) {
            return null;
        }

        return new Ocorrencia('A3', null, [
            'valor_prejuizo' => Dinheiro::brl(-$lucro),
            'competencia' => $competencia->rotulo(),
        ], -$lucro);
    }
}

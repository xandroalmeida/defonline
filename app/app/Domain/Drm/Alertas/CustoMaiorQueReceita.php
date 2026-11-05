<?php

declare(strict_types=1);

namespace App\Domain\Drm\Alertas;

use App\Domain\Drm\Competencia;
use App\Domain\Drm\Dinheiro;
use App\Domain\Drm\Grupo;
use App\Domain\Drm\Lancamento;
use App\Domain\Drm\Ocorrencia;

/** A2 — total do custo do que foi vendido maior que D01. */
final class CustoMaiorQueReceita implements RegraDeAlerta
{
    public function codigo(): string
    {
        return 'A2';
    }

    public function avaliar(Lancamento $lancamento, Competencia $competencia): ?Ocorrencia
    {
        $custo = $lancamento->total(Grupo::Custo);
        if ($custo <= $lancamento->receita()) {
            return null;
        }

        return new Ocorrencia('A2', Grupo::Custo->totalizador(), [
            'valor_custo' => Dinheiro::brl($custo),
            'valor_receita' => Dinheiro::brl($lancamento->receita()),
        ], $custo);
    }
}

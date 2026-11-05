<?php

declare(strict_types=1);

namespace App\Domain\Drm\Alertas;

use App\Domain\Drm\Competencia;
use App\Domain\Drm\Lancamento;
use App\Domain\Drm\Ocorrencia;

final class AvaliadorDeAlertas
{
    /**
     * @param  list<RegraDeAlerta>  $regras
     */
    public function __construct(private readonly array $regras) {}

    /** Os alertas que só precisam do lançamento: A1, A2, A3 e A5 (STORY-047). */
    public static function padrao(): self
    {
        return new self([
            new SemFaturamento,
            new CustoMaiorQueReceita,
            new Prejuizo,
            new ProLaboreZerado,
        ]);
    }

    /**
     * @return list<Ocorrencia>
     */
    public function avaliar(Lancamento $lancamento, Competencia $competencia): array
    {
        $ocorrencias = [];
        foreach ($this->regras as $regra) {
            $ocorrencia = $regra->avaliar($lancamento, $competencia);
            if ($ocorrencia !== null) {
                $ocorrencias[] = $ocorrencia;
            }
        }

        return $ocorrencias;
    }
}

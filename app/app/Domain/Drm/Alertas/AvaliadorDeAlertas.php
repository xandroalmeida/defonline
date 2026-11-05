<?php

declare(strict_types=1);

namespace App\Domain\Drm\Alertas;

use App\Domain\Drm\Competencia;
use App\Domain\Drm\Lancamento;
use App\Domain\Drm\Ocorrencia;

final class AvaliadorDeAlertas
{
    /**
     * @param  list<RegraDeAlerta|RegraComVariasOcorrencias>  $regras
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
     * Os alertas de antes mais A4 e A6, que dependem do histórico da empresa (STORY-051).
     *
     * @param  array<string, array<string, int|null>>  $camposPorMes  `AAAA-MM` => D01..D42 de cada mês lançado
     * @param  list<string>  $lancadas  competências lançadas (`AAAA-MM`); vazio desliga A6 (retificação)
     */
    public static function comHistorico(array $camposPorMes, array $lancadas): self
    {
        return new self([
            new SemFaturamento,
            new CustoMaiorQueReceita,
            new Prejuizo,
            new RubricaAtipica($camposPorMes),
            new ProLaboreZerado,
            new SaltoDeCompetencia($lancadas),
        ]);
    }

    /**
     * @return list<Ocorrencia>
     */
    public function avaliar(Lancamento $lancamento, Competencia $competencia): array
    {
        $ocorrencias = [];
        foreach ($this->regras as $regra) {
            if ($regra instanceof RegraComVariasOcorrencias) {
                array_push($ocorrencias, ...$regra->ocorrencias($lancamento, $competencia));

                continue;
            }
            $ocorrencia = $regra->avaliar($lancamento, $competencia);
            if ($ocorrencia !== null) {
                $ocorrencias[] = $ocorrencia;
            }
        }

        return $ocorrencias;
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Drm\Alertas;

use App\Domain\Drm\Competencia;
use App\Domain\Drm\Lancamento;
use App\Domain\Drm\Ocorrencia;

/**
 * Regra que pode disparar mais de uma vez no mesmo lançamento, uma por campo (A4).
 * O aceite continua sendo por código: aceitar A4 aceita todas as ocorrências dele.
 */
interface RegraComVariasOcorrencias
{
    public function codigo(): string;

    /**
     * @return list<Ocorrencia>
     */
    public function ocorrencias(Lancamento $lancamento, Competencia $competencia): array;
}

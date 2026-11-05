<?php

declare(strict_types=1);

namespace App\Domain\Drm\Alertas;

use App\Domain\Drm\Competencia;
use App\Domain\Drm\Lancamento;
use App\Domain\Drm\Ocorrencia;

/**
 * Ponto de extensão dos alertas do Anexo K §K.6.
 *
 * A4 (rubrica atípica) e A6 (salto de competência) dependem de histórico e entraram
 * na STORY-051 por {@see AvaliadorDeAlertas::comHistorico()} — o bloco de conferência não mudou.
 */
interface RegraDeAlerta
{
    public function codigo(): string;

    public function avaliar(Lancamento $lancamento, Competencia $competencia): ?Ocorrencia;
}

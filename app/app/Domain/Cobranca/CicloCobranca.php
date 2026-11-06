<?php

declare(strict_types=1);

namespace App\Domain\Cobranca;

use App\Support\DataExibicao;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * STORY-077 — ciclo de cobrança de um plano. Todas as datas se ancoram no início da assinatura
 * (`inicio + n meses`), nunca na data anterior: assim 31/01 vira 28/02 e volta a 31/03, sem deriva.
 */
enum CicloCobranca: string
{
    case Mensal = 'mensal';
    case Anual = 'anual';

    public function meses(): int
    {
        return $this === self::Mensal ? 1 : 12;
    }

    /** Fim do período `$periodos` (1 = o primeiro) contado a partir do início. */
    public function vencimento(CarbonInterface $inicio, int $periodos = 1): CarbonImmutable
    {
        return DataExibicao::somarMeses($inicio, $this->meses() * max(1, $periodos));
    }

    public function precoDe(int $mensalCentavos, int $anualCentavos): int
    {
        return $this === self::Mensal ? $mensalCentavos : $anualCentavos;
    }

    public function rotulo(): string
    {
        return $this === self::Mensal ? 'Mensal' : 'Anual';
    }

    public function porExtenso(): string
    {
        return $this === self::Mensal ? 'por mês' : 'por ano';
    }
}

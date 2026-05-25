<?php

declare(strict_types=1);

namespace App\Domain\Motor\Indicadores;

use App\Domain\Motor\Calculos\DreAdaptada;
use App\Domain\Motor\Farois\FarolIndustria;
use App\Domain\Motor\IndicadorResultado;
use App\Domain\Motor\MatrizRecomendacoes;
use App\Domain\Motor\MotivosIndisponibilidade;

/**
 * Indicador #4 — Dívida Líquida / EBITDA = `(PCF − ACF) / EBITDA` (Anexo D).
 *   - PCF = Q06 (dívidas financeiras).
 *   - ACF = Q02 (disponibilidades).
 *
 * Casos extremos (catálogo §4):
 *   - EBITDA = 0 → `indisponivel:ebitda_zero`.
 *   - EBITDA < 0 → `indisponivel:ebitda_negativo` (interpretação não-significativa).
 *   - Q04/Q06 ausente → `indisponivel:divida_componente_faltante`.
 *   - Dívida líquida negativa (Q06 < Q02) → calculado normalmente; verde
 *     (caixa líquido positivo é o cenário ideal).
 */
final class DividaLiquidaEbitda implements Indicador
{
    public function chave(): string
    {
        return 'divida_liquida_ebitda';
    }

    public function calcular(array $payload, DreAdaptada $dre): IndicadorResultado
    {
        if (($payload['Q02'] ?? null) === null || ($payload['Q06'] ?? null) === null) {
            return IndicadorResultado::indisponivel(
                MotivosIndisponibilidade::DIVIDA_COMPONENTE_FALTANTE,
                MotivosIndisponibilidade::mensagem(MotivosIndisponibilidade::DIVIDA_COMPONENTE_FALTANTE),
            );
        }

        $ebitda = $dre->ebitda();
        if ($ebitda === null) {
            return IndicadorResultado::indisponivel(
                MotivosIndisponibilidade::DESPESAS_FALTANTE,
                MotivosIndisponibilidade::mensagem(MotivosIndisponibilidade::DESPESAS_FALTANTE),
            );
        }

        $cmp = bccomp($ebitda, '0', DreAdaptada::ESCALA);
        if ($cmp === 0) {
            return IndicadorResultado::indisponivel(
                MotivosIndisponibilidade::EBITDA_ZERO,
                MotivosIndisponibilidade::mensagem(MotivosIndisponibilidade::EBITDA_ZERO),
            );
        }
        if ($cmp === -1) {
            return IndicadorResultado::indisponivel(
                MotivosIndisponibilidade::EBITDA_NEGATIVO,
                MotivosIndisponibilidade::mensagem(MotivosIndisponibilidade::EBITDA_NEGATIVO),
            );
        }

        $pcf = $dre->dividasFinanceiras();
        $acf = $dre->disponibilidades();
        // Já checado null acima; mas defensa contra payload inválido.
        if ($pcf === null || $acf === null) {
            return IndicadorResultado::indisponivel(
                MotivosIndisponibilidade::DIVIDA_COMPONENTE_FALTANTE,
                MotivosIndisponibilidade::mensagem(MotivosIndisponibilidade::DIVIDA_COMPONENTE_FALTANTE),
            );
        }

        $dividaLiquida = bcsub($pcf, $acf, DreAdaptada::ESCALA);
        $ratio = bcdiv($dividaLiquida, $ebitda, 4);
        $valor = (float) $ratio;

        $farol = FarolIndustria::classificar('divida_liquida_ebitda', $valor);

        return new IndicadorResultado(
            valor: $valor,
            farol: $farol,
            motivo: null,
            mensagem: (new MatrizRecomendacoes)->texto($this->chave(), $farol),
        );
    }
}

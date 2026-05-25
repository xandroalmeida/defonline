<?php

declare(strict_types=1);

namespace App\Domain\Motor\Indicadores;

use App\Domain\Motor\Calculos\DreAdaptada;
use App\Domain\Motor\Farois\FarolIndustria;
use App\Domain\Motor\IndicadorResultado;
use App\Domain\Motor\MatrizRecomendacoes;
use App\Domain\Motor\MotivosIndisponibilidade;

/**
 * Indicador #3 — Margem Líquida = `LOL / Vendas × 100` (Anexo D).
 * LOL = EBITDA − Despesas Financeiras (Anexo C).
 *
 * Casos extremos (catálogo §3):
 *   - Vendas zero → `indisponivel:vendas_zero`.
 *   - Q16 ausente (Desp. financeiras) → `indisponivel:despesas_financeiras_faltante`.
 *   - Q14 ou Q15 ausente → `indisponivel:despesas_faltante` (propaga via EBITDA null).
 *
 * **Margem Líquida pode ser negativa** (LOL < 0 = prejuízo operacional). Isso
 * é semanticamente válido — não dispara indisponibilidade. O farol classifica
 * normalmente (provável vermelho).
 */
final class MargemLiquida implements Indicador
{
    public function chave(): string
    {
        return 'margem_liquida';
    }

    public function calcular(array $payload, DreAdaptada $dre): IndicadorResultado
    {
        if (($payload['Q09'] ?? null) === null) {
            return IndicadorResultado::indisponivel(
                MotivosIndisponibilidade::VENDAS_FALTANTE,
                MotivosIndisponibilidade::mensagem(MotivosIndisponibilidade::VENDAS_FALTANTE),
            );
        }

        $vendas = $dre->vendasAnuais();
        if ($vendas === null) {
            return IndicadorResultado::indisponivel(
                MotivosIndisponibilidade::VENDAS_FALTANTE,
                MotivosIndisponibilidade::mensagem(MotivosIndisponibilidade::VENDAS_FALTANTE),
            );
        }

        if (bccomp($vendas, '0', DreAdaptada::ESCALA) === 0) {
            return IndicadorResultado::indisponivel(
                MotivosIndisponibilidade::VENDAS_ZERO,
                MotivosIndisponibilidade::mensagem(MotivosIndisponibilidade::VENDAS_ZERO),
            );
        }

        if (($payload['Q16'] ?? null) === null) {
            return IndicadorResultado::indisponivel(
                MotivosIndisponibilidade::DESPESAS_FINANCEIRAS_FALTANTE,
                MotivosIndisponibilidade::mensagem(MotivosIndisponibilidade::DESPESAS_FINANCEIRAS_FALTANTE),
            );
        }

        if (($payload['Q14'] ?? null) === null || ($payload['Q15'] ?? null) === null) {
            return IndicadorResultado::indisponivel(
                MotivosIndisponibilidade::DESPESAS_FALTANTE,
                MotivosIndisponibilidade::mensagem(MotivosIndisponibilidade::DESPESAS_FALTANTE),
            );
        }

        $lol = $dre->lucroOperacionalLiquido();
        if ($lol === null) {
            // Defensa: Q08/Q09/etc não-numérico.
            return IndicadorResultado::indisponivel(
                MotivosIndisponibilidade::DESPESAS_FALTANTE,
                MotivosIndisponibilidade::mensagem(MotivosIndisponibilidade::DESPESAS_FALTANTE),
            );
        }

        $percentual = bcmul(bcdiv($lol, $vendas, 6), '100', DreAdaptada::ESCALA);
        $valor = (float) $percentual;

        $farol = FarolIndustria::classificar('margem_liquida', $valor);

        return new IndicadorResultado(
            valor: $valor,
            farol: $farol,
            motivo: null,
            mensagem: (new MatrizRecomendacoes)->texto($this->chave(), $farol),
        );
    }
}

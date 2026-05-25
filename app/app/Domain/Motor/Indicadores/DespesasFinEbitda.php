<?php

declare(strict_types=1);

namespace App\Domain\Motor\Indicadores;

use App\Domain\Motor\Calculos\DreAdaptada;
use App\Domain\Motor\Farois\FarolIndustria;
use App\Domain\Motor\IndicadorResultado;
use App\Domain\Motor\MensagensFarol;
use App\Domain\Motor\MotivosIndisponibilidade;

/**
 * Indicador #5 — Despesas Financeiras / EBITDA = `Despesas financeiras / EBITDA × 100`
 * (Anexo D). Despesas financeiras = Q16 × 12.
 *
 * Anexo E (Indústria): ≤ 35% verde · 35.01–50% amarelo · > 50% vermelho.
 *
 * Casos extremos (catálogo §5):
 *   - EBITDA = 0 → `indisponivel:ebitda_zero`.
 *   - EBITDA < 0 → `indisponivel:ebitda_negativo`.
 *   - Q16 ausente → `indisponivel:despesas_financeiras_faltante`.
 *   - Q14/Q15/Q08/Q09 ausentes propagam via DRE (despesas_faltante / vendas_faltante).
 */
final class DespesasFinEbitda implements Indicador
{
    public function chave(): string
    {
        return 'despesas_fin_ebitda';
    }

    public function calcular(array $payload, DreAdaptada $dre): IndicadorResultado
    {
        if (($payload['Q16'] ?? null) === null) {
            return IndicadorResultado::indisponivel(
                MotivosIndisponibilidade::DESPESAS_FINANCEIRAS_FALTANTE,
                MotivosIndisponibilidade::mensagem(MotivosIndisponibilidade::DESPESAS_FINANCEIRAS_FALTANTE),
            );
        }

        $despFin = $dre->despesasFinanceirasAnuais();
        if ($despFin === null) {
            // Defesa: Q16 presente mas não-numérico.
            return IndicadorResultado::indisponivel(
                MotivosIndisponibilidade::DESPESAS_FINANCEIRAS_FALTANTE,
                MotivosIndisponibilidade::mensagem(MotivosIndisponibilidade::DESPESAS_FINANCEIRAS_FALTANTE),
            );
        }

        $ebitda = $dre->ebitda();
        if ($ebitda === null) {
            // Q08/Q09/Q14/Q15 não totalmente informados (propaga via DRE).
            if (($payload['Q09'] ?? null) === null) {
                return IndicadorResultado::indisponivel(
                    MotivosIndisponibilidade::VENDAS_FALTANTE,
                    MotivosIndisponibilidade::mensagem(MotivosIndisponibilidade::VENDAS_FALTANTE),
                );
            }

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

        $percentual = bcmul(bcdiv($despFin, $ebitda, 6), '100', DreAdaptada::ESCALA);
        $valor = (float) $percentual;

        $farol = FarolIndustria::classificar('despesas_fin_ebitda', $valor);

        return new IndicadorResultado(
            valor: $valor,
            farol: $farol,
            motivo: null,
            mensagem: MensagensFarol::paraFarol($farol),
        );
    }
}

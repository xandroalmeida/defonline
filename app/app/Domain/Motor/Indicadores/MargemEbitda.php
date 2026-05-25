<?php

declare(strict_types=1);

namespace App\Domain\Motor\Indicadores;

use App\Domain\Motor\Calculos\DreAdaptada;
use App\Domain\Motor\Farois\FarolIndustria;
use App\Domain\Motor\IndicadorResultado;
use App\Domain\Motor\MensagensFarol;
use App\Domain\Motor\MotivosIndisponibilidade;

/**
 * Indicador #2 — Margem EBITDA = `EBITDA / Vendas × 100` (Anexo D).
 * EBITDA = LB − Despesas fixas − Despesas variáveis (Anexo C).
 *
 * Anexo E (Indústria): > 20% verde · 15.01–20% amarelo · ≤ 15% vermelho.
 *
 * Casos extremos (catálogo §2):
 *   - Vendas zero → `indisponivel:vendas_zero`.
 *   - Q14 ou Q15 ausente → `indisponivel:despesas_faltante`.
 *   - Q08 ausente → `indisponivel:despesas_faltante` (LB nulo via Compras).
 *   - Q09 ausente → `indisponivel:vendas_faltante`.
 *   - EBITDA calculado < −Vendas (margem inferior a −100%) → `indisponivel:ebitda_extremo`
 *     — input suspeito, não classifica.
 *
 * **Margem EBITDA pode ser negativa** (prejuízo operacional) — semanticamente válida
 * desde que ≥ −100%; classifica normalmente (provável vermelho).
 */
final class MargemEbitda implements Indicador
{
    public function chave(): string
    {
        return 'margem_ebitda';
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

        if (($payload['Q14'] ?? null) === null || ($payload['Q15'] ?? null) === null) {
            return IndicadorResultado::indisponivel(
                MotivosIndisponibilidade::DESPESAS_FALTANTE,
                MotivosIndisponibilidade::mensagem(MotivosIndisponibilidade::DESPESAS_FALTANTE),
            );
        }

        $ebitda = $dre->ebitda();
        if ($ebitda === null) {
            // Defesa: Q08 faltante (LB null) ou Q*/etc não-numérico.
            return IndicadorResultado::indisponivel(
                MotivosIndisponibilidade::DESPESAS_FALTANTE,
                MotivosIndisponibilidade::mensagem(MotivosIndisponibilidade::DESPESAS_FALTANTE),
            );
        }

        // EBITDA < −Vendas → margem < −100% (input suspeito).
        $vendasNeg = bcmul($vendas, '-1', DreAdaptada::ESCALA);
        if (bccomp($ebitda, $vendasNeg, DreAdaptada::ESCALA) < 0) {
            return IndicadorResultado::indisponivel(
                MotivosIndisponibilidade::EBITDA_EXTREMO,
                MotivosIndisponibilidade::mensagem(MotivosIndisponibilidade::EBITDA_EXTREMO),
            );
        }

        $percentual = bcmul(bcdiv($ebitda, $vendas, 6), '100', DreAdaptada::ESCALA);
        $valor = (float) $percentual;

        $farol = FarolIndustria::classificar('margem_ebitda', $valor);

        return new IndicadorResultado(
            valor: $valor,
            farol: $farol,
            motivo: null,
            mensagem: MensagensFarol::paraFarol($farol),
        );
    }
}

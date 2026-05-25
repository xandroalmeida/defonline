<?php

declare(strict_types=1);

namespace App\Domain\Motor\Indicadores;

use App\Domain\Motor\Calculos\DreAdaptada;
use App\Domain\Motor\Farois\FarolIndustria;
use App\Domain\Motor\IndicadorResultado;
use App\Domain\Motor\MatrizRecomendacoes;
use App\Domain\Motor\MotivosIndisponibilidade;

/**
 * Indicador #1 — Margem Bruta = `Lucro Bruto / Vendas × 100` (Anexo D).
 *
 * Casos extremos (catálogo §1):
 *   - Vendas zero → `indisponivel:vendas_zero`.
 *   - Q09 faltante → `indisponivel:vendas_faltante`.
 *   - Q08 faltante (Compras) → indispoñível por LB nulo: mesma chave de vendas_faltante
 *     **não** se aplica — usamos um motivo derivado. A spec não cita Q08 faltante
 *     explicitamente; tratamos como dado da DRE faltante (similar ao caso de despesas).
 */
final class MargemBruta implements Indicador
{
    public function chave(): string
    {
        return 'margem_bruta';
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
            // Q09 presente mas não-numérico — defensa.
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

        $lb = $dre->lucroBruto();
        if ($lb === null) {
            // Compras (Q08) faltante.
            return IndicadorResultado::indisponivel(
                MotivosIndisponibilidade::VENDAS_FALTANTE,
                MotivosIndisponibilidade::mensagem(MotivosIndisponibilidade::VENDAS_FALTANTE),
            );
        }

        // Percentual = LB / Vendas × 100. Escala maior na divisão pra evitar truncamento.
        $percentual = bcmul(bcdiv($lb, $vendas, 6), '100', DreAdaptada::ESCALA);
        $valor = (float) $percentual;

        $farol = FarolIndustria::classificar('margem_bruta', $valor);

        return new IndicadorResultado(
            valor: $valor,
            farol: $farol,
            motivo: null,
            mensagem: (new MatrizRecomendacoes)->texto($this->chave(), $farol),
        );
    }
}

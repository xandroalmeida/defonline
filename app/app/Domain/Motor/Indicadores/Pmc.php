<?php

declare(strict_types=1);

namespace App\Domain\Motor\Indicadores;

use App\Domain\Motor\Calculos\DreAdaptada;
use App\Domain\Motor\Farois\FarolIndustria;
use App\Domain\Motor\IndicadorResultado;
use App\Domain\Motor\MensagensFarol;
use App\Domain\Motor\MotivosIndisponibilidade;

/**
 * Indicador #11 — PMC (prazo médio de pagamento a fornecedores) = Q10 declarado (Anexo D).
 *
 * Diferente do PMR/PME, **PMC é "maior melhor"** — pagar mais tarde a
 * fornecedores é vantagem operacional dentro de limites (espec §4.5; Anexo E:
 * "PMC > 60 verde · 30.01–60 amarelo · ≤ 30 vermelho").
 *
 * Casos extremos (catálogo §11):
 *   - Q10 ausente → `indisponivel:pmc_faltante`.
 */
final class Pmc implements Indicador
{
    public function chave(): string
    {
        return 'pmc';
    }

    public function calcular(array $payload, DreAdaptada $dre): IndicadorResultado
    {
        $q10 = $payload['Q10'] ?? null;
        if ($q10 === null || ! is_numeric($q10)) {
            return IndicadorResultado::indisponivel(
                MotivosIndisponibilidade::PMC_FALTANTE,
                MotivosIndisponibilidade::mensagem(MotivosIndisponibilidade::PMC_FALTANTE),
            );
        }

        $valor = (float) $q10;
        $farol = FarolIndustria::classificar('pmc', $valor);

        return new IndicadorResultado(
            valor: $valor,
            farol: $farol,
            motivo: null,
            mensagem: MensagensFarol::paraFarol($farol),
        );
    }
}

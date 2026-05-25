<?php

declare(strict_types=1);

namespace App\Domain\Motor\Indicadores;

use App\Domain\Motor\Calculos\DreAdaptada;
use App\Domain\Motor\Farois\FarolIndustria;
use App\Domain\Motor\IndicadorResultado;
use App\Domain\Motor\MensagensFarol;
use App\Domain\Motor\MotivosIndisponibilidade;

/**
 * Indicador #8 — Ciclo Financeiro = `PME + PMR − PMC` (Anexo D).
 *   - PME = Q11 (estoque)
 *   - PMR = Q12 (a receber)
 *   - PMC = Q10 (a pagar)
 *
 * Casos extremos (catálogo §8):
 *   - Qualquer um (Q10, Q11, Q12) ausente → `indisponivel:prazo_faltante`.
 *   - Ciclo < −90 dias (matematicamente válido — paga depois de receber+vender)
 *     é calculado normalmente; a validação cruzada de outlier vive no quiz.
 *
 * **PME (Q11) entra como input desta V1 mas NÃO é exibido como linha de
 * relatório.** Mostrar PME isolado é da STORY-030 (motor V2).
 */
final class CicloFinanceiro implements Indicador
{
    public function chave(): string
    {
        return 'ciclo_financeiro';
    }

    public function calcular(array $payload, DreAdaptada $dre): IndicadorResultado
    {
        $q10 = $payload['Q10'] ?? null;
        $q11 = $payload['Q11'] ?? null;
        $q12 = $payload['Q12'] ?? null;

        if ($q10 === null || $q11 === null || $q12 === null
            || ! is_numeric($q10) || ! is_numeric($q11) || ! is_numeric($q12)) {
            return IndicadorResultado::indisponivel(
                MotivosIndisponibilidade::PRAZO_FALTANTE,
                MotivosIndisponibilidade::mensagem(MotivosIndisponibilidade::PRAZO_FALTANTE),
            );
        }

        // bcmath para precisão (dias podem ser inteiros mas a soma com sinal usa bcadd/bcsub).
        $valor = (float) bcsub(bcadd((string) $q11, (string) $q12, DreAdaptada::ESCALA), (string) $q10, DreAdaptada::ESCALA);

        $farol = FarolIndustria::classificar('ciclo_financeiro', $valor);

        return new IndicadorResultado(
            valor: $valor,
            farol: $farol,
            motivo: null,
            mensagem: MensagensFarol::paraFarol($farol),
        );
    }
}

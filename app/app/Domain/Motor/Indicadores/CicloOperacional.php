<?php

declare(strict_types=1);

namespace App\Domain\Motor\Indicadores;

use App\Domain\Motor\Calculos\DreAdaptada;
use App\Domain\Motor\Farol;
use App\Domain\Motor\IndicadorResultado;
use App\Domain\Motor\MotivosIndisponibilidade;

/**
 * Indicador complementar — Ciclo Operacional = `PME + PMR` (sem PMC).
 *
 * **Informativo — sem farol.** Não está no Anexo D explicitamente (apenas
 * Ciclo Financeiro #8 = PME + PMR − PMC), mas o `epic.md` lista entre os 7
 * a completar. Interpretação registrada na STORY-030 (Briefing): linha
 * contextual sem farol, similar ao NCG absoluto.
 *
 * Casos extremos:
 *   - Q11 ∨ Q12 ausente → `indisponivel:ciclo_operacional_prazo_faltante`.
 *
 * **Sem mensagem semântica por faixa** — esta classe entrega apenas a
 * mensagem padrão informativa. O texto definitivo da matriz DEZ/2025
 * (STORY-032) pode reescrever.
 */
final class CicloOperacional implements Indicador
{
    public const MSG_INFORMATIVO = 'Indicador informativo: ciclo operacional = PME + PMR.';

    public function chave(): string
    {
        return 'ciclo_operacional';
    }

    public function calcular(array $payload, DreAdaptada $dre): IndicadorResultado
    {
        $q11 = $payload['Q11'] ?? null;
        $q12 = $payload['Q12'] ?? null;

        if ($q11 === null || $q12 === null
            || ! is_numeric($q11) || ! is_numeric($q12)) {
            return IndicadorResultado::indisponivel(
                MotivosIndisponibilidade::CICLO_OPERACIONAL_PRAZO_FALTANTE,
                MotivosIndisponibilidade::mensagem(MotivosIndisponibilidade::CICLO_OPERACIONAL_PRAZO_FALTANTE),
            );
        }

        $valor = (float) bcadd((string) $q11, (string) $q12, DreAdaptada::ESCALA);

        return new IndicadorResultado(
            valor: $valor,
            farol: Farol::NENHUM,
            motivo: null,
            mensagem: self::MSG_INFORMATIVO,
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Motor\Indicadores;

use App\Domain\Motor\Calculos\DreAdaptada;
use App\Domain\Motor\Farois\FarolIndustria;
use App\Domain\Motor\IndicadorResultado;
use App\Domain\Motor\MensagensFarol;
use App\Domain\Motor\MotivosIndisponibilidade;

/**
 * Indicador #14 — Inadimplência = Q13 declarado (Anexo D), em % sobre clientes.
 *
 * Anexo E (Indústria): ≤ 3% verde · 3.01–5% amarelo · > 5% vermelho.
 *
 * Casos extremos (catálogo §14):
 *   - Q13 ausente → `indisponivel:inadimplencia_faltante`.
 *   - Q13 > 100% (inválido) → **exception** (invariante violada — validação CA-2
 *     da STORY-027 deveria ter bloqueado no quiz; se chegou aqui é bypass).
 */
final class Inadimplencia implements Indicador
{
    public function chave(): string
    {
        return 'inadimplencia';
    }

    public function calcular(array $payload, DreAdaptada $dre): IndicadorResultado
    {
        $q13 = $payload['Q13'] ?? null;
        if ($q13 === null || ! is_numeric($q13)) {
            return IndicadorResultado::indisponivel(
                MotivosIndisponibilidade::INADIMPLENCIA_FALTANTE,
                MotivosIndisponibilidade::mensagem(MotivosIndisponibilidade::INADIMPLENCIA_FALTANTE),
            );
        }

        $valor = (float) $q13;

        if ($valor > 100.0) {
            throw new \DomainException(
                "Inadimplência (Q13) inválida: {$valor}%. Esperado 0..100. ".
                'Validação CA-2 da STORY-027 deveria ter bloqueado no quiz.',
            );
        }

        $farol = FarolIndustria::classificar('inadimplencia', $valor);

        return new IndicadorResultado(
            valor: $valor,
            farol: $farol,
            motivo: null,
            mensagem: MensagensFarol::paraFarol($farol),
        );
    }
}

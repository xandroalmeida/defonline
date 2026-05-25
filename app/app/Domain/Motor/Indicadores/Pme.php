<?php

declare(strict_types=1);

namespace App\Domain\Motor\Indicadores;

use App\Domain\Motor\Calculos\DreAdaptada;
use App\Domain\Motor\Farois\FarolIndustria;
use App\Domain\Motor\IndicadorResultado;
use App\Domain\Motor\MensagensFarol;
use App\Domain\Motor\MotivosIndisponibilidade;

/**
 * Indicador #12 — PME (prazo médio de estoque) = Q11 declarado (Anexo D).
 *
 * Anexo E (Indústria): ≤ 30 dias verde · 30.01–60 amarelo · > 60 vermelho.
 *
 * Casos extremos (catálogo §12):
 *   - Q11 ausente → `indisponivel:pme_faltante`.
 *   - Q11 > 365 dias é matematicamente classificado (provavelmente vermelho);
 *     validação cruzada "valor atípico" é responsabilidade do quiz §6.8.
 *
 * **Não aplicável para Serviços (Q01=3)** — mas no EPIC-002 só Indústria entra,
 * então PME é sempre obrigatório. Suporte multi-setor entra em estória futura.
 */
final class Pme implements Indicador
{
    public function chave(): string
    {
        return 'pme';
    }

    public function calcular(array $payload, DreAdaptada $dre): IndicadorResultado
    {
        $q11 = $payload['Q11'] ?? null;
        if ($q11 === null || ! is_numeric($q11)) {
            return IndicadorResultado::indisponivel(
                MotivosIndisponibilidade::PME_FALTANTE,
                MotivosIndisponibilidade::mensagem(MotivosIndisponibilidade::PME_FALTANTE),
            );
        }

        $valor = (float) $q11;
        $farol = FarolIndustria::classificar('pme', $valor);

        return new IndicadorResultado(
            valor: $valor,
            farol: $farol,
            motivo: null,
            mensagem: MensagensFarol::paraFarol($farol),
        );
    }
}

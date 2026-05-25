<?php

declare(strict_types=1);

namespace App\Domain\Motor\Indicadores;

use App\Domain\Motor\Calculos\DreAdaptada;
use App\Domain\Motor\Farois\FarolIndustria;
use App\Domain\Motor\IndicadorResultado;
use App\Domain\Motor\MensagensFarol;
use App\Domain\Motor\MotivosIndisponibilidade;

/**
 * Indicador #13 — PMR (prazo médio de recebimento) = Q12 declarado (Anexo D).
 *
 * Casos extremos (catálogo §13):
 *   - Q12 ausente → `indisponivel:pmr_faltante`.
 *   - Q12 > 365 dias é matemáticamente classificado (provavelmente vermelho).
 *     A validação cruzada de "valor atípico" é responsabilidade do quiz §6.8.
 */
final class Pmr implements Indicador
{
    public function chave(): string
    {
        return 'pmr';
    }

    public function calcular(array $payload, DreAdaptada $dre): IndicadorResultado
    {
        $q12 = $payload['Q12'] ?? null;
        if ($q12 === null || ! is_numeric($q12)) {
            return IndicadorResultado::indisponivel(
                MotivosIndisponibilidade::PMR_FALTANTE,
                MotivosIndisponibilidade::mensagem(MotivosIndisponibilidade::PMR_FALTANTE),
            );
        }

        $valor = (float) $q12;
        $farol = FarolIndustria::classificar('pmr', $valor);

        return new IndicadorResultado(
            valor: $valor,
            farol: $farol,
            motivo: null,
            mensagem: MensagensFarol::paraFarol($farol),
        );
    }
}

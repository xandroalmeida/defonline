<?php

declare(strict_types=1);

namespace App\Domain\Motor\Indicadores;

use App\Domain\Motor\Calculos\DreAdaptada;
use App\Domain\Motor\Farois\FarolIndustria;
use App\Domain\Motor\IndicadorResultado;
use App\Domain\Motor\MensagensFarol;
use App\Domain\Motor\MotivosIndisponibilidade;

/**
 * Indicador #6 — Fontes de Recursos = `PC / PL` (Anexo D).
 * PC = Q06 (dívidas) + Q07 (fornecedores). PL = AT − PC (DreAdaptada).
 *
 * Anexo E (Indústria): ≤ 0,5 verde · 0,501–1 amarelo · > 1 vermelho.
 *
 * Casos extremos (catálogo §6):
 *   - PL ≤ 0 → `indisponivel:pl_nao_positivo` (passivo ≥ ativo).
 *   - Q02 ∨ Q03 ∨ Q04 ∨ Q05 = null (AT faltante) → `indisponivel:ativo_componente_faltante`.
 *   - Q06 ∨ Q07 = null (PC faltante) → `indisponivel:divida_componente_faltante`.
 */
final class FontesRecursos implements Indicador
{
    public function chave(): string
    {
        return 'fontes_recursos';
    }

    public function calcular(array $payload, DreAdaptada $dre): IndicadorResultado
    {
        if (($payload['Q02'] ?? null) === null
            || ($payload['Q03'] ?? null) === null
            || ($payload['Q04'] ?? null) === null
            || ($payload['Q05'] ?? null) === null) {
            return IndicadorResultado::indisponivel(
                MotivosIndisponibilidade::ATIVO_COMPONENTE_FALTANTE,
                MotivosIndisponibilidade::mensagem(MotivosIndisponibilidade::ATIVO_COMPONENTE_FALTANTE),
            );
        }

        if (($payload['Q06'] ?? null) === null || ($payload['Q07'] ?? null) === null) {
            return IndicadorResultado::indisponivel(
                MotivosIndisponibilidade::DIVIDA_COMPONENTE_FALTANTE,
                MotivosIndisponibilidade::mensagem(MotivosIndisponibilidade::DIVIDA_COMPONENTE_FALTANTE),
            );
        }

        $at = $dre->ativoTotal();
        $pc = $dre->passivoCirculante();
        if ($at === null) {
            return IndicadorResultado::indisponivel(
                MotivosIndisponibilidade::ATIVO_COMPONENTE_FALTANTE,
                MotivosIndisponibilidade::mensagem(MotivosIndisponibilidade::ATIVO_COMPONENTE_FALTANTE),
            );
        }
        if ($pc === null) {
            return IndicadorResultado::indisponivel(
                MotivosIndisponibilidade::DIVIDA_COMPONENTE_FALTANTE,
                MotivosIndisponibilidade::mensagem(MotivosIndisponibilidade::DIVIDA_COMPONENTE_FALTANTE),
            );
        }

        $pl = bcsub($at, $pc, DreAdaptada::ESCALA);
        if (bccomp($pl, '0', DreAdaptada::ESCALA) <= 0) {
            return IndicadorResultado::indisponivel(
                MotivosIndisponibilidade::PL_NAO_POSITIVO,
                MotivosIndisponibilidade::mensagem(MotivosIndisponibilidade::PL_NAO_POSITIVO),
            );
        }

        $ratio = bcdiv($pc, $pl, 6);
        $valor = (float) bcadd($ratio, '0', 4);

        $farol = FarolIndustria::classificar('fontes_recursos', $valor);

        return new IndicadorResultado(
            valor: $valor,
            farol: $farol,
            motivo: null,
            mensagem: MensagensFarol::paraFarol($farol),
        );
    }
}

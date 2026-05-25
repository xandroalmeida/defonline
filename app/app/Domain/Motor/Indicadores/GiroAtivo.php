<?php

declare(strict_types=1);

namespace App\Domain\Motor\Indicadores;

use App\Domain\Motor\Calculos\DreAdaptada;
use App\Domain\Motor\Farois\FarolIndustria;
use App\Domain\Motor\IndicadorResultado;
use App\Domain\Motor\MensagensFarol;
use App\Domain\Motor\MotivosIndisponibilidade;

/**
 * Indicador #7 — Giro do Ativo = `Vendas / Ativo Total` (Anexo D).
 * AT = Q02 + Q03 + Q04 + Q05 (DreAdaptada).
 *
 * Anexo E (Indústria): > 2 verde · 1.01–2 amarelo · ≤ 1 vermelho.
 *
 * Casos extremos (catálogo §7):
 *   - AT = 0 → `indisponivel:ativo_zero`.
 *   - Q02 ∨ Q03 ∨ Q04 ∨ Q05 = null → `indisponivel:ativo_componente_faltante`.
 *   - Q09 ausente → `indisponivel:vendas_faltante`.
 *
 * Vendas zero é semanticamente válido (Giro = 0 → vermelho); não dispara
 * indisponibilidade.
 */
final class GiroAtivo implements Indicador
{
    public function chave(): string
    {
        return 'giro_ativo';
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

        $at = $dre->ativoTotal();
        if ($at === null) {
            // Defesa: componente não-numérico.
            return IndicadorResultado::indisponivel(
                MotivosIndisponibilidade::ATIVO_COMPONENTE_FALTANTE,
                MotivosIndisponibilidade::mensagem(MotivosIndisponibilidade::ATIVO_COMPONENTE_FALTANTE),
            );
        }

        if (bccomp($at, '0', DreAdaptada::ESCALA) === 0) {
            return IndicadorResultado::indisponivel(
                MotivosIndisponibilidade::ATIVO_ZERO,
                MotivosIndisponibilidade::mensagem(MotivosIndisponibilidade::ATIVO_ZERO),
            );
        }

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

        $giro = bcdiv($vendas, $at, 6);
        // Arredonda a 4 casas (idempotência — bcdiv só trunca).
        $valor = (float) bcadd($giro, '0', 4);

        $farol = FarolIndustria::classificar('giro_ativo', $valor);

        return new IndicadorResultado(
            valor: $valor,
            farol: $farol,
            motivo: null,
            mensagem: MensagensFarol::paraFarol($farol),
        );
    }
}

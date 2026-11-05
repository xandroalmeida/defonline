<?php

declare(strict_types=1);

namespace App\Domain\Cobranca;

/**
 * STORY-075 / spec STORY-070-planos-e-precos §3 — valores derivados que a página de planos mostra.
 * O catálogo guarda só o preço mensal e o anual; o resto é conta, feita aqui (em centavos
 * inteiros, meio centavo para cima) e nunca digitada em tela.
 */
final class VitrinePrecos
{
    /** O preço anual dividido em 12 parcelas iguais. */
    public static function equivalenteMensal(int $anualCentavos): int
    {
        return intdiv($anualCentavos + 6, 12);
    }

    /** Quanto o anual economiza sobre 12 mensais, em % inteiro. Zero se não economiza. */
    public static function economiaPercentual(int $mensalCentavos, int $anualCentavos): int
    {
        $dozeMensais = $mensalCentavos * 12;

        if ($dozeMensais <= 0 || $anualCentavos >= $dozeMensais) {
            return 0;
        }

        return intdiv(($dozeMensais - $anualCentavos) * 100 + intdiv($dozeMensais, 2), $dozeMensais);
    }

    /** Preço de uma análise dentro do pacote. */
    public static function unitario(int $precoCentavos, int $quantidade): int
    {
        return $quantidade < 1 ? 0 : intdiv($precoCentavos * 2 + $quantidade, $quantidade * 2);
    }
}

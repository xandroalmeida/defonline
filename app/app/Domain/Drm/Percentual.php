<?php

declare(strict_types=1);

namespace App\Domain\Drm;

/**
 * Percentual sobre a receita em aritmética inteira (STORY-048 CA-2, CA-8).
 *
 * O snapshot guarda o percentual **truncado** (em direção a zero) em quatro casas,
 * como string: `13.3333`. Truncar — e não arredondar — é o que torna o arredondamento
 * da tela exato: arredondar meio-para-cima em uma casa a partir de `13.3500` dá o
 * mesmo resultado que arredondar o valor exato, e a partir de um valor já arredondado
 * em quatro casas poderia não dar (`13.34996` viraria `13.3500` e depois `13.4`).
 * Nenhum ponto flutuante: a divisão é feita com quociente e resto inteiros, sem o
 * estouro que `nominal * 1e6` poderia causar perto do teto de 64 bits.
 */
final class Percentual
{
    public const CASAS = 4;

    /** `parte / base * 100`, truncado em quatro casas. `null` se a base é zero. */
    public static function deCentavos(int $parte, int $base): ?string
    {
        if ($base <= 0) {
            return null;
        }

        $magnitude = abs($parte) * 100;
        $quociente = intdiv($magnitude, $base);
        $fracao = intdiv(($magnitude % $base) * 10_000, $base);

        $sinal = $parte < 0 && ($quociente > 0 || $fracao > 0) ? '-' : '';

        return $sinal.$quociente.'.'.str_pad((string) $fracao, self::CASAS, '0', STR_PAD_LEFT);
    }

    /**
     * Apresentação: uma casa decimal, meio para cima (em módulo), vírgula decimal.
     * `13.3500` → `13,4`; `-0.0400` → `0,0`.
     */
    public static function formatar(string $percentual): string
    {
        $negativo = str_starts_with($percentual, '-');
        [$inteiro, $fracao] = explode('.', ltrim($percentual, '-'));

        $decimos = (int) $inteiro * 10 + (int) $fracao[0];
        if ((int) $fracao[1] >= 5) {
            $decimos++;
        }

        return ($negativo && $decimos > 0 ? '-' : '').intdiv($decimos, 10).','.($decimos % 10);
    }
}

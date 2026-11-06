<?php

declare(strict_types=1);

namespace App\Support\Drm;

use App\Domain\Drm\Dinheiro;
use App\Domain\Drm\Percentual;

/** Texto de tela para dinheiro e percentual do DRM: sinal de menos de verdade (U+2212), nunca cor (DDR-004 item 6). */
final class Formato
{
    /** `R$ 3.240,00` / `−R$ 3.240,00`. */
    public static function moeda(int $centavos, bool $comMoeda = true): string
    {
        $texto = ($comMoeda ? 'R$ ' : '').Dinheiro::mascara(abs($centavos));

        return $centavos < 0 ? '−'.$texto : $texto;
    }

    /** `12,4%` / `−2,1%`: o percentual do snapshot (`-2.1000`) a uma casa. */
    public static function percentual(string $percentual): string
    {
        return self::sinal(Percentual::formatar($percentual)).'%';
    }

    /** `+12,4%` / `−2,1%` / `0,0%`: variação, onde o sinal e a palavra carregam a direção. */
    public static function variacao(string $percentual): string
    {
        $formatado = Percentual::formatar($percentual);
        if (str_starts_with($formatado, '-')) {
            return '−'.substr($formatado, 1).'%';
        }

        return ($formatado === '0,0' ? '' : '+').$formatado.'%';
    }

    /** `1 mês lançado` / `3 meses lançados` (STORY-095, O-5). */
    public static function mesesLancados(int $n): string
    {
        return $n === 1 ? '1 mês lançado' : $n.' meses lançados';
    }

    public static function sinal(string $percentualFormatado): string
    {
        return str_starts_with($percentualFormatado, '-') ? '−'.substr($percentualFormatado, 1) : $percentualFormatado;
    }
}

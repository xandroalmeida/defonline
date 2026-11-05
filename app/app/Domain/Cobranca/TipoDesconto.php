<?php

declare(strict_types=1);

namespace App\Domain\Cobranca;

/**
 * STORY-072 / spec §2.5 — o desconto de um cupom é um percentual (1 a 100) ou um valor fixo em centavos.
 */
enum TipoDesconto: string
{
    case Percentual = 'percentual';
    case ValorFixo = 'valor_fixo';

    /**
     * Desconto em centavos sobre um preço. Nunca passa do preço: o valor final não fica negativo.
     * O percentual arredonda para o centavo mais próximo (metade para cima), em inteiros.
     */
    public function desconto(int $valor, int $precoCentavos): int
    {
        $preco = max(0, $precoCentavos);
        $bruto = match ($this) {
            self::Percentual => intdiv($preco * $valor + 50, 100),
            self::ValorFixo => $valor,
        };

        return max(0, min($bruto, $preco));
    }

    public function rotulo(): string
    {
        return match ($this) {
            self::Percentual => 'Percentual',
            self::ValorFixo => 'Valor fixo',
        };
    }

    /** Legível: "20%" ou "R$ 50,00". */
    public function formatar(int $valor): string
    {
        return match ($this) {
            self::Percentual => $valor.'%',
            self::ValorFixo => 'R$ '.number_format($valor / 100, 2, ',', '.'),
        };
    }
}

<?php

declare(strict_types=1);

use App\Domain\Drm\Percentual;
use App\Support\Drm\Formato;

/* STORY-051 — aritmética de percentual do snapshot sem ponto flutuante, e o texto de tela. */

it('converte o percentual do snapshot em unidades de 0,0001 ponto e volta', function () {
    expect(Percentual::emUnidades('13.3333'))->toBe(133_333)
        ->and(Percentual::emUnidades('-13.3333'))->toBe(-133_333)
        ->and(Percentual::emUnidades('0.0000'))->toBe(0)
        ->and(Percentual::deUnidades(133_333))->toBe('13.3333')
        ->and(Percentual::deUnidades(-5))->toBe('-0.0005')
        ->and(Percentual::deUnidades(0))->toBe('0.0000')
        ->and(Percentual::deUnidades(1_000_000))->toBe('100.0000');
});

it('recusa texto fora do formato do snapshot', function (string $texto) {
    expect(fn () => Percentual::emUnidades($texto))->toThrow(InvalidArgumentException::class);
})->with(['12.5', '12,5000', 'abc', '', '1.23456']);

it('média simples, truncada em direção a zero na 4ª casa', function () {
    expect(Percentual::media(['10.0000', '20.0000', '33.3333']))->toBe('21.1111')
        ->and(Percentual::media(['5.0000']))->toBe('5.0000')
        ->and(Percentual::media(['-1.0000', '0.0001']))->toBe('-0.4999')   // −9999 / 2 = −4999,5 → −4999
        ->and(Percentual::media(['-4.0000', '2.0000']))->toBe('-1.0000');
});

describe('Formato', function () {
    it('moeda com sinal de menos de verdade', function () {
        expect(Formato::moeda(424_000))->toBe('R$ 4.240,00')
            ->and(Formato::moeda(-118_000))->toBe('−R$ 1.180,00')
            ->and(Formato::moeda(-118_000, false))->toBe('−1.180,00')
            ->and(Formato::moeda(0))->toBe('R$ 0,00');
    });

    it('percentual a uma casa, com U+2212 no negativo', function () {
        expect(Formato::percentual('5.0000'))->toBe('5,0%')
            ->and(Formato::percentual('-1.3499'))->toBe('−1,3%');
    });

    it('variação: + explícito, − real, e zero sem sinal', function () {
        expect(Formato::variacao('12.4668'))->toBe('+12,5%')
            ->and(Formato::variacao('-2.1000'))->toBe('−2,1%')
            ->and(Formato::variacao('0.0000'))->toBe('0,0%')
            ->and(Formato::variacao('-0.0400'))->toBe('0,0%');
    });
});

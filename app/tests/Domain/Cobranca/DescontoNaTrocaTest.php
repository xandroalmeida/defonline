<?php

declare(strict_types=1);

use App\Domain\Cobranca\DescontoDaAssinatura;
use App\Domain\Cobranca\DescontoNaTroca;
use App\Domain\Cobranca\TipoDesconto;

/** STORY-082 × 083 — o que a troca de plano faz com o desconto de cupom da assinatura. */
describe('preço do ciclo em vigor (base do crédito)', function () {
    it('com desconto no ciclo, vale o preço com desconto; sem desconto ou depois que acabou, o contratado', function () {
        $d = new DescontoDaAssinatura(10000, 4000, 3);

        expect(DescontoNaTroca::precoDoCicloEmVigor($d, 1, 6000))->toBe(6000)
            ->and(DescontoNaTroca::precoDoCicloEmVigor($d, 3, 6000))->toBe(6000)
            ->and(DescontoNaTroca::precoDoCicloEmVigor($d, 4, 10000))->toBe(10000)
            ->and(DescontoNaTroca::precoDoCicloEmVigor(null, 2, 7777))->toBe(7777)
            ->and(DescontoNaTroca::precoDoCicloEmVigor(new DescontoDaAssinatura(10000, 0, null), 1, 10000))->toBe(10000);
    });
});

describe('desconto sobre o plano novo', function () {
    $sobre = fn (?DescontoDaAssinatura $atual, int $pagos = 1, bool $mesmo = true, bool $cobre = true, TipoDesconto $tipo = TipoDesconto::Percentual, int $valor = 50, int $novo = 20000) => DescontoNaTroca::sobreOPlanoNovo($atual, $pagos, $mesmo, $cobre, $tipo, $valor, $novo, 500);

    it('continua recalculado sobre o preço novo, com os mesmos ciclos', function () use ($sobre) {
        $novo = $sobre(new DescontoDaAssinatura(10000, 5000, 3));

        expect($novo)->not->toBeNull()
            ->and($novo->cheio)->toBe(20000)->and($novo->desconto)->toBe(10000)->and($novo->periodos)->toBe(3)
            ->and($novo->valorDoPeriodo(2))->toBe(10000);
    });

    it('não leva desconto quando não havia, o cupom não cobre o plano novo, o ciclo mudou ou o desconto já acabou', function () use ($sobre) {
        $d = new DescontoDaAssinatura(10000, 5000, 3);

        expect($sobre(null))->toBeNull()
            ->and($sobre($d, cobre: false))->toBeNull()
            ->and($sobre($d, mesmo: false))->toBeNull()
            ->and($sobre($d, pagos: 4))->toBeNull();
    });

    it('o valor final nunca fica negativo: valor fixo maior que o preço novo vira desconto total', function () use ($sobre) {
        $novo = $sobre(new DescontoDaAssinatura(10000, 3000, null), tipo: TipoDesconto::ValorFixo, valor: 90000, novo: 20000);

        expect($novo->desconto)->toBe(20000)->and($novo->valorDoPeriodo(5))->toBe(0);
    });

    it('termina na troca só se ainda valeria em ciclos futuros e não continua', function () use ($sobre) {
        $d = new DescontoDaAssinatura(10000, 5000, 3);

        expect(DescontoNaTroca::termina($d, 1, null))->toBeTrue()
            ->and(DescontoNaTroca::termina($d, 2, null))->toBeTrue()
            ->and(DescontoNaTroca::termina($d, 3, null))->toBeFalse()   // o último ciclo com desconto: acabaria de qualquer jeito
            ->and(DescontoNaTroca::termina($d, 1, $sobre($d)))->toBeFalse()
            ->and(DescontoNaTroca::termina(null, 1, null))->toBeFalse()
            ->and(DescontoNaTroca::termina(new DescontoDaAssinatura(10000, 5000, null), 9, null))->toBeTrue();
    });
});

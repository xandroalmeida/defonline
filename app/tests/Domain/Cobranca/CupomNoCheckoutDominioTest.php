<?php

declare(strict_types=1);

use App\Domain\Cobranca\CicloCobranca;
use App\Domain\Cobranca\DescontoDaAssinatura;
use App\Domain\Cobranca\PrecoComCupom;
use App\Domain\Cobranca\TipoDesconto;
use Carbon\CarbonImmutable;

/** STORY-083 CA-1, CA-5, CA-7, CA-8 — regras puras do desconto. */
describe('PrecoComCupom', function () {
    it('mostra preço cheio, desconto e valor a pagar', function () {
        $p = PrecoComCupom::calcular(4990, TipoDesconto::Percentual, 50, 500);

        expect($p->cheio)->toBe(4990)->and($p->desconto)->toBe(2495)->and($p->final)->toBe(2495)
            ->and($p->semCobranca())->toBeFalse()->and($p->comDesconto())->toBeTrue();
    });

    it('sem cupom não há desconto', function () {
        $p = PrecoComCupom::semCupom(4990);

        expect($p->final)->toBe(4990)->and($p->comDesconto())->toBeFalse();
        expect(PrecoComCupom::semCupom(-5)->final)->toBe(0);
    });

    it('o valor final nunca fica negativo (valor fixo maior que o preço)', function () {
        $p = PrecoComCupom::calcular(4990, TipoDesconto::ValorFixo, 999999, 500);

        expect($p->final)->toBe(0)->and($p->desconto)->toBe(4990)->and($p->semCobranca())->toBeTrue()->and($p->abaixoDoMinimo)->toBeFalse();
    });

    it('desconto de 100% é sem cobrança', function () {
        $p = PrecoComCupom::calcular(4990, TipoDesconto::Percentual, 100, 500);

        expect($p->semCobranca())->toBeTrue()->and($p->desconto)->toBe(4990);
    });

    it('valor final abaixo do mínimo do provedor vira sem cobrança', function () {
        $p = PrecoComCupom::calcular(4990, TipoDesconto::ValorFixo, 4700, 500);

        expect($p->semCobranca())->toBeTrue()->and($p->abaixoDoMinimo)->toBeTrue()->and($p->desconto)->toBe(4990);
    });

    it('valor final exatamente no mínimo ainda é cobrado', function () {
        $p = PrecoComCupom::calcular(4990, TipoDesconto::ValorFixo, 4490, 500);

        expect($p->final)->toBe(500)->and($p->semCobranca())->toBeFalse();
    });
});

describe('DescontoDaAssinatura', function () {
    $d = fn (?int $periodos = 3, int $desc = 2495) => new DescontoDaAssinatura(4990, $desc, $periodos);

    it('vale pelos N primeiros ciclos e depois volta ao cheio', function () use ($d) {
        $x = $d();

        expect($x->valorDoPeriodo(1))->toBe(2495)->and($x->valorDoPeriodo(3))->toBe(2495)
            ->and($x->valorDoPeriodo(4))->toBe(4990)->and($x->vale(4))->toBeFalse();
    });

    it('sem duração vale para sempre', function () use ($d) {
        expect($d(null)->vale(500))->toBeTrue()->and($d(null)->acabaApos(3))->toBeFalse()->and($d(null)->fim(CicloCobranca::Mensal, now()))->toBeNull();
    });

    it('sem desconto nada vale', function () use ($d) {
        $x = $d(3, 0);

        expect($x->existe())->toBeFalse()->and($x->vale(1))->toBeFalse()->and($x->valorDoPeriodo(1))->toBe(4990)
            ->and($x->acabaApos(3))->toBeFalse()->and($x->fim(CicloCobranca::Mensal, now()))->toBeNull();
    });

    it('detecta o ciclo gratuito', function () use ($d) {
        expect($d(2, 4990)->gratuito(2))->toBeTrue()->and($d(2, 4990)->gratuito(3))->toBeFalse()->and($d()->gratuito(1))->toBeFalse();
    });

    it('o desconto acaba depois do último ciclo com desconto', function () use ($d) {
        expect($d()->acabaApos(3))->toBeTrue()->and($d()->acabaApos(2))->toBeFalse();
    });

    it('o fim é o vencimento do último ciclo com desconto e o aviso é a 7 dias dele', function () use ($d) {
        $inicio = CarbonImmutable::parse('2026-11-05 12:00:00');
        $x = $d();

        expect($x->fim(CicloCobranca::Mensal, $inicio)->toDateString())->toBe('2027-02-05')
            ->and($x->pertoDoFim(CicloCobranca::Mensal, $inicio, $inicio->setDate(2027, 1, 29)))->toBeTrue()
            ->and($x->pertoDoFim(CicloCobranca::Mensal, $inicio, $inicio->setDate(2027, 1, 20)))->toBeFalse()
            ->and($x->pertoDoFim(CicloCobranca::Mensal, $inicio, $inicio->setDate(2027, 2, 6)))->toBeFalse();
    });
});

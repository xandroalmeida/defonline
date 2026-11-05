<?php

declare(strict_types=1);

use App\Domain\Cobranca\CicloCobranca;
use App\Domain\Cobranca\CotaMensal;
use App\Domain\Cobranca\MeioPagamento;
use App\Domain\Cobranca\PedidoCheckout;
use Carbon\CarbonImmutable;

/**
 * STORY-077 CA-6 — ciclos e dia de aniversário da cota, sem banco.
 */
function _d(string $data): CarbonImmutable
{
    return CarbonImmutable::parse($data, 'UTC');
}

describe('CicloCobranca', function () {
    it('mede o ciclo em meses e escolhe o preço', function () {
        expect(CicloCobranca::Mensal->meses())->toBe(1)
            ->and(CicloCobranca::Anual->meses())->toBe(12)
            ->and(CicloCobranca::Mensal->precoDe(4990, 39900))->toBe(4990)
            ->and(CicloCobranca::Anual->precoDe(4990, 39900))->toBe(39900)
            ->and(CicloCobranca::Mensal->rotulo())->toBe('Mensal')
            ->and(CicloCobranca::Anual->rotulo())->toBe('Anual')
            ->and(CicloCobranca::Mensal->porExtenso())->toBe('por mês')
            ->and(CicloCobranca::Anual->porExtenso())->toBe('por ano');
    });

    it('ancora o vencimento no início, sem deriva de fim de mês', function () {
        $inicio = _d('2026-01-31 10:00:00');

        expect(CicloCobranca::Mensal->vencimento($inicio)->toDateString())->toBe('2026-02-28')
            ->and(CicloCobranca::Mensal->vencimento($inicio, 2)->toDateString())->toBe('2026-03-31')
            ->and(CicloCobranca::Anual->vencimento($inicio)->toDateString())->toBe('2027-01-31')
            ->and(CicloCobranca::Anual->vencimento(_d('2028-02-29'))->toDateString())->toBe('2029-02-28')
            ->and(CicloCobranca::Mensal->vencimento($inicio, 0)->toDateString())->toBe('2026-02-28');
    });
});

describe('CotaMensal::proxima', function () {
    it('é o aniversário seguinte dentro do ano pago', function () {
        $inicio = _d('2026-11-05 12:00:00');

        expect(CotaMensal::proxima($inicio, $inicio, CicloCobranca::Anual->vencimento($inicio))?->toDateString())->toBe('2026-12-05')
            ->and(CotaMensal::proxima($inicio, _d('2026-12-05 12:00:00'), CicloCobranca::Anual->vencimento($inicio))?->toDateString())->toBe('2027-01-05');
    });

    it('usa o último dia do mês quando o mês não tem o dia, e volta ao dia 31 depois', function () {
        $inicio = _d('2026-01-31 09:00:00');
        $fim = CicloCobranca::Anual->vencimento($inicio);

        expect(CotaMensal::proxima($inicio, $inicio, $fim)?->toDateString())->toBe('2026-02-28')
            ->and(CotaMensal::proxima($inicio, _d('2026-02-28 09:00:00'), $fim)?->toDateString())->toBe('2026-03-31')
            ->and(CotaMensal::proxima($inicio, _d('2026-03-31 09:00:00'), $fim)?->toDateString())->toBe('2026-04-30');
    });

    it('recupera dias perdidos: devolve o próximo aniversário ainda no futuro', function () {
        $inicio = _d('2026-01-10 00:00:00');

        expect(CotaMensal::proxima($inicio, _d('2026-04-20 00:00:00'), CicloCobranca::Anual->vencimento($inicio))?->toDateString())->toBe('2026-05-10');
    });

    it('é nula quando o próximo aniversário cai no fim do ano pago ou depois', function () {
        $inicio = _d('2026-01-10 00:00:00');
        $fim = CicloCobranca::Anual->vencimento($inicio);

        expect(CotaMensal::proxima($inicio, _d('2026-12-10 00:00:00'), $fim))->toBeNull()
            ->and(CotaMensal::proxima($inicio, _d('2026-12-20 00:00:00'), $fim))->toBeNull();
    });
});

describe('PedidoCheckout recorrente', function () {
    it('aceita cartão e recusa Pix na assinatura recorrente (ADR-010 4.4)', function () {
        $ok = new PedidoCheckout('ref', 'Plano', 4990, [MeioPagamento::Cartao], 60, 'https://a/ok', 'https://a/c', 'https://a/e', CicloCobranca::Mensal);

        expect($ok->recorrencia)->toBe(CicloCobranca::Mensal)
            ->and(fn () => new PedidoCheckout('ref', 'Plano', 39900, [MeioPagamento::Pix], 60, 'https://a/ok', 'https://a/c', 'https://a/e', CicloCobranca::Anual))
            ->toThrow(InvalidArgumentException::class)
            ->and(fn () => new PedidoCheckout('ref', 'Plano', 39900, [MeioPagamento::Cartao, MeioPagamento::Pix], 60, 'https://a/ok', 'https://a/c', 'https://a/e', CicloCobranca::Anual))
            ->toThrow(InvalidArgumentException::class);
    });
});

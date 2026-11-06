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
            ->and(CicloCobranca::Anual->vencimento(_d('2028-02-29 12:00:00'))->toDateString())->toBe('2029-02-28')
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

describe('calendário de Brasília na soma de meses', function () {
    function _br(string $data): CarbonImmutable
    {
        return CarbonImmutable::parse($data, 'America/Sao_Paulo')->utc(); // como vem do banco: em UTC
    }

    it('vence no mesmo dia de Brasília quando o pagamento da noite já é o mês seguinte em UTC', function () {
        $inicio = _br('2026-02-28 22:00:00'); // 01/03 01:00 UTC

        expect($inicio->utc()->toDateString())->toBe('2026-03-01')
            ->and(CicloCobranca::Mensal->vencimento($inicio)->setTimezone('America/Sao_Paulo')->format('Y-m-d H:i'))->toBe('2026-03-28 22:00')
            ->and(CicloCobranca::Anual->vencimento($inicio)->setTimezone('America/Sao_Paulo')->format('Y-m-d H:i'))->toBe('2027-02-28 22:00');
    });

    it('31/01 à noite vira 28/02 e volta a 31/03 sem deriva, mensal e anual', function () {
        $inicio = _br('2026-01-31 22:00:00');
        $em = fn (CarbonImmutable $d) => $d->setTimezone('America/Sao_Paulo')->format('Y-m-d H:i');

        expect($em(CicloCobranca::Mensal->vencimento($inicio)))->toBe('2026-02-28 22:00')
            ->and($em(CicloCobranca::Mensal->vencimento($inicio, 2)))->toBe('2026-03-31 22:00')
            ->and($em(CicloCobranca::Anual->vencimento($inicio)))->toBe('2027-01-31 22:00');
    });

    it('respeita o ano bissexto', function () {
        $inicio = _br('2028-01-29 23:00:00');
        $em = fn (CarbonImmutable $d) => $d->setTimezone('America/Sao_Paulo')->format('Y-m-d');

        expect($em(CicloCobranca::Mensal->vencimento($inicio)))->toBe('2028-02-29')
            ->and($em(CicloCobranca::Mensal->vencimento(_br('2028-02-29 22:00:00'))))->toBe('2028-03-29')
            ->and($em(CicloCobranca::Anual->vencimento(_br('2028-02-29 22:00:00'))))->toBe('2029-02-28');
    });

    it('a cota mensal do anual cai no dia de Brasília, não no de UTC', function () {
        $inicio = _br('2026-02-28 22:00:00');
        $fim = CicloCobranca::Anual->vencimento($inicio);
        $em = fn (?CarbonImmutable $d) => $d?->setTimezone('America/Sao_Paulo')->format('Y-m-d H:i');

        expect($em(CotaMensal::proxima($inicio, $inicio, $fim)))->toBe('2026-03-28 22:00')
            ->and($em(CotaMensal::proxima($inicio, _br('2026-03-28 22:00:00'), $fim)))->toBe('2026-04-28 22:00');

        [$ini, $fimJanela] = CotaMensal::janela($inicio, _br('2026-03-30 10:00:00'), $fim);

        expect($em($ini))->toBe('2026-03-28 22:00')
            ->and($em($fimJanela))->toBe('2026-04-28 22:00');
    });

    it('o dia 31 da cota volta a 31 depois de fevereiro, no horário de Brasília', function () {
        $inicio = _br('2026-01-31 22:00:00');
        $fim = CicloCobranca::Anual->vencimento($inicio);
        $em = fn (?CarbonImmutable $d) => $d?->setTimezone('America/Sao_Paulo')->format('Y-m-d');

        expect($em(CotaMensal::proxima($inicio, $inicio, $fim)))->toBe('2026-02-28')
            ->and($em(CotaMensal::proxima($inicio, _br('2026-02-28 22:00:00'), $fim)))->toBe('2026-03-31');
    });
});

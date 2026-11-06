<?php

declare(strict_types=1);

use App\Domain\Cobranca\CalculoDeTroca;
use App\Domain\Cobranca\CicloCobranca;
use App\Domain\Cobranca\CotaContratada;
use App\Domain\Cobranca\CotaExtraNoUpgrade;
use App\Domain\Cobranca\CotaMensal;
use Carbon\CarbonImmutable;

/**
 * STORY-091 — a regra provisória da cota extra no upgrade (CA-3), a cota contratada na renovação (CA-2)
 * e a janela da cota em curso, sem banco.
 */
function _t(string $data): CarbonImmutable
{
    return CarbonImmutable::parse($data, 'UTC');
}

describe('CotaExtraNoUpgrade (CA-3, regra provisória)', function () {
    it('é proporcional ao que falta, arredondada para baixo', function () {
        // Janela de 30 dias; faltam 20: 9 × 20/30 = 6.
        expect(CotaExtraNoUpgrade::calcular(1, 10, _t('2026-11-01'), _t('2026-12-01'), _t('2026-11-11')))->toBe(6);
        // Faltam 3 dias: 9 × 3/30 = 0,9 → 0.
        expect(CotaExtraNoUpgrade::calcular(1, 10, _t('2026-11-01'), _t('2026-12-01'), _t('2026-11-28')))->toBe(0);
        // Faltam 12 horas: 0 (o caso E6, que dava 9 análises).
        expect(CotaExtraNoUpgrade::calcular(1, 10, _t('2026-11-01'), _t('2026-12-01'), _t('2026-11-30 12:00')))->toBe(0);
    });

    it('no primeiro instante dá a diferença inteira, nunca mais que ela', function () {
        expect(CotaExtraNoUpgrade::calcular(1, 10, _t('2026-11-01'), _t('2026-12-01'), _t('2026-11-01')))->toBe(9)
            ->and(CotaExtraNoUpgrade::calcular(1, 10, _t('2026-11-01'), _t('2026-12-01'), _t('2026-10-31')))->toBe(9);
    });

    it('nunca é negativa: cota igual ou menor não tira nada da que já existe', function () {
        expect(CotaExtraNoUpgrade::calcular(10, 10, _t('2026-11-01'), _t('2026-12-01'), _t('2026-11-05')))->toBe(0)
            ->and(CotaExtraNoUpgrade::calcular(10, 4, _t('2026-11-01'), _t('2026-12-01'), _t('2026-11-05')))->toBe(0);
    });

    it('janela vazia ou já vencida não divide por zero nem dá extra', function () {
        expect(CotaExtraNoUpgrade::calcular(1, 10, _t('2026-11-01'), _t('2026-11-01'), _t('2026-11-01')))->toBe(0)
            ->and(CotaExtraNoUpgrade::calcular(1, 10, _t('2026-11-01'), _t('2026-12-01'), _t('2026-12-05')))->toBe(0);
    });
});

describe('CotaContratada (CA-2)', function () {
    it('mantém a cota contratada enquanto o plano é o mesmo, mesmo com o catálogo diferente', function () {
        expect(CotaContratada::naRenovacao(10, 'p1', 'p1', 5))->toBe(10)
            ->and(CotaContratada::naRenovacao(10, 'p1', 'p1', 20))->toBe(10);
    });

    it('vale a do catálogo de hoje quando ele muda de plano', function () {
        expect(CotaContratada::naRenovacao(10, 'p1', 'p2', 1))->toBe(1);
    });
});

describe('CotaMensal::janela', function () {
    it('mensal: do início ao vencimento', function () {
        [$ini, $fim] = CotaMensal::janela(_t('2026-11-05'), _t('2026-11-20'), _t('2026-12-05'));

        expect($ini->toDateString())->toBe('2026-11-05')->and($fim->toDateString())->toBe('2026-12-05');
    });

    it('o aniversário do último dia do período é a renovação: a janela mensal vai até o fim do período, não 24 h (STORY-093)', function () {
        // Início à meia-noite de Brasília (data só com o dia do provedor); o vencimento é o último segundo do dia 05/12.
        $inicio = _t('2026-11-05 03:00:00');
        $fimDoPeriodo = _t('2026-12-06 02:59:59');

        [$ini, $fim] = CotaMensal::janela($inicio, _t('2026-12-05 14:59:00'), $fimDoPeriodo);

        expect($ini->equalTo($inicio))->toBeTrue()->and($fim->equalTo(_t('2026-12-05 03:00:00')))->toBeTrue()
            ->and(CotaExtraNoUpgrade::calcular(1, 10, $ini, $fim, _t('2026-12-02 03:00:00')))->toBe(0);
    });

    it('anual: do último aniversário ao próximo', function () {
        [$ini, $fim] = CotaMensal::janela(_t('2026-01-31 12:00'), _t('2026-03-10'), _t('2027-01-31 12:00'));

        expect($ini->toDateString())->toBe('2026-02-28')->and($fim->toDateString())->toBe('2026-03-31');
    });

    it('no último mês termina no fim do período pago', function () {
        [$ini, $fim] = CotaMensal::janela(_t('2026-01-15'), _t('2026-12-20'), _t('2027-01-15'));

        expect($ini->toDateString())->toBe('2026-12-15')->and($fim->toDateString())->toBe('2027-01-15');
    });

    it('limita ao fim do período pago quando o aniversário o ultrapassa', function () {
        [, $fim] = CotaMensal::janela(_t('2026-01-15'), _t('2026-01-20'), _t('2026-02-01'));

        expect($fim->toDateString())->toBe('2026-02-01');
    });
});

describe('CalculoDeTroca com a cota (CA-3)', function () {
    it('a prévia traz a cota extra de agora e a data da cota cheia', function () {
        $p = CalculoDeTroca::calcular(
            4990, CicloCobranca::Mensal, 1, _t('2026-11-01'), _t('2026-12-01'), _t('2026-11-11'),
            19900, CicloCobranca::Mensal, 10,
        );

        expect($p->cotaExtraAgora)->toBe(6)->and($p->cotaCheiaEm?->toDateString())->toBe('2026-12-01');
    });

    it('no anual, a proporção é da janela do mês e não do ano', function () {
        // Ano de 01/01; estamos a 10 dias do próximo aniversário (01/04).
        $p = CalculoDeTroca::calcular(
            39900, CicloCobranca::Anual, 1, _t('2026-01-01'), _t('2027-01-01'), _t('2026-03-22'),
            159900, CicloCobranca::Anual, 10,
            _t('2026-03-01'), _t('2026-04-01'),
        );

        expect($p->cotaExtraAgora)->toBe(2)->and($p->cotaCheiaEm?->toDateString())->toBe('2026-04-01');
    });

    it('troca de ciclo começa um período novo: cota cheia já, sem data de espera', function () {
        $p = CalculoDeTroca::calcular(
            4990, CicloCobranca::Mensal, 1, _t('2026-11-01'), _t('2026-12-01'), _t('2026-11-11'),
            159900, CicloCobranca::Anual, 10,
        );

        expect($p->mesmoCiclo)->toBeFalse()->and($p->cotaExtraAgora)->toBe(10)->and($p->cotaCheiaEm)->toBeNull();
    });

    it('downgrade não dá cota extra', function () {
        $p = CalculoDeTroca::calcular(
            19900, CicloCobranca::Mensal, 10, _t('2026-11-01'), _t('2026-12-01'), _t('2026-11-11'),
            4990, CicloCobranca::Mensal, 1,
        );

        expect($p->imediata)->toBeFalse()->and($p->cotaExtraAgora)->toBe(0);
    });
});

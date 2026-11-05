<?php

declare(strict_types=1);

use App\Domain\Cobranca\CalculoDeTroca;
use App\Domain\Cobranca\CicloCobranca;
use Carbon\CarbonImmutable;

/**
 * STORY-082 CA-1, CA-3, CA-4 — a regra de preço da troca de plano.
 * Catálogo do teste: Básico R$ 49,90/mês (399,00/ano, cota 1); Pro R$ 199,00/mês (1.599,00/ano, cota 10).
 */
function _troca(
    int $atual, CicloCobranca $cicloAtual, int $cotaAtual,
    string $inicio, string $fim, string $agora,
    int $novo, CicloCobranca $cicloNovo, int $cotaNova,
) {
    return CalculoDeTroca::calcular(
        $atual, $cicloAtual, $cotaAtual,
        CarbonImmutable::parse($inicio, 'UTC'), CarbonImmutable::parse($fim, 'UTC'), CarbonImmutable::parse($agora, 'UTC'),
        $novo, $cicloNovo, $cotaNova,
    );
}

/** Básico mensal -> Pro mensal num período de 30 dias (05/11 a 05/12). */
function _basicoParaPro(string $agora)
{
    return _troca(4990, CicloCobranca::Mensal, 1, '2026-11-05 12:00:00', '2026-12-05 12:00:00', $agora, 19900, CicloCobranca::Mensal, 10);
}

describe('upgrade no mesmo ciclo (CA-1)', function () {
    it('cobra a diferença proporcional ao que falta: 21 de 30 dias', function () {
        $p = _basicoParaPro('2026-11-14 12:00:00');

        expect($p->imediata)->toBeTrue()->and($p->mesmoCiclo)->toBeTrue()
            ->and($p->diasRestantes)->toBe(21)
            ->and($p->parcelaNovaCentavos)->toBe(13930)   // 19900 × 21/30
            ->and($p->creditoCentavos)->toBe(3493)        // 4990 × 21/30 = 3493
            ->and($p->aPagarCentavos)->toBe(10437)
            ->and($p->semCobranca)->toBeFalse()
            ->and($p->proximaCobranca->toIso8601String())->toBe('2026-12-05T12:00:00+00:00')
            ->and($p->precoNovoCentavos)->toBe(19900)->and($p->cotaNova)->toBe(10);
    });

    it('no primeiro dia paga quase a diferença cheia', function () {
        $p = _basicoParaPro('2026-11-05 12:00:00');

        expect($p->aPagarCentavos)->toBe(14910)->and($p->diasRestantes)->toBe(30)->and($p->creditoCentavos)->toBe(4990);
    });

    it('no último instante do ciclo não há nada a pagar e nada é cobrado', function () {
        $p = _basicoParaPro('2026-12-05 12:00:00');

        expect($p->aPagarCentavos)->toBe(0)->and($p->semCobranca)->toBeTrue()->and($p->diasRestantes)->toBe(0);
    });

    it('depois do fim do ciclo não gera valor negativo nem proporção maior que 1', function () {
        $depois = _basicoParaPro('2026-12-20 12:00:00');
        $antes = _basicoParaPro('2026-10-01 12:00:00');

        expect($depois->aPagarCentavos)->toBe(0)->and($depois->creditoCentavos)->toBe(0)
            ->and($antes->aPagarCentavos)->toBe(14910)->and($antes->diasRestantes)->toBe(30);
    });

    it('nunca gera valor negativo, em nenhum ponto do ciclo nem com preços quaisquer', function () {
        foreach ([[4990, 19900], [100, 101], [19900, 19901], [1, 1000000]] as [$atual, $novo]) {
            for ($h = 0; $h <= 31 * 24; $h += 7) {
                $agora = CarbonImmutable::parse('2026-11-05 12:00:00', 'UTC')->addHours($h)->toDateTimeString();
                $p = _troca($atual, CicloCobranca::Mensal, 1, '2026-11-05 12:00:00', '2026-12-05 12:00:00', $agora, $novo, CicloCobranca::Mensal, 10);

                expect($p->aPagarCentavos)->toBeGreaterThanOrEqual(0)->and($p->creditoCentavos)->toBeGreaterThanOrEqual(0);
            }
        }
    });

    it('diferença abaixo de R$ 5,00 é aplicada sem cobrança; a partir de R$ 5,00 cobra', function () {
        // Preço 1.000 -> 1.500 (diferença 500) num período de 100 s.
        $abaixo = _troca(1000, CicloCobranca::Mensal, 1, '2026-11-05 00:00:00', '2026-11-05 00:01:40', '2026-11-05 00:00:01', 1500, CicloCobranca::Mensal, 2);
        $exato = _troca(1000, CicloCobranca::Mensal, 1, '2026-11-05 00:00:00', '2026-11-05 00:01:40', '2026-11-05 00:00:00', 1500, CicloCobranca::Mensal, 2);

        expect($abaixo->aPagarCentavos)->toBe(495)->and($abaixo->semCobranca)->toBeTrue()
            ->and($exato->aPagarCentavos)->toBe(500)->and($exato->semCobranca)->toBeFalse();
    });

    it('plano anual: a proporção é do ano', function () {
        $p = _troca(39900, CicloCobranca::Anual, 1, '2026-01-01 00:00:00', '2027-01-01 00:00:00', '2026-07-02 00:00:00', 159900, CicloCobranca::Anual, 10);

        expect($p->imediata)->toBeTrue()->and($p->mesmoCiclo)->toBeTrue()->and($p->diasRestantes)->toBe(183)
            ->and($p->aPagarCentavos)->toBe($p->parcelaNovaCentavos - $p->creditoCentavos)
            ->and($p->aPagarCentavos)->toBeGreaterThan(50000)->and($p->aPagarCentavos)->toBeLessThan(70000);
    });
});

describe('o que é "superior" (CA-4 e ajuste do ADR-010): só o preço', function () {
    it('plano mais barato é downgrade: agendado, sem cobrança hoje, vale no fim do ciclo', function () {
        $p = _troca(19900, CicloCobranca::Mensal, 10, '2026-11-05 12:00:00', '2026-12-05 12:00:00', '2026-11-14 12:00:00', 4990, CicloCobranca::Mensal, 1);

        expect($p->imediata)->toBeFalse()->and($p->aPagarCentavos)->toBe(0)->and($p->semCobranca)->toBeTrue()
            ->and($p->vigenteAte->toIso8601String())->toBe('2026-12-05T12:00:00+00:00')
            ->and($p->proximaCobranca->toIso8601String())->toBe('2026-12-05T12:00:00+00:00')
            ->and($p->precoNovoCentavos)->toBe(4990)->and($p->cotaNova)->toBe(1);
    });

    it('mesmo preço: só sobe se a cota for maior; senão é troca no fim do ciclo', function () {
        $maisCota = _troca(10000, CicloCobranca::Mensal, 5, '2026-11-05 12:00:00', '2026-12-05 12:00:00', '2026-11-14 12:00:00', 10000, CicloCobranca::Mensal, 8);
        $igualCota = _troca(10000, CicloCobranca::Mensal, 5, '2026-11-05 12:00:00', '2026-12-05 12:00:00', '2026-11-14 12:00:00', 10000, CicloCobranca::Mensal, 5);

        expect($maisCota->imediata)->toBeTrue()->and($maisCota->aPagarCentavos)->toBe(0)->and($maisCota->semCobranca)->toBeTrue()
            ->and($igualCota->imediata)->toBeFalse();
    });

    it('o código ou o nome do plano não entram na conta: só preço e cota', function () {
        expect(CalculoDeTroca::ehUpgrade(100, CicloCobranca::Mensal, 1, 101, CicloCobranca::Mensal, 1))->toBeTrue()
            ->and(CalculoDeTroca::ehUpgrade(101, CicloCobranca::Mensal, 1, 100, CicloCobranca::Mensal, 99))->toBeFalse();
    });
});

describe('troca de ciclo (pergunta 2 da spec: pela regra de valor)', function () {
    it('mensal para anual mais caro por mês: o ano começa hoje pelo preço cheio, menos o crédito do mês', function () {
        $p = _troca(4990, CicloCobranca::Mensal, 1, '2026-11-05 12:00:00', '2026-12-05 12:00:00', '2026-11-14 12:00:00', 159900, CicloCobranca::Anual, 10);

        expect($p->imediata)->toBeTrue()->and($p->mesmoCiclo)->toBeFalse()
            ->and($p->parcelaNovaCentavos)->toBe(159900)->and($p->creditoCentavos)->toBe(3493)
            ->and($p->aPagarCentavos)->toBe(156407)
            ->and($p->proximaCobranca->toIso8601String())->toBe('2027-11-14T12:00:00+00:00');
    });

    it('mensal para o anual do mesmo plano sai mais barato por mês: vale no fim do ciclo', function () {
        $p = _troca(4990, CicloCobranca::Mensal, 1, '2026-11-05 12:00:00', '2026-12-05 12:00:00', '2026-11-14 12:00:00', 39900, CicloCobranca::Anual, 1);

        expect($p->imediata)->toBeFalse()->and($p->aPagarCentavos)->toBe(0)->and($p->mesmoCiclo)->toBeFalse();
    });

    it('anual para mensal nunca é imediato, mesmo que o mensal custe mais por mês (o crédito do ano não vira dinheiro)', function () {
        $p = _troca(159900, CicloCobranca::Anual, 10, '2026-01-01 00:00:00', '2027-01-01 00:00:00', '2026-02-01 00:00:00', 19900, CicloCobranca::Mensal, 10);

        expect(CalculoDeTroca::ehUpgrade(159900, CicloCobranca::Anual, 10, 19900, CicloCobranca::Mensal, 10))->toBeTrue()
            ->and($p->imediata)->toBeFalse()->and($p->aPagarCentavos)->toBe(0)
            ->and($p->vigenteAte->toIso8601String())->toBe('2027-01-01T00:00:00+00:00');
    });
});

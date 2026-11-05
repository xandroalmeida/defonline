<?php

declare(strict_types=1);

use App\Domain\Cobranca\Arrependimento;
use App\Domain\Cobranca\LoteVisto;
use App\Domain\Cobranca\SituacaoDoPlano as S;
use App\Domain\Cobranca\StatusAssinatura as St;
use App\Domain\Cobranca\StatusLote;
use Carbon\CarbonImmutable;

/** STORY-081 / DDR-009 — vocabulário das situações do plano e regras de prazo da tela Meu plano. */
$agora = CarbonImmutable::parse('2026-11-05 12:00:00', 'America/Sao_Paulo');

describe('SituacaoDoPlano (DDR-009)', function () use ($agora) {
    it('sem assinatura é "sem plano" e não tem pill', function () use ($agora) {
        expect(S::de(null, false, null, $agora))->toBe(S::SemPlano)
            ->and(S::SemPlano->rotulo())->toBeNull()
            ->and(S::SemPlano->temVinculo())->toBeFalse();
    });

    it('trial ativo é Teste; plano ativo é Ativo', function () use ($agora) {
        $fim = $agora->addDays(5);

        expect(S::de(St::Ativa, true, $fim, $agora))->toBe(S::Teste)
            ->and(S::de(St::Ativa, false, $fim, $agora))->toBe(S::Ativo)
            ->and(S::Teste->rotulo())->toBe('Teste')
            ->and(S::Ativo->rotulo())->toBe('Ativo')
            ->and(S::Ativo->temVinculo())->toBeTrue();
    });

    it('cancelada com ciclo por correr é Cancelado (acesso até a data)', function () use ($agora) {
        expect(S::de(St::Cancelada, false, $agora->addDay(), $agora))->toBe(S::Cancelado)
            ->and(S::Cancelado->rotulo())->toBe('Cancelado');
    });

    it('em atraso é Em atraso', function () use ($agora) {
        expect(S::de(St::EmAtraso, false, $agora->subDays(2), $agora))->toBe(S::EmAtraso)
            ->and(S::EmAtraso->rotulo())->toBe('Em atraso');
    });

    it('expirada, ou com o ciclo vencido (ativa ou cancelada), é Encerrado', function () use ($agora) {
        expect(S::de(St::Expirada, false, $agora->addDays(3), $agora))->toBe(S::Encerrado)
            ->and(S::de(St::Cancelada, false, $agora->subSecond(), $agora))->toBe(S::Encerrado)
            ->and(S::de(St::Ativa, true, $agora, $agora))->toBe(S::Encerrado)
            ->and(S::Encerrado->rotulo())->toBe('Encerrado');
    });
});

describe('Arrependimento (CA-6)', function () use ($agora) {
    it('cabe nos 7 dias corridos sem análise e devolve os dias passados', function () use ($agora) {
        expect(Arrependimento::diasDesde($agora, $agora, false))->toBe(0)
            ->and(Arrependimento::diasDesde($agora->subDays(3), $agora, false))->toBe(3)
            ->and(Arrependimento::diasDesde($agora->subDays(7), $agora, false))->toBe(7);
    });

    it('não cabe passados os 7 dias, com análise feita, ou com data futura', function () use ($agora) {
        expect(Arrependimento::diasDesde($agora->subDays(7)->subSecond(), $agora, false))->toBeNull()
            ->and(Arrependimento::diasDesde($agora->subDay(), $agora, true))->toBeNull()
            ->and(Arrependimento::diasDesde($agora->addMinute(), $agora, false))->toBeNull();
    });

    it('o canal é o e-mail definido pelo PO', function () {
        expect(Arrependimento::CANAL)->toBe('eb@ebparcerias.com')->and(Arrependimento::DIAS)->toBe(7);
    });
});

describe('LoteVisto::pertoDeVencer (CA-1)', function () use ($agora) {
    $lote = fn (CarbonImmutable $expira): LoteVisto => new LoteVisto('l', StatusLote::Ativo, 2, $agora->subMonth(), $expira);

    it('avisa só dentro do limiar e antes de vencer', function () use ($agora, $lote) {
        expect($lote($agora->addDays(30))->pertoDeVencer($agora, 30))->toBeTrue()
            ->and($lote($agora->addDays(1))->pertoDeVencer($agora, 30))->toBeTrue()
            ->and($lote($agora->addDays(31))->pertoDeVencer($agora, 30))->toBeFalse()
            ->and($lote($agora)->pertoDeVencer($agora, 30))->toBeFalse()
            ->and($lote($agora->subDay())->pertoDeVencer($agora, 30))->toBeFalse();
    });
});

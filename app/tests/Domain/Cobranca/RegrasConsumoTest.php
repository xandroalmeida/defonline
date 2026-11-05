<?php

declare(strict_types=1);

use App\Domain\Cobranca\AssinaturaVista;
use App\Domain\Cobranca\LoteVisto;
use App\Domain\Cobranca\MotivoBloqueio;
use App\Domain\Cobranca\Origem;
use App\Domain\Cobranca\RegrasConsumo;
use App\Domain\Cobranca\SnapshotCarteira;
use App\Domain\Cobranca\StatusAssinatura;
use App\Domain\Cobranca\StatusLote;
use Carbon\CarbonImmutable;

/**
 * STORY-073 CA-2, CA-4, CA-11 — a regra de consumo sem banco: cota primeiro, depois o lote
 * que vence antes, e o motivo exato quando não sobra nada.
 */
function _agora(): CarbonImmutable
{
    return CarbonImmutable::parse('2026-11-10 12:00:00');
}

function _assinatura(StatusAssinatura $status = StatusAssinatura::Ativa, int $restante = 3, int $snapshot = 10, string $vence = '2026-12-01 00:00:00', bool $trial = false): AssinaturaVista
{
    return new AssinaturaVista('ass-1', $trial, $status, $snapshot, $restante, CarbonImmutable::parse($vence));
}

function _lote(string $id, int $restante, string $expira, StatusLote $status = StatusLote::Ativo, string $comprado = '2026-01-01 00:00:00'): LoteVisto
{
    return new LoteVisto($id, $status, $restante, CarbonImmutable::parse($comprado), CarbonImmutable::parse($expira));
}

/** @param  list<LoteVisto>  $lotes */
function _carteira(?AssinaturaVista $assinatura = null, array $lotes = [], bool $trialElegivel = false): SnapshotCarteira
{
    return new SnapshotCarteira(_agora(), $assinatura, $lotes, $trialElegivel);
}

it('debita a cota quando há cota restante', function () {
    $d = RegrasConsumo::decidir(_carteira(_assinatura(), [_lote('l1', 5, '2027-01-01')]));

    expect($d->permitido())->toBeTrue()
        ->and($d->origem)->toBe(Origem::Cota)
        ->and($d->assinaturaId)->toBe('ass-1')
        ->and($d->loteId)->toBeNull()
        ->and($d->bloqueio)->toBeNull();
});

it('usa a cota do trial vigente', function () {
    $d = RegrasConsumo::decidir(_carteira(_assinatura(restante: 1, snapshot: 1, vence: '2026-11-15 00:00:00', trial: true)));

    expect($d->origem)->toBe(Origem::Cota);
});

it('mantém a cota de uma assinatura cancelada até o fim do ciclo pago', function () {
    $d = RegrasConsumo::decidir(_carteira(_assinatura(StatusAssinatura::Cancelada)));

    expect($d->origem)->toBe(Origem::Cota);
});

it('sem cota, debita o lote que vence primeiro', function () {
    $d = RegrasConsumo::decidir(_carteira(_assinatura(restante: 0), [
        _lote('tarde', 5, '2027-06-01'),
        _lote('cedo', 5, '2026-12-01'),
        _lote('meio', 5, '2027-02-01'),
    ]));

    expect($d->origem)->toBe(Origem::Credito)
        ->and($d->loteId)->toBe('cedo')
        ->and($d->assinaturaId)->toBeNull();
});

it('desempata lotes com o mesmo vencimento pela compra mais antiga e depois pelo id', function () {
    $porCompra = RegrasConsumo::decidir(_carteira(null, [
        _lote('b', 1, '2027-01-01', comprado: '2026-03-01 00:00:00'),
        _lote('a', 1, '2027-01-01', comprado: '2026-04-01 00:00:00'),
    ]));
    $porId = RegrasConsumo::decidir(_carteira(null, [
        _lote('b', 1, '2027-01-01'),
        _lote('a', 1, '2027-01-01'),
    ]));

    expect($porCompra->loteId)->toBe('b')->and($porId->loteId)->toBe('a');
});

it('não consome lote vencido nem esgotado, mesmo marcado como ativo', function () {
    $d = RegrasConsumo::decidir(_carteira(null, [
        _lote('vencido', 5, '2026-11-10 12:00:00'),
        _lote('esgotado', 0, '2027-01-01'),
        _lote('marcado', 5, '2027-01-01', StatusLote::Esgotado),
        _lote('expirado', 5, '2027-01-01', StatusLote::Expirado),
        _lote('bom', 2, '2027-03-01'),
    ]));

    expect($d->loteId)->toBe('bom');
});

it('usa crédito quando a assinatura está em atraso e ainda há lote válido', function () {
    $d = RegrasConsumo::decidir(_carteira(_assinatura(StatusAssinatura::EmAtraso), [_lote('l1', 1, '2027-01-01')]));

    expect($d->origem)->toBe(Origem::Credito);
});

it('bloqueia quem nunca teve nada, indicando se o teste grátis está disponível', function (bool $elegivel) {
    $b = RegrasConsumo::decidir(_carteira(trialElegivel: $elegivel))->bloqueio;

    expect($b->motivo)->toBe(MotivoBloqueio::SemNada)->and($b->trialElegivel)->toBe($elegivel);
})->with([true, false]);

it('trata lotes todos consumidos como sem nada, sem falar em vencimento', function () {
    $d = RegrasConsumo::decidir(_carteira(null, [_lote('l1', 0, '2026-01-01', StatusLote::Esgotado)]));

    expect($d->permitido())->toBeFalse()->and($d->bloqueio->motivo)->toBe(MotivoBloqueio::SemNada);
});

it('bloqueia com créditos vencidos e informa a data do último a vencer', function () {
    $b = RegrasConsumo::decidir(_carteira(null, [
        _lote('a', 2, '2026-09-01'),
        _lote('b', 1, '2026-10-15'),
    ]))->bloqueio;

    expect($b->motivo)->toBe(MotivoBloqueio::CreditosVencidos)
        ->and($b->data->toDateString())->toBe('2026-10-15');
});

it('bloqueia o teste cuja análise foi usada e o prazo ainda vale', function () {
    $b = RegrasConsumo::decidir(_carteira(_assinatura(restante: 0, snapshot: 1, vence: '2026-11-15 00:00:00', trial: true)))->bloqueio;

    expect($b->motivo)->toBe(MotivoBloqueio::TesteUsado)->and($b->data->toDateString())->toBe('2026-11-15');
});

it('bloqueia o teste encerrado, tenha sobrado análise ou não', function (int $restante) {
    $b = RegrasConsumo::decidir(_carteira(_assinatura(restante: $restante, vence: '2026-11-09 00:00:00', trial: true)))->bloqueio;

    expect($b->motivo)->toBe(MotivoBloqueio::TesteEncerrado)->and($b->data->toDateString())->toBe('2026-11-09');
})->with([0, 1]);

it('bloqueia com a cota do ciclo esgotada e informa o tamanho da cota e a data da renovação', function () {
    $b = RegrasConsumo::decidir(_carteira(_assinatura(restante: 0, snapshot: 10)))->bloqueio;

    expect($b->motivo)->toBe(MotivoBloqueio::CotaEsgotada)
        ->and($b->cotaDoCiclo)->toBe(10)
        ->and($b->data->toDateString())->toBe('2026-12-01')
        ->and($b->renova)->toBeTrue();
});

it('avisa que a cota não volta quando o plano está cancelado', function () {
    $b = RegrasConsumo::decidir(_carteira(_assinatura(StatusAssinatura::Cancelada, restante: 0)))->bloqueio;

    expect($b->motivo)->toBe(MotivoBloqueio::CotaEsgotada)->and($b->renova)->toBeFalse();
});

it('bloqueia a assinatura em atraso com cota sobrando', function () {
    $b = RegrasConsumo::decidir(_carteira(_assinatura(StatusAssinatura::EmAtraso, restante: 5)))->bloqueio;

    expect($b->motivo)->toBe(MotivoBloqueio::EmAtraso);
});

it('bloqueia o plano encerrado: expirada, ou ciclo vencido sem renovação', function (StatusAssinatura $status, string $vence) {
    $b = RegrasConsumo::decidir(_carteira(_assinatura($status, restante: 5, vence: $vence)))->bloqueio;

    expect($b->motivo)->toBe(MotivoBloqueio::PlanoEncerrado)->and($b->data->toDateString())->toBe(substr($vence, 0, 10));
})->with([
    'expirada' => [StatusAssinatura::Expirada, '2026-12-01 00:00:00'],
    'ciclo vencido' => [StatusAssinatura::Ativa, '2026-11-01 00:00:00'],
    'cancelada vencida' => [StatusAssinatura::Cancelada, '2026-11-10 12:00:00'],
]);

it('prefere a explicação do plano à dos créditos vencidos', function () {
    $b = RegrasConsumo::decidir(_carteira(_assinatura(StatusAssinatura::Expirada), [_lote('a', 2, '2026-01-01')]))->bloqueio;

    expect($b->motivo)->toBe(MotivoBloqueio::PlanoEncerrado);
});

it('só a assinatura vigente ocupa o lugar único do Usuário', function () {
    expect(StatusAssinatura::Ativa->vigente())->toBeTrue()
        ->and(StatusAssinatura::EmAtraso->vigente())->toBeTrue()
        ->and(StatusAssinatura::Cancelada->vigente())->toBeFalse()
        ->and(StatusAssinatura::Expirada->vigente())->toBeFalse();
});

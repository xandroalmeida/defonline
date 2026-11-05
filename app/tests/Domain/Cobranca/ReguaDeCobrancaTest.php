<?php

declare(strict_types=1);

use App\Domain\Cobranca\AcaoDaRegua;
use App\Domain\Cobranca\CobrancaNoProvedor;
use App\Domain\Cobranca\EstadoCobranca;
use App\Domain\Cobranca\ReferenciaProvedor;
use App\Domain\Cobranca\ReguaDeCobranca;
use Carbon\CarbonImmutable;

/** STORY-079 — a régua é uma regra pura: 3 retentativas, a cada 48 h, e o encerramento 48 h depois da última. */
$t0 = CarbonImmutable::parse('2026-12-05 10:00:00', 'UTC');

it('as constantes são as da spec: 3 tentativas, 48 h, aviso a 7 dias', function () {
    expect(ReguaDeCobranca::MAX_TENTATIVAS)->toBe(3)
        ->and(ReguaDeCobranca::INTERVALO_HORAS)->toBe(48)
        ->and(ReguaDeCobranca::AVISO_RENOVACAO_DIAS)->toBe(7);
});

it('a próxima tentativa é 48 horas depois da base e a última, 144 horas depois do atraso', function () use ($t0) {
    expect(ReguaDeCobranca::proximaTentativa($t0)->toIso8601String())->toBe('2026-12-07T10:00:00+00:00')
        ->and(ReguaDeCobranca::ultimaTentativaEm($t0)->toIso8601String())->toBe('2026-12-11T10:00:00+00:00');
});

it('decide: espera antes da hora, retenta nas três primeiras e encerra depois da terceira', function (int $feitas, int $horas, AcaoDaRegua $esperado) use ($t0) {
    $proxima = $t0->addHours(48);

    expect(ReguaDeCobranca::decidir($feitas, $proxima, $t0->addHours($horas)))->toBe($esperado);
})->with([
    'antes do prazo, nenhuma feita' => [0, 47, AcaoDaRegua::Aguardar],
    'no prazo, nenhuma feita' => [0, 48, AcaoDaRegua::Retentar],
    'depois do prazo, uma feita' => [1, 60, AcaoDaRegua::Retentar],
    'terceira ainda é retentativa' => [2, 48, AcaoDaRegua::Retentar],
    'três feitas, antes do prazo da última' => [3, 47, AcaoDaRegua::Aguardar],
    'três feitas e o prazo passou' => [3, 48, AcaoDaRegua::Encerrar],
    'mais que o máximo também encerra' => [9, 100, AcaoDaRegua::Encerrar],
]);

it('sem data agendada, decide só pelo contador', function () use ($t0) {
    expect(ReguaDeCobranca::decidir(0, null, $t0))->toBe(AcaoDaRegua::Retentar)
        ->and(ReguaDeCobranca::decidir(3, null, $t0))->toBe(AcaoDaRegua::Encerrar);
});

it('o aviso é devido a 7 dias ou menos do fim e deixa de ser no instante do fim', function (int $minutosAteOFim, bool $devido) use ($t0) {
    expect(ReguaDeCobranca::avisoDeRenovacaoDevido($t0->addMinutes($minutosAteOFim), $t0))->toBe($devido);
})->with([
    'faltam 8 dias' => [8 * 1440, false],
    'faltam 7 dias exatos' => [7 * 1440, true],
    'falta 1 dia' => [1440, true],
    'falta 1 minuto' => [1, true],
    'acabou agora' => [0, false],
    'já acabou' => [-1, false],
]);

it('a cobrança do ciclo em aberto é a que vence perto do fim do período pago; sem vencimento, vale', function (?string $vencimento, bool $esperado) {
    $fim = CarbonImmutable::parse('2026-12-05 17:00:00', 'UTC');
    $venc = $vencimento === null ? null : new DateTimeImmutable($vencimento);

    expect(ReguaDeCobranca::cobrancaDoCicloEmAberto($venc, $fim))->toBe($esperado);
})->with([
    'sem vencimento' => [null, true],
    'no dia do fim' => ['2026-12-05', true],
    'dois dias antes (folga)' => ['2026-12-03', true],
    'três dias antes: é de ciclo antigo' => ['2026-12-02', false],
    'depois do fim' => ['2026-12-08', true],
]);

describe('CobrancaNoProvedor::semPagamento', function () {
    $cobranca = fn (EstadoCobranca $e, ?string $venc) => new CobrancaNoProvedor(new ReferenciaProvedor('fake', 'p1'), $e, 100, vencimento: $venc === null ? null : new DateTimeImmutable($venc));
    $agora = new DateTimeImmutable('2026-12-06 09:00:00');

    it('vencida e recusada contam; paga e estados finais, nunca', function (EstadoCobranca $estado, bool $esperado) use ($cobranca, $agora) {
        expect($cobranca($estado, '2026-12-01')->semPagamento($agora))->toBe($esperado);
    })->with([
        [EstadoCobranca::Vencida, true],
        [EstadoCobranca::Falhou, true],
        [EstadoCobranca::Paga, false],
        [EstadoCobranca::Cancelada, false],
        [EstadoCobranca::Estornada, false],
        [EstadoCobranca::Contestada, false],
    ]);

    it('pendente só conta um dia depois do vencimento', function (?string $venc, bool $esperado) use ($cobranca, $agora) {
        expect($cobranca(EstadoCobranca::Pendente, $venc)->semPagamento($agora))->toBe($esperado);
    })->with([
        'sem vencimento' => [null, false],
        'vence hoje' => ['2026-12-06', false],
        'venceu ontem' => ['2026-12-05', true],
        'vence amanhã' => ['2026-12-07', false],
    ]);
});

<?php

declare(strict_types=1);

use App\Domain\Cobranca\AssinaturaVista;
use App\Domain\Cobranca\AvisoTrial;
use App\Domain\Cobranca\StatusAssinatura;
use Carbon\CarbonImmutable;

/**
 * STORY-074 / spec STORY-070-trial §3 — qual aviso o usuário em teste vê na Home.
 */
function _trialVisto(string $vence, int $restante = 1, bool $trial = true, StatusAssinatura $status = StatusAssinatura::Ativa): AssinaturaVista
{
    return new AssinaturaVista('t1', $trial, $status, 1, $restante, CarbonImmutable::parse($vence, 'America/Sao_Paulo'));
}

function _agoraSp(string $quando): CarbonImmutable
{
    return CarbonImmutable::parse($quando, 'America/Sao_Paulo');
}

it('mostra a data e as análises restantes durante o teste', function () {
    $aviso = AvisoTrial::para(_trialVisto('2026-11-12 15:00:00', restante: 1), _agoraSp('2026-11-07 10:00:00'));

    expect($aviso)->toBe(['severidade' => 'info', 'texto' => 'Teste grátis: termina em 12/11/2026 · 1 análise restante.', 'acao' => 'planos', 'rotulo' => 'Ver planos']);
});

it('usa o plural para mais de uma análise restante', function () {
    $aviso = AvisoTrial::para(_trialVisto('2026-11-12 15:00:00', restante: 3), _agoraSp('2026-11-07 10:00:00'));

    expect($aviso['texto'])->toBe('Teste grátis: termina em 12/11/2026 · 3 análises restantes.');
});

it('avisa que a análise do teste foi usada e o acesso ao Raio X segue', function () {
    $aviso = AvisoTrial::para(_trialVisto('2026-11-12 15:00:00', restante: 0), _agoraSp('2026-11-07 10:00:00'));

    expect($aviso['texto'])->toBe('Você usou a análise do teste. O acesso ao Raio X continua até 12/11/2026.');
});

it('avisa em D-2 e em D-1, com convite para assinar', function () {
    $d2 = AvisoTrial::para(_trialVisto('2026-11-12 15:00:00'), _agoraSp('2026-11-10 08:00:00'));
    $d1 = AvisoTrial::para(_trialVisto('2026-11-12 15:00:00'), _agoraSp('2026-11-11 23:59:00'));

    expect($d2['texto'])->toBe('Seu teste termina em 12/11/2026 (em 2 dias). Assine para continuar usando o que já faz.')
        ->and($d2['rotulo'])->toBe('Assinar um plano')
        ->and($d1['texto'])->toBe('Seu teste termina em 12/11/2026 (em 1 dia). Assine para continuar usando o que já faz.');
});

it('avisa em D-0 mesmo que a análise já tenha sido usada', function () {
    $aviso = AvisoTrial::para(_trialVisto('2026-11-12 15:00:00', restante: 0), _agoraSp('2026-11-12 09:00:00'));

    expect($aviso['texto'])->toBe('Seu teste termina hoje.')->and($aviso['rotulo'])->toBe('Assinar um plano');
});

it('conta os dias em Brasília, não em UTC', function () {
    // 12/11 01:00 em Brasília já é 12/11 04:00 UTC; às 22:00 de 11/11 em Brasília ainda é "em 1 dia".
    $aviso = AvisoTrial::para(_trialVisto('2026-11-12 01:00:00'), _agoraSp('2026-11-11 22:00:00'));

    expect($aviso['texto'])->toContain('em 1 dia');
});

it('bloqueia com a data do fim depois do vencimento, sem cobrança', function () {
    $aviso = AvisoTrial::para(_trialVisto('2026-11-12 15:00:00'), _agoraSp('2026-11-13 09:00:00'));

    expect($aviso)->toBe(['severidade' => 'bloqueio', 'texto' => 'Seu teste terminou em 12/11/2026. Seus diagnósticos continuam disponíveis.', 'acao' => 'planos', 'rotulo' => 'Ver planos']);
});

it('não bloqueia o fim do teste de quem tem crédito válido', function () {
    expect(AvisoTrial::para(_trialVisto('2026-11-12 15:00:00'), _agoraSp('2026-11-13 09:00:00'), temOutroSaldo: true))->toBeNull();
});

it('não avisa nada para plano pago nem para trial já encerrado por assinatura', function () {
    expect(AvisoTrial::para(_trialVisto('2026-11-12 15:00:00', trial: false), _agoraSp('2026-11-07 10:00:00')))->toBeNull()
        ->and(AvisoTrial::para(_trialVisto('2026-11-12 15:00:00', status: StatusAssinatura::Expirada), _agoraSp('2026-11-07 10:00:00')))->toBeNull();
});

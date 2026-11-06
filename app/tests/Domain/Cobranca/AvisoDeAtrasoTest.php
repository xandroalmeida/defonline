<?php

declare(strict_types=1);

use App\Domain\Cobranca\AssinaturaVista;
use App\Domain\Cobranca\AvisoDeAtraso;
use App\Domain\Cobranca\StatusAssinatura;
use Carbon\CarbonImmutable;

/** STORY-092 CA-4 — o aviso de atraso da Home: só para a assinatura em atraso, sem bloquear. */
function _vistaAtraso(StatusAssinatura $status): AssinaturaVista
{
    return new AssinaturaVista('ass-1', false, $status, 10, 3, CarbonImmutable::parse('2026-12-01 00:00:00'));
}

it('avisa a assinatura em atraso, sem bloquear, e leva ao Meu plano para atualizar o cartão', function () {
    $aviso = AvisoDeAtraso::para(_vistaAtraso(StatusAssinatura::EmAtraso));

    expect($aviso['severidade'])->toBe('info')
        ->and($aviso['texto'])->toContain('em atraso')->toContain('continuar usando')
        ->and($aviso['acao'])->toBe('meu-plano')->and($aviso['rotulo'])->toBe('Atualizar cartão');
});

it('não avisa as outras situações', function (StatusAssinatura $status) {
    expect(AvisoDeAtraso::para(_vistaAtraso($status)))->toBeNull();
})->with([StatusAssinatura::Ativa, StatusAssinatura::Cancelada, StatusAssinatura::Expirada]);

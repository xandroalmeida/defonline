<?php

declare(strict_types=1);

use App\Domain\Cobranca\RenovacaoPix;
use App\Support\DataExibicao;
use Carbon\CarbonImmutable;

/** STORY-080 — as regras de tempo do plano anual pago com Pix, sem banco. */
$fim = CarbonImmutable::parse('2027-11-05 12:00:00', 'UTC');

it('a janela abre 7 dias antes do fim e fecha no fim', function () use ($fim) {
    expect(RenovacaoPix::abreEm($fim)->toIso8601String())->toBe('2027-10-29T12:00:00+00:00')
        ->and(RenovacaoPix::janelaAberta($fim, $fim->subDays(8)))->toBeFalse()
        ->and(RenovacaoPix::janelaAberta($fim, $fim->subDays(7)->subSecond()))->toBeFalse()
        ->and(RenovacaoPix::janelaAberta($fim, $fim->subDays(7)))->toBeTrue()
        ->and(RenovacaoPix::janelaAberta($fim, $fim->subSecond()))->toBeTrue()
        ->and(RenovacaoPix::janelaAberta($fim, $fim))->toBeFalse()
        ->and(RenovacaoPix::janelaAberta($fim, $fim->addDay()))->toBeFalse();
});

it('o novo fim é um ano depois do fim atual, contado do início (sem deriva em 29/02 nem 31/01)', function () {
    $inicio = CarbonImmutable::parse('2026-11-05 15:30:00', 'UTC');

    expect(DataExibicao::converter(RenovacaoPix::novoVencimento($inicio, 1))->toDateString())->toBe('2028-11-05')
        ->and(DataExibicao::converter(RenovacaoPix::novoVencimento($inicio, 2))->toDateString())->toBe('2029-11-05');

    $bissexto = CarbonImmutable::parse('2027-02-28 12:00:00', 'UTC');
    expect(DataExibicao::converter(RenovacaoPix::novoVencimento($bissexto, 1))->toDateString())->toBe('2029-02-28');

    $fimDeMes = CarbonImmutable::parse('2024-02-29 12:00:00', 'UTC');
    expect(DataExibicao::converter(RenovacaoPix::novoVencimento($fimDeMes, 1))->toDateString())->toBe('2026-02-28');
});

it('o aviso é devido uma vez por ciclo, só com a janela aberta', function () use ($fim) {
    $dentro = $fim->subDays(3);

    expect(RenovacaoPix::avisoDevido($fim, null, $dentro))->toBeTrue()
        ->and(RenovacaoPix::avisoDevido($fim, $fim, $dentro))->toBeFalse()
        ->and(RenovacaoPix::avisoDevido($fim, $fim->subYear(), $dentro))->toBeTrue()
        ->and(RenovacaoPix::avisoDevido($fim, null, $fim->subDays(10)))->toBeFalse()
        ->and(RenovacaoPix::avisoDevido($fim, null, $fim->addMinute()))->toBeFalse();
});

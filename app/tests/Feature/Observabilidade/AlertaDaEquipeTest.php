<?php

declare(strict_types=1);

use App\Observabilidade\AlertaDaEquipe;
use App\Services\Rfb\RfbAlerter;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

/**
 * STORY-086 CA-2 e CA-5 / IDR-028 — o canal único de alerta da equipe: Telegram quando configurado, log
 * quando não, nunca derruba quem alerta e nunca leva dado pessoal ou de cartão.
 */
beforeEach(function () {
    $this->logs = [];
    Event::listen(MessageLogged::class, function (MessageLogged $e) {
        $this->logs[] = [$e->level, $e->message, $e->context];
    });
});

function _comTelegram(): void
{
    config(['services.telegram.bot_token' => 'tok-secreto-do-bot', 'services.telegram.chat_id' => '-100123']);
}

it('envia pelo Telegram quando há credenciais e registra o envio sem o token', function () {
    _comTelegram();
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

    app(AlertaDaEquipe::class)->enviar('[P2] Algo', 'Detalhe da ocorrência', ['total' => 3]);

    Http::assertSent(fn (Request $r) => str_contains($r->url(), '/bottok-secreto-do-bot/sendMessage')
        && $r['chat_id'] === '-100123'
        && $r['text'] === "[P2] Algo\n\nDetalhe da ocorrência");
    expect(collect($this->logs)->firstWhere(1, 'alerta.alert.enviado'))->not->toBeNull()
        ->and(json_encode($this->logs))->not->toContain('tok-secreto-do-bot');
});

it('sem credenciais cai para o log em nível warning, nunca error', function () {
    config(['services.telegram.bot_token' => '', 'services.telegram.chat_id' => '']);
    Http::fake();

    app(AlertaDaEquipe::class)->enviar('[P2] Algo', 'Detalhe', ['total' => 3]);

    Http::assertNothingSent();
    $log = collect($this->logs)->firstWhere(1, 'alerta.alert');
    expect($log[0])->toBe('warning')->and($log[2])->toMatchArray(['module' => 'alerta', 'canal' => 'log', 'titulo' => '[P2] Algo', 'total' => 3]);
});

it('Telegram fora do ar não derruba o chamador: cai para o log, sem a mensagem da exceção (que traz a URL com o token)', function () {
    _comTelegram();
    Http::fake(fn () => throw new ConnectionException('cURL error 7: https://api.telegram.org/bottok-secreto-do-bot/sendMessage'));

    app(AlertaDaEquipe::class)->enviar('[P2] Algo', 'Detalhe');

    expect(collect($this->logs)->pluck(1)->all())->toContain('alerta.alert.telegram_falhou', 'alerta.alert')
        ->and(json_encode($this->logs))->not->toContain('tok-secreto-do-bot');
});

it('o monitor da consulta de CNPJ continua assinando como rfb, mas usa o mesmo canal', function () {
    config(['services.telegram.bot_token' => '', 'services.telegram.chat_id' => '']);

    expect(app(RfbAlerter::class))->toBeInstanceOf(AlertaDaEquipe::class);
    app(RfbAlerter::class)->enviar('[RFB] Taxa alta', 'Detalhe');

    expect(collect($this->logs)->firstWhere(1, 'rfb.alert')[2])->toMatchArray(['module' => 'rfb', 'canal' => 'log']);
});

describe('CA-2 nada pessoal chega à equipe', function () {
    it('mascara e-mail, CPF, CNPJ, cartão e IP no título, na mensagem e no contexto', function () {
        config(['services.telegram.bot_token' => '', 'services.telegram.chat_id' => '']);
        _comTelegram();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        app(AlertaDaEquipe::class)->enviar(
            'Falha de maria@exemplo.com.br',
            'CPF 123.456.789-09, CNPJ 12.345.678/0001-95, CPF solto 12345678909, cartão 4111 1111 1111 1111, IP 198.51.100.77 e 2001:db8:abcd:12::1.',
            ['quem' => 'joao@exemplo.com', 'total' => 3],
        );

        Http::assertSent(function (Request $r) {
            $texto = $r['text'];

            return str_contains($texto, '[e-mail]') && substr_count($texto, '[documento]') === 3 && str_contains($texto, '[cartão]')
                && str_contains($texto, '198.51.100.x') && str_contains($texto, '2001:db8:abcd:x')
                && ! preg_match('/@|123\.456|12345678|4111|198\.51\.100\.77|db8:abcd:12/', $texto);
        });
        expect(json_encode($this->logs))->not->toContain('joao@exemplo.com');
    });

    it('deixa passar identificadores opacos, contagens e datas (não confunde UUID com documento)', function () {
        $texto = 'invoice 01a1adfa-e238-7086-96f9-8359cf2bd446 em 2026-11-05 10:00:00, 7 de 12 (58.3%) e 4990 centavos';

        expect(AlertaDaEquipe::higienizar($texto))->toBe($texto);
    });

    it('mascara IPv4 e IPv6 pelo ajudante público', function () {
        expect(AlertaDaEquipe::mascararIp('203.0.113.9'))->toBe('203.0.113.x')
            ->and(AlertaDaEquipe::mascararIp('2001:db8:1:2:3:4:5:6'))->toBe('2001:db8:1:x');
    });
});

<?php

declare(strict_types=1);

use App\Services\Rfb\RfbAlerter;
use App\Support\RequestId;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Feature da STORY-015 CA-5 — comando rfb:monitorar-error-rate.
 */
function inserirMetricaRfb(string $status, string $provider = 'cnpja', ?Carbon $quando = null): void
{
    // toIso8601String preserva o offset — sem isso, Postgres trata como timestamp
    // "naive" na sessão (`America/Sao_Paulo` no compose) enquanto Carbon usa UTC,
    // jogando o instante 3h pra frente e quebrando o filtro de janela.
    DB::table('business_metrics')->insert([
        'request_id' => 'test:'.RequestId::generate(),
        'tipo' => 'rfb_consulta',
        'sucesso' => $status === 'sucesso',
        'duracao_ms' => 100,
        'meta' => json_encode(['provider' => $provider, 'status' => $status]),
        'inserido_em' => ($quando ?? now())->toIso8601String(),
    ]);
}

it('não alerta quando há menos de 5 consultas na janela', function () {
    for ($i = 0; $i < 3; $i++) {
        inserirMetricaRfb('timeout');
    }

    $alerter = Mockery::mock(RfbAlerter::class);
    $alerter->shouldNotReceive('enviar');
    app()->instance(RfbAlerter::class, $alerter);

    $this->artisan('rfb:monitorar-error-rate')->assertExitCode(0);
});

it('não alerta quando a taxa de erro do provedor é ≤ 5%', function () {
    for ($i = 0; $i < 19; $i++) {
        inserirMetricaRfb('sucesso');
    }
    inserirMetricaRfb('sucesso'); // 20 sucessos, 0 falhas

    $alerter = Mockery::mock(RfbAlerter::class);
    $alerter->shouldNotReceive('enviar');
    app()->instance(RfbAlerter::class, $alerter);

    $this->artisan('rfb:monitorar-error-rate')->assertExitCode(0);
});

it('alerta quando a taxa de erro do provedor passa de 5% (CA-5)', function () {
    // 18 sucessos + 2 timeouts = 10% > 5% sobre 20 consultas.
    for ($i = 0; $i < 18; $i++) {
        inserirMetricaRfb('sucesso');
    }
    inserirMetricaRfb('timeout');
    inserirMetricaRfb('erro_5xx');

    $alerter = Mockery::mock(RfbAlerter::class);
    $alerter->shouldReceive('enviar')
        ->once()
        ->withArgs(function (string $titulo, string $msg, array $ctx) {
            return str_contains($titulo, 'cnpja')
                && $ctx['provider'] === 'cnpja'
                && $ctx['total_consultas'] === 20
                && $ctx['erros_provedor'] === 2;
        });
    app()->instance(RfbAlerter::class, $alerter);

    $this->artisan('rfb:monitorar-error-rate')->assertExitCode(0);
});

it('cnpj_inexistente não conta como erro do provedor (não dispara alerta)', function () {
    for ($i = 0; $i < 10; $i++) {
        inserirMetricaRfb('cnpj_inexistente');
    }

    $alerter = Mockery::mock(RfbAlerter::class);
    $alerter->shouldNotReceive('enviar');
    app()->instance(RfbAlerter::class, $alerter);

    $this->artisan('rfb:monitorar-error-rate')->assertExitCode(0);
});

it('ignora consultas fora da janela configurada', function () {
    // Tudo antigo (>10min atrás) — não deve contar.
    for ($i = 0; $i < 10; $i++) {
        inserirMetricaRfb('timeout', 'cnpja', now()->subMinutes(30));
    }

    $alerter = Mockery::mock(RfbAlerter::class);
    $alerter->shouldNotReceive('enviar');
    app()->instance(RfbAlerter::class, $alerter);

    $this->artisan('rfb:monitorar-error-rate')->assertExitCode(0);
});

it('agrupa por provider e alerta apenas o que estourou', function () {
    // cnpja explode (5/10 erros = 50%), receitaws limpo (10 sucessos).
    for ($i = 0; $i < 5; $i++) {
        inserirMetricaRfb('sucesso', 'cnpja');
        inserirMetricaRfb('timeout', 'cnpja');
    }
    for ($i = 0; $i < 10; $i++) {
        inserirMetricaRfb('sucesso', 'receitaws');
    }

    $alerter = Mockery::mock(RfbAlerter::class);
    $alerter->shouldReceive('enviar')
        ->once()
        ->withArgs(fn ($t, $m, $ctx) => $ctx['provider'] === 'cnpja');
    app()->instance(RfbAlerter::class, $alerter);

    $this->artisan('rfb:monitorar-error-rate')->assertExitCode(0);
});

it('RfbAlerter cai para Log::warning quando não há token Telegram (canal=log)', function () {
    config(['services.telegram.bot_token' => '', 'services.telegram.chat_id' => '']);
    putenv('TELEGRAM_BOT_TOKEN=');
    putenv('TELEGRAM_CHAT_ID=');

    Log::shouldReceive('warning')->once()->withArgs(function (string $msg, array $ctx) {
        return $msg === 'rfb.alert' && ($ctx['canal'] ?? null) === 'log';
    });

    app(RfbAlerter::class)->enviar('título', 'mensagem', ['k' => 'v']);
});

it('RfbAlerter envia para Telegram via HTTP quando token e chat_id estão configurados', function () {
    config([
        'services.telegram.bot_token' => 'AAA-token',
        'services.telegram.chat_id' => '-100123',
    ]);

    Http::fake([
        'api.telegram.org/*' => Http::response(['ok' => true], 200),
    ]);

    app(RfbAlerter::class)->enviar('título', 'mensagem', ['provider' => 'cnpja']);

    Http::assertSent(function ($request) {
        $url = $request->url();
        $body = $request->data();

        return str_contains($url, 'api.telegram.org/botAAA-token/sendMessage')
            && $body['chat_id'] === '-100123'
            && str_contains($body['text'], 'título')
            && str_contains($body['text'], 'mensagem');
    });
});

it('RfbAlerter cai para Log::warning quando Telegram retorna erro de rede', function () {
    config([
        'services.telegram.bot_token' => 'AAA-token',
        'services.telegram.chat_id' => '-100123',
    ]);

    Http::fake([
        'api.telegram.org/*' => function () {
            throw new ConnectionException('boom');
        },
    ]);

    Log::shouldReceive('warning')->twice(); // telegram_falhou + canal=log

    app(RfbAlerter::class)->enviar('título', 'mensagem');
});

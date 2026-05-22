<?php

declare(strict_types=1);

use App\Jobs\HelloWorldEmail;
use App\Mail\HelloWorldMessage;
use App\Support\RequestId;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

afterEach(function () {
    RequestId::reset();
});

it('sends the mailable and records a successful business_metrics row', function () {
    Mail::fake();
    RequestId::set('0190b1aa-0000-7000-8000-000000000000');

    HelloWorldEmail::dispatchSync('alice@indústria.example');

    Mail::assertSent(HelloWorldMessage::class, function (HelloWorldMessage $mail) {
        return $mail->hasTo('alice@indústria.example');
    });

    $row = DB::table('business_metrics')
        ->where('tipo', 'email_enviado')
        ->orderByDesc('inserido_em')
        ->first();

    expect($row)->not->toBeNull();
    expect($row->sucesso)->toBeTrue();
    expect($row->request_id)->toBe('0190b1aa-0000-7000-8000-000000000000');
    expect($row->duracao_ms)->toBeGreaterThanOrEqual(0);

    $meta = json_decode((string) $row->meta, true);
    expect($meta)
        ->toMatchArray([
            'job' => 'HelloWorldEmail',
            'destinatario_dominio' => 'indústria.example',
        ]);
});

it('records a failure row when Mail throws and rethrows the exception', function () {
    Mail::shouldReceive('to')->andThrow(new RuntimeException('smtp_down'));
    Log::shouldReceive('withContext')->andReturnNull();
    Log::shouldReceive('error')
        ->once()
        ->withArgs(fn (string $msg, array $ctx) => $msg === 'hello_world_email_falhou'
            && $ctx['destinatario'] === 'bob@x.com'
            && $ctx['error'] === 'smtp_down');
    Log::shouldReceive('info')->andReturnNull();
    Log::shouldReceive('warning')->andReturnNull();

    expect(fn () => HelloWorldEmail::dispatchSync('bob@x.com'))
        ->toThrow(RuntimeException::class, 'smtp_down');

    $row = DB::table('business_metrics')
        ->where('tipo', 'email_enviado')
        ->orderByDesc('inserido_em')
        ->first();

    expect($row)->not->toBeNull();
    expect($row->sucesso)->toBeFalse();
});

it('handles business_metrics insertion failure gracefully (just logs warning)', function () {
    Mail::fake();
    // Desliga o CollectJobMetrics — ele também insere DB e poluiria o teste com
    // erros de "transaction aborted" depois do INSERT em business_metrics falhar.
    Event::forget(JobProcessing::class);
    Event::forget(JobProcessed::class);
    Event::forget(JobFailed::class);
    // Apaga a tabela para forçar o insert do business_metrics a falhar.
    DB::statement('DROP TABLE business_metrics');

    Log::shouldReceive('withContext')->andReturnNull();
    Log::shouldReceive('info')->andReturnNull();
    Log::shouldReceive('warning')
        ->once()
        ->withArgs(fn (string $msg) => $msg === 'business_metrics_insert_failed');

    // O job NÃO deve relançar a exception do business_metrics.
    HelloWorldEmail::dispatchSync('charlie@y.com');

    Mail::assertSent(HelloWorldMessage::class);
});

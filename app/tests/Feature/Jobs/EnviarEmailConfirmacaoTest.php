<?php

declare(strict_types=1);

use App\Jobs\EnviarEmailConfirmacao;
use App\Mail\EmailConfirmacaoMessage;
use App\Models\Usuario;
use App\Support\RequestId;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * STORY-013 CA-2 — job de envio de email de confirmação.
 *
 * Cobre: envio síncrono (dispatchSync), idempotência (já confirmado),
 * usuário ausente, falha de envio + business_metrics.
 */
afterEach(function () {
    RequestId::reset();
});

it('envia EmailConfirmacaoMessage para usuário pendente com link assinado (CA-2)', function () {
    Mail::fake();
    $usuario = Usuario::factory()->unconfirmed()->create([
        'nome' => 'Roberto Souza',
        'email' => 'roberto@exemplo.com.br',
    ]);

    EnviarEmailConfirmacao::dispatchSync($usuario->id);

    Mail::assertSent(EmailConfirmacaoMessage::class, function (EmailConfirmacaoMessage $mail) use ($usuario) {
        // O Mailable carrega o link signed + primeiro nome.
        return $mail->hasTo($usuario->email)
            && $mail->nome === 'Roberto'
            && str_contains($mail->link, '/email/confirmar/'.$usuario->id)
            && str_contains($mail->link, 'signature=')
            && str_contains($mail->link, 'expires=');
    });
});

it('grava business_metrics email_confirmacao_enviado no sucesso (CA-2 + ADR-004)', function () {
    Mail::fake();
    RequestId::set('0190b1aa-0000-7000-8000-000000000000');
    $usuario = Usuario::factory()->unconfirmed()->create();

    EnviarEmailConfirmacao::dispatchSync($usuario->id);

    $row = DB::table('business_metrics')
        ->where('tipo', 'email_confirmacao_enviado')
        ->orderByDesc('inserido_em')
        ->first();

    expect($row)->not->toBeNull();
    expect($row->sucesso)->toBeTrue();
    expect($row->request_id)->toBe('0190b1aa-0000-7000-8000-000000000000');
});

it('não envia se o usuário já está confirmado (idempotente)', function () {
    Mail::fake();
    $usuario = Usuario::factory()->create();          // factory: confirmado por default
    expect($usuario->emailConfirmado())->toBeTrue();

    EnviarEmailConfirmacao::dispatchSync($usuario->id);

    Mail::assertNothingSent();
});

it('não falha quando o usuário foi removido entre dispatch e execução', function () {
    Mail::fake();
    Log::shouldReceive('withContext')->andReturnNull();
    Log::shouldReceive('warning')
        ->once()
        ->withArgs(fn (string $msg) => $msg === 'email_confirmacao_usuario_nao_encontrado');
    Log::shouldReceive('info')->andReturnNull();

    EnviarEmailConfirmacao::dispatchSync('019e0000-0000-7000-8000-000000000000');

    Mail::assertNothingSent();
});

it('grava business_metrics email_confirmacao_falhou no hook failed()', function () {
    $usuario = Usuario::factory()->unconfirmed()->create();
    RequestId::set('0190b1cc-0000-7000-8000-000000000001');

    $job = new EnviarEmailConfirmacao($usuario->id);
    $job->failed(new RuntimeException('smtp_down'));

    $row = DB::table('business_metrics')
        ->where('tipo', 'email_confirmacao_falhou')
        ->orderByDesc('inserido_em')
        ->first();

    expect($row)->not->toBeNull();
    expect($row->sucesso)->toBeFalse();
});

it('relança a exception quando Mail falha (para acionar retry da fila)', function () {
    $usuario = Usuario::factory()->unconfirmed()->create();
    Mail::shouldReceive('to')->andThrow(new RuntimeException('smtp_down'));
    Log::shouldReceive('withContext')->andReturnNull();
    Log::shouldReceive('error')->andReturnNull();
    Log::shouldReceive('warning')->andReturnNull();
    Log::shouldReceive('info')->andReturnNull();

    expect(fn () => EnviarEmailConfirmacao::dispatchSync($usuario->id))
        ->toThrow(RuntimeException::class, 'smtp_down');

    // Tentativa registra no business_metrics como email_confirmacao_envio_erro.
    $row = DB::table('business_metrics')
        ->where('tipo', 'email_confirmacao_envio_erro')
        ->orderByDesc('inserido_em')
        ->first();
    expect($row)->not->toBeNull();
    expect($row->sucesso)->toBeFalse();
});

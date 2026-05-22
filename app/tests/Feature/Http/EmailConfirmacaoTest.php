<?php

declare(strict_types=1);

use App\Jobs\EnviarEmailConfirmacao;
use App\Models\AuditLog;
use App\Models\Usuario;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;

/**
 * Helper local — POST de reenvio com token de sessão (padrão do projeto, ver HomeTest).
 */
function postReenvio(string $email): TestResponse
{
    return test()
        ->from('/email/confirmar-erro')
        ->withSession(['_token' => 'test-token'])
        ->post('/email/reenviar-confirmacao', ['email' => $email, '_token' => 'test-token']);
}

/**
 * STORY-013 CA-3 — confirmação por link assinado.
 * STORY-013 CA-5 — reenvio com throttling + resposta genérica.
 */
function linkAssinado(Usuario $u, int $minutos = 60): string
{
    return URL::temporarySignedRoute(
        'email.confirmar',
        Carbon::now()->addMinutes($minutos),
        ['usuario' => $u->id],
    );
}

it('confirma email quando o link é válido (CA-3)', function () {
    $usuario = Usuario::factory()->unconfirmed()->create();

    $this->get(linkAssinado($usuario))
        ->assertRedirect(route('email.confirmado'));

    $usuario->refresh();
    expect($usuario->emailConfirmado())->toBeTrue();
    expect($usuario->email_confirmed_at)->not->toBeNull();
});

it('emite audit_log usuario.email_confirmado no sucesso (CA-3)', function () {
    $usuario = Usuario::factory()->unconfirmed()->create();

    $this->get(linkAssinado($usuario));

    $audit = AuditLog::where('action', 'usuario.email_confirmado')
        ->where('usuario_id', $usuario->id)
        ->first();
    expect($audit)->not->toBeNull();
    expect($audit->subject_type)->toBe('Usuario');
    expect($audit->subject_id)->toBe($usuario->id);
});

it('redireciona com motivo "expirado" quando o link expira (CA-3)', function () {
    $usuario = Usuario::factory()->unconfirmed()->create();
    $link = linkAssinado($usuario, minutos: 60);

    // Adianta o relógio: link com TTL 60min vence em 90min.
    Carbon::setTestNow(Carbon::now()->addMinutes(90));

    $this->get($link)
        ->assertRedirect(route('email.confirmar-erro'))
        ->assertSessionHas('email_confirmar_erro_motivo', 'expirado');

    expect($usuario->fresh()->emailConfirmado())->toBeFalse();
    Carbon::setTestNow();
});

it('rejeita assinatura tampered (CA-3)', function () {
    $usuario = Usuario::factory()->unconfirmed()->create();
    $link = linkAssinado($usuario);

    // Corrompe a assinatura mantendo o resto.
    $tampered = preg_replace('/signature=[a-f0-9]+/', 'signature=00000000', $link) ?? '';

    $this->get($tampered)
        ->assertRedirect(route('email.confirmar-erro'))
        ->assertSessionHas('email_confirmar_erro_motivo', 'expirado');

    expect($usuario->fresh()->emailConfirmado())->toBeFalse();
});

it('redireciona com motivo "ja_confirmado" se o usuário já está confirmado (CA-3)', function () {
    $usuario = Usuario::factory()->create();          // confirmado por default

    $this->get(linkAssinado($usuario))
        ->assertRedirect(route('email.confirmar-erro'))
        ->assertSessionHas('email_confirmar_erro_motivo', 'ja_confirmado');
});

it('rota /email/confirmado mostra a mensagem amigável (CA-3)', function () {
    $this->get('/email/confirmado')
        ->assertOk()
        ->assertSee('Email confirmado')
        ->assertSee('Você já pode fazer login');
});

it('rota /email/confirmar-erro tem o form de reenvio (CA-3)', function () {
    $this->get('/email/confirmar-erro')
        ->assertOk()
        ->assertSee('Reenviar email');
});

// ----- CA-5 — reenvio -------------------------------------------------------

beforeEach(function () {
    Cache::flush();          // limpa rate limiter entre testes
});

it('reenvio enfileira EnviarEmailConfirmacao para email cadastrado pendente (CA-5)', function () {
    Queue::fake();
    $usuario = Usuario::factory()->unconfirmed()->create([
        'email' => 'roberto@exemplo.com.br',
    ]);

    postReenvio('roberto@exemplo.com.br')
        ->assertRedirect('/email/confirmar-erro')
        ->assertSessionHas('email_reenvio_aviso');

    Queue::assertPushed(EnviarEmailConfirmacao::class, fn ($j) => $j->usuarioId === $usuario->id);
});

it('reenvio responde mensagem genérica para email não cadastrado (CA-5 — anti-enumeração)', function () {
    Queue::fake();

    postReenvio('nao-existe@exemplo.com.br')
        ->assertRedirect('/email/confirmar-erro')
        ->assertSessionHas('email_reenvio_aviso', 'Se este email estiver cadastrado, enviamos um novo link de confirmação.');

    Queue::assertNothingPushed();
});

it('reenvio NÃO enfileira para usuário já confirmado (CA-5)', function () {
    Queue::fake();
    Usuario::factory()->create(['email' => 'ja@exemplo.com.br']);

    postReenvio('ja@exemplo.com.br')
        ->assertSessionHas('email_reenvio_aviso');

    Queue::assertNothingPushed();
});

it('reenvio bloqueia após 3 disparos por hora para o mesmo email (CA-5 — throttling)', function () {
    Queue::fake();
    Usuario::factory()->unconfirmed()->create(['email' => 'spam@exemplo.com.br']);

    for ($i = 1; $i <= 3; $i++) {
        postReenvio('spam@exemplo.com.br')
            ->assertSessionHas('email_reenvio_aviso');
    }
    Queue::assertPushed(EnviarEmailConfirmacao::class, 3);

    // 4ª tentativa: throttled → resposta genérica, sem novo dispatch.
    postReenvio('spam@exemplo.com.br')
        ->assertSessionHas('email_reenvio_aviso');

    Queue::assertPushed(EnviarEmailConfirmacao::class, 3);
});

it('reenvio rejeita formato de email inválido silenciosamente (CA-5)', function () {
    Queue::fake();

    postReenvio('nao-eh-email')
        ->assertSessionHas('email_reenvio_aviso');

    Queue::assertNothingPushed();
});

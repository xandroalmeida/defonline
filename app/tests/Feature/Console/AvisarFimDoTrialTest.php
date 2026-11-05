<?php

declare(strict_types=1);

use App\Domain\Cobranca\Carteira;
use App\Domain\Cobranca\StatusAssinatura;
use App\Mail\FimDoTrialMessage;
use App\Models\Assinatura;
use App\Models\CobrancaConfig;
use App\Models\Plano;
use App\Models\Usuario;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;

/**
 * STORY-074 CA-6 — e-mail de convite para assinar em D-2 e em D-0.
 */
beforeEach(function () {
    Mail::fake();
    CobrancaConfig::atual()->update(['ativa' => true]);
    $this->usuario = Usuario::factory()->create(['nome' => 'Roberto Souza']);
});

/** Um trial que termina em `$fim` (instante UTC), iniciado sete dias antes. */
function _trialQueTermina(Usuario $usuario, string $fim): Assinatura
{
    $f = CarbonImmutable::parse($fim, 'UTC');

    return app(Carteira::class)->concederCota($usuario->id, 1, $f->subDays(7), $f, trial: true);
}

function _agora(string $quando): void
{
    test()->travelTo(CarbonImmutable::parse($quando, 'UTC'));
}

it('envia o convite de D-2 uma vez só, com a data do fim e o link dos planos', function () {
    // Margem de horas de sobra: o fuso da sessão do Postgres de dev (São Paulo) não muda o dia de Brasília.
    $trial = _trialQueTermina($this->usuario, '2026-11-12 18:00:00');
    _agora('2026-11-10 12:00:00');

    $this->artisan('trial:avisar-fim')->assertSuccessful();
    $this->artisan('trial:avisar-fim')->assertSuccessful();

    Mail::assertSent(FimDoTrialMessage::class, 1);
    Mail::assertSent(FimDoTrialMessage::class, fn (FimDoTrialMessage $m) => $m->hasTo($this->usuario->email)
        && $m->dias === 2 && $m->dataFim === '12/11/2026' && $m->nome === 'Roberto' && $m->link === route('planos')
        && $m->envelope()->subject === 'Seu teste termina em 2 dias — DEFOnline');
    expect($trial->refresh()->aviso_d2_em)->not->toBeNull()->and($trial->aviso_d0_em)->toBeNull();
});

it('envia o convite de D-0 no último dia, antes do fim', function () {
    $trial = _trialQueTermina($this->usuario, '2026-11-12 22:00:00');
    _agora('2026-11-12 12:00:00');

    $this->artisan('trial:avisar-fim')->assertSuccessful();

    Mail::assertSent(FimDoTrialMessage::class, fn (FimDoTrialMessage $m) => $m->dias === 0 && $m->envelope()->subject === 'Seu teste termina hoje — DEFOnline');
    expect($trial->refresh()->aviso_d0_em)->not->toBeNull();
});

it('o texto do e-mail tem o mesmo texto e CTA da Home, em HTML e em texto', function () {
    $d2 = new FimDoTrialMessage('Roberto', '12/11/2026', 2, 'https://exemplo.test/planos');
    $d0 = new FimDoTrialMessage('Roberto', '12/11/2026', 0, 'https://exemplo.test/planos');

    $d2->assertSeeInHtml('Seu teste termina em 12/11/2026 (em 2 dias). Assine para continuar usando o que já faz.');
    $d2->assertSeeInHtml('Assinar um plano');
    $d2->assertSeeInHtml('https://exemplo.test/planos', false);
    $d2->assertSeeInText('Assinar um plano: https://exemplo.test/planos');
    $d0->assertSeeInHtml('Seu teste termina hoje.');
    $d0->assertDontSeeInHtml('Assine para continuar');
});

it('não manda nada fora de D-2 e D-0, nem a trial já vencido, pago ou de outro estado', function () {
    $longe = _trialQueTermina($this->usuario, '2026-11-20 18:00:00');
    $vencido = _trialQueTermina(Usuario::factory()->create(), '2026-11-09 18:00:00');
    $expirado = _trialQueTermina($u3 = Usuario::factory()->create(), '2026-11-12 18:00:00');
    $expirado->forceFill(['status' => StatusAssinatura::Expirada])->save();
    $pago = Usuario::factory()->create();
    app(Carteira::class)->concederCota($pago->id, 1, CarbonImmutable::parse('2026-11-05', 'UTC'), CarbonImmutable::parse('2026-11-12 18:00:00', 'UTC'), Plano::query()->firstOrFail()->id, 'mensal');
    _agora('2026-11-10 12:00:00');

    $this->artisan('trial:avisar-fim')->assertSuccessful();

    Mail::assertNothingSent();
});

it('não manda no mesmo dia da ativação de um teste curto', function () {
    $f = CarbonImmutable::parse('2026-11-12 18:00:00', 'UTC');
    app(Carteira::class)->concederCota($this->usuario->id, 1, $f->subDays(2)->setTime(12, 0), $f, trial: true);
    _agora('2026-11-10 13:00:00');

    $this->artisan('trial:avisar-fim')->assertSuccessful();

    Mail::assertNothingSent();
});

it('com a cobrança desligada não faz nada', function () {
    CobrancaConfig::atual()->update(['ativa' => false]);
    _trialQueTermina($this->usuario, '2026-11-12 18:00:00');
    _agora('2026-11-10 12:00:00');

    $this->artisan('trial:avisar-fim')->expectsOutputToContain('Cobrança desligada')->assertSuccessful();

    Mail::assertNothingSent();
});

it('uma falha de envio não marca o aviso (tenta de novo na hora seguinte) e não derruba os outros', function () {
    $falho = _trialQueTermina($this->usuario, '2026-11-12 18:00:00');
    $bom = _trialQueTermina($outro = Usuario::factory()->create(['email' => 'bom@exemplo.com.br']), '2026-11-12 18:00:00');
    _agora('2026-11-10 12:00:00');
    Mail::shouldReceive('to')->with($this->usuario->email)->andThrow(new RuntimeException('smtp fora'));
    Mail::shouldReceive('to')->with('bom@exemplo.com.br')->andReturn(new class
    {
        public function send(mixed $m): void {}
    });

    $this->artisan('trial:avisar-fim')->assertFailed();

    expect($falho->refresh()->aviso_d2_em)->toBeNull()->and($bom->refresh()->aviso_d2_em)->not->toBeNull();
});

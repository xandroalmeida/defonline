<?php

declare(strict_types=1);

use App\Domain\Cobranca\EstadoCobranca;
use App\Domain\Cobranca\PaymentProvider;
use App\Domain\Cobranca\ProvedorIndisponivel;
use App\Domain\TermoTipo;
use App\Infrastructure\Cobranca\Fake\FakePaymentProvider;
use App\Jobs\EnviarEmailPagamento;
use App\Mail\LinkDePagamentoMessage;
use App\Models\Invoice;
use App\Models\LoteCredito;
use App\Models\TermAcceptance;
use App\Models\Usuario;
use App\Services\Cobranca\CompraNaoPermitida;
use App\Services\Cobranca\IniciarCompraDeCreditos;
use App\Support\TermosVigentes;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * STORY-076 CA-1, CA-2, CA-6, CA-7 — o início da compra: aceite do Termo, checkout, e-mail e falhas.
 */
beforeEach(function () {
    _usarProvedor('fake');
    Mail::fake();
});

it('registra o aceite do Termo (versão vigente, IP e hash) e abre o checkout (CA-1, CA-2)', function () {
    $usuario = _compradorConfirmado();

    $invoice = app(IniciarCompraDeCreditos::class)->executar($usuario, _pacote(), '203.0.113.7', 'PestAgent');

    $aceite = TermAcceptance::query()->where('usuario_id', $usuario->id)->where('termo_tipo', TermoTipo::TermoAdesao->value)->sole();
    $aceiteId = DB::table('term_acceptances')->where('usuario_id', $usuario->id)->where('termo_tipo', TermoTipo::TermoAdesao->value)->value('id');
    $vigente = TermosVigentes::para(TermoTipo::TermoAdesao);

    expect($aceite->aceito)->toBeTrue()
        ->and($aceite->versao)->toBe($vigente->versao)
        ->and($aceite->conteudo_hash)->toBe($vigente->conteudoHash)
        ->and($aceite->ip)->toBe('203.0.113.7')
        ->and($aceite->aceito_at)->not->toBeNull()
        ->and(TermAcceptance::query()->where('usuario_id', $usuario->id)->where('termo_tipo', TermoTipo::Lgpd->value)->exists())->toBeTrue()
        ->and($invoice->aceite_termo_id)->toBe($aceiteId)
        ->and($invoice->status)->toBe(EstadoCobranca::Pendente)
        ->and($invoice->provedor)->toBe('fake')
        ->and($invoice->checkout_id_externo)->toStartWith('fakechk_')
        ->and($invoice->checkout_url)->not->toBeNull()
        ->and($invoice->valor_centavos)->toBe(_pacote()->preco_centavos)
        ->and($invoice->checkout_expira_em->isFuture())->toBeTrue();
});

it('manda o link do checkout por e-mail para quem fechar a tela (CA-2)', function () {
    $usuario = _compradorConfirmado();

    $invoice = app(IniciarCompraDeCreditos::class)->executar($usuario, _pacote(), null, null);

    Mail::assertSent(LinkDePagamentoMessage::class, fn (LinkDePagamentoMessage $m) => $m->hasTo($usuario->email) && $m->link === $invoice->checkout_url);
});

it('o e-mail não sai se a compra já foi paga ou expirou antes do envio', function () {
    $usuario = _compradorConfirmado();
    $invoice = app(IniciarCompraDeCreditos::class)->executar($usuario, _pacote(), null, null);
    Mail::fake();

    $invoice->forceFill(['status' => EstadoCobranca::Paga])->save();
    (new EnviarEmailPagamento($invoice->id))->handle();
    $invoice->forceFill(['status' => EstadoCobranca::Pendente, 'checkout_expira_em' => now()->subMinute()])->save();
    (new EnviarEmailPagamento($invoice->id))->handle();
    (new EnviarEmailPagamento('00000000-0000-4000-8000-000000000000'))->handle();

    Mail::assertNothingSent();
});

it('exige e-mail confirmado e não grava nada (CA-1)', function () {
    $usuario = Usuario::factory()->unconfirmed()->create();

    expect(fn () => app(IniciarCompraDeCreditos::class)->executar($usuario, _pacote(), null, null))
        ->toThrow(fn (CompraNaoPermitida $e) => $e->motivo === CompraNaoPermitida::EMAIL_NAO_CONFIRMADO);

    expect(Invoice::query()->count())->toBe(0)->and(TermAcceptance::query()->where('usuario_id', $usuario->id)->count())->toBe(0);
});

it('recusa pacote desativado', function () {
    $usuario = _compradorConfirmado();
    $pacote = _pacote();
    $pacote->update(['ativo' => false]);

    expect(fn () => app(IniciarCompraDeCreditos::class)->executar($usuario, $pacote->fresh(), null, null))
        ->toThrow(fn (CompraNaoPermitida $e) => $e->motivo === CompraNaoPermitida::PACOTE_INDISPONIVEL);
});

it('o admin em "Logar como" não consegue contratar (CA-7)', function () {
    $alvo = _compradorConfirmado();
    $admin = _adminDaEquipe();
    $this->actingAs($admin);
    _personificar($admin, $alvo);

    expect(fn () => app(IniciarCompraDeCreditos::class)->executar($alvo, _pacote(), null, null))
        ->toThrow(fn (CompraNaoPermitida $e) => $e->motivo === CompraNaoPermitida::ADMIN_PERSONIFICANDO);

    expect(Invoice::query()->count())->toBe(0);
});

it('falha do provedor deixa a compra como falhou, registra para alerta e não credita (CA-6)', function () {
    Log::spy();
    $usuario = _compradorConfirmado();
    FakePaymentProvider::falharProximoCheckout();

    expect(fn () => app(IniciarCompraDeCreditos::class)->executar($usuario, _pacote(), null, null))->toThrow(ProvedorIndisponivel::class);

    $invoice = Invoice::query()->sole();
    expect($invoice->status)->toBe(EstadoCobranca::Falhou)
        ->and($invoice->falha_motivo)->not->toBeNull()
        ->and($invoice->provedor)->toBe('fake')
        ->and(LoteCredito::query()->count())->toBe(0);
    Log::shouldHaveReceived('error')->with('cobranca.provedor_falhou', Mockery::on(fn ($c) => $c['invoice_id'] === $invoice->id));
    Mail::assertNothingSent();
});

it('reaproveita o checkout ainda válido do mesmo pacote em vez de abrir outro', function () {
    $usuario = _compradorConfirmado();
    $servico = app(IniciarCompraDeCreditos::class);

    $primeira = $servico->executar($usuario, _pacote(), null, null);
    $segunda = $servico->executar($usuario, _pacote(), null, null);
    $outroPacote = $servico->executar($usuario, _pacote('mini'), null, null);

    expect($segunda->id)->toBe($primeira->id)->and($outroPacote->id)->not->toBe($primeira->id)
        ->and(Invoice::query()->count())->toBe(2);

    $primeira->forceFill(['checkout_expira_em' => now()->subMinute()])->save();
    expect($servico->executar($usuario, _pacote(), null, null)->id)->not->toBe($primeira->id);
});

it('usa a expiração configurada para o Pix', function () {
    Bus::fake();
    config(['services.payments.pix_expiracao_minutos' => 45]);
    $usuario = _compradorConfirmado();

    $invoice = app(IniciarCompraDeCreditos::class)->executar($usuario, _pacote(), null, null);

    expect((int) round(now()->diffInMinutes($invoice->checkout_expira_em)))->toBe(45);
    Bus::assertDispatched(EnviarEmailPagamento::class);
});

it('o provedor desconhecido ou o fake em produção são recusados', function () {
    config(['services.payments.provider' => 'outro']);
    app()->forgetInstance(PaymentProvider::class);
    expect(fn () => app(PaymentProvider::class))->toThrow(InvalidArgumentException::class, 'desconhecido');

    config(['services.payments.provider' => 'fake']);
    app()->forgetInstance(PaymentProvider::class);
    $this->app['env'] = 'production';
    expect(fn () => app(PaymentProvider::class))->toThrow(InvalidArgumentException::class, 'produção');
    $this->app['env'] = 'testing';
});

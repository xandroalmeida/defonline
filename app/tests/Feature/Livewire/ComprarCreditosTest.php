<?php

declare(strict_types=1);

use App\Domain\Cobranca\EstadoCobranca;
use App\Infrastructure\Cobranca\Fake\FakePaymentProvider;
use App\Jobs\EnviarEmailConfirmacao;
use App\Livewire\Cobranca\ComprarCreditos;
use App\Models\CobrancaConfig;
use App\Models\Invoice;
use App\Models\TermAcceptance;
use App\Models\Usuario;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

/**
 * STORY-076 CA-1, CA-6, CA-7, CA-10 — a revisão do pedido (spec STORY-070-checkout-pix-creditos).
 */
beforeEach(function () {
    _usarProvedor('fake');
    Mail::fake();
});

it('com a cobrança desligada a rota é 404 para o usuário comum (CA-10)', function () {
    $usuario = Usuario::factory()->create();
    CobrancaConfig::atual()->update(['ativa' => false]);

    $this->actingAs($usuario)->get(route('creditos.comprar'))->assertNotFound();
    $this->actingAs($usuario)->get(route('creditos.comprar', _pacote()->id))->assertNotFound();
    $this->actingAs($usuario)->get(route('creditos.retorno', '00000000-0000-4000-8000-000000000000'))->assertNotFound();
});

it('exige login', function () {
    $this->get(route('creditos.comprar'))->assertRedirect(route('login'));
});

it('lista os pacotes ativos quando nenhum foi escolhido', function () {
    $usuario = _compradorConfirmado();
    _pacote('max')->update(['ativo' => false]);

    $this->actingAs($usuario)->get(route('creditos.comprar'))
        ->assertOk()->assertSee('Escolha o pacote')->assertSee('Pacote Mini')->assertDontSee('Pacote Max');
});

it('mostra o resumo do pedido com preço, ciclo e validade', function () {
    $usuario = _compradorConfirmado();
    $pacote = _pacote();

    Livewire::actingAs($usuario)->test(ComprarCreditos::class, ['pacote' => $pacote->id])
        ->assertSee('Resumo do pedido')
        ->assertSee('Sem desconto')
        ->assertSee('Compra única, não renova')
        ->assertSee('Os créditos não incluem o Raio X do Negócio')
        ->assertSee('Pix · o QR Code vale por 30 minutos.')
        ->assertSee('R$ '.number_format($pacote->preco_centavos / 100, 2, ',', '.'))
        ->assertSee('até '.now()->addMonthsNoOverflow($pacote->validade_meses)->setTimezone('America/Sao_Paulo')->format('d/m/Y'));
});

it('pacote inexistente ou desativado mostra o aviso, sem formulário', function () {
    $usuario = _compradorConfirmado();
    _pacote()->update(['ativo' => false]);

    Livewire::actingAs($usuario)->test(ComprarCreditos::class, ['pacote' => _pacote()->id])
        ->assertSee('Este pacote não está mais disponível.')
        ->assertDontSee('Gerar QR Code Pix');
});

it('sem aceite do Termo mostra o erro e não gera cobrança (CA-1)', function () {
    $usuario = _compradorConfirmado();

    Livewire::actingAs($usuario)->test(ComprarCreditos::class, ['pacote' => _pacote()->id])
        ->call('gerar')
        ->assertHasErrors(['aceito'])
        ->assertSee('Marque o aceite do Termo para continuar.');

    expect(Invoice::query()->count())->toBe(0)->and(TermAcceptance::query()->where('usuario_id', $usuario->id)->count())->toBe(0);
});

it('com o aceite, registra tudo e leva ao checkout do provedor (CA-1, CA-2)', function () {
    $usuario = _compradorConfirmado();

    Livewire::actingAs($usuario)->test(ComprarCreditos::class, ['pacote' => _pacote()->id])
        ->set('aceito', true)
        ->call('gerar')
        ->assertHasNoErrors()
        ->assertRedirect(Invoice::query()->sole()->checkout_url);

    expect(TermAcceptance::query()->where('usuario_id', $usuario->id)->where('termo_tipo', 'termo_adesao')->sole()->aceito)->toBeTrue();
});

it('e-mail não confirmado mostra o bloqueio sem formulário e não deixa gerar (CA-1)', function () {
    $usuario = Usuario::factory()->unconfirmed()->create();
    CobrancaConfig::atual()->update(['ativa' => true]);

    Livewire::actingAs($usuario)->test(ComprarCreditos::class, ['pacote' => _pacote()->id])
        ->assertSee('Confirme seu e-mail para comprar.')
        ->assertDontSee('Gerar QR Code Pix')
        ->set('aceito', true)
        ->call('gerar')
        ->assertNoRedirect();

    expect(Invoice::query()->count())->toBe(0);
});

it('reenvia a confirmação de e-mail, com limite por hora', function () {
    Bus::fake();
    $usuario = Usuario::factory()->unconfirmed()->create();
    CobrancaConfig::atual()->update(['ativa' => true]);

    $c = Livewire::actingAs($usuario)->test(ComprarCreditos::class, ['pacote' => _pacote()->id]);
    foreach (range(1, 5) as $_) {
        $c->call('reenviarConfirmacao');
    }

    $c->assertSee('Enviamos um novo link');
    Bus::assertDispatchedTimes(EnviarEmailConfirmacao::class, 3);
});

it('quem já confirmou o e-mail não reenvia nada', function () {
    Bus::fake();
    $usuario = _compradorConfirmado();

    Livewire::actingAs($usuario)->test(ComprarCreditos::class, ['pacote' => _pacote()->id])->call('reenviarConfirmacao');

    Bus::assertNotDispatched(EnviarEmailConfirmacao::class);
});

it('falha do provedor mostra mensagem clara, não cobra e permite tentar de novo (CA-6)', function () {
    $usuario = _compradorConfirmado();
    FakePaymentProvider::falharProximoCheckout();

    $c = Livewire::actingAs($usuario)->test(ComprarCreditos::class, ['pacote' => _pacote()->id])
        ->set('aceito', true)
        ->call('gerar')
        ->assertNoRedirect()
        ->assertSee('Não conseguimos gerar o QR Code agora. Nada foi cobrado.');

    expect(Invoice::query()->sole()->status)->toBe(EstadoCobranca::Falhou);

    $c->call('gerar')->assertRedirect()->assertSet('falhaDoProvedor', false);
});

it('durante o "Logar como" o botão fica desabilitado e o aviso aparece (CA-7)', function () {
    $alvo = _compradorConfirmado();
    $admin = _adminDaEquipe();

    $this->withSession(_sessaoDePersonificacao($admin, $alvo))->actingAs($alvo)
        ->get(route('creditos.comprar', _pacote()->id))
        ->assertOk()
        ->assertSee('Você está acessando como administrador. Contratar e pagar não está disponível nesse modo.')
        ->assertSeeInOrder(['aria-disabled="true"', 'Gerar QR Code Pix'], false);
});

it('o aceite previamente dado continua marcado ao gerar outro código', function () {
    $usuario = _compradorConfirmado();
    session()->flash('aceite_previo', true);

    Livewire::actingAs($usuario)->test(ComprarCreditos::class, ['pacote' => _pacote()->id])->assertSet('aceito', true);
});

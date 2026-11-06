<?php

declare(strict_types=1);

use App\Domain\Cobranca\EstadoCobranca;
use App\Domain\Cobranca\MeioPagamento;
use App\Infrastructure\Cobranca\Fake\FakePaymentProvider;
use App\Jobs\EnviarEmailConfirmacao;
use App\Livewire\Cobranca\ComprarCreditos;
use App\Models\CobrancaConfig;
use App\Models\Invoice;
use App\Models\TermAcceptance;
use App\Models\Usuario;
use App\Services\Cobranca\IniciarCompraDeCreditos;
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

// STORY-078 — escolha entre Pix e cartão (spec STORY-070-checkout-creditos-cartao).
describe('seletor de meio (STORY-078 CA-1)', function () {
    it('o Pix vem selecionado, sem "recomendado", e o resumo diz que não renova nos dois meios', function () {
        $usuario = _compradorConfirmado();

        Livewire::actingAs($usuario)->test(ComprarCreditos::class, ['pacote' => _pacote()->id])
            ->assertSet('meio', 'pix')
            ->assertSee('Como você quer pagar?')
            ->assertSee('Cartão de crédito')
            ->assertSee('libera na hora, sem renovação')
            ->assertSee('Gerar QR Code Pix')
            ->assertDontSee('ecomendado')
            ->set('meio', 'cartao')
            ->assertSee('Pagar com cartão')
            ->assertDontSee('Gerar QR Code Pix')
            ->assertSee('Compra única, não renova');
    });

    it('um meio inválido volta ao Pix', function () {
        Livewire::actingAs(_compradorConfirmado())->test(ComprarCreditos::class, ['pacote' => _pacote()->id])
            ->set('meio', 'boleto')->assertSet('meio', 'pix');
    });

    it('?meio=cartao abre já no cartão', function () {
        $this->actingAs(_compradorConfirmado())->get(route('creditos.comprar', ['pacote' => _pacote()->id, 'meio' => 'cartao']))
            ->assertOk()->assertSee('Pagar com cartão');
    });

    it('trocar o meio mantém o aceite marcado', function () {
        Livewire::actingAs(_compradorConfirmado())->test(ComprarCreditos::class, ['pacote' => _pacote()->id])
            ->set('aceito', true)->set('meio', 'cartao')->assertSet('aceito', true);
    });

    it('com cartão e aceite, abre o checkout do cartão e leva ao provedor', function () {
        $usuario = _compradorConfirmado();

        Livewire::actingAs($usuario)->test(ComprarCreditos::class, ['pacote' => _pacote()->id])
            ->set('meio', 'cartao')->set('aceito', true)->call('gerar')
            ->assertRedirect(Invoice::query()->sole()->checkout_url);

        expect(Invoice::query()->sole()->meio)->toBe('cartao');
    });

    it('com cartão, sem aceite, não gera nada', function () {
        $usuario = _compradorConfirmado();

        Livewire::actingAs($usuario)->test(ComprarCreditos::class, ['pacote' => _pacote()->id])
            ->set('meio', 'cartao')->call('gerar')->assertHasErrors(['aceito']);

        expect(Invoice::query()->count())->toBe(0);
    });

    it('falha do provedor no cartão usa a mensagem do cartão e deixa tentar de novo', function () {
        FakePaymentProvider::falharProximoCheckout();

        Livewire::actingAs(_compradorConfirmado())->test(ComprarCreditos::class, ['pacote' => _pacote()->id])
            ->set('meio', 'cartao')->set('aceito', true)->call('gerar')
            ->assertSee('Não conseguimos abrir o pagamento agora. Nada foi cobrado.')
            ->call('gerar')->assertRedirect();
    });

    it('avisa do QR Code Pix em aberto ao escolher cartão, e só então', function () {
        $usuario = _compradorConfirmado();
        $pix = app(IniciarCompraDeCreditos::class)->executar($usuario, _pacote(), null, null);

        $c = Livewire::actingAs($usuario)->test(ComprarCreditos::class, ['pacote' => _pacote()->id])
            ->assertDontSee('QR Code Pix em aberto')
            ->set('meio', 'cartao')
            ->assertSee('Você tem um QR Code Pix em aberto, válido até '.$pix->checkout_expira_em->setTimezone('America/Sao_Paulo')->format('H:i'))
            ->assertSee('Se pagar com cartão, ignore-o.');

        $pix->forceFill(['checkout_expira_em' => now()->subMinute()])->save();
        $c->call('$refresh')->assertDontSee('QR Code Pix em aberto');

        $pix->forceFill(['status' => EstadoCobranca::Paga, 'checkout_expira_em' => now()->addMinutes(20)])->save();
        $c->call('$refresh')->assertDontSee('QR Code Pix em aberto');
    });

    it('com a cobrança desligada o cartão também é 404 (CA-6)', function () {
        $usuario = Usuario::factory()->create();
        CobrancaConfig::atual()->update(['ativa' => false]);

        $this->actingAs($usuario)->get(route('creditos.comprar', ['pacote' => _pacote()->id, 'meio' => 'cartao']))->assertNotFound();
    });
});

it('STORY-098 — pacote de 1 análise fica no singular na tela e na descrição da cobrança', function () {
    CobrancaConfig::atual()->update(['ativa' => true]);
    _usarProvedor('fake');
    $u = _compradorConfirmado();
    $pacote = _pacote();
    $pacote->forceFill(['quantidade_creditos' => 1])->save();

    Livewire::actingAs($u)->test(ComprarCreditos::class, ['pacote' => $pacote->id])
        ->assertSee('1 análise')
        ->assertDontSee('1 análises');

    $invoice = app(IniciarCompraDeCreditos::class)->executar($u, $pacote->fresh(), '127.0.0.1', 'teste', MeioPagamento::Pix, null);

    expect($invoice->descricao)->toEndWith('· 1 análise');
});

it('marcar o aceite tira o erro na hora, e desmarcar não o traz de volta sem novo clique', function () {
    CobrancaConfig::atual()->update(['ativa' => true]);
    _usarProvedor('fake');

    Livewire::actingAs(_compradorConfirmado())->test(ComprarCreditos::class, ['pacote' => _pacote()->id])
        ->call('gerar')
        ->assertHasErrors('aceito')
        ->set('aceito', true)
        ->assertHasNoErrors('aceito')
        ->assertDontSee('Marque o aceite do Termo para continuar.');
});

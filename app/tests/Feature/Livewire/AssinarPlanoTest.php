<?php

declare(strict_types=1);

use App\Domain\Cobranca\Carteira;
use App\Domain\Cobranca\EstadoCobranca;
use App\Infrastructure\Cobranca\Fake\FakePaymentProvider;
use App\Jobs\EnviarEmailConfirmacao;
use App\Livewire\Cobranca\AcompanharAssinatura;
use App\Livewire\Cobranca\AssinarPlano;
use App\Models\Assinatura;
use App\Models\CobrancaConfig;
use App\Models\Invoice;
use App\Models\Usuario;
use App\Services\Cobranca\IniciarCompraDeCreditos;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

/**
 * STORY-077 CA-1, CA-3, CA-7, CA-9, CA-11 — checkout de assinatura e tela de retorno
 * (spec STORY-070-checkout-assinatura, no formato hospedado do ADR-010: sem campos de cartão).
 */
beforeEach(function () {
    _usarProvedor('fake');
    Mail::fake();
});

function _urlAssinar(?string $ciclo = 'mensal'): string
{
    return route('checkout.assinatura', ['plano' => _plano()->id, 'ciclo' => $ciclo]);
}

describe('acesso', function () {
    it('com a cobrança desligada as duas rotas são 404 para o usuário comum e nada é criado (CA-11)', function () {
        $usuario = Usuario::factory()->create();
        CobrancaConfig::atual()->update(['ativa' => false]);

        $this->actingAs($usuario)->get(_urlAssinar())->assertNotFound();
        $this->actingAs($usuario)->get(route('assinatura.retorno', '00000000-0000-4000-8000-000000000000'))->assertNotFound();
        expect(Invoice::query()->count())->toBe(0);
    });

    it('exige login', function () {
        $this->get(_urlAssinar())->assertRedirect(route('login'));
    });

    it('a rota só aceita os ciclos mensal e anual', function () {
        $this->actingAs(_compradorConfirmado())->get(route('checkout.assinatura', ['plano' => _plano()->id, 'ciclo' => 'semanal']))->assertNotFound();
    });
});

describe('CA-1 resumo', function () {
    it('mostra plano, ciclo, valor, próxima cobrança e renovação automática, sem campos de cartão', function () {
        $plano = _plano();

        $this->actingAs(_compradorConfirmado())->get(_urlAssinar())->assertOk()
            ->assertSee('Assinar plano '.$plano->nome)
            ->assertSee('R$ '.number_format($plano->preco_mensal_centavos / 100, 2, ',', '.'))
            ->assertSee('Mensal, renova sozinho no cartão')
            ->assertSee(now()->addMonthNoOverflow()->setTimezone('America/Sao_Paulo')->format('d/m/Y'))
            ->assertSee('Termo de Adesão')
            ->assertSee('Cancele quando quiser em Meu plano')
            ->assertDontSee('screen-checkout-cartao-numero', false)
            ->assertDontSee('autocomplete="cc-', false);
    });

    it('no anual mostra o valor por ano, a renovação a cada 12 meses e a economia', function () {
        $plano = _plano();

        $this->actingAs(_compradorConfirmado())->get(_urlAssinar('anual'))->assertOk()
            ->assertSee('R$ '.number_format($plano->preco_anual_centavos / 100, 2, ',', '.'))
            ->assertSee('Anual, renova sozinho no cartão')
            ->assertSee(now()->addYear()->setTimezone('America/Sao_Paulo')->format('d/m/Y'))
            ->assertSee('por ano')
            ->assertDontSee('Pix');
    });

    it('trocar o ciclo na tela atualiza o resumo', function () {
        $plano = _plano();

        Livewire::actingAs(_compradorConfirmado())->test(AssinarPlano::class, ['plano' => $plano->id, 'ciclo' => 'mensal'])
            ->set('ciclo', 'anual')
            ->assertSee('Anual, renova sozinho no cartão')
            ->set('ciclo', 'lixo')
            ->assertSee('Mensal, renova sozinho no cartão');
    });

    it('ciclo desconhecido na montagem cai no mensal', function () {
        Livewire::actingAs(_compradorConfirmado())->test(AssinarPlano::class, ['plano' => _plano()->id, 'ciclo' => 'x'])->assertSet('ciclo', 'mensal');
    });

    it('sem o aceite não abre o checkout', function () {
        Livewire::actingAs(_compradorConfirmado())->test(AssinarPlano::class, ['plano' => _plano()->id, 'ciclo' => 'mensal'])
            ->call('assinar')->assertHasErrors('aceito')->assertNoRedirect();

        expect(Invoice::query()->count())->toBe(0);
    });

    it('com o aceite abre o checkout recorrente e leva o usuário à página do provedor', function () {
        $usuario = _compradorConfirmado();

        $c = Livewire::actingAs($usuario)->test(AssinarPlano::class, ['plano' => _plano()->id, 'ciclo' => 'anual'])
            ->set('aceito', true)->call('assinar');

        $invoice = Invoice::query()->sole();
        $c->assertRedirect($invoice->checkout_url);
        expect($invoice->ciclo)->toBe('anual')->and($invoice->usuario_id)->toBe($usuario->id)->and(Assinatura::query()->count())->toBe(0);
    });
});

describe('estados', function () {
    it('plano desativado ou inexistente: "não está mais disponível"', function () {
        $plano = _plano();
        $plano->update(['ativo' => false]);

        $this->actingAs(_compradorConfirmado())->get(_urlAssinar())->assertOk()->assertSee('Este plano não está mais disponível.')->assertSee('Ver os planos');
        $this->actingAs(_compradorConfirmado())->get(route('checkout.assinatura', ['plano' => '00000000-0000-4000-8000-000000000000', 'ciclo' => 'mensal']))
            ->assertOk()->assertSee('Este plano não está mais disponível.');
    });

    it('e-mail não confirmado vê o bloqueio no lugar do formulário e pode reenviar o link', function () {
        Bus::fake();
        $usuario = Usuario::factory()->unconfirmed()->create();
        CobrancaConfig::atual()->update(['ativa' => true]);

        $this->actingAs($usuario)->get(_urlAssinar())->assertOk()->assertSee('Confirme seu e-mail para assinar.')->assertDontSee('screen-checkout-primary-cta', false);

        Livewire::actingAs($usuario)->test(AssinarPlano::class, ['plano' => _plano()->id, 'ciclo' => 'mensal'])
            ->call('reenviarConfirmacao')->assertSee('Enviamos um novo link');
        Bus::assertDispatched(EnviarEmailConfirmacao::class);
    });

    it('reenviar não faz nada para quem já confirmou', function () {
        Bus::fake();

        Livewire::actingAs(_compradorConfirmado())->test(AssinarPlano::class, ['plano' => _plano()->id, 'ciclo' => 'mensal'])->call('reenviarConfirmacao');
        Bus::assertNotDispatched(EnviarEmailConfirmacao::class);
    });

    it('quem já tem assinatura vê o aviso e nenhum formulário, e a chamada direta não cria checkout (CA-7)', function () {
        $usuario = _compradorConfirmado();
        _pagarAssinatura(_assinaturaPendente($usuario));
        $antes = Invoice::query()->count();

        $this->actingAs($usuario)->get(_urlAssinar('anual'))->assertOk()
            ->assertSee('Você já tem o plano '._plano()->nome.', que renova em')
            ->assertDontSee('screen-checkout-primary-cta', false);

        Livewire::actingAs($usuario)->test(AssinarPlano::class, ['plano' => _plano()->id, 'ciclo' => 'anual'])
            ->set('aceito', true)->call('assinar')->assertNoRedirect();
        expect(Invoice::query()->count())->toBe($antes);
    });

    it('quem está no teste vê o aviso de que o teste termina', function () {
        $usuario = _compradorConfirmado();
        app(Carteira::class)->concederCota($usuario->id, 1, now(), now()->addDays(7), null, null, true);

        $this->actingAs($usuario)->get(_urlAssinar())->assertOk()
            ->assertSee('Ao assinar, seu teste termina. A análise que sobrou do teste não soma à cota do plano.');
    });

    it('falha do provedor diz que não é problema do cartão e deixa tentar de novo', function () {
        FakePaymentProvider::falharProximoCheckout();

        $c = Livewire::actingAs(_compradorConfirmado())->test(AssinarPlano::class, ['plano' => _plano()->id, 'ciclo' => 'mensal'])
            ->set('aceito', true)->call('assinar')
            ->assertSee('Não conseguimos falar com o processador de pagamento agora. Não é problema com o seu cartão.')
            ->assertNoRedirect();

        $c->call('assinar')->assertRedirect();
    });

    it('"Logar como": o botão fica desabilitado e o aviso aparece (CA-9)', function () {
        $admin = _adminDaEquipe();
        $alvo = _compradorConfirmado();

        $this->withSession(_sessaoDePersonificacao($admin, $alvo))->actingAs($alvo)->get(_urlAssinar())->assertOk()
            ->assertSee('Contratar e pagar não está disponível nesse modo.')
            ->assertSee('aria-disabled="true"', false);

        // A recusa no serviço (IniciarAssinatura) está em AssinaturaComCartaoTest, CA-9.
        expect(Invoice::query()->count())->toBe(0);
    });
});

describe('retorno do checkout', function () {
    it('antes da confirmação mostra "Estamos confirmando" e não ativa nada', function () {
        $usuario = _compradorConfirmado();
        $invoice = _assinaturaPendente($usuario);

        $this->actingAs($usuario)->get(route('assinatura.retorno', $invoice))->assertOk()
            ->assertSee('Estamos confirmando seu pagamento')->assertSee('wire:poll', false);
        expect(Assinatura::query()->count())->toBe(0);
    });

    it('depois do pagamento mostra a assinatura ativa, a cota e a próxima cobrança com o cartão final', function () {
        $usuario = _compradorConfirmado();
        $invoice = _pagarAssinatura(_assinaturaPendente($usuario));
        $a = Assinatura::query()->sole();

        $this->actingAs($usuario)->get(route('assinatura.retorno', $invoice))->assertOk()
            ->assertSee('Assinatura ativa')
            ->assertSee('Você tem '.$a->cota_restante_ciclo)
            ->assertSee('A próxima cobrança é em '.$a->proximo_vencimento->setTimezone('America/Sao_Paulo')->format('d/m/Y'))
            ->assertSee('Visa final 4242')
            ->assertDontSee('wire:poll', false);
    });

    it('cartão recusado: diz que foi recusado em linguagem simples, que segue sem assinatura, e oferece nova tentativa (CA-3)', function () {
        $usuario = _compradorConfirmado();
        $invoice = _assinaturaPendente($usuario);
        _entregar(_fake()->simularRecusa((string) $invoice->checkout_id_externo));

        $this->actingAs($usuario)->get(route('assinatura.retorno', $invoice))->assertOk()
            ->assertSee('Cartão recusado')
            ->assertSee('O banco não autorizou o pagamento. Tente outro cartão.')
            ->assertSee('Você continua sem assinatura.')
            ->assertSee('Tentar de novo')
            ->assertSee(route('checkout.assinatura', ['plano' => $invoice->plano_id, 'ciclo' => 'mensal']), false);
    });

    it('prazo vencido: nada foi cobrado e dá para tentar de novo', function () {
        $usuario = _compradorConfirmado();
        $invoice = _assinaturaPendente($usuario);
        _entregar(_fake()->simularExpiracao((string) $invoice->checkout_id_externo));

        $this->actingAs($usuario)->get(route('assinatura.retorno', $invoice))->assertOk()
            ->assertSee('nada foi cobrado')->assertSee('Você continua sem assinatura.');
    });

    it('checkout cancelado ou que falhou ao abrir: assinatura não concluída', function () {
        $usuario = _compradorConfirmado();
        $invoice = _assinaturaPendente($usuario);
        _entregar(_fake()->simularCancelamento((string) $invoice->checkout_id_externo));

        $this->actingAs($usuario)->get(route('assinatura.retorno', $invoice))->assertOk()->assertSee('Assinatura não concluída')->assertSee('Nada foi cobrado.');

        $falhou = Invoice::query()->create([
            'usuario_id' => $usuario->id, 'tipo' => 'assinatura', 'plano_id' => _plano()->id, 'ciclo' => 'mensal', 'cota_snapshot' => 1,
            'descricao' => 'x', 'valor_centavos' => 100, 'status' => EstadoCobranca::Falhou, 'meio' => 'cartao', 'provedor' => 'fake', 'motivo_encerramento' => 'provedor',
        ]);
        $this->actingAs($usuario)->get(route('assinatura.retorno', $falhou))->assertOk()->assertSee('Assinatura não concluída');
    });

    it('pago em duplicidade (já havia assinatura): explica que não abriu outra', function () {
        $usuario = _compradorConfirmado();
        $a = _assinaturaPendente($usuario);
        $b = _assinaturaPendente($usuario, _plano('Pro'));
        _pagarAssinatura($a);
        _pagarAssinatura($b);

        $this->actingAs($usuario)->get(route('assinatura.retorno', $b))->assertOk()->assertSee('Recebemos o pagamento')->assertSee('Você já tem uma assinatura ativa');
    });

    it('a compra de outro usuário, de créditos ou a renovação respondem 404', function () {
        $dono = _compradorConfirmado();
        $invoice = _pagarAssinatura(_assinaturaPendente($dono));
        $outro = Usuario::factory()->create(['email_confirmed_at' => now()]);
        $renovacao = Invoice::query()->create([
            'usuario_id' => $dono->id, 'tipo' => 'assinatura', 'renovacao' => true, 'descricao' => 'r', 'valor_centavos' => 100,
            'status' => EstadoCobranca::Paga, 'meio' => 'cartao', 'provedor' => 'fake',
        ]);
        $credito = _compraPendenteAsaasFake($dono);

        $this->actingAs($outro)->get(route('assinatura.retorno', $invoice))->assertNotFound();
        $this->actingAs($dono)->get(route('assinatura.retorno', $renovacao))->assertNotFound();
        $this->actingAs($dono)->get(route('assinatura.retorno', $credito))->assertNotFound();
        expect(Livewire::actingAs($dono)->test(AcompanharAssinatura::class, ['invoice' => $invoice->id])->get('invoiceId'))->toBe($invoice->id);
    });
});

function _compraPendenteAsaasFake(Usuario $u): Invoice
{
    return app(IniciarCompraDeCreditos::class)->executar($u, _pacote(), null, null);
}

it('o Pix e o cartão passam pela mesma página fake e voltam à tela certa', function () {
    $usuario = _compradorConfirmado();
    $invoice = _assinaturaPendente($usuario);

    $this->actingAs($usuario)->get(route('pagamentos.fake', ['invoice' => $invoice]))->assertOk()
        ->assertSee('Simular cartão aprovado')->assertSee('Simular cartão recusado');

    $this->actingAs($usuario)->post(route('pagamentos.fake.simular', $invoice), ['acao' => 'recusar'])->assertRedirect(route('assinatura.retorno', $invoice));
    expect($invoice->fresh()->status)->toBe(EstadoCobranca::Falhou);

    $credito = _compraPendenteAsaasFake($usuario);
    $this->actingAs($usuario)->post(route('pagamentos.fake.simular', $credito), ['acao' => 'recusar'])->assertStatus(422);
    $this->actingAs($usuario)->post(route('pagamentos.fake.simular', $credito), ['acao' => 'pagar'])->assertRedirect(route('creditos.retorno', $credito));
});

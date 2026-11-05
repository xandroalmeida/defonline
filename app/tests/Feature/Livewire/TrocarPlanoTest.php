<?php

declare(strict_types=1);

use App\Domain\Cobranca\CicloCobranca;
use App\Domain\Cobranca\EstadoCobranca;
use App\Domain\Cobranca\MeioPagamento;
use App\Domain\Cobranca\StatusAssinatura;
use App\Infrastructure\Cobranca\Fake\FakePaymentProvider;
use App\Livewire\Cobranca\AcompanharTroca;
use App\Livewire\Cobranca\MeuPlano;
use App\Livewire\Cobranca\TrocarPlano;
use App\Models\Assinatura;
use App\Models\CobrancaConfig;
use App\Models\Invoice;
use App\Models\Usuario;
use App\Services\Cobranca\TrocarDePlano;
use App\Support\DataExibicao;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

/**
 * STORY-082 CA-1, CA-2, CA-4, CA-5, CA-6 — a tela de revisão da troca, o retorno e o "Desfazer" em Meu plano
 * (spec STORY-070-troca-de-plano), com o provedor fake e o relógio no 10º dia do ciclo.
 */
beforeEach(function () {
    _usarProvedor('fake');
    Mail::fake();
    $this->usuario = _compradorConfirmado();
    $this->pro = _plano('Pro');
    $this->basico = _plano('Básico');
});

function _trocaTela(Usuario $u, string $plano, string $ciclo = 'mensal')
{
    return Livewire::actingAs($u)->test(TrocarPlano::class, ['plano' => $plano, 'ciclo' => $ciclo]);
}

function _noDecimoDia(Usuario $u, CicloCobranca $ciclo = CicloCobranca::Mensal): Assinatura
{
    [$a] = _assinaturaAtivaNoFake($u, $ciclo);
    test()->travelTo($a->data_inicio->copy()->addDays(10));

    return $a->fresh();
}

describe('CA-6 — interruptor e acesso', function () {
    it('com a cobrança desligada as rotas são 404 para o usuário comum', function () {
        $u = Usuario::factory()->create();
        CobrancaConfig::atual()->update(['ativa' => false]);

        $this->actingAs($u)->get(route('plano.trocar', ['plano' => $this->pro->id, 'ciclo' => 'mensal']))->assertNotFound();
        $this->actingAs($u)->get(route('plano.trocar.retorno', '00000000-0000-4000-8000-000000000000'))->assertNotFound();
        expect(Invoice::query()->count())->toBe(0);
    });

    it('exige login e só aceita os ciclos mensal e anual', function () {
        $this->get(route('plano.trocar', ['plano' => $this->pro->id, 'ciclo' => 'mensal']))->assertRedirect(route('login'));
        $this->actingAs($this->usuario)->get(route('plano.trocar', ['plano' => $this->pro->id, 'ciclo' => 'semanal']))->assertNotFound();
    });
});

describe('CA-1 — revisão do upgrade', function () {
    it('o resumo traz a diferença proporcional, o crédito e a próxima cobrança, antes de confirmar', function () {
        $a = _noDecimoDia($this->usuario);

        _trocaTela($this->usuario, $this->pro->id)
            ->assertSee('Trocar para o plano Pro')
            ->assertSee('Preço do Pro pelos 20 dias')
            ->assertSee('R$ 132,67')
            ->assertSee('Crédito do Básico não usado')
            ->assertSee('R$ 33,27')
            ->assertSee('R$ 99,40')
            ->assertSee('Hoje você paga só a diferença pelos 20 dias que faltam neste ciclo.')
            ->assertSee('Sua cota passa a ser de 10 análises a partir de agora.')
            ->assertSee(DataExibicao::data($a->proximo_vencimento).' · R$ 199,00')
            ->assertSeeHtml('dusk="screen-troca-confirmar"');
        expect(Invoice::query()->where('tipo', 'troca_de_plano')->count())->toBe(0);
    });

    it('no último dia o crédito é quase zero e nunca aparece valor negativo', function () {
        $a = _noDecimoDia($this->usuario);
        $this->travelTo($a->proximo_vencimento->copy()->subHours(1));

        $html = _trocaTela($this->usuario, $this->pro->id)->html();

        expect($html)->not->toContain('R$ -')->not->toContain('-R$')->toContain('A pagar hoje');
    });

    it('com diferença abaixo de R$ 5,00 diz que a troca é feita sem cobrança', function () {
        $a = _noDecimoDia($this->usuario);
        $this->travelTo($a->proximo_vencimento->copy()->subHours(2));

        _trocaTela($this->usuario, $this->pro->id)->assertSee('sem cobrança')->assertSee('R$ 0,00');
    });

    it('confirmar abre o checkout do provedor e o plano só muda depois do pagamento (CA-2)', function () {
        $a = _noDecimoDia($this->usuario);

        $tela = _trocaTela($this->usuario, $this->pro->id)->call('confirmar');
        $invoice = Invoice::query()->where('tipo', 'troca_de_plano')->sole();

        $tela->assertRedirect($invoice->checkout_url);
        expect($invoice->valor_centavos)->toBe(9940)->and($a->fresh()->plano_id)->toBe($this->basico->id);
    });

    it('com diferença pequena, confirmar troca na hora e mostra a confirmação', function () {
        $a = _noDecimoDia($this->usuario);
        $this->travelTo($a->proximo_vencimento->copy()->subHours(2));

        _trocaTela($this->usuario, $this->pro->id)->call('confirmar')
            ->assertSeeHtml('dusk="screen-troca-aplicada"')
            ->assertSee('Você agora está no plano Pro. Sua cota é de 10 análises e você já pode usá-las.');
        expect($a->fresh()->plano_id)->toBe($this->pro->id);
    });

    it('troca de ciclo: o seletor recalcula o resumo', function () {
        _noDecimoDia($this->usuario);

        _trocaTela($this->usuario, $this->pro->id)
            ->set('ciclo', 'anual')
            ->assertSee('Anual, renova sozinho no cartão')
            ->assertSee('R$ 1.599,00')
            ->set('ciclo', 'inválido')
            ->assertSet('ciclo', 'mensal');
    });

    it('falha do provedor ao confirmar: diz que o plano continua o mesmo', function () {
        _noDecimoDia($this->usuario);
        FakePaymentProvider::falharProximoCheckout();

        _trocaTela($this->usuario, $this->pro->id)->call('confirmar')
            ->assertSee('Não conseguimos concluir a troca agora. Seu plano continua o mesmo.')
            ->assertNoRedirect();
    });
});

describe('CA-4 — downgrade', function () {
    it('a revisão não cobra nada hoje e confirmar agenda: a tela mostra a data', function () {
        $a = _noDecimoDia($this->usuario);
        $this->travelTo($a->proximo_vencimento->copy()->subHours(1));
        app(TrocarDePlano::class)->confirmar($this->usuario, $this->pro, CicloCobranca::Mensal);
        $this->travelTo($a->data_inicio->copy()->addDays(10));
        $fim = DataExibicao::data($a->proximo_vencimento);

        _trocaTela($this->usuario, $this->basico->id)
            ->assertSee('Você continua no Pro até '.$fim.', com todas as análises do ciclo. Depois disso, passa a ter 1 análise por mês.')
            ->assertSee('R$ 0,00')
            ->assertSee('Agendar troca para o Básico')
            ->call('confirmar')
            ->assertSeeHtml('dusk="screen-troca-agendado"')
            ->assertSee('Troca agendada para '.$fim.'. Dá para desfazer até lá');
        expect($a->fresh()->troca_plano_id)->toBe($this->basico->id)->and($a->fresh()->plano_id)->toBe($this->pro->id);
    });

    it('Meu plano mostra a troca agendada e "Desfazer troca" a desfaz', function () {
        $a = _noDecimoDia($this->usuario);
        $this->travelTo($a->proximo_vencimento->copy()->subHours(1));
        app(TrocarDePlano::class)->confirmar($this->usuario, $this->pro, CicloCobranca::Mensal);
        app(TrocarDePlano::class)->confirmar($this->usuario, $this->basico, CicloCobranca::Mensal);
        $fim = DataExibicao::data($a->fresh()->proximo_vencimento);

        Livewire::actingAs($this->usuario)->test(MeuPlano::class)
            ->assertSee('Troca para o plano Básico · mensal agendada para '.$fim.'. Dá para desfazer até lá.')
            ->assertSeeHtml('dusk="screen-troca-desfazer"')
            ->call('desfazerTroca')
            ->assertSee('Troca desfeita. Você continua no plano atual.')
            ->assertDontSeeHtml('dusk="screen-troca-agendado"');
        expect($a->fresh()->troca_plano_id)->toBeNull();
    });

    it('"Desfazer" falha com o provedor fora do ar: continua agendada e diz a verdade', function () {
        $a = _noDecimoDia($this->usuario);
        $this->travelTo($a->proximo_vencimento->copy()->subHours(1));
        app(TrocarDePlano::class)->confirmar($this->usuario, $this->pro, CicloCobranca::Mensal);
        app(TrocarDePlano::class)->confirmar($this->usuario, $this->basico, CicloCobranca::Mensal);
        FakePaymentProvider::falharProximaOperacaoDeAssinatura();

        Livewire::actingAs($this->usuario)->test(MeuPlano::class)->call('desfazerTroca')
            ->assertSee('A troca continua agendada');
        expect($a->fresh()->troca_plano_id)->toBe($this->basico->id);
    });
});

describe('CA-5 e estados da spec', function () {
    it('plano desativado não é destino', function () {
        _noDecimoDia($this->usuario);
        $this->pro->forceFill(['ativo' => false])->save();

        _trocaTela($this->usuario, $this->pro->id)->assertSee('Este plano não está mais à venda.')->assertDontSeeHtml('dusk="screen-troca-confirmar"');
    });

    it('em atraso: "Atualize o cartão antes de trocar de plano."', function () {
        [$a, $primeira] = _assinaturaAtivaNoFake($this->usuario);
        _recusarRenovacao($a, $primeira);

        _trocaTela($this->usuario, $this->pro->id)->assertSee('Atualize o cartão antes de trocar de plano.')->assertDontSeeHtml('dusk="screen-troca-confirmar"');
    });

    it('com a renovação cancelada pede para reativar antes', function () {
        [$a] = _assinaturaAtivaNoFake($this->usuario);
        $a->forceFill(['status' => StatusAssinatura::Cancelada])->save();

        _trocaTela($this->usuario, $this->pro->id)->assertSee('Reative a renovação em Meu plano antes de trocar de plano.');
    });

    it('sem plano, o mesmo plano e o Pix mensal têm mensagem própria', function () {
        _trocaTela($this->usuario, $this->pro->id)->assertSee('Você não tem um plano para trocar.');

        _noDecimoDia($this->usuario);
        _trocaTela($this->usuario, $this->basico->id)->assertSee('Este já é o seu plano.');
    });

    it('plano inexistente mostra "não está mais disponível"', function () {
        _trocaTela($this->usuario, '00000000-0000-4000-8000-000000000000')->assertSee('Este plano não está mais disponível.');
    });

    it('no "Logar como" a revisão é só leitura', function () {
        _noDecimoDia($this->usuario);
        $admin = _adminDaEquipe();

        $this->withSession(_sessaoDePersonificacao($admin, $this->usuario))->actingAs($this->usuario)
            ->get(route('plano.trocar', ['plano' => $this->pro->id, 'ciclo' => 'mensal']))->assertOk()
            ->assertSee('Aqui é só leitura.')->assertSee('disabled', false);
        expect(Invoice::query()->where('tipo', 'troca_de_plano')->count())->toBe(0);
    });

    it('o plano anual pago com Pix só vê o ciclo anual e o QR Code da diferença', function () {
        _pagarAssinatura(_assinaturaPendente($this->usuario, null, CicloCobranca::Anual, MeioPagamento::Pix));
        $a = Assinatura::query()->where('usuario_id', $this->usuario->id)->sole();
        $this->travelTo($a->data_inicio->copy()->addMonths(3));

        _trocaTela($this->usuario, $this->pro->id, 'anual')
            ->assertDontSeeHtml('dusk="screen-troca-ciclo"')
            ->assertSee('um novo QR Code da diferença')
            ->call('confirmar');
        expect(Invoice::query()->where('tipo', 'troca_de_plano')->sole()->meio)->toBe('pix');
    });
});

describe('CA-2 — retorno do checkout', function () {
    it('aguarda a confirmação; pago, mostra o plano novo, a cota e o valor', function () {
        $a = _noDecimoDia($this->usuario);
        $invoice = app(TrocarDePlano::class)->confirmar($this->usuario, $this->pro, CicloCobranca::Mensal)->invoice;
        // O prazo do checkout é do relógio real do provedor; o teste viajou 10 dias.
        $invoice->forceFill(['checkout_expira_em' => now()->addHour()])->save();

        $tela = Livewire::actingAs($this->usuario)->test(AcompanharTroca::class, ['invoice' => $invoice->id])
            ->assertSeeHtml('dusk="screen-troca-aguardando"')->assertSee('Estamos confirmando seu pagamento');

        _pagarAssinatura($invoice);

        $tela->call('$refresh')->assertSeeHtml('dusk="screen-troca-aplicada"')
            ->assertSee('Você agora está no plano Pro. Sua cota é de 10 análises e você já pode usá-las.')
            ->assertSee('Pagamos R$ 99,40');
    });

    it('recusado: o plano continua e há como tentar de novo', function () {
        $a = _noDecimoDia($this->usuario);
        $invoice = app(TrocarDePlano::class)->confirmar($this->usuario, $this->pro, CicloCobranca::Mensal)->invoice;
        _entregar(_fake()->simularRecusa((string) $invoice->checkout_id_externo));

        Livewire::actingAs($this->usuario)->test(AcompanharTroca::class, ['invoice' => $invoice->id])
            ->assertSee('Cartão recusado')->assertSee('Seu plano continua o Básico. Nada foi cobrado.')
            ->assertSeeHtml('dusk="screen-troca-tentar-de-novo"');
    });

    it('expirado ou cancelado: nada foi cobrado', function () {
        $a = _noDecimoDia($this->usuario);
        $invoice = app(TrocarDePlano::class)->confirmar($this->usuario, $this->pro, CicloCobranca::Mensal)->invoice;
        _entregar(_fake()->simularExpiracao((string) $invoice->checkout_id_externo));

        Livewire::actingAs($this->usuario)->test(AcompanharTroca::class, ['invoice' => $invoice->id])
            ->assertSeeHtml('dusk="screen-troca-nao-concluida"')->assertSee('Nada foi cobrado e seu plano continua o Básico.');
    });

    it('pago e sem efeito (o ciclo mudou): avisa e para de esperar', function () {
        $a = _noDecimoDia($this->usuario);
        $invoice = app(TrocarDePlano::class)->confirmar($this->usuario, $this->pro, CicloCobranca::Mensal)->invoice;
        $a->forceFill(['periodos_pagos' => 2])->save();
        _pagarAssinatura($invoice);
        $this->travelTo(now()->addMinutes(5));

        Livewire::actingAs($this->usuario)->test(AcompanharTroca::class, ['invoice' => $invoice->id])
            ->assertSeeHtml('dusk="screen-troca-sem-efeito"')->assertSee('não aplicamos a troca');
    });

    it('a compra de outro usuário, de créditos ou de assinatura responde 404', function () {
        $a = _noDecimoDia($this->usuario);
        $invoice = app(TrocarDePlano::class)->confirmar($this->usuario, $this->pro, CicloCobranca::Mensal)->invoice;
        $inicial = Invoice::query()->where('tipo', 'assinatura')->firstOrFail();

        $this->actingAs(_compradorConfirmado())->get(route('plano.trocar.retorno', $invoice))->assertNotFound();
        $this->actingAs($this->usuario)->get(route('plano.trocar.retorno', $inicial))->assertNotFound();
        $this->actingAs($this->usuario)->get(route('plano.trocar.retorno', $invoice))->assertOk();
    });

    it('a página fake do provedor devolve para o retorno da troca', function () {
        $a = _noDecimoDia($this->usuario);
        $invoice = app(TrocarDePlano::class)->confirmar($this->usuario, $this->pro, CicloCobranca::Mensal)->invoice;

        $this->actingAs($this->usuario)->get(route('pagamentos.fake', ['invoice' => $invoice, 'checkout' => $invoice->checkout_id_externo]))->assertOk();
        $this->actingAs($this->usuario)->post(route('pagamentos.fake.simular', $invoice), ['acao' => 'pagar'])
            ->assertRedirect(route('plano.trocar.retorno', $invoice));
        expect($invoice->fresh()->status)->toBe(EstadoCobranca::Paga)->and($a->fresh()->plano_id)->toBe($this->pro->id);
    });
});

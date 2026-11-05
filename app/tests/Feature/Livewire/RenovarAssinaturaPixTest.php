<?php

declare(strict_types=1);

use App\Domain\Cobranca\CicloCobranca;
use App\Domain\Cobranca\EstadoCobranca;
use App\Domain\Cobranca\MeioPagamento;
use App\Infrastructure\Cobranca\Fake\FakePaymentProvider;
use App\Jobs\EnviarEmailConfirmacao;
use App\Livewire\Cobranca\AcompanharAssinatura;
use App\Livewire\Cobranca\AssinarPlano;
use App\Livewire\Cobranca\RenovarAssinaturaPix;
use App\Models\Assinatura;
use App\Models\Invoice;
use App\Models\Usuario;
use App\Services\Cobranca\IniciarRenovacaoPix;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

/** STORY-080 — telas do Pix anual: seletor no checkout, renovação, retorno. */
beforeEach(function () {
    _usarProvedor('fake');
    Mail::fake();
    $this->usuario = _compradorConfirmado();
});

function _urlAnual(array $q = []): string
{
    return route('checkout.assinatura', ['plano' => _plano()->id, 'ciclo' => 'anual'] + $q);
}

describe('checkout (CA-1)', function () {
    it('no anual oferece cartão ou Pix, com o cartão selecionado', function () {
        $this->actingAs($this->usuario)->get(_urlAnual())->assertOk()
            ->assertSee('Como você quer pagar?')->assertSee('libera quando o banco confirmar, você paga a renovação', false)
            ->assertSee('Assinar e pagar');
    });

    it('no mensal só cartão: sem seletor, e ?meio=pix é ignorado', function () {
        $this->actingAs($this->usuario)->get(route('checkout.assinatura', ['plano' => _plano()->id, 'ciclo' => 'mensal', 'meio' => 'pix']))
            ->assertOk()->assertDontSee('Como você quer pagar?')->assertSee('Assinar e pagar')->assertDontSee('Gerar QR Code Pix');
    });

    it('com Pix o resumo diz que não renova sozinho e o botão gera o QR Code', function () {
        $plano = _plano();
        $c = Livewire::actingAs($this->usuario)->test(AssinarPlano::class, ['plano' => $plano->id, 'ciclo' => 'anual'])
            ->set('meio', 'pix')
            ->assertSee('Anual, não renova sozinho — avisamos 7 dias antes')
            ->assertSee('Não há cobrança automática. Renovação em '.now()->addYear()->setTimezone('America/Sao_Paulo')->format('d/m/Y'))
            ->assertSee('por um novo QR Code, se quiser continuar')
            ->assertSee('Gerar QR Code Pix · R$ '.number_format($plano->preco_anual_centavos / 100, 2, ',', '.'))
            ->assertSee('não há o que cancelar');

        $c->call('assinar')->assertHasErrors(['aceito']);
        $c->set('aceito', true)->call('assinar')->assertRedirect(Invoice::query()->sole()->checkout_url);
        expect(Invoice::query()->sole()->meio)->toBe('pix');
    });

    it('trocar para mensal volta ao cartão, e meio inválido também', function () {
        Livewire::actingAs($this->usuario)->test(AssinarPlano::class, ['plano' => _plano()->id, 'ciclo' => 'anual'])
            ->set('meio', 'pix')->set('ciclo', 'mensal')->assertSet('meio', 'cartao')
            ->set('ciclo', 'anual')->set('meio', 'boleto')->assertSet('meio', 'cartao');
    });

    it('falha do provedor no Pix usa o texto do QR Code', function () {
        FakePaymentProvider::falharProximoCheckout();

        Livewire::actingAs($this->usuario)->test(AssinarPlano::class, ['plano' => _plano()->id, 'ciclo' => 'anual'])
            ->set('meio', 'pix')->set('aceito', true)->call('assinar')
            ->assertSee('Não conseguimos gerar o QR Code agora. Nada foi cobrado.');
    });

    it('quem já tem plano Pix vê que termina (não renova) e, na janela, o botão de renovar', function () {
        $a = _assinaturaPixAtiva($this->usuario);

        $this->actingAs($this->usuario)->get(_urlAnual())->assertOk()
            ->assertSee('não renova sozinho')->assertDontSee('Pagar renovação');

        $this->travelTo($a->proximo_vencimento->copy()->subDays(2));
        $this->actingAs($this->usuario)->get(_urlAnual())->assertOk()->assertSee('Pagar renovação');
    });
});

describe('tela de renovação (CA-4, CA-5)', function () {
    it('sem plano Pix, avisa', function () {
        Livewire::actingAs($this->usuario)->test(RenovarAssinaturaPix::class)->assertSee('Você não tem um plano anual pago com Pix para renovar.');
    });

    it('fora da janela diz quando abre e não mostra o botão', function () {
        $a = _assinaturaPixAtiva($this->usuario);

        Livewire::actingAs($this->usuario)->test(RenovarAssinaturaPix::class)
            ->assertSee('Este plano não renova sozinho. Termina em '.$a->proximo_vencimento->setTimezone('America/Sao_Paulo')->format('d/m/Y'))
            ->assertDontSee('Pagar renovação');
    });

    it('na janela mostra o aviso, o novo ciclo e gera o QR da renovação', function () {
        $a = _assinaturaPixAtiva($this->usuario);
        $this->travelTo($a->proximo_vencimento->copy()->subDays(3));
        $fim = $a->proximo_vencimento->setTimezone('America/Sao_Paulo');

        $c = Livewire::actingAs($this->usuario)->test(RenovarAssinaturaPix::class)
            ->assertSee("Seu plano anual termina em {$fim->format('d/m/Y')}. Ele não renova sozinho.")
            ->assertSee('O novo ciclo vai até '.$fim->copy()->addYear()->format('d/m/Y').'.')
            ->assertSee('Pagar renovação · R$');

        $c->call('renovar')->assertHasErrors(['aceito']);
        $c->set('aceito', true)->call('renovar')->assertRedirect(Invoice::query()->where('renovacao', true)->sole()->checkout_url);
    });

    it('falha do provedor e e-mail não confirmado', function () {
        $a = _assinaturaPixAtiva($this->usuario);
        $this->travelTo($a->proximo_vencimento->copy()->subDays(1));
        FakePaymentProvider::falharProximoCheckout();

        Livewire::actingAs($this->usuario)->test(RenovarAssinaturaPix::class)->set('aceito', true)->call('renovar')
            ->assertSee('Não conseguimos gerar o QR Code agora. Nada foi cobrado.');

        Bus::fake();
        $sem = Usuario::query()->find($this->usuario->id);
        $sem->forceFill(['email_confirmed_at' => null])->save();
        $c = Livewire::actingAs($sem->fresh())->test(RenovarAssinaturaPix::class)->assertSee('Confirme seu e-mail para renovar.');
        foreach (range(1, 4) as $_) {
            $c->call('reenviarConfirmacao');
        }
        Bus::assertDispatchedTimes(EnviarEmailConfirmacao::class, 3);
        $c->set('aceito', true)->call('renovar')->assertNoRedirect();
        $sem->forceFill(['email_confirmed_at' => now()])->save();
        Livewire::actingAs($this->usuario->fresh())->test(RenovarAssinaturaPix::class)->call('reenviarConfirmacao');
    });

    it('depois do fim mostra Encerrado, com o histórico preservado e os caminhos para voltar', function () {
        $a = _assinaturaPixAtiva($this->usuario);
        $this->travelTo($a->proximo_vencimento->copy()->addDay());
        $this->artisan('assinaturas:pix-anual')->assertSuccessful();

        $this->actingAs($this->usuario)->get(route('assinatura.renovar'))->assertOk()
            ->assertSee('Seu plano terminou em '.$a->proximo_vencimento->setTimezone('America/Sao_Paulo')->format('d/m/Y').'. Seus diagnósticos continuam disponíveis.')
            ->assertSee('Renovar com Pix')->assertSee('Assinar com cartão')
            ->assertSee('meio=pix', false);
    });

    it('o admin em "Logar como" vê o botão desabilitado', function () {
        $a = _assinaturaPixAtiva($this->usuario);
        $this->travelTo($a->proximo_vencimento->copy()->subDays(1));

        $this->withSession(_sessaoDePersonificacao(_adminDaEquipe(), $this->usuario))->actingAs($this->usuario)
            ->get(route('assinatura.renovar'))->assertOk()
            ->assertSee('Você está acessando como administrador.')->assertSeeInOrder(['aria-disabled="true"', 'Pagar renovação'], false);
    });
});

describe('retorno do Pix (CA-2, CA-3)', function () {
    it('aguarda o banco, e o retorno por si só não ativa', function () {
        $invoice = _assinaturaPendente($this->usuario, null, CicloCobranca::Anual, MeioPagamento::Pix);

        $this->actingAs($this->usuario)->get(route('assinatura.retorno', $invoice))->assertOk()
            ->assertSee('Pague o plano anual')->assertSee('O plano começa quando o banco confirmar o pagamento.')
            ->assertSee('Abrir o QR Code Pix')->assertSee('Aguardando o pagamento…');
        expect(Assinatura::query()->count())->toBe(0);
    });

    it('confirmado: plano anual ativo e que não renova sozinho', function () {
        $invoice = _assinaturaPendente($this->usuario, null, CicloCobranca::Anual, MeioPagamento::Pix);
        _pagarAssinatura($invoice);

        Livewire::actingAs($this->usuario)->test(AcompanharAssinatura::class, ['invoice' => $invoice->id])
            ->assertSee('Plano anual ativo')->assertSee('Esta assinatura não renova sozinha: avisaremos 7 dias antes.');
    });

    it('renovação paga: plano renovado; a pendente aguarda; expirada oferece gerar outro', function () {
        $a = _assinaturaPixAtiva($this->usuario);
        $this->travelTo($a->proximo_vencimento->copy()->subDays(2));
        $renov = app(IniciarRenovacaoPix::class)->executar($this->usuario, null, null);
        // O fake marca o prazo pelo relógio real; com o tempo viajado, ajusta para dentro da janela.
        $renov->forceFill(['checkout_expira_em' => now()->addMinutes(20)])->save();

        Livewire::actingAs($this->usuario)->test(AcompanharAssinatura::class, ['invoice' => $renov->id])
            ->assertSee('A renovação vale quando o banco confirmar o pagamento.');

        $renov->forceFill(['checkout_expira_em' => now()->subMinute()])->save();
        $c = Livewire::actingAs($this->usuario)->test(AcompanharAssinatura::class, ['invoice' => $renov->id])
            ->assertSee('O QR Code expirou e nada foi cobrado.')->assertSee('Seu plano segue valendo até o fim do ciclo.')->assertSee('Gerar novo QR Code');
        expect($c->html())->toContain(route('assinatura.renovar'));

        $renov->forceFill(['checkout_expira_em' => now()->addMinutes(20)])->save();
        _entregar(_fake()->simularPagamento((string) $renov->checkout_id_externo));
        Livewire::actingAs($this->usuario)->test(AcompanharAssinatura::class, ['invoice' => $renov->id])->assertSee('Plano renovado');
    });

    it('Pix expirado na contratação: nada ativado e o link volta ao checkout em Pix', function () {
        $invoice = _assinaturaPendente($this->usuario, null, CicloCobranca::Anual, MeioPagamento::Pix);
        _entregar(_fake()->simularExpiracao((string) $invoice->checkout_id_externo));

        $c = Livewire::actingAs($this->usuario)->test(AcompanharAssinatura::class, ['invoice' => $invoice->id])
            ->assertSee('O QR Code expirou e nada foi cobrado.')->assertSee('O plano não foi ativado.');
        expect($c->html())->toContain('meio=pix')->and($invoice->fresh()->status)->toBe(EstadoCobranca::Vencida);
    });

    it('a página fake do Pix da assinatura oferece pagar e expirar', function () {
        $invoice = _assinaturaPendente($this->usuario, null, CicloCobranca::Anual, MeioPagamento::Pix);

        $this->actingAs($this->usuario)->get($invoice->checkout_url)->assertOk()
            ->assertSee('Simular pagamento do Pix')->assertDontSee('Simular cartão recusado');
    });
});

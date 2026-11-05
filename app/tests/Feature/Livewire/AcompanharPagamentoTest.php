<?php

declare(strict_types=1);

use App\Domain\Cobranca\EstadoCobranca;
use App\Domain\Cobranca\MeioPagamento;
use App\Domain\Cobranca\PaymentProvider;
use App\Domain\Cobranca\ProvedorIndisponivel;
use App\Livewire\Cobranca\AcompanharPagamento;
use App\Models\EmpresaAnalisada;
use App\Models\LoteCredito;
use App\Models\QuizRascunho;
use App\Services\Cobranca\IniciarCompraDeCreditos;
use App\Services\Cobranca\ReceberWebhook;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

/**
 * STORY-076 CA-3, CA-5 — a tela de retorno: aguarda o evento, confirma, expira, cancela.
 */
beforeEach(function () {
    _usarProvedor('fake');
    Mail::fake();
    $this->usuario = _compradorConfirmado();
    $this->invoice = _compraPendenteAsaas($this->usuario);
    $this->fake = app(PaymentProvider::class);
});

function _pagarNoFake($teste): void
{
    $teste->webhook = $teste->fake->simularPagamento($teste->invoice->checkout_id_externo);
    app(ReceberWebhook::class)->tratar($teste->webhook);
}

it('aguarda o pagamento: o retorno por si só nunca libera crédito (CA-3)', function () {
    $this->actingAs($this->usuario)->get(route('creditos.retorno', $this->invoice))
        ->assertOk()->assertSee('Aguardando o pagamento…')->assertSee('wire:poll', false);

    expect(LoteCredito::query()->count())->toBe(0)->and($this->invoice->fresh()->status)->toBe(EstadoCobranca::Pendente);
});

it('mostra a confirmação com os créditos e a validade assim que o webhook chega (CA-3)', function () {
    $c = Livewire::actingAs($this->usuario)->test(AcompanharPagamento::class, ['invoice' => $this->invoice->id])
        ->assertSee('Aguardando o pagamento…');

    _pagarNoFake($this);

    $lote = LoteCredito::query()->sole();
    $c->call('$refresh')
        ->assertSee('Pagamento confirmado')
        ->assertSee("Você tem {$lote->quantidade_comprada} créditos")
        ->assertSee('Fazer uma análise')
        ->assertDontSee('Aguardando o pagamento…')
        ->assertDontSee('O QR Code expirou');
});

it('com um rascunho de quiz salvo, o primário passa a continuar o diagnóstico', function () {
    $empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $this->usuario->id]);
    QuizRascunho::query()->create([
        'usuario_id' => $this->usuario->id, 'empresa_analisada_id' => $empresa->id, 'quiz_payload' => [],
        'ultimo_bloco_preenchido' => 1, 'expires_at' => now()->addDays(10),
    ]);
    _pagarNoFake($this);

    Livewire::actingAs($this->usuario)->test(AcompanharPagamento::class, ['invoice' => $this->invoice->id])
        ->assertSee('Continuar o diagnóstico');
});

it('Pix expirado mostra o aviso, não credita e oferece gerar outro com o aceite marcado (CA-5)', function () {
    $this->invoice->forceFill(['checkout_expira_em' => now()->subMinute()])->save();

    $c = Livewire::actingAs($this->usuario)->test(AcompanharPagamento::class, ['invoice' => $this->invoice->id])
        ->assertSee('O QR Code expirou e nada foi cobrado.')
        ->assertSee('Gerar novo QR Code')
        ->assertDontSee('Aguardando o pagamento…')
        ->call('novoCodigo')
        ->assertRedirect(route('creditos.comprar', ['pacote' => $this->invoice->pacote_credito_id]));

    expect(LoteCredito::query()->count())->toBe(0);
});

it('pagamento confirmado depois da expiração mostra a confirmação, nunca "expirado" junto', function () {
    $this->invoice->forceFill(['checkout_expira_em' => now()->subMinute()])->save();
    app(ReceberWebhook::class)->tratar($this->fake->simularExpiracao($this->invoice->checkout_id_externo));
    _pagarNoFake($this);

    Livewire::actingAs($this->usuario)->test(AcompanharPagamento::class, ['invoice' => $this->invoice->id])
        ->assertSee('Pagamento confirmado')->assertDontSee('O QR Code expirou');
});

it('cancelar a compra cancela no provedor e volta aos pacotes', function () {
    Livewire::actingAs($this->usuario)->test(AcompanharPagamento::class, ['invoice' => $this->invoice->id])
        ->call('cancelar')->assertRedirect(route('creditos.comprar'));

    expect($this->invoice->fresh()->status)->toBe(EstadoCobranca::Cancelada);
});

it('se o provedor não cancela, a compra continua pendente e a tela avisa', function () {
    $quebrado = Mockery::mock(PaymentProvider::class);
    $quebrado->shouldReceive('cancelCheckout')->andThrow(new ProvedorIndisponivel('fora'));
    app()->instance(PaymentProvider::class, $quebrado);

    Livewire::actingAs($this->usuario)->test(AcompanharPagamento::class, ['invoice' => $this->invoice->id])
        ->call('cancelar')->assertNoRedirect()->assertSee('Não conseguimos cancelar agora');

    expect($this->invoice->fresh()->status)->toBe(EstadoCobranca::Pendente);
});

it('cancelar uma compra que já foi paga não a cancela', function () {
    $c = Livewire::actingAs($this->usuario)->test(AcompanharPagamento::class, ['invoice' => $this->invoice->id]);
    _pagarNoFake($this);

    $c->call('cancelar')->assertRedirect(route('creditos.comprar'));

    expect($this->invoice->fresh()->status)->toBe(EstadoCobranca::Paga);
});

it('o admin em "Logar como" vê o botão de cancelar desabilitado (CA-7)', function () {
    $admin = _adminDaEquipe();

    $this->withSession(_sessaoDePersonificacao($admin, $this->usuario))->actingAs($this->usuario)
        ->get(route('creditos.retorno', $this->invoice))
        ->assertOk()->assertSeeInOrder(['aria-disabled="true"', 'Cancelar compra'], false);
});

it('compra cancelada e compra que falhou mostram a mensagem certa', function () {
    $this->invoice->forceFill(['status' => EstadoCobranca::Cancelada])->save();
    Livewire::actingAs($this->usuario)->test(AcompanharPagamento::class, ['invoice' => $this->invoice->id])->assertSee('Compra cancelada');

    $this->invoice->forceFill(['status' => EstadoCobranca::Falhou])->save();
    Livewire::actingAs($this->usuario)->test(AcompanharPagamento::class, ['invoice' => $this->invoice->id])
        ->assertSee('Não conseguimos gerar o QR Code agora. Nada foi cobrado.')
        ->call('novoCodigo')->assertRedirect();
});

it('a compra de outro usuário é 404', function () {
    $outro = _compradorConfirmado();

    $this->actingAs($outro)->get(route('creditos.retorno', $this->invoice))->assertNotFound();
});

it('a página fake só existe com o provedor fake, para o dono, e simula pagamento e expiração', function () {
    $this->actingAs($this->usuario)->get($this->invoice->checkout_url)->assertOk()->assertSee('Simular pagamento do Pix');

    $this->actingAs($this->usuario)->post(route('pagamentos.fake.simular', $this->invoice), ['acao' => 'pagar'])
        ->assertRedirect(route('creditos.retorno', $this->invoice));
    expect($this->invoice->fresh()->status)->toBe(EstadoCobranca::Paga)->and(LoteCredito::query()->count())->toBe(1);

    $outra = _compraPendenteAsaas($this->usuario, _pacote('mini'));
    $this->actingAs($this->usuario)->post(route('pagamentos.fake.simular', $outra), ['acao' => 'expirar'])->assertRedirect();
    expect($outra->fresh()->status)->toBe(EstadoCobranca::Vencida);

    $this->actingAs($this->usuario)->post(route('pagamentos.fake.simular', $outra), ['acao' => 'nada'])->assertStatus(422);
    $this->actingAs(_compradorConfirmado())->get(route('pagamentos.fake', $this->invoice))->assertNotFound();

    _asaasSimulado();
    $this->actingAs($this->usuario)->get(route('pagamentos.fake', $this->invoice))->assertNotFound();
});

// STORY-078 — a mesma tela de retorno para o pacote pago com cartão.
describe('pacote pago com cartão (STORY-078)', function () {
    beforeEach(function () {
        $this->cartao = app(IniciarCompraDeCreditos::class)->executar($this->usuario, _pacote(), null, null, MeioPagamento::Cartao);
    });

    it('aguarda a confirmação sem falar em QR Code e sem creditar (CA-2)', function () {
        Livewire::actingAs($this->usuario)->test(AcompanharPagamento::class, ['invoice' => $this->cartao->id])
            ->assertSee('Pague com cartão')
            ->assertSee('Abrir o pagamento com cartão')
            ->assertSee('Aguardando a confirmação do pagamento…')
            ->assertSee('não renova')
            ->assertDontSee('QR Code Pix');

        expect(LoteCredito::query()->count())->toBe(0);
    });

    it('confirmada: mostra o cartão final, os créditos e nenhuma recorrência (CA-2)', function () {
        $c = Livewire::actingAs($this->usuario)->test(AcompanharPagamento::class, ['invoice' => $this->cartao->id]);
        app(ReceberWebhook::class)->tratar($this->fake->simularPagamento($this->cartao->checkout_id_externo));

        $lote = LoteCredito::query()->sole();
        $c->call('$refresh')
            ->assertSee('Pagamento confirmado')
            ->assertSee('Pago com cartão Visa final 4242')
            ->assertSee("Você tem {$lote->quantidade_comprada} créditos")
            ->assertDontSee('Recebemos o Pix');
    });

    it('recusado: explica o motivo, diz que nada foi creditado e oferece tentar de novo ou pagar com Pix (CA-3)', function () {
        app(ReceberWebhook::class)->tratar($this->fake->simularRecusa($this->cartao->checkout_id_externo));

        $c = Livewire::actingAs($this->usuario)->test(AcompanharPagamento::class, ['invoice' => $this->cartao->id])
            ->assertSee('Cartão recusado')
            ->assertSee('O banco não autorizou o pagamento.')
            ->assertSee('Você pode tentar outro cartão ou pagar com Pix.')
            ->assertSee('Pagar com Pix');

        $c->call('pagarComPix')->assertRedirect(route('creditos.comprar', ['pacote' => $this->cartao->pacote_credito_id, 'meio' => 'pix']));
        $c->call('novoCodigo')->assertRedirect(route('creditos.comprar', ['pacote' => $this->cartao->pacote_credito_id, 'meio' => 'cartao']));
        expect(LoteCredito::query()->count())->toBe(0);
    });

    it('prazo vencido: nada cobrado e tentar de novo, sem falar em QR Code', function () {
        $this->cartao->forceFill(['checkout_expira_em' => now()->subMinute()])->save();

        Livewire::actingAs($this->usuario)->test(AcompanharPagamento::class, ['invoice' => $this->cartao->id])
            ->assertSee('O pagamento não foi concluído e nada foi cobrado.')
            ->assertDontSee('QR Code')
            ->call('novoCodigo')->assertRedirect(route('creditos.comprar', ['pacote' => $this->cartao->pacote_credito_id, 'meio' => 'cartao']));
    });

    it('falha do provedor no cartão usa o texto do cartão', function () {
        $this->cartao->forceFill(['status' => EstadoCobranca::Falhou, 'motivo_encerramento' => 'provedor'])->save();

        Livewire::actingAs($this->usuario)->test(AcompanharPagamento::class, ['invoice' => $this->cartao->id])
            ->assertSee('Não conseguimos abrir o pagamento agora. Nada foi cobrado.')
            ->assertDontSee('Cartão recusado');
    });

    it('a página fake oferece cartão aprovado, recusado e desistência, e o Pix continua com o Pix', function () {
        $this->actingAs($this->usuario)->get($this->cartao->checkout_url)
            ->assertOk()->assertSee('Simular cartão aprovado')->assertSee('Simular cartão recusado')->assertDontSee('Simular pagamento do Pix');
        $this->actingAs($this->usuario)->get($this->invoice->checkout_url)
            ->assertOk()->assertSee('Simular pagamento do Pix')->assertDontSee('Simular cartão recusado');

        $this->actingAs($this->usuario)->post(route('pagamentos.fake.simular', $this->cartao), ['acao' => 'recusar'])->assertRedirect(route('creditos.retorno', $this->cartao));
        expect($this->cartao->fresh()->motivo_encerramento)->toBe('recusado');
        $this->actingAs($this->usuario)->post(route('pagamentos.fake.simular', $this->invoice), ['acao' => 'recusar'])->assertStatus(422);
    });
});

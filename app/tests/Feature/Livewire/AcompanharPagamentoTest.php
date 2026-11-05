<?php

declare(strict_types=1);

use App\Domain\Cobranca\EstadoCobranca;
use App\Domain\Cobranca\PaymentProvider;
use App\Domain\Cobranca\ProvedorIndisponivel;
use App\Livewire\Cobranca\AcompanharPagamento;
use App\Models\EmpresaAnalisada;
use App\Models\LoteCredito;
use App\Models\QuizRascunho;
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

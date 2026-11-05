<?php

declare(strict_types=1);

use App\Domain\Cobranca\StatusAssinatura;
use App\Infrastructure\Cobranca\Fake\FakePaymentProvider;
use App\Models\Assinatura;
use App\Models\TrocaDeCartao;
use App\Services\Cobranca\TrocarCartao;
use Illuminate\Support\Facades\Mail;

/** STORY-081 — a "página do provedor" fake da troca de cartão (dev e E2E): só para o dono e só com o provedor fake. */
beforeEach(function () {
    _usarProvedor('fake');
    Mail::fake();
    $this->usuario = _compradorConfirmado();
    _pagarAssinatura(_assinaturaPendente($this->usuario));
    $this->troca = app(TrocarCartao::class)->iniciar($this->usuario);
});

it('mostra a página ao dono, com as três saídas', function () {
    $this->actingAs($this->usuario)->get(route('pagamentos.fake.troca', $this->troca))->assertOk()
        ->assertSee('Simular cartão aprovado')->assertSee('Simular cartão recusado')->assertSee('Simular desistência');
});

it('é 404 para outro usuário, fora do provedor fake ou em produção', function () {
    $this->actingAs(_compradorConfirmado())->get(route('pagamentos.fake.troca', $this->troca))->assertNotFound();

    app()->detectEnvironment(fn () => 'production');
    $this->actingAs($this->usuario)->get(route('pagamentos.fake.troca', $this->troca))->assertNotFound();
    app()->detectEnvironment(fn () => 'testing');

    _usarProvedor('asaas', ['services.asaas.base_url' => 'https://api-sandbox.asaas.com/v3', 'services.asaas.api_key' => '$aact_hmlg_x', 'services.asaas.webhook_token' => str_repeat('t', 40)]);
    $this->actingAs($this->usuario)->post(route('pagamentos.fake.troca.simular', $this->troca), ['acao' => 'pagar'])->assertNotFound();
});

it('cartão aprovado conclui a troca e volta para Meu plano esperando a confirmação', function () {
    $antiga = Assinatura::query()->sole()->id_externo;

    $this->actingAs($this->usuario)->post(route('pagamentos.fake.troca.simular', $this->troca), ['acao' => 'pagar'])
        ->assertRedirect(route('meu-plano', ['cartao' => 'retorno']));

    expect($this->troca->refresh()->status)->toBe('concluida')
        ->and(Assinatura::query()->sole()->id_externo)->not->toBe($antiga)
        ->and(FakePaymentProvider::situacaoDaAssinatura((string) $antiga))->toBe('cancelada');
});

it('em atraso, aprovar também paga a renovação e a assinatura volta a ativa', function () {
    Assinatura::query()->sole()->forceFill(['status' => StatusAssinatura::EmAtraso])->save();
    TrocaDeCartao::query()->delete();
    $troca = app(TrocarCartao::class)->iniciar($this->usuario);

    $this->actingAs($this->usuario)->post(route('pagamentos.fake.troca.simular', $troca), ['acao' => 'pagar'])->assertRedirect();

    expect(Assinatura::query()->sole()->status)->toBe(StatusAssinatura::Ativa)->and(Assinatura::query()->sole()->periodos_pagos)->toBe(2);
});

it('recusa e desistência encerram a troca sem mexer na assinatura; ação inválida é 422', function () {
    $antiga = Assinatura::query()->sole()->id_externo;

    $this->actingAs($this->usuario)->post(route('pagamentos.fake.troca.simular', $this->troca), ['acao' => 'recusar'])->assertRedirect();
    expect($this->troca->refresh()->status)->toBe('falhou');

    $outra = app(TrocarCartao::class)->iniciar($this->usuario);
    $this->actingAs($this->usuario)->post(route('pagamentos.fake.troca.simular', $outra), ['acao' => 'expirar'])->assertRedirect();
    expect($outra->refresh()->status)->toBe('expirada')->and(Assinatura::query()->sole()->id_externo)->toBe($antiga);

    $mais = app(TrocarCartao::class)->iniciar($this->usuario);
    $this->actingAs($this->usuario)->post(route('pagamentos.fake.troca.simular', $mais), ['acao' => 'xis'])->assertStatus(422);
});

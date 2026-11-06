<?php

declare(strict_types=1);

use App\Domain\Cobranca\CicloCobranca;
use App\Domain\Cobranca\EstadoCobranca;
use App\Domain\Cobranca\MeioPagamento;
use App\Domain\Cobranca\StatusAssinatura;
use App\Domain\Cobranca\TipoEvento;
use App\Infrastructure\Cobranca\Fake\FakePaymentProvider;
use App\Livewire\Backoffice\Financeiro;
use App\Livewire\Cobranca\MeuPlano;
use App\Livewire\Cobranca\TrocarPlano;
use App\Models\Assinatura;
use App\Models\Cupom;
use App\Models\CupomItem;
use App\Models\Invoice;
use App\Models\Usuario;
use App\Services\Cobranca\IniciarAssinatura;
use App\Services\Cobranca\TrocarCartao;
use App\Services\Cobranca\TrocarDePlano;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

/**
 * STORY-090 (D-06, D-15, D-20). Cenários E5, E23 e E24 do exploratório da STORY-087, com o comportamento correto.
 */
beforeEach(function () {
    _usarProvedor('fake');
    Mail::fake();
    $this->u = _compradorConfirmado();
});

function _cupomGratis(string $codigo, array $planos, int $ciclos = 3): void
{
    $c = Cupom::query()->create(['codigo' => $codigo, 'tipo' => 'percentual', 'valor' => 1000, 'vigencia_inicio' => now()->subDay(), 'vigencia_fim' => now()->addYear(), 'ativo' => true, 'ciclos_duracao' => $ciclos]);

    foreach ($planos as $n) {
        CupomItem::query()->create(['cupom_id' => $c->id, 'plano_id' => _plano($n)->id, 'ciclo' => 'mensal']);
    }
}

describe('CA-1 — troca de cartão e upgrade (E5)', function () {
    it('E5 troca de cartão aberta antes do upgrade: a recorrência nova passa a cobrar o preço do plano atual', function () {
        [$a] = _assinaturaAtivaNoFake($this->u);
        $this->travelTo($a->proximo_vencimento->copy()->subDays(10));
        $troca = app(TrocarCartao::class)->iniciar($this->u);
        expect($troca->valor_centavos)->toBe(4990)->and($troca->ciclo)->toBe('mensal');
        $t = app(TrocarDePlano::class)->confirmar($this->u, _plano('Pro'), CicloCobranca::Mensal);
        _pagarAssinatura($t->invoice);

        _entregar(_fake()->webhook(TipoEvento::CheckoutConcluido, $troca->checkout_id_externo));

        $a = $a->fresh();
        expect(FakePaymentProvider::valorCobradoDaAssinatura($a->id_externo))->toBe(_plano('Pro')->preco_mensal_centavos)
            ->and($a->ajuste_provedor_pendente)->toBeFalse();
    });

    it('se o provedor falhar na correção, fica pendente e a rotina horária repete até conseguir', function () {
        [$a] = _assinaturaAtivaNoFake($this->u);
        $this->travelTo($a->proximo_vencimento->copy()->subDays(10));
        $troca = app(TrocarCartao::class)->iniciar($this->u);
        _pagarAssinatura(app(TrocarDePlano::class)->confirmar($this->u, _plano('Pro'), CicloCobranca::Mensal)->invoice);
        FakePaymentProvider::falharProximaOperacaoDeAssinatura();

        _entregar(_fake()->webhook(TipoEvento::CheckoutConcluido, $troca->checkout_id_externo));

        $a = $a->fresh();
        expect($a->ajuste_provedor_pendente)->toBeTrue()->and(FakePaymentProvider::valorCobradoDaAssinatura($a->id_externo))->toBe(4990);

        Artisan::call('assinaturas:ajustar-no-provedor');

        expect($a->fresh()->ajuste_provedor_pendente)->toBeFalse()
            ->and(FakePaymentProvider::valorCobradoDaAssinatura($a->id_externo))->toBe(19900);
    });

    it('sem mudança de plano entre abrir e confirmar, não há chamada de correção', function () {
        [$a] = _assinaturaAtivaNoFake($this->u);
        $troca = app(TrocarCartao::class)->iniciar($this->u);

        _entregar(_fake()->webhook(TipoEvento::CheckoutConcluido, $troca->checkout_id_externo));

        expect(FakePaymentProvider::valorDaAssinatura($a->fresh()->id_externo))->toBeNull()->and($a->fresh()->ajuste_provedor_pendente)->toBeFalse();
    });

    it('com downgrade agendado, o ajuste vale o plano agendado e não o atual', function () {
        [$a] = _assinaturaAtivaNoFake($this->u);
        $this->travelTo($a->proximo_vencimento->copy()->subHours(1));
        app(TrocarDePlano::class)->confirmar($this->u, _plano('Pro'), CicloCobranca::Mensal);
        $this->travelTo($a->data_inicio->copy()->addDays(10));
        $troca = app(TrocarCartao::class)->iniciar($this->u);
        expect($troca->valor_centavos)->toBe(19900);
        app(TrocarDePlano::class)->confirmar($this->u, _plano('Básico'), CicloCobranca::Mensal);

        _entregar(_fake()->webhook(TipoEvento::CheckoutConcluido, $troca->checkout_id_externo));

        expect(FakePaymentProvider::valorCobradoDaAssinatura($a->fresh()->id_externo))->toBe(4990);
    });
});

describe('CA-2 — renovação com valor diferente do esperado', function () {
    it('alerta a equipe, marca a Invoice e aparece no Financeiro', function () {
        config(['services.telegram.bot_token' => 'tok-do-bot', 'services.telegram.chat_id' => '-100', 'backoffice.equipe_emails' => []]);
        Cache::flush();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        $i = _pagarAssinatura(_assinaturaPendente($this->u));
        $w = _fake()->simularRenovacao($i->checkout_id_externo);
        $c = Cache::get('fake-pagamento:pay:'.json_decode($w->corpo, true)['cobranca_id']);
        $c['valor'] = 3990;
        Cache::put('fake-pagamento:pay:'.json_decode($w->corpo, true)['cobranca_id'], $c, now()->addDay());

        _entregar($w);

        $renov = Invoice::query()->where('renovacao', true)->sole();
        expect($renov->valor_esperado_centavos)->toBe(4990);
        $texto = Http::recorded()->map(fn (array $p): string => $p[0]['text'])->implode("\n");
        expect($texto)->toContain('cobranca.valor_da_renovacao_divergente', 'esperado_centavos: 4990', 'recebido_centavos: 3990');

        $admin = Usuario::factory()->create();
        $admin->forceFill(['permissoes_admin' => ['financeiro.gerenciar']])->save();
        Livewire::actingAs($admin)->test(Financeiro::class)
            ->assertSeeHtml('dusk="divergente-'.$renov->id.'"')->assertSee('Cobrado R$ 39,90, esperado R$ 49,90')
            ->assertSeeHtml('dusk="cobranca-divergente-'.$renov->id.'"');
    });

    it('renovação no valor esperado não gera marca nem alerta', function () {
        $i = _pagarAssinatura(_assinaturaPendente($this->u));
        Log::spy();

        _entregar(_fake()->simularRenovacao($i->checkout_id_externo));

        expect(Invoice::query()->where('renovacao', true)->sole()->valor_esperado_centavos)->toBeNull();
        Log::shouldNotHaveReceived('error');
    });
});

describe('CA-3 — plano gratuito por cupom que recebe upgrade pago (E23)', function () {
    beforeEach(function () {
        _cupomGratis('V100', ['Básico']);
        $i = app(IniciarAssinatura::class)->executar($this->u, _plano('Básico'), CicloCobranca::Mensal, '1.1.1.1', 'x', MeioPagamento::Cartao, 'V100');
        expect($i->status)->toBe(EstadoCobranca::Paga);
        $this->a = Assinatura::query()->where('usuario_id', $this->u->id)->sole();
        $this->travelTo($this->a->proximo_vencimento->copy()->subDays(15));
        $this->troca = app(TrocarDePlano::class)->confirmar($this->u, _plano('Pro'), CicloCobranca::Mensal);
    });

    it('a tela de revisão avisa que nada será cobrado sem cartão e até quando cadastrar', function () {
        Livewire::actingAs($this->u)->test(TrocarPlano::class, ['plano' => _plano('Pro')->id, 'ciclo' => 'mensal'])
            ->assertSeeHtml('dusk="screen-troca-sem-cartao"')
            ->assertSee('Você não tem cartão cadastrado, então nada será cobrado automaticamente')
            ->assertSee('Nenhuma, até você cadastrar um cartão');
    });

    it('depois do upgrade o usuário consegue cadastrar o cartão, e a renovação cobra o preço do plano novo', function () {
        _pagarAssinatura($this->troca->invoice);
        $a = $this->a->fresh();
        expect($a->plano_id)->toBe(_plano('Pro')->id)->and($a->id_externo)->toBeNull();

        $troca = app(TrocarCartao::class)->iniciar($this->u);
        expect($troca->valor_centavos)->toBe(19900)->and($troca->cobra_agora)->toBeFalse();
        _entregar(_fake()->webhook(TipoEvento::CheckoutConcluido, $troca->checkout_id_externo));
        $a = $a->fresh();
        expect($a->id_externo)->not->toBeNull()->and($a->meio_pagamento)->toBe('cartao');

        $this->travelTo($a->proximo_vencimento->copy()->addHour());
        Artisan::call('assinaturas:ciclos-gratuitos');
        _entregar(_fake()->simularRenovacao((string) Invoice::query()->where('tipo', 'troca_de_plano')->value('checkout_id_externo')));

        expect($a->fresh()->status)->toBe(StatusAssinatura::Ativa);
    });

    it('Meu plano diz que o plano termina e oferece o cartão', function () {
        _pagarAssinatura($this->troca->invoice);

        Livewire::actingAs($this->u)->test(MeuPlano::class)
            ->assertSee('Você ainda não cadastrou um cartão')
            ->assertSeeHtml('dusk="screen-meuplano-cadastrar-cartao"');
    });

    it('sem cartão até o vencimento o plano expira com a saída de recontratar à vista', function () {
        _pagarAssinatura($this->troca->invoice);
        $a = $this->a->fresh();

        $this->travelTo($a->proximo_vencimento->copy()->addHour());
        Artisan::call('assinaturas:ciclos-gratuitos');

        expect($a->fresh()->status)->toBe(StatusAssinatura::Expirada);
        Livewire::actingAs($this->u)->test(MeuPlano::class)
            ->assertSeeHtml('dusk="screen-meuplano-cadastrar-cartao-encerrado"');
    });
});

describe('CA-4 — downgrade agendado em ciclo gratuito (E24)', function () {
    it('E24 vale na renovação gratuita: plano, cota e preço novos, e a troca deixa de estar agendada', function () {
        _cupomGratis('V200', ['Básico', 'Pro']);
        app(IniciarAssinatura::class)->executar($this->u, _plano('Pro'), CicloCobranca::Mensal, '1.1.1.1', 'x', MeioPagamento::Cartao, 'V200');
        $a = Assinatura::query()->where('usuario_id', $this->u->id)->sole();
        $t = app(TrocarDePlano::class)->confirmar($this->u, _plano('Básico'), CicloCobranca::Mensal);
        expect($t->tipo)->toBe('agendada');

        $this->travelTo($a->proximo_vencimento->copy()->addHour());
        Artisan::call('assinaturas:ciclos-gratuitos');

        $a = $a->fresh();
        expect($a->periodos_pagos)->toBe(2)->and($a->plano_id)->toBe(_plano('Básico')->id)->and($a->troca_plano_id)->toBeNull()
            ->and($a->cota_snapshot)->toBe(1)->and($a->cota_restante_ciclo)->toBe(1)
            ->and(Invoice::query()->where('renovacao', true)->sole()->plano_id)->toBe(_plano('Básico')->id);
    });

    it('sem troca agendada, a renovação gratuita segue como antes', function () {
        _cupomGratis('V300', ['Básico']);
        app(IniciarAssinatura::class)->executar($this->u, _plano('Básico'), CicloCobranca::Mensal, '1.1.1.1', 'x', MeioPagamento::Cartao, 'V300');
        $a = Assinatura::query()->where('usuario_id', $this->u->id)->sole();

        $this->travelTo($a->proximo_vencimento->copy()->addHour());
        Artisan::call('assinaturas:ciclos-gratuitos');

        expect($a->fresh()->periodos_pagos)->toBe(2)->and($a->fresh()->plano_id)->toBe(_plano('Básico')->id);
    });
});

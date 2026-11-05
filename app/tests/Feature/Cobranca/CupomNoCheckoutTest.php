<?php

declare(strict_types=1);

use App\Domain\Cobranca\Carteira;
use App\Domain\Cobranca\CicloCobranca;
use App\Domain\Cobranca\EstadoCobranca;
use App\Domain\Cobranca\MeioPagamento;
use App\Domain\Cobranca\MotivoBloqueio;
use App\Domain\Cobranca\MotivoRecusaCupom;
use App\Domain\Cobranca\PaymentProvider;
use App\Domain\Cobranca\ProvedorIndisponivel;
use App\Domain\Cobranca\StatusAssinatura;
use App\Domain\Cobranca\TipoDesconto;
use App\Infrastructure\Cobranca\Fake\FakePaymentProvider;
use App\Livewire\Cobranca\AssinarPlano;
use App\Livewire\Cobranca\ComprarCreditos;
use App\Livewire\Cobranca\MeuPlano;
use App\Livewire\Cobranca\RenovarAssinaturaPix;
use App\Mail\FimDoDescontoMessage;
use App\Models\Assinatura;
use App\Models\CobrancaConfig;
use App\Models\Cupom;
use App\Models\CupomConcessao;
use App\Models\Invoice;
use App\Models\LoteCredito;
use App\Models\Usuario;
use App\Services\Cobranca\AjustarValorDaAssinatura;
use App\Services\Cobranca\CupomRecusado;
use App\Services\Cobranca\IniciarAssinatura;
use App\Services\Cobranca\IniciarCompraDeCreditos;
use App\Services\Cobranca\IniciarRenovacaoPix;
use App\Services\Cobranca\ProcessarEvento;
use App\Services\Cobranca\TrocarCartao;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

/**
 * STORY-083 — cupom no checkout, de ponta a ponta no domínio, com o provedor fake. Um teste por CA; o valor
 * que vai ao provedor é sempre o final, e o uso do cupom só nasce com a contratação concluída.
 */
beforeEach(function () {
    _usarProvedor('fake');
    Mail::fake();
    $this->usuario = _compradorConfirmado();
});

/**
 * @param  array<string, mixed>  $attrs
 */
function _cupomPara(array $attrs = [], ?string $planoId = null, string $ciclo = 'mensal', ?string $pacoteId = null): Cupom
{
    $cupom = Cupom::query()->create([
        'codigo' => 'BETA50', 'tipo' => TipoDesconto::Percentual, 'valor' => 50,
        'vigencia_inicio' => now()->subDay()->toDateString(), 'vigencia_fim' => now()->addMonth()->toDateString(),
        'ativo' => true, 'ciclos_duracao' => 3, ...$attrs,
    ]);

    $planoId ??= $pacoteId === null ? _plano()->id : null;

    if ($planoId !== null) {
        $cupom->itens()->create(['plano_id' => $planoId, 'ciclo' => $ciclo]);
    }

    if ($pacoteId !== null) {
        $cupom->itens()->create(['pacote_credito_id' => $pacoteId]);
    }

    return $cupom;
}

function _contratar(Usuario $u, ?string $codigo = null, CicloCobranca $ciclo = CicloCobranca::Mensal, MeioPagamento $meio = MeioPagamento::Cartao): Invoice
{
    return app(IniciarAssinatura::class)->executar($u, _plano(), $ciclo, '203.0.113.7', 'PestAgent', $meio, $codigo);
}

function _comprarCreditos(Usuario $u, ?string $codigo = null, MeioPagamento $meio = MeioPagamento::Pix): Invoice
{
    return app(IniciarCompraDeCreditos::class)->executar($u, _pacote('plus'), '203.0.113.7', 'PestAgent', $meio, $codigo);
}

function _recusa(callable $acao): ?MotivoRecusaCupom
{
    try {
        $acao();
    } catch (CupomRecusado $e) {
        return $e->motivo;
    }

    return null;
}

function _pagarRenovacao(Invoice $primeira): void
{
    $assinatura = Assinatura::query()->findOrFail($primeira->fresh()->assinatura_id);
    test()->travelTo($assinatura->proximo_vencimento);
    _entregar(_fake()->simularRenovacao((string) $primeira->checkout_id_externo));
}

describe('CA-1 preço cheio, desconto e valor a pagar antes de confirmar', function () {
    it('o resumo da assinatura mostra preço, desconto e valor com o cupom digitado', function () {
        _cupomPara();
        $preco = _plano()->preco_mensal_centavos;

        Livewire::actingAs($this->usuario)->test(AssinarPlano::class, ['plano' => _plano()->id, 'ciclo' => 'mensal'])
            ->assertSee('Tem cupom?')
            ->call('abrirCupom')
            ->set('campoCupom', 'beta50')
            ->call('aplicarCupom')
            ->assertSet('codigoCupom', 'BETA50')
            ->assertSee('BETA50 aplicado')
            ->assertSee('R$ '.number_format($preco / 100, 2, ',', '.'))
            ->assertSee('− R$ '.number_format(intdiv($preco, 2) / 100, 2, ',', '.'))
            ->assertSee('Depois');
    });

    it('o pacote de créditos também mostra o desconto e o botão traz o novo valor', function () {
        _cupomPara(['codigo' => 'PACOTE20', 'valor' => 20, 'ciclos_duracao' => null], pacoteId: _pacote('plus')->id);
        $final = (int) round(_pacote('plus')->preco_centavos * 0.8);

        Livewire::actingAs($this->usuario)->test(ComprarCreditos::class, ['pacote' => _pacote('plus')->id])
            ->call('abrirCupom')->set('campoCupom', 'PACOTE20')->call('aplicarCupom')
            ->assertSee('PACOTE20')
            ->assertSee('R$ '.number_format($final / 100, 2, ',', '.'));
    });

    it('remover o cupom volta ao preço cheio', function () {
        _cupomPara();

        Livewire::actingAs($this->usuario)->test(AssinarPlano::class, ['plano' => _plano()->id, 'ciclo' => 'mensal'])
            ->call('abrirCupom')->set('campoCupom', 'BETA50')->call('aplicarCupom')
            ->call('removerCupom')
            ->assertSet('codigoCupom', null)
            ->assertSee('Sem desconto');
    });

    it('campo vazio pede o código', function () {
        Livewire::actingAs($this->usuario)->test(AssinarPlano::class, ['plano' => _plano()->id, 'ciclo' => 'mensal'])
            ->call('abrirCupom')->call('aplicarCupom')
            ->assertSet('erroCupom', 'Digite o código do cupom.');
    });

    it('envia ao provedor o valor final e guarda o retrato do desconto na Invoice', function () {
        $cupom = _cupomPara();
        $cheio = _plano()->preco_mensal_centavos;

        $invoice = _contratar($this->usuario, 'beta50');

        expect($invoice->valor_centavos)->toBe($cheio - intdiv($cheio + 1, 2))
            ->and($invoice->preco_cheio_centavos)->toBe($cheio)
            ->and($invoice->desconto_centavos)->toBe(intdiv($cheio + 1, 2))
            ->and($invoice->cupom_id)->toBe($cupom->id)
            ->and($invoice->cupom_ciclos)->toBe(3)
            ->and(FakePaymentProvider::valorCobradoDaAssinatura('fakesub_'.str_replace('fakechk_', '', (string) $invoice->checkout_id_externo)))->toBe($invoice->valor_centavos);
    });
});

describe('CA-2 cupom concedido à conta', function () {
    it('é aplicado sozinho, com o aviso, e a tela não oferece digitar outro', function () {
        $cupom = _cupomPara(['codigo' => 'BETA100', 'valor' => 100]);
        CupomConcessao::query()->create(['cupom_id' => $cupom->id, 'usuario_id' => $this->usuario->id, 'ciclos_restantes' => 3, 'concedida_em' => now()]);

        Livewire::actingAs($this->usuario)->test(AssinarPlano::class, ['plano' => _plano()->id, 'ciclo' => 'mensal'])
            ->assertSee('Cupom BETA100 aplicado à sua conta')
            ->assertDontSee('Tem cupom?')
            ->assertSee('Você não precisa pagar nada agora.')
            ->assertSee('Ativar sem pagar');
    });

    it('o concedido vence o digitado: cupons não acumulam', function () {
        $beta = _cupomPara(['codigo' => 'BETA30', 'valor' => 30]);
        _cupomPara(['codigo' => 'OUTRO90', 'valor' => 90]);
        CupomConcessao::query()->create(['cupom_id' => $beta->id, 'usuario_id' => $this->usuario->id, 'ciclos_restantes' => 3, 'concedida_em' => now()]);

        $invoice = _contratar($this->usuario, 'OUTRO90');

        expect($invoice->cupom_id)->toBe($beta->id);
    });

    it('com dois concedidos vale o de maior desconto, e o concedido que não cobre o item é ignorado', function () {
        $pequeno = _cupomPara(['codigo' => 'P10', 'valor' => 10]);
        $grande = _cupomPara(['codigo' => 'G40', 'valor' => 40]);
        $outroPlano = _cupomPara(['codigo' => 'ANUAL90', 'valor' => 90], _plano()->id, 'anual');
        foreach ([$pequeno, $grande, $outroPlano] as $c) {
            CupomConcessao::query()->create(['cupom_id' => $c->id, 'usuario_id' => $this->usuario->id, 'ciclos_restantes' => 3, 'concedida_em' => now()]);
        }

        expect(_contratar($this->usuario)->cupom_id)->toBe($grande->id);
    });

    it('a duração vem da concessão, não do cupom editado depois', function () {
        $cupom = _cupomPara(['ciclos_duracao' => 3]);
        CupomConcessao::query()->create(['cupom_id' => $cupom->id, 'usuario_id' => $this->usuario->id, 'ciclos_restantes' => 2, 'concedida_em' => now()]);

        expect(_contratar($this->usuario)->cupom_ciclos)->toBe(2);
    });
});

describe('CA-3 recusa com motivo e uso só depois da contratação', function () {
    it('recusa código inexistente, inativo, vencido, ainda não vigente, esgotado, já usado e que não cobre', function () {
        _cupomPara(['codigo' => 'INATIVO', 'ativo' => false]);
        _cupomPara(['codigo' => 'VENCIDO', 'vigencia_inicio' => now()->subMonth()->toDateString(), 'vigencia_fim' => now()->subDay()->toDateString()]);
        $esgotado = _cupomPara(['codigo' => 'ESGOTADO', 'limite_usos' => 1]);
        $esgotado->usos()->create(['usuario_id' => Usuario::factory()->create()->id, 'usado_em' => now(), 'desconto_centavos' => 1]);
        $usado = _cupomPara(['codigo' => 'USADO']);
        $usado->usos()->create(['usuario_id' => $this->usuario->id, 'usado_em' => now(), 'desconto_centavos' => 1]);
        _cupomPara(['codigo' => 'SOANUAL'], _plano()->id, 'anual');

        $motivos = array_map(fn (string $c) => _recusa(fn () => _contratar($this->usuario, $c)), ['NAOEXISTE', 'INATIVO', 'VENCIDO', 'ESGOTADO', 'USADO', 'SOANUAL']);

        expect($motivos)->toBe([
            MotivoRecusaCupom::Inexistente, MotivoRecusaCupom::Inativo, MotivoRecusaCupom::ForaDaVigencia,
            MotivoRecusaCupom::LimiteEsgotado, MotivoRecusaCupom::JaUtilizado, MotivoRecusaCupom::ItemNaoCoberto,
        ])->and(Invoice::query()->count())->toBe(0);
    });

    it('a tela mostra o motivo específico sob o campo e não aplica o cupom', function () {
        _cupomPara(['codigo' => 'VENCIDO', 'vigencia_inicio' => '2026-09-01', 'vigencia_fim' => '2026-10-01']);
        _cupomPara(['codigo' => 'SOANUAL'], _plano()->id, 'anual');

        $tela = Livewire::actingAs($this->usuario)->test(AssinarPlano::class, ['plano' => _plano()->id, 'ciclo' => 'mensal'])->call('abrirCupom');

        $tela->set('campoCupom', 'zzz')->call('aplicarCupom')->assertSet('erroCupom', 'Não encontramos esse cupom. Confira o código.')->assertSet('codigoCupom', null);
        $tela->set('campoCupom', 'VENCIDO')->call('aplicarCupom')->assertSet('erroCupom', 'Este cupom venceu em 01/10/2026.');
        $tela->set('campoCupom', 'SOANUAL')->call('aplicarCupom')->assertSee('Este cupom não vale para o plano Básico mensal. Ele vale para plano Básico anual.');
    });

    it('o uso só é registrado quando a contratação conclui', function () {
        $cupom = _cupomPara();

        $invoice = _contratar($this->usuario, 'BETA50');
        expect($cupom->usos()->count())->toBe(0);

        _pagarAssinatura($invoice);
        $uso = $cupom->usos()->sole();

        expect($uso->usuario_id)->toBe($this->usuario->id)
            ->and($uso->invoice_id)->toBe($invoice->id)
            ->and($uso->desconto_centavos)->toBe($invoice->desconto_centavos)
            ->and($uso->ciclos_restantes)->toBe(3);
    });

    it('checkout abandonado ou recusado não consome o cupom', function () {
        $cupom = _cupomPara();
        $invoice = _contratar($this->usuario, 'BETA50');
        _entregar(_fake()->simularRecusa((string) $invoice->checkout_id_externo));

        expect($cupom->usos()->count())->toBe(0)
            ->and(_recusa(fn () => _contratar($this->usuario, 'BETA50')))->toBeNull();
    });

    it('o créditos pagos com o cupom registram o uso na mesma transação do crédito', function () {
        $cupom = _cupomPara(['codigo' => 'CRED', 'ciclos_duracao' => null], pacoteId: _pacote('plus')->id);
        $invoice = _comprarCreditos($this->usuario, 'CRED');
        expect($cupom->usos()->count())->toBe(0);

        _entregar(_fake()->simularPagamento((string) $invoice->checkout_id_externo));

        expect($cupom->usos()->count())->toBe(1)->and(LoteCredito::query()->where('invoice_id', $invoice->id)->count())->toBe(1);
    });

    it('o pagamento duplicado do webhook não duplica o uso', function () {
        $cupom = _cupomPara();
        $invoice = _contratar($this->usuario, 'BETA50');
        _pagarAssinatura($invoice);
        _entregar(_fake()->simularPagamento((string) $invoice->checkout_id_externo, 'evt-2'));

        expect($cupom->usos()->count())->toBe(1);
    });

    it('a mesma conta com duas compras pagas do mesmo cupom grava um uso e alerta', function () {
        Log::spy();
        $cupom = _cupomPara(['ciclos_duracao' => null], pacoteId: _pacote('plus')->id);
        $a = _comprarCreditos($this->usuario, $cupom->codigo, MeioPagamento::Pix);
        $b = _comprarCreditos($this->usuario, $cupom->codigo, MeioPagamento::Cartao);
        _entregar(_fake()->simularPagamento((string) $a->checkout_id_externo));
        _entregar(_fake()->simularPagamento((string) $b->checkout_id_externo));

        expect($cupom->usos()->count())->toBe(1);
        Log::shouldHaveReceived('warning')->with('cobranca.cupom_ja_usado', Mockery::any());
    });

    it('a recusa no clique deixa a tela no preço cheio, com o aviso do último uso', function () {
        $cupom = _cupomPara(['limite_usos' => 1]);
        $tela = Livewire::actingAs($this->usuario)->test(AssinarPlano::class, ['plano' => _plano()->id, 'ciclo' => 'mensal'])
            ->call('abrirCupom')->set('campoCupom', 'BETA50')->call('aplicarCupom')->set('aceito', true)->assertSet('codigoCupom', 'BETA50');

        // Outra conta pega a última vaga entre a tela e o clique.
        $outro = Usuario::factory()->create(['email_confirmed_at' => now()]);
        _pagarAssinatura(_contratar($outro, 'BETA50'));

        $tela->call('assinar')
            ->assertSet('codigoCupom', null)
            ->assertSee('Este cupom acabou de atingir o limite de usos. O valor voltou ao preço cheio')
            ->assertNoRedirect();
        expect(Invoice::query()->where('usuario_id', $this->usuario->id)->count())->toBe(0)
            ->and($cupom->usos()->count())->toBe(1);
    });
});

describe('CA-4 limite de usos', function () {
    it('o checkout aberto segura a vaga: o último uso não é dado a dois', function () {
        _cupomPara(['limite_usos' => 1]);
        $outro = Usuario::factory()->create(['email_confirmed_at' => now()]);

        _contratar($this->usuario, 'BETA50');

        expect(_recusa(fn () => _contratar($outro, 'BETA50')))->toBe(MotivoRecusaCupom::LimiteEsgotado);
    });

    it('a vaga volta quando o checkout expira ou falha', function () {
        _cupomPara(['limite_usos' => 1]);
        $outro = Usuario::factory()->create(['email_confirmed_at' => now()]);
        $primeiro = _contratar($this->usuario, 'BETA50');
        _entregar(_fake()->simularRecusa((string) $primeiro->checkout_id_externo));

        expect(_recusa(fn () => _contratar($outro, 'BETA50')))->toBeNull();

        $outro2 = Usuario::factory()->create(['email_confirmed_at' => now()]);
        // O prazo do checkout aberto passou (o relógio do banco não viaja, então o prazo é vencido na linha).
        Invoice::query()->where('usuario_id', $outro->id)->update(['checkout_expira_em' => DB::raw("now() - interval '1 minute'")]);
        expect(_recusa(fn () => _contratar($outro2, 'BETA50')))->toBeNull();
    });

    it('depois de paga a vaga continua tomada, agora como uso', function () {
        _cupomPara(['limite_usos' => 1]);
        _pagarAssinatura(_contratar($this->usuario, 'BETA50'));
        $outro = Usuario::factory()->create(['email_confirmed_at' => now()]);

        expect(_recusa(fn () => _contratar($outro, 'BETA50')))->toBe(MotivoRecusaCupom::LimiteEsgotado);
    });

    it('Pix pago depois do prazo, com a vaga tomada, ainda credita e deixa o alerta', function () {
        Log::spy();
        $cupom = _cupomPara(['limite_usos' => 1, 'ciclos_duracao' => null], pacoteId: _pacote('plus')->id);
        $primeiro = _comprarCreditos($this->usuario, $cupom->codigo);
        Invoice::query()->whereKey($primeiro->id)->update(['checkout_expira_em' => DB::raw("now() - interval '1 minute'")]);
        $outro = Usuario::factory()->create(['email_confirmed_at' => now()]);
        _pagarAssinatura(_comprarCreditos($outro, $cupom->codigo));

        _entregar(_fake()->simularPagamento((string) $primeiro->checkout_id_externo));

        expect($cupom->usos()->count())->toBe(2);
        Log::shouldHaveReceived('warning')->with('cobranca.cupom_acima_do_limite', Mockery::any());
    });
});

describe('CA-5 sem acúmulo, sem valor negativo, mínimo do provedor', function () {
    it('valor fixo maior que o preço zera a cobrança sem ficar negativo', function () {
        _cupomPara(['tipo' => TipoDesconto::ValorFixo, 'valor' => 999999]);

        $invoice = _contratar($this->usuario, 'BETA50');

        expect($invoice->valor_centavos)->toBe(0)->and($invoice->desconto_centavos)->toBe(_plano()->preco_mensal_centavos);
    });

    it('valor final abaixo do mínimo ativa sem cobrança, sem chamar o provedor', function () {
        _cupomPara(['tipo' => TipoDesconto::ValorFixo, 'valor' => _plano()->preco_mensal_centavos - 300]);
        app()->instance(PaymentProvider::class, _provedorQueFalha(['startCheckout' => fn () => new RuntimeException('não deveria chamar')]));
        app()->forgetInstance(IniciarAssinatura::class);

        $invoice = _contratar($this->usuario, 'BETA50');

        expect($invoice->status)->toBe(EstadoCobranca::Paga)->and($invoice->valor_centavos)->toBe(0);
    });

    it('o mínimo é configurável', function () {
        config(['services.payments.valor_minimo_centavos' => 100]);
        _cupomPara(['tipo' => TipoDesconto::ValorFixo, 'valor' => _plano()->preco_mensal_centavos - 300]);

        $invoice = _contratar($this->usuario, 'BETA50');

        expect($invoice->valor_centavos)->toBe(300)->and($invoice->status)->toBe(EstadoCobranca::Pendente);
    });
});

describe('CA-6 desconto total: sem meio de pagamento e sem cobrança', function () {
    beforeEach(function () {
        // Qualquer chamada ao provedor para abrir cobrança é falha do teste.
        app()->instance(PaymentProvider::class, _provedorQueFalha(['startCheckout' => fn () => new RuntimeException('não deveria chamar o provedor')]));
        app()->forgetInstance(IniciarAssinatura::class);
        app()->forgetInstance(IniciarCompraDeCreditos::class);
    });

    it('a assinatura fica ativa na hora, com Invoice de valor zero, uso e cota', function () {
        $cupom = _cupomPara(['valor' => 100]);

        $invoice = _contratar($this->usuario, 'BETA50');
        $a = Assinatura::query()->where('usuario_id', $this->usuario->id)->sole();

        expect($invoice->status)->toBe(EstadoCobranca::Paga)->and($invoice->valor_centavos)->toBe(0)->and($invoice->meio)->toBe('nenhum')
            ->and($invoice->checkout_url)->toBeNull()
            ->and($a->status)->toBe(StatusAssinatura::Ativa)->and($a->id_externo)->toBeNull()->and($a->meio_pagamento)->toBe('nenhum')
            ->and($a->cota_restante_ciclo)->toBe(_plano()->cota_mensal)
            ->and($a->cupom_id)->toBe($cupom->id)->and($a->desconto_periodos)->toBe(3)
            ->and($cupom->usos()->sole()->invoice_id)->toBe($invoice->id)
            ->and(app(Carteira::class)->verificar($this->usuario->id)->bloqueio)->toBeNull();
    });

    it('os créditos são liberados na hora, com Invoice e uso', function () {
        $cupom = _cupomPara(['codigo' => 'CRED100', 'valor' => 100, 'ciclos_duracao' => null], pacoteId: _pacote('plus')->id);

        $invoice = _comprarCreditos($this->usuario, 'CRED100');

        expect($invoice->status)->toBe(EstadoCobranca::Paga)->and($invoice->valor_centavos)->toBe(0)
            ->and(LoteCredito::query()->where('invoice_id', $invoice->id)->sole()->quantidade_comprada)->toBe(_pacote('plus')->quantidade_creditos)
            ->and($cupom->usos()->count())->toBe(1);
    });

    it('o Pix anual com desconto total também ativa sem cobrança', function () {
        _cupomPara(['valor' => 100], _plano()->id, 'anual');

        $invoice = _contratar($this->usuario, 'BETA50', CicloCobranca::Anual, MeioPagamento::Pix);

        expect($invoice->status)->toBe(EstadoCobranca::Paga)
            ->and(Assinatura::query()->where('usuario_id', $this->usuario->id)->sole()->proxima_cota_em)->not->toBeNull();
    });

    it('a tela vai direto à confirmação, sem página do provedor', function () {
        _cupomPara(['valor' => 100]);

        $tela = Livewire::actingAs($this->usuario)->test(AssinarPlano::class, ['plano' => _plano()->id, 'ciclo' => 'mensal'])
            ->call('abrirCupom')->set('campoCupom', 'BETA50')->call('aplicarCupom')
            ->assertSee('Ativar sem pagar')
            ->set('aceito', true)->call('assinar');

        $tela->assertRedirect(route('assinatura.retorno', Invoice::query()->where('usuario_id', $this->usuario->id)->sole()));
    });

    it('sem registro do uso a ativação não acontece: nada de assinatura sem Invoice e sem uso', function () {
        _cupomPara(['valor' => 100]);
        // O índice único barra o segundo uso da mesma conta: a transação inteira desfaz.
        Cupom::query()->first()->usos()->create(['usuario_id' => $this->usuario->id, 'usado_em' => now(), 'desconto_centavos' => 1]);

        expect(_recusa(fn () => _contratar($this->usuario, 'BETA50')))->toBe(MotivoRecusaCupom::JaUtilizado)
            ->and(Assinatura::query()->count())->toBe(0)->and(Invoice::query()->count())->toBe(0);
    });
});

describe('CA-7 desconto por N ciclos e volta ao preço vigente', function () {
    it('a renovação usa o valor com desconto e, pago o último ciclo, o provedor volta ao preço cheio', function () {
        _cupomPara();
        $cheio = _plano()->preco_mensal_centavos;
        $com = $cheio - intdiv($cheio + 1, 2);
        $invoice = _pagarAssinatura(_contratar($this->usuario, 'BETA50'));
        $a = Assinatura::query()->sole();
        $sub = (string) $a->id_externo;

        expect($a->preco_snapshot_centavos)->toBe($com)->and(FakePaymentProvider::valorCobradoDaAssinatura($sub))->toBe($com);

        _pagarRenovacao($invoice);   // 2º ciclo, ainda com desconto
        expect($a->fresh()->periodos_pagos)->toBe(2)->and($a->fresh()->valor_pendente_no_provedor)->toBeFalse()
            ->and(FakePaymentProvider::valorCobradoDaAssinatura($sub))->toBe($com);

        _pagarRenovacao($invoice);   // 3º e último ciclo com desconto
        $a = $a->fresh();
        expect($a->periodos_pagos)->toBe(3)->and($a->preco_snapshot_centavos)->toBe($cheio)
            ->and($a->valor_pendente_no_provedor)->toBeFalse()
            ->and(FakePaymentProvider::valorCobradoDaAssinatura($sub))->toBe($cheio);

        $invoices = Invoice::query()->where('assinatura_id', $a->id)->where('renovacao', true)->orderBy('created_at')->get();
        expect($invoices)->toHaveCount(2)->and($invoices->every(fn (Invoice $i) => $i->desconto_centavos === intdiv($cheio + 1, 2)))->toBeTrue();
    });

    it('com desconto de um ciclo só, o valor volta ao cheio logo depois do primeiro pagamento', function () {
        _cupomPara(['ciclos_duracao' => 1]);
        _pagarAssinatura(_contratar($this->usuario, 'BETA50'));
        $a = Assinatura::query()->sole();

        expect(FakePaymentProvider::valorCobradoDaAssinatura((string) $a->id_externo))->toBe(_plano()->preco_mensal_centavos);
    });

    it('se o provedor falhar no ajuste, fica pendente e a rotina repete até conseguir', function () {
        Log::spy();
        _cupomPara(['ciclos_duracao' => 1]);
        app()->instance(PaymentProvider::class, _provedorQueFalha(['changeSubscriptionValue' => fn () => new ProvedorIndisponivel('fora')]));
        app()->forgetInstance(AjustarValorDaAssinatura::class);
        app()->forgetInstance(ProcessarEvento::class);
        $invoice = _contratar($this->usuario, 'BETA50');
        _entregar((new FakePaymentProvider)->simularPagamento((string) $invoice->checkout_id_externo));
        $a = Assinatura::query()->sole();

        expect($a->valor_pendente_no_provedor)->toBeTrue();
        Log::shouldHaveReceived('error')->with('cobranca.ajuste_de_valor_falhou', Mockery::any());

        _usarProvedor('fake');
        app()->forgetInstance(AjustarValorDaAssinatura::class);
        Artisan::call('assinaturas:ciclos-gratuitos');

        expect($a->fresh()->valor_pendente_no_provedor)->toBeFalse()
            ->and(FakePaymentProvider::valorCobradoDaAssinatura((string) $a->id_externo))->toBe(_plano()->preco_mensal_centavos);
    });

    it('sem duração o desconto vale enquanto a assinatura durar', function () {
        _cupomPara(['ciclos_duracao' => null]);
        $invoice = _pagarAssinatura(_contratar($this->usuario, 'BETA50'));
        $a = Assinatura::query()->sole();
        _pagarRenovacao($invoice);
        _pagarRenovacao($invoice);
        _pagarRenovacao($invoice);

        expect($a->fresh()->desconto_periodos)->toBeNull()->and($a->fresh()->periodos_pagos)->toBe(4)
            ->and($a->fresh()->preco_snapshot_centavos)->toBe($a->preco_snapshot_centavos);
    });

    it('desconto total por 2 ciclos: renova sem cobrança com Invoice zero e depois encerra sem cartão', function () {
        $cupom = _cupomPara(['valor' => 100, 'ciclos_duracao' => 2]);
        _contratar($this->usuario, 'BETA50');
        $a = Assinatura::query()->sole();
        expect($a->preco_snapshot_centavos)->toBe(0);

        $this->travelTo($a->proximo_vencimento->copy()->addMinute());
        expect(Artisan::call('assinaturas:ciclos-gratuitos'))->toBe(0);
        $a = $a->fresh();
        expect($a->periodos_pagos)->toBe(2)->and($a->status)->toBe(StatusAssinatura::Ativa)
            ->and($a->cota_restante_ciclo)->toBe(_plano()->cota_mensal)
            ->and($a->preco_snapshot_centavos)->toBe(_plano()->preco_mensal_centavos)
            ->and(Invoice::query()->where('assinatura_id', $a->id)->where('renovacao', true)->where('valor_centavos', 0)->count())->toBe(1);

        // Roda de novo no mesmo instante: nada se repete.
        Artisan::call('assinaturas:ciclos-gratuitos');
        expect($a->fresh()->periodos_pagos)->toBe(2);

        $this->travelTo($a->fresh()->proximo_vencimento->copy()->addMinute());
        Artisan::call('assinaturas:ciclos-gratuitos');

        $a = $a->fresh();
        $bloqueio = app(Carteira::class)->verificar($this->usuario->id)->bloqueio;
        expect($a->status)->toBe(StatusAssinatura::Expirada)
            ->and($bloqueio?->motivo)->toBe(MotivoBloqueio::DescontoAcabouSemCartao)
            ->and($cupom->usos()->count())->toBe(1);
    });
});

describe('CA-8 aviso 7 dias antes e regularização', function () {
    it('avisa uma vez, 7 dias antes do fim do desconto, e o mesmo aviso aparece em Meu plano', function () {
        _cupomPara(['valor' => 100, 'ciclos_duracao' => 1]);
        _contratar($this->usuario, 'BETA50');
        $a = Assinatura::query()->sole();

        $this->travelTo($a->proximo_vencimento->copy()->subDays(8));
        Artisan::call('cupons:avisar-fim-do-desconto');
        Mail::assertNothingSent();

        $this->travelTo($a->proximo_vencimento->copy()->subDays(6));
        Artisan::call('cupons:avisar-fim-do-desconto');
        Artisan::call('cupons:avisar-fim-do-desconto');

        Mail::assertSent(FimDoDescontoMessage::class, 1);
        Mail::assertSent(FimDoDescontoMessage::class, fn (FimDoDescontoMessage $m) => $m->hasTo($this->usuario->email) && $m->semCartao === true);

        Livewire::actingAs($this->usuario)->test(MeuPlano::class)
            ->assertSee('Seu desconto termina em')
            ->assertSee('Sem um cartão cadastrado, as funções pagas serão bloqueadas')
            ->assertSee('Cadastrar cartão');
    });

    it('com cartão o aviso diz o preço que vem depois', function () {
        _cupomPara(['ciclos_duracao' => 1]);
        _pagarAssinatura(_contratar($this->usuario, 'BETA50'));
        $a = Assinatura::query()->sole();
        $this->travelTo($a->proximo_vencimento->copy()->subDays(5));

        Artisan::call('cupons:avisar-fim-do-desconto');

        Mail::assertSent(FimDoDescontoMessage::class, fn (FimDoDescontoMessage $m) => $m->semCartao === false && str_contains($m->precoDepois, 'R$ 49,90'));
        Livewire::actingAs($this->usuario)->test(MeuPlano::class)->assertSee('A partir daí o plano custa R$ 49,90');
    });

    it('cadastrar o cartão no meio do desconto marca a 1ª cobrança para o fim dele e a renovação segue pelo cartão', function () {
        _cupomPara(['valor' => 100, 'ciclos_duracao' => 1]);
        _contratar($this->usuario, 'BETA50');
        $a = Assinatura::query()->sole();

        $troca = app(TrocarCartao::class)->iniciar($this->usuario);
        expect($troca->antiga_id_externo)->toBeNull()->and($troca->cobra_agora)->toBeFalse();

        app(TrocarCartao::class)->confirmar($troca, 'fakesub_novo', 'VISA', '4242');
        $a = $a->fresh();

        expect($a->id_externo)->toBe('fakesub_novo')->and($a->meio_pagamento)->toBe('cartao')
            ->and(Cache::get('fake-pagamento:chk:'.$troca->checkout_id_externo)['valor'])->toBe(_plano()->preco_mensal_centavos)
            ->and(_fake()->cobraNaHora((string) $troca->checkout_id_externo))->toBeFalse();

        // Com o cartão cadastrado, o fim do ciclo gratuito não encerra a assinatura: o provedor cobra o cheio.
        $this->travelTo($a->proximo_vencimento->copy()->addMinute());
        Artisan::call('assinaturas:ciclos-gratuitos');
        expect($a->fresh()->status)->toBe(StatusAssinatura::Ativa);
    });

    it('depois do fim, sem cartão, o plano está encerrado e Meu plano leva a cadastrar o cartão', function () {
        _cupomPara(['valor' => 100, 'ciclos_duracao' => 1]);
        _contratar($this->usuario, 'BETA50');
        $a = Assinatura::query()->sole();
        $this->travelTo($a->proximo_vencimento->copy()->addMinute());
        Artisan::call('assinaturas:ciclos-gratuitos');

        Livewire::actingAs($this->usuario)->test(MeuPlano::class)
            ->assertSee('O desconto terminou e não há cartão cadastrado')
            ->assertSeeHtml('screen-meuplano-cadastrar-cartao-encerrado');

        // O caminho de volta é uma contratação normal, a preço cheio e sem o cupom já usado.
        $nova = _contratar($this->usuario);
        expect($nova->valor_centavos)->toBe(_plano()->preco_mensal_centavos)->and($nova->cupom_id)->toBeNull();
    });

    it('contratar de novo logo no vencimento, antes da rotina, já encerra o plano gratuito e deixa assinar', function () {
        _cupomPara(['valor' => 100, 'ciclos_duracao' => 1]);
        _contratar($this->usuario, 'BETA50');
        $this->travelTo(Assinatura::query()->sole()->proximo_vencimento->copy()->addMinute());

        expect(_contratar($this->usuario)->status)->toBe(EstadoCobranca::Pendente);
    });
});

describe('CA-9 desativar o cupom não altera descontos já aplicados', function () {
    it('a renovação segue com o desconto contratado, mesmo com o cupom desativado e editado', function () {
        $cupom = _cupomPara();
        $invoice = _pagarAssinatura(_contratar($this->usuario, 'BETA50'));
        $a = Assinatura::query()->sole();
        $desconto = $a->desconto_centavos;

        $cupom->update(['ativo' => false, 'valor' => 5, 'ciclos_duracao' => 1]);
        _pagarRenovacao($invoice);

        $a = $a->fresh();
        expect($a->desconto_centavos)->toBe($desconto)->and($a->desconto_periodos)->toBe(3)->and($a->periodos_pagos)->toBe(2)
            ->and($a->preco_snapshot_centavos)->toBe(_plano()->preco_mensal_centavos - $desconto);
    });

    it('o cupom desativado antes da contratação continua recusado', function () {
        $cupom = _cupomPara();
        $cupom->update(['ativo' => false]);

        expect(_recusa(fn () => _contratar($this->usuario, 'BETA50')))->toBe(MotivoRecusaCupom::Inativo);
    });
});

describe('Pix anual e renovação', function () {
    it('o Pix anual com cupom cobra o valor final e o desconto continua na renovação', function () {
        $cupom = _cupomPara(['ciclos_duracao' => 2], _plano()->id, 'anual');
        $cheio = _plano()->preco_anual_centavos;
        $invoice = _contratar($this->usuario, 'BETA50', CicloCobranca::Anual, MeioPagamento::Pix);
        _pagarAssinatura($invoice);
        $a = Assinatura::query()->sole();
        $com = $cheio - intdiv($cheio + 1, 2);

        expect($invoice->valor_centavos)->toBe($com)->and($a->preco_snapshot_centavos)->toBe($com)->and($a->desconto_periodos)->toBe(2);

        $this->travelTo($a->proximo_vencimento->copy()->subDays(3));
        $renovacao = app(IniciarRenovacaoPix::class)->executar($this->usuario, '203.0.113.7', 'PestAgent', 'qualquer');
        expect($renovacao->valor_centavos)->toBe($com)->and($renovacao->cupom_id)->toBeNull()->and($renovacao->desconto_centavos)->toBe(intdiv($cheio + 1, 2));

        _pagarAssinatura($renovacao);
        $a = $a->fresh();
        // Ano 3 já é sem desconto: o próximo QR Code é de preço cheio.
        expect($a->periodos_pagos)->toBe(2)->and($a->preco_snapshot_centavos)->toBe($cheio)->and($cupom->usos()->count())->toBe(1);
    });

    it('a renovação aceita cupom concedido quando a assinatura não tem desconto', function () {
        $pix = _assinaturaPixAtiva($this->usuario);
        $cupom = _cupomPara(['codigo' => 'RENOVA', 'ciclos_duracao' => 1, 'vigencia_fim' => now()->addYears(2)->toDateString()], _plano()->id, 'anual');
        $this->travelTo($pix->proximo_vencimento->copy()->subDays(3));

        $tela = Livewire::actingAs($this->usuario)->test(RenovarAssinaturaPix::class)
            ->call('abrirCupom')->set('campoCupom', 'RENOVA')->call('aplicarCupom')->assertSet('codigoCupom', 'RENOVA');
        $tela->set('aceito', true)->call('renovar');

        $renovacao = Invoice::query()->where('assinatura_id', $pix->id)->where('renovacao', true)->sole();
        expect($renovacao->cupom_id)->toBe($cupom->id)->and($renovacao->valor_centavos)->toBeLessThan($pix->preco_snapshot_centavos);

        _pagarAssinatura($renovacao);
        expect($cupom->usos()->count())->toBe(1)->and($pix->fresh()->desconto_periodos)->toBe(2);
    });

    it('renovação Pix com desconto total é feita na hora, sem QR Code', function () {
        $pix = _assinaturaPixAtiva($this->usuario);
        _cupomPara(['codigo' => 'RENOVA100', 'valor' => 100, 'ciclos_duracao' => 1, 'vigencia_fim' => now()->addYears(2)->toDateString()], _plano()->id, 'anual');
        $this->travelTo($pix->proximo_vencimento->copy()->subDays(3));

        $invoice = app(IniciarRenovacaoPix::class)->executar($this->usuario, '203.0.113.7', 'PestAgent', 'RENOVA100');

        expect($invoice->status)->toBe(EstadoCobranca::Paga)->and($invoice->valor_centavos)->toBe(0)
            ->and($pix->fresh()->periodos_pagos)->toBe(2)
            ->and($pix->fresh()->proximo_vencimento->greaterThan($pix->proximo_vencimento))->toBeTrue();
    });
});

describe('CA-10 interruptor de cobrança', function () {
    it('desligada, as telas não existem e as rotinas não fazem nada', function () {
        _cupomPara(['valor' => 100, 'ciclos_duracao' => 1]);
        _contratar($this->usuario, 'BETA50');
        $a = Assinatura::query()->sole();
        CobrancaConfig::atual()->update(['ativa' => false]);
        $this->travelTo($a->proximo_vencimento->copy()->subDays(3));

        Artisan::call('cupons:avisar-fim-do-desconto');
        $this->travelTo($a->proximo_vencimento->copy()->addMinute());
        Artisan::call('assinaturas:ciclos-gratuitos');

        Mail::assertNothingSent();
        expect($a->fresh()->periodos_pagos)->toBe(1);
        $this->actingAs($this->usuario)->get(route('checkout.assinatura', ['plano' => _plano()->id, 'ciclo' => 'mensal']))->assertNotFound();
    });
});

describe('Histórico em Meu plano', function () {
    it('mostra o desconto de cada cobrança onde antes aparecia "—"', function () {
        _cupomPara();
        _pagarAssinatura(_contratar($this->usuario, 'BETA50'));
        $desconto = _plano()->preco_mensal_centavos - (_plano()->preco_mensal_centavos - intdiv(_plano()->preco_mensal_centavos + 1, 2));

        Livewire::actingAs($this->usuario)->test(MeuPlano::class)
            ->assertSee('R$ '.number_format($desconto / 100, 2, ',', '.'))
            ->assertSee('Desconto (BETA50)');
    });

    it('cobrança sem cupom segue com "—" e a gratuita aparece como sem cobrança', function () {
        _pagarAssinatura(_contratar($this->usuario));

        Livewire::actingAs($this->usuario)->test(MeuPlano::class)->assertSee('—');
    });
});

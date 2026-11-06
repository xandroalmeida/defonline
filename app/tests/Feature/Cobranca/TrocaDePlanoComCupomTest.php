<?php

declare(strict_types=1);

use App\Domain\Cobranca\CicloCobranca;
use App\Domain\Cobranca\EstadoCobranca;
use App\Domain\Cobranca\MeioPagamento;
use App\Infrastructure\Cobranca\Fake\FakePaymentProvider;
use App\Livewire\Cobranca\TrocarPlano;
use App\Models\Assinatura;
use App\Models\CupomUso;
use App\Models\Invoice;
use App\Models\Usuario;
use App\Services\Cobranca\IniciarAssinatura;
use App\Services\Cobranca\TrocarDePlano;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

/**
 * STORY-082 × STORY-083 — regra combinada de troca de plano e cupom. Catálogo: Básico R$ 49,90/mês e Pro R$ 199,00/mês;
 * o cupom é de 50% por 3 ciclos. O crédito usa o preço COM o desconto do ciclo em vigor; o cupom vinculado não é
 * reaplicado; o desconto só continua se o cupom cobre o plano novo, senão termina na troca.
 */
beforeEach(function () {
    _usarProvedor('fake');
    Mail::fake();
    $this->usuario = _compradorConfirmado();
    $this->pro = _plano('Pro');
    $this->basico = _plano('Básico');
});

/** Contrata o plano com o cupom, paga e leva o relógio ao 10º dia (20 de 30 dias restantes). */
function _comCupomNoMeioDoCiclo(Usuario $u, string $plano, bool $cupomCobreOutro): Assinatura
{
    $cupom = _cupomPara(planoId: _plano($plano)->id);

    if ($cupomCobreOutro) {
        $cupom->itens()->create(['plano_id' => _plano($plano === 'Pro' ? 'Básico' : 'Pro')->id, 'ciclo' => 'mensal']);
    }

    $invoice = app(IniciarAssinatura::class)->executar($u, _plano($plano), CicloCobranca::Mensal, '203.0.113.7', 'PestAgent', MeioPagamento::Cartao, $cupom->codigo);
    _pagarAssinatura($invoice);
    $a = Assinatura::query()->where('usuario_id', $u->id)->sole();
    test()->travelTo($a->data_inicio->copy()->addDays(10));

    return $a->fresh();
}

function _proporcional(int $valor, int $dias = 20, int $total = 30): int
{
    return (int) round($valor * $dias / $total);
}

describe('upgrade com cupom que cobre os dois planos', function () {
    it('o crédito é o do preço COM desconto e o plano novo também tem o desconto; o cupom não é reaplicado', function () {
        $a = _comCupomNoMeioDoCiclo($this->usuario, 'Básico', true);
        $usosAntes = CupomUso::query()->count();

        $previa = app(TrocarDePlano::class)->previa(app(TrocarDePlano::class)->assinaturaDaTroca($this->usuario), $this->pro, CicloCobranca::Mensal);

        // Básico pagou 2.495 (50% de 4.990); Pro com o mesmo cupom seria 9.950.
        $credito = _proporcional(2495);
        $parcela = _proporcional(9950);
        expect($previa->creditoCentavos)->toBe($credito)->and($credito)->not->toBe(_proporcional(4990))
            ->and($previa->parcelaNovaCentavos)->toBe($parcela)
            ->and($previa->aPagarCentavos)->toBe($parcela - $credito)
            ->and($previa->descontoNovo)->not->toBeNull()->and($previa->descontoTermina)->toBeFalse()
            ->and($previa->precoNovoCheioCentavos)->toBe(19900);

        $feito = app(TrocarDePlano::class)->confirmar($this->usuario, $this->pro, CicloCobranca::Mensal);
        expect($feito->invoice->fresh()->cupom_id)->toBeNull()->and($feito->invoice->fresh()->desconto_centavos)->toBe(0);
        _pagarAssinatura($feito->invoice);

        $a->refresh();
        expect($a->plano_id)->toBe($this->pro->id)
            ->and($a->preco_cheio_centavos)->toBe(19900)->and($a->desconto_centavos)->toBe(9950)->and($a->desconto_periodos)->toBe(3)
            ->and($a->preco_snapshot_centavos)->toBe(9950)
            ->and(FakePaymentProvider::valorCobradoDaAssinatura((string) $a->id_externo))->toBe(9950)
            ->and(CupomUso::query()->count())->toBe($usosAntes);
    });

    it('o valor nunca fica negativo e uma troca coberta por desconto total é aplicada sem cobrança', function () {
        $cupom = _cupomPara(['codigo' => 'TOTAL', 'valor' => 1000], planoId: $this->basico->id);
        $cupom->itens()->create(['plano_id' => $this->pro->id, 'ciclo' => 'mensal']);
        $invoice = app(IniciarAssinatura::class)->executar($this->usuario, $this->basico, CicloCobranca::Mensal, '203.0.113.7', 'PestAgent', MeioPagamento::Cartao, 'TOTAL');
        expect($invoice->valor_centavos)->toBe(0);
        $a = Assinatura::query()->where('usuario_id', $this->usuario->id)->sole();
        $this->travelTo($a->data_inicio->copy()->addDays(10));

        $previa = app(TrocarDePlano::class)->previa(app(TrocarDePlano::class)->assinaturaDaTroca($this->usuario), $this->pro, CicloCobranca::Mensal);

        expect($previa->creditoCentavos)->toBe(0)->and($previa->aPagarCentavos)->toBe(0)->and($previa->semCobranca)->toBeTrue();

        app(TrocarDePlano::class)->confirmar($this->usuario, $this->pro, CicloCobranca::Mensal);
        $a->refresh();
        expect($a->plano_id)->toBe($this->pro->id)->and($a->desconto_centavos)->toBe(19900)->and($a->preco_snapshot_centavos)->toBe(0);
    });
});

describe('upgrade com cupom que NÃO cobre o plano novo', function () {
    it('o desconto termina na troca, o crédito usa o preço com desconto e a tela de revisão avisa', function () {
        $a = _comCupomNoMeioDoCiclo($this->usuario, 'Básico', false);

        Livewire::actingAs($this->usuario)->test(TrocarPlano::class, ['plano' => $this->pro->id, 'ciclo' => 'mensal'])
            ->assertSee('Seu desconto de cupom não vale para o Pro')
            ->assertSee('ele termina nesta troca');

        $previa = app(TrocarDePlano::class)->previa(app(TrocarDePlano::class)->assinaturaDaTroca($this->usuario), $this->pro, CicloCobranca::Mensal);
        $credito = _proporcional(2495);

        expect($previa->descontoTermina)->toBeTrue()->and($previa->descontoNovo)->toBeNull()
            ->and($previa->creditoCentavos)->toBe($credito)
            ->and($previa->aPagarCentavos)->toBe(_proporcional(19900) - $credito);

        _pagarAssinatura(app(TrocarDePlano::class)->confirmar($this->usuario, $this->pro, CicloCobranca::Mensal)->invoice);

        $a->refresh();
        expect($a->plano_id)->toBe($this->pro->id)
            ->and($a->desconto_centavos)->toBe(0)->and($a->preco_cheio_centavos)->toBeNull()->and($a->desconto())->toBeNull()
            ->and($a->preco_snapshot_centavos)->toBe(19900)
            ->and(FakePaymentProvider::valorCobradoDaAssinatura((string) $a->id_externo))->toBe(19900)
            ->and(CupomUso::query()->count())->toBe(1);
    });

    it('sem desconto a vencer em ciclos futuros, não há aviso (ele acabaria de qualquer jeito)', function () {
        $cupom = _cupomPara(['ciclos_duracao' => 1], planoId: $this->basico->id);
        _pagarAssinatura(app(IniciarAssinatura::class)->executar($this->usuario, $this->basico, CicloCobranca::Mensal, '203.0.113.7', 'PestAgent', MeioPagamento::Cartao, $cupom->codigo));
        $a = Assinatura::query()->sole();
        $this->travelTo($a->data_inicio->copy()->addDays(10));

        $previa = app(TrocarDePlano::class)->previa(app(TrocarDePlano::class)->assinaturaDaTroca($this->usuario), $this->pro, CicloCobranca::Mensal);

        expect($previa->descontoTermina)->toBeFalse()->and($previa->creditoCentavos)->toBe(_proporcional(2495));
    });
});

describe('downgrade agendado com cupom', function () {
    it('o desconto que continua no plano novo vale na renovação, sem novo uso do cupom', function () {
        $primeira = _comCupomNoMeioDoCiclo($this->usuario, 'Pro', true);
        $invoice = Invoice::query()->where('assinatura_id', $primeira->id)->sole();

        app(TrocarDePlano::class)->confirmar($this->usuario, $this->basico, CicloCobranca::Mensal);
        $a = $primeira->fresh();
        expect($a->troca_plano_id)->toBe($this->basico->id)
            ->and($a->troca_preco_cheio_centavos)->toBe(4990)->and($a->troca_desconto_centavos)->toBe(2495)->and($a->troca_preco_centavos)->toBe(2495)
            ->and(FakePaymentProvider::valorCobradoDaAssinatura((string) $a->id_externo))->toBe(2495);

        _pagarRenovacao($invoice);

        $a->refresh();
        $renovacao = Invoice::query()->where('assinatura_id', $a->id)->where('renovacao', true)->sole();
        expect($a->plano_id)->toBe($this->basico->id)->and($a->troca_plano_id)->toBeNull()->and($a->troca_desconto_centavos)->toBeNull()
            ->and($a->preco_cheio_centavos)->toBe(4990)->and($a->desconto_centavos)->toBe(2495)->and($a->preco_snapshot_centavos)->toBe(2495)
            ->and($renovacao->valor_centavos)->toBe(2495)->and($renovacao->status)->toBe(EstadoCobranca::Paga)
            ->and(CupomUso::query()->count())->toBe(1);
    });

    it('se o cupom não cobre o plano novo, o desconto termina na renovação e a cobrança é a cheia', function () {
        $primeira = _comCupomNoMeioDoCiclo($this->usuario, 'Pro', false);
        $invoice = Invoice::query()->where('assinatura_id', $primeira->id)->sole();

        app(TrocarDePlano::class)->confirmar($this->usuario, $this->basico, CicloCobranca::Mensal);
        $a = $primeira->fresh();
        expect($a->troca_desconto_centavos)->toBe(0)->and($a->troca_preco_centavos)->toBe(4990)
            ->and(FakePaymentProvider::valorCobradoDaAssinatura((string) $a->id_externo))->toBe(4990);

        _pagarRenovacao($invoice);

        $a->refresh();
        expect($a->plano_id)->toBe($this->basico->id)->and($a->desconto())->toBeNull()->and($a->preco_snapshot_centavos)->toBe(4990)
            ->and(Invoice::query()->where('assinatura_id', $a->id)->where('renovacao', true)->sole()->valor_centavos)->toBe(4990);
    });

    it('desfazer o downgrade limpa também o retrato do desconto agendado', function () {
        $primeira = _comCupomNoMeioDoCiclo($this->usuario, 'Pro', true);
        app(TrocarDePlano::class)->confirmar($this->usuario, $this->basico, CicloCobranca::Mensal);

        app(TrocarDePlano::class)->desfazer($this->usuario);

        $a = $primeira->fresh();
        expect($a->troca_plano_id)->toBeNull()->and($a->troca_preco_cheio_centavos)->toBeNull()->and($a->troca_desconto_centavos)->toBeNull()
            ->and(FakePaymentProvider::valorCobradoDaAssinatura((string) $a->id_externo))->toBe($a->preco_snapshot_centavos);
    });
});

<?php

declare(strict_types=1);

use App\Domain\Cobranca\CicloCobranca;
use App\Domain\Cobranca\EstadoCobranca;
use App\Domain\Cobranca\MeioPagamento;
use App\Domain\Cobranca\MotivoRecusaCupom;
use App\Domain\Cobranca\StatusAssinatura;
use App\Domain\Cobranca\TipoDesconto;
use App\Infrastructure\Cobranca\Fake\FakePaymentProvider;
use App\Livewire\Backoffice\Financeiro;
use App\Models\Assinatura;
use App\Models\CupomUso;
use App\Models\EventoProduto;
use App\Models\Invoice;
use App\Models\Usuario;
use App\Observabilidade\EventosCobranca;
use App\Observabilidade\MetricasDeCobranca;
use App\Services\Cobranca\IniciarAssinatura;
use App\Services\Cobranca\IniciarCompraDeCreditos;
use App\Services\Cobranca\ReembolsarCobranca;
use App\Services\Cobranca\ReembolsoNaoRealizado;
use App\Services\Cobranca\TrocarDePlano;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

/**
 * Integração das pistas 082, 083, 085 e 086: cupom_aplicado, contratação sem cobrança no funil, reembolso com
 * cupom, reembolso de troca de plano e Invoice de valor zero. Provedor fake.
 */
beforeEach(function () {
    DB::statement("SET LOCAL TIME ZONE 'UTC'");
    _usarProvedor('fake');
    Mail::fake();
    $this->usuario = _compradorConfirmado();
    $this->admin = Usuario::factory()->create();
    $this->admin->forceFill(['permissoes_admin' => ['financeiro.gerenciar']])->save();
    $this->pro = _plano('Pro');
    $this->basico = _plano('Básico');
    EventoProduto::creating(function (EventoProduto $e): void {
        $e->ocorrido_em = now();
    });
});

afterEach(fn () => EventoProduto::flushEventListeners());

function _eventos(string $nome): array
{
    return EventoProduto::query()->where('nome_evento', $nome)->orderBy('id')->get()->map(fn ($e) => $e->propriedades->getArrayCopy())->all();
}

describe('1 — cupom_aplicado onde o uso é gravado', function () {
    it('contratação paga: o evento sai uma vez, com cupom, invoice e desconto, sem dado pessoal', function () {
        $cupom = _cupomPara();
        $invoice = _contratar($this->usuario, $cupom->codigo);
        expect(_eventos(EventosCobranca::CUPOM_APLICADO))->toBe([]);   // reservar a vaga não é aplicar

        _pagarAssinatura($invoice);
        _entregar(_fake()->simularPagamento((string) $invoice->checkout_id_externo, 'evt-repetido'));

        $eventos = _eventos(EventosCobranca::CUPOM_APLICADO);
        expect($eventos)->toHaveCount(1)
            ->and($eventos[0])->toEqual(['cupom_id' => $cupom->id, 'tipo' => 'assinatura', 'invoice_id' => $invoice->id, 'desconto_centavos' => $invoice->fresh()->desconto_centavos])
            ->and(EventoProduto::query()->where('nome_evento', EventosCobranca::CUPOM_APLICADO)->sole()->usuario_id)->toBe($this->usuario->id);
    });

    it('contratação SEM cobrança também emite, e compra de créditos emite como crédito', function () {
        $total = _cupomPara(['codigo' => 'TOTAL', 'valor' => 100]);
        $semCobranca = _contratar($this->usuario, 'TOTAL');
        expect($semCobranca->status)->toBe(EstadoCobranca::Paga);

        $outro = _compradorConfirmado();
        $creditos = _cupomPara(['codigo' => 'CRED', 'valor' => 100], pacoteId: _pacote('plus')->id);
        $compra = app(IniciarCompraDeCreditos::class)->executar($outro, _pacote('plus'), null, null, MeioPagamento::Pix, 'CRED');
        expect($compra->status)->toBe(EstadoCobranca::Paga);

        $eventos = _eventos(EventosCobranca::CUPOM_APLICADO);
        expect($eventos)->toHaveCount(2)
            ->and(collect($eventos)->pluck('tipo')->sort()->values()->all())->toBe(['assinatura', 'credito'])
            ->and(collect($eventos)->pluck('invoice_id')->sort()->values()->all())->toBe(collect([$semCobranca->id, $compra->id])->sort()->values()->all());
        expect($total->usos()->count())->toBe(1)->and($creditos->usos()->count())->toBe(1);
    });

    it('renovação com o desconto que continua não repete o evento (o uso do cupom é um só)', function () {
        $cupom = _cupomPara();
        $invoice = _pagarAssinatura(_contratar($this->usuario, $cupom->codigo));

        _pagarRenovacao($invoice);

        expect(_eventos(EventosCobranca::CUPOM_APLICADO))->toHaveCount(1);
    });
});

describe('2 — contratação sem cobrança no funil, fora da taxa de falha', function () {
    it('assinatura sem cobrança: checkout_iniciado e contratacao_concluida, e nenhum fato de pagamento', function () {
        _cupomPara(['codigo' => 'TOTAL', 'valor' => 100]);
        $invoice = _contratar($this->usuario, 'TOTAL');

        $iniciados = _eventos(EventosCobranca::CHECKOUT_INICIADO);
        $concluidos = _eventos(EventosCobranca::CONTRATACAO_CONCLUIDA);
        expect($iniciados)->toHaveCount(1)->and($iniciados[0]['invoice_id'])->toBe($invoice->id)->and($iniciados[0]['valor_centavos'])->toBe(0)
            ->and($concluidos)->toHaveCount(1)->and($concluidos[0]['invoice_id'])->toBe($invoice->id)->and($concluidos[0]['meio'])->toBe('nenhum')
            ->and(_eventos(EventosCobranca::PAGAMENTO_CONFIRMADO))->toBe([])
            ->and(_eventos(EventosCobranca::PAGAMENTO_RECUSADO))->toBe([]);
    });

    it('abaixo do mínimo do provedor também é contratação sem cobrança', function () {
        _cupomPara(['codigo' => 'QUASE', 'tipo' => TipoDesconto::ValorFixo, 'valor' => _plano()->preco_mensal_centavos - 300]);
        $invoice = _contratar($this->usuario, 'QUASE');

        expect($invoice->status)->toBe(EstadoCobranca::Paga)->and($invoice->valor_centavos)->toBe(0)
            ->and(_eventos(EventosCobranca::CONTRATACAO_CONCLUIDA))->toHaveCount(1);
    });

    it('compra de créditos sem cobrança entra no funil e não vira pagamento', function () {
        _cupomPara(['codigo' => 'CRED', 'valor' => 100], pacoteId: _pacote('plus')->id);
        $compra = app(IniciarCompraDeCreditos::class)->executar($this->usuario, _pacote('plus'), null, null, MeioPagamento::Pix, 'CRED');

        expect(_eventos(EventosCobranca::CHECKOUT_INICIADO))->toHaveCount(1)
            ->and(_eventos(EventosCobranca::CONTRATACAO_CONCLUIDA))->toHaveCount(1)
            ->and(_eventos(EventosCobranca::CONTRATACAO_CONCLUIDA)[0]['invoice_id'])->toBe($compra->id)
            ->and(_eventos(EventosCobranca::PAGAMENTO_CONFIRMADO))->toBe([]);
    });

    it('a conversão conta a contratação sem cobrança e a taxa de falha de pagamento não se mexe', function () {
        _cupomPara(['codigo' => 'TOTAL', 'valor' => 100]);
        _contratar($this->usuario, 'TOTAL');
        $outro = _compradorConfirmado();
        $pago = _pagarAssinatura(app(IniciarAssinatura::class)->executar($outro, _plano(), CicloCobranca::Mensal, '203.0.113.7', 'PestAgent', MeioPagamento::Cartao));
        $recusa = _compradorConfirmado();
        $recusada = app(IniciarAssinatura::class)->executar($recusa, _plano(), CicloCobranca::Mensal, '203.0.113.7', 'PestAgent', MeioPagamento::Cartao);
        _entregar(_fake()->simularRecusa((string) $recusada->checkout_id_externo));

        $metricas = app(MetricasDeCobranca::class);
        $conversao = $metricas->conversaoDoCheckout(now()->subDay(), now()->addDay());
        $falha = $metricas->falhaDePagamento(now()->subHour());

        // 3 checkouts, 2 concluídos (o gratuito e o pago): a taxa nunca passa de 100%.
        expect($conversao['iniciados'])->toBe(3)->and($conversao['concluidos'])->toBe(2)
            ->and($falha['confirmados'])->toBe(1)->and($falha['recusados'])->toBe(1)->and($falha['total'])->toBe(2)
            ->and($pago->status)->toBe(EstadoCobranca::Paga);
    });
});

describe('3 — reembolso e cupom', function () {
    it('estornar uma cobrança com cupom não devolve o uso nem reabre a vaga', function () {
        $cupom = _cupomPara(['limite_usos' => 1]);
        $invoice = _pagarAssinatura(_contratar($this->usuario, $cupom->codigo));

        app(ReembolsarCobranca::class)->solicitar($invoice, $this->admin, 'Arrependimento');

        expect($invoice->fresh()->status)->toBe(EstadoCobranca::Estornada)
            ->and(CupomUso::query()->where('cupom_id', $cupom->id)->count())->toBe(1)
            ->and($cupom->fresh()->usos()->where('usuario_id', $this->usuario->id)->exists())->toBeTrue();
        // A vaga única continua tomada: nem a mesma conta nem outra conseguem usar o cupom.
        expect($cupom->fresh()->recusaPara($this->usuario->id, _plano()->id, 'mensal', null, now()))->toBe(MotivoRecusaCupom::LimiteEsgotado);
        expect($cupom->fresh()->recusaPara(_compradorConfirmado()->id, _plano()->id, 'mensal', null, now()))->toBe(MotivoRecusaCupom::LimiteEsgotado);
    });

    it('a tela mostra preço cheio, desconto, cupom e valor pago, e avisa que o uso não volta', function () {
        $cupom = _cupomPara();
        $invoice = _pagarAssinatura(_contratar($this->usuario, $cupom->codigo))->fresh();

        Livewire::actingAs($this->admin)->test(Financeiro::class)->call('abrirReembolso', $invoice->id)
            ->assertSee('Preço cheio R$ '.number_format($invoice->preco_cheio_centavos / 100, 2, ',', '.'))
            ->assertSee('desconto R$ '.number_format($invoice->desconto_centavos / 100, 2, ',', '.'))
            ->assertSee('(cupom '.$cupom->codigo.')')
            ->assertSee('pago R$ '.number_format($invoice->valor_centavos / 100, 2, ',', '.'))
            ->assertSee('o uso do cupom não é devolvido');
    });

    it('cobrança sem cupom não mostra a linha de desconto', function () {
        $invoice = _pagarAssinatura(_contratar($this->usuario));

        Livewire::actingAs($this->admin)->test(Financeiro::class)->call('abrirReembolso', $invoice->id)->assertDontSee('Preço cheio');
    });

    it('Invoice de valor zero não oferece reembolso: nem botão, nem painel, nem serviço', function () {
        _cupomPara(['codigo' => 'TOTAL', 'valor' => 100]);
        $zero = _contratar($this->usuario, 'TOTAL');
        $zero->forceFill(['reembolso_estado' => null])->save();

        $tela = Livewire::actingAs($this->admin)->test(Financeiro::class)
            ->assertDontSeeHtml('dusk="cobranca-reembolsar-'.$zero->id.'"')
            ->call('abrirReembolso', $zero->id)
            ->assertSee('não há o que reembolsar')
            ->assertSet('reembolsoId', null);

        expect(fn () => app(ReembolsarCobranca::class)->solicitar($zero, $this->admin, 'x'))->toThrow(ReembolsoNaoRealizado::class);
        expect($zero->fresh()->status)->toBe(EstadoCobranca::Paga)->and($zero->fresh()->reembolso_estado)->toBeNull();
        // E não entra na lista de "pagas sem nota fiscal".
        $tela->assertDontSee($zero->descricao.' · ');
    });
});

describe('4 — reembolso da diferença de uma troca de plano', function () {
    /** Básico mensal no 10º dia, upgrade para o Pro pago; devolve a Invoice da troca e a assinatura. */
    function _upgradePago(Usuario $u): array
    {
        [$a] = _assinaturaAtivaNoFake($u);
        test()->travelTo($a->data_inicio->copy()->addDays(10));
        $invoice = app(TrocarDePlano::class)->confirmar($u, _plano('Pro'), CicloCobranca::Mensal)->invoice;
        _pagarAssinatura($invoice);

        return [$invoice->fresh(), $a->fresh()];
    }

    it('estornar a diferença volta ao plano anterior no mesmo ciclo, com o uso mantido, sem encerrar a assinatura', function () {
        [$invoice, $a] = _upgradePago($this->usuario);
        expect($a->plano_id)->toBe($this->pro->id)->and($a->cota_snapshot)->toBe(10);
        // O upgrade deixou 1 + 6 (proporcional, STORY-091 CA-3); usou 2 depois da troca.
        Assinatura::query()->whereKey($a->id)->update(['cota_restante_ciclo' => 5]);
        $vencimento = $a->proximo_vencimento->toIso8601String();

        $previa = app(ReembolsarCobranca::class)->previa($invoice);
        expect($previa->tipo)->toBe('troca')->and($previa->planoAnterior)->toBe('Básico')->and($previa->alerta)->toBeFalse();

        app(ReembolsarCobranca::class)->solicitar($invoice, $this->admin, 'Cobrado por engano');

        $a->refresh();
        expect($invoice->fresh()->status)->toBe(EstadoCobranca::Estornada)
            ->and($a->status)->toBe(StatusAssinatura::Ativa)
            ->and($a->plano_id)->toBe($this->basico->id)->and($a->cota_snapshot)->toBe(1)->and($a->cota_restante_ciclo)->toBe(0)
            ->and($a->preco_snapshot_centavos)->toBe($this->basico->preco_mensal_centavos)
            ->and($a->proximo_vencimento->toIso8601String())->toBe($vencimento)
            ->and($a->encerrada_em)->toBeNull()
            ->and($a->ajuste_provedor_pendente)->toBeTrue();

        // A rotina horária leva o valor do plano anterior ao provedor.
        app(TrocarDePlano::class)->reenviarAjustesPendentes();
        expect(FakePaymentProvider::valorCobradoDaAssinatura((string) $a->id_externo))->toBe($this->basico->preco_mensal_centavos)
            ->and($a->fresh()->ajuste_provedor_pendente)->toBeFalse();
    });

    it('se o ciclo já seguiu (renovou), o plano atual é mantido e a tela avisa ANTES de confirmar', function () {
        [$invoice, $a] = _upgradePago($this->usuario);
        $primeira = Invoice::query()->where('tipo', 'assinatura')->sole();
        _pagarRenovacao($primeira);

        $previa = app(ReembolsarCobranca::class)->previa($invoice);
        expect($previa->tipo)->toBe('nada')->and($previa->alerta)->toBeTrue()->and($previa->porqueNada)->toContain('Não dá para voltar ao plano anterior');

        Livewire::actingAs($this->admin)->test(Financeiro::class)->call('abrirReembolso', $invoice->id)
            ->assertSee('Não dá para voltar ao plano anterior')->assertSeeHtml('role="alert"');

        app(ReembolsarCobranca::class)->solicitar($invoice, $this->admin, 'Devolver a diferença');

        $a->refresh();
        expect($invoice->fresh()->status)->toBe(EstadoCobranca::Estornada)
            ->and($a->status)->toBe(StatusAssinatura::Ativa)->and($a->plano_id)->toBe($this->pro->id);
    });

    it('outra troca depois também impede voltar, e a assinatura nunca é encerrada por estornar uma troca', function () {
        [$invoice, $a] = _upgradePago($this->usuario);
        Assinatura::query()->whereKey($a->id)->update(['plano_id' => $this->basico->id]);

        $previa = app(ReembolsarCobranca::class)->previa($invoice);

        expect($previa->tipo)->toBe('nada')->and($previa->alerta)->toBeTrue()->and($previa->encerraAssinatura)->toBeFalse();
    });

    it('troca sem o registro do plano anterior (anterior à integração) só devolve o dinheiro, com aviso', function () {
        [$invoice] = _upgradePago($this->usuario);
        $invoice->forceFill(['troca_anterior' => null])->save();

        $previa = app(ReembolsarCobranca::class)->previa($invoice);

        expect($previa->tipo)->toBe('nada')->and($previa->alerta)->toBeTrue();
    });
});

<?php

declare(strict_types=1);

use App\Domain\Cobranca\Carteira;
use App\Domain\Cobranca\CicloCobranca;
use App\Domain\Cobranca\EstadoCobranca;
use App\Domain\Cobranca\MeioPagamento;
use App\Domain\Cobranca\MotivoBloqueio;
use App\Domain\Cobranca\ProvedorIndisponivel;
use App\Domain\Cobranca\StatusAssinatura;
use App\Domain\Cobranca\TipoEvento;
use App\Infrastructure\Cobranca\Fake\FakePaymentProvider;
use App\Mail\RenovacaoPixMessage;
use App\Models\Assinatura;
use App\Models\AuditLog;
use App\Models\CobrancaConfig;
use App\Models\Invoice;
use App\Models\Plano;
use App\Models\Usuario;
use App\Services\Cobranca\CompraNaoPermitida;
use App\Services\Cobranca\IniciarAssinatura;
use App\Services\Cobranca\IniciarRenovacaoPix;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

/**
 * STORY-080 — plano anual pago com Pix: Checkout Pix avulso por ano, sem assinatura no provedor,
 * renovação por um novo QR Code, aviso 7 dias antes e encerramento sem retentativa.
 */
beforeEach(function () {
    _usarProvedor('fake');
    Mail::fake();
    $this->usuario = _compradorConfirmado();
});

function _pixAnual($usuario): Invoice
{
    return _assinaturaPendente($usuario, null, CicloCobranca::Anual, MeioPagamento::Pix);
}

/** Leva o relógio para `$dias` antes do fim do ciclo da assinatura. */
function _viajarParaAntesDoFim(Assinatura $a, int $dias): void
{
    test()->travelTo($a->proximo_vencimento->copy()->subDays($dias));
}

describe('CA-1 Pix só no anual', function () {
    it('o Pix no mensal é recusado e nada é gravado', function () {
        expect(fn () => _assinaturaPendente($this->usuario, null, CicloCobranca::Mensal, MeioPagamento::Pix))
            ->toThrow(CompraNaoPermitida::class);
        expect(Invoice::query()->count())->toBe(0);
    });

    it('o anual abre um checkout Pix avulso, com o prazo do Pix e o meio na Invoice', function () {
        $invoice = _pixAnual($this->usuario);

        expect($invoice->meio)->toBe('pix')->and($invoice->ciclo)->toBe('anual')->and((bool) $invoice->renovacao)->toBeFalse()
            ->and($invoice->checkout_expira_em->between(now()->addMinutes(29), now()->addMinutes(31)))->toBeTrue();
    });

    it('Pix e cartão do mesmo plano não reaproveitam o checkout um do outro', function () {
        $pix = _pixAnual($this->usuario);
        $cartao = _assinaturaPendente($this->usuario, null, CicloCobranca::Anual);

        expect($cartao->id)->not->toBe($pix->id)->and(_pixAnual($this->usuario)->id)->toBe($pix->id);
    });

    it('no Asaas vai DETACHED só com PIX e sem assinatura', function () {
        _asaasSimulado();
        $usuario = _compradorConfirmado();

        _pixAnual($usuario);

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/checkouts')
            && $r->data()['billingTypes'] === ['PIX'] && $r->data()['chargeTypes'] === ['DETACHED']
            && ! array_key_exists('subscription', $r->data()) && $r->data()['minutesToExpire'] === 30);
    });

    it('falha do provedor deixa a Invoice como falhou por motivo do provedor', function () {
        FakePaymentProvider::falharProximoCheckout();

        expect(fn () => _pixAnual($this->usuario))->toThrow(ProvedorIndisponivel::class);
        expect(Invoice::query()->sole()->motivo_encerramento)->toBe('provedor');
    });
});

describe('CA-2 ativa só depois da confirmação', function () {
    it('Pix pendente não ativa nada', function () {
        _pixAnual($this->usuario);

        expect(Assinatura::query()->count())->toBe(0);
    });

    it('confirmado o Pix, a assinatura anual nasce sem vínculo recorrente no provedor', function () {
        $a = _assinaturaPixAtiva($this->usuario);

        expect($a->status)->toBe(StatusAssinatura::Ativa)
            ->and($a->pagoComPix())->toBeTrue()
            ->and($a->id_externo)->toBeNull()
            ->and($a->ciclo)->toBe('anual')
            ->and($a->periodos_pagos)->toBe(1)
            ->and($a->cota_restante_ciclo)->toBe($a->cota_snapshot)
            ->and($a->cartao_ultimos4)->toBeNull()
            ->and($a->proximo_vencimento->toDateString())->toBe($a->data_inicio->copy()->addYear()->toDateString())
            ->and($a->proxima_cota_em)->not->toBeNull();
    });

    it('o cartão continua marcado como cartão', function () {
        $invoice = _pagarAssinatura(_assinaturaPendente($this->usuario));

        expect(Assinatura::query()->findOrFail($invoice->assinatura_id)->meio_pagamento)->toBe('cartao');
    });
});

describe('CA-3 Pix expirado', function () {
    it('expirado não ativa e dá para gerar outro', function () {
        $invoice = _pixAnual($this->usuario);
        _entregar(_fake()->simularExpiracao((string) $invoice->checkout_id_externo));

        expect($invoice->fresh()->status)->toBe(EstadoCobranca::Vencida)->and(Assinatura::query()->count())->toBe(0);

        $novo = _pixAnual($this->usuario);
        expect($novo->id)->not->toBe($invoice->id)->and($novo->status)->toBe(EstadoCobranca::Pendente);
    });

    it('se o banco ainda confirmar o QR vencido, ativa uma vez', function () {
        $invoice = _pixAnual($this->usuario);
        _entregar(_fake()->simularExpiracao((string) $invoice->checkout_id_externo));
        _entregar(_fake()->simularPagamento((string) $invoice->checkout_id_externo));

        expect(Assinatura::query()->count())->toBe(1);
    });
});

describe('CA-6 idempotência', function () {
    it('o mesmo webhook duas vezes ativa uma vez', function () {
        $invoice = _pixAnual($this->usuario);
        $w = _fake()->simularPagamento((string) $invoice->checkout_id_externo, 'evt-pix-1');
        _entregar($w);
        _entregar($w);

        expect(Assinatura::query()->count())->toBe(1);
    });

    it('dois eventos da mesma cobrança ativam uma vez', function () {
        $invoice = _pixAnual($this->usuario);
        _entregar(_fake()->simularPagamento((string) $invoice->checkout_id_externo, 'evt-a'));
        _entregar(_fake()->webhook(TipoEvento::CobrancaPaga, (string) $invoice->checkout_id_externo, 'evt-b'));

        expect(Assinatura::query()->count())->toBe(1)->and(Assinatura::query()->sole()->periodos_pagos)->toBe(1);
    });

    it('a renovação avisada duas vezes renova uma vez', function () {
        $a = _assinaturaPixAtiva($this->usuario);
        _viajarParaAntesDoFim($a, 3);
        $renov = app(IniciarRenovacaoPix::class)->executar($this->usuario, null, null);

        _entregar(_fake()->simularPagamento((string) $renov->checkout_id_externo, 'evt-r1'));
        _entregar(_fake()->simularPagamento((string) $renov->checkout_id_externo, 'evt-r1'));
        _entregar(_fake()->webhook(TipoEvento::CobrancaPaga, (string) $renov->checkout_id_externo, 'evt-r2'));

        expect($a->fresh()->periodos_pagos)->toBe(2);
    });
});

describe('CA-4 renovação e aviso', function () {
    it('só abre nos últimos 7 dias', function () {
        $a = _assinaturaPixAtiva($this->usuario);
        _viajarParaAntesDoFim($a, 8);

        expect(fn () => app(IniciarRenovacaoPix::class)->executar($this->usuario, null, null))->toThrow(CompraNaoPermitida::class);
        expect(Invoice::query()->where('renovacao', true)->count())->toBe(0);
    });

    it('renova a partir do fim do ciclo, sem perder dias nem zerar a cota do mês', function () {
        $a = _assinaturaPixAtiva($this->usuario);
        $fimAntigo = $a->proximo_vencimento->copy();
        Assinatura::query()->whereKey($a->id)->update(['cota_restante_ciclo' => 0]);
        _viajarParaAntesDoFim($a, 2);

        $renov = app(IniciarRenovacaoPix::class)->executar($this->usuario, '203.0.113.7', 'Pest');
        expect($renov->renovacao)->toBeTrue()->and($renov->assinatura_id)->toBe($a->id)->and($renov->valor_centavos)->toBe($a->preco_snapshot_centavos);

        _entregar(_fake()->simularPagamento((string) $renov->checkout_id_externo));

        $a = $a->fresh();
        expect($a->status)->toBe(StatusAssinatura::Ativa)
            ->and($a->proximo_vencimento->toDateString())->toBe($fimAntigo->copy()->addYear()->toDateString())
            ->and($a->periodos_pagos)->toBe(2)
            ->and($a->cota_restante_ciclo)->toBe(0)
            ->and($a->proxima_cota_em->toDateString())->toBe($fimAntigo->toDateString())
            ->and($renov->fresh()->status)->toBe(EstadoCobranca::Paga)
            ->and(Assinatura::query()->count())->toBe(1);
    });

    it('a mesma tela reaproveita o QR em aberto', function () {
        $a = _assinaturaPixAtiva($this->usuario);
        _viajarParaAntesDoFim($a, 1);

        $um = app(IniciarRenovacaoPix::class)->executar($this->usuario, null, null);

        expect(app(IniciarRenovacaoPix::class)->executar($this->usuario, null, null)->id)->toBe($um->id);
    });

    it('o preço é o da última contratação e a cota, a contratada (retrato, STORY-091 CA-2)', function () {
        $a = _assinaturaPixAtiva($this->usuario);
        $plano = Plano::query()->findOrFail($a->plano_id);
        $contratada = $a->cota_snapshot;
        $plano->update(['preco_anual_centavos' => $plano->preco_anual_centavos + 5000, 'cota_mensal' => $plano->cota_mensal + 3]);
        _viajarParaAntesDoFim($a, 1);

        $renov = app(IniciarRenovacaoPix::class)->executar($this->usuario, null, null);
        _entregar(_fake()->simularPagamento((string) $renov->checkout_id_externo));

        expect($renov->valor_centavos)->toBe($a->preco_snapshot_centavos)->and($a->fresh()->cota_snapshot)->toBe($contratada);
    });

    it('recusa quem não tem plano Pix, plano de cartão, e-mail não confirmado, plano desativado e admin', function () {
        expect(fn () => app(IniciarRenovacaoPix::class)->executar($this->usuario, null, null))->toThrow(CompraNaoPermitida::class);

        $cartao = Usuario::factory()->create(['email_confirmed_at' => now()]);
        _pagarAssinatura(_assinaturaPendente($cartao, null, CicloCobranca::Anual));
        expect(fn () => app(IniciarRenovacaoPix::class)->executar($cartao, null, null))->toThrow(CompraNaoPermitida::class);

        $a = _assinaturaPixAtiva($this->usuario);
        _viajarParaAntesDoFim($a, 1);

        $naoConfirmado = Usuario::query()->find($this->usuario->id);
        $naoConfirmado->forceFill(['email_confirmed_at' => null])->save();
        expect(fn () => app(IniciarRenovacaoPix::class)->executar($naoConfirmado->fresh(), null, null))->toThrow(CompraNaoPermitida::class);
        $naoConfirmado->forceFill(['email_confirmed_at' => now()])->save();

        _personificar(_adminDaEquipe(), $this->usuario->fresh());
        expect(fn () => app(IniciarRenovacaoPix::class)->executar($this->usuario->fresh(), null, null))->toThrow(CompraNaoPermitida::class);
    });

    it('plano desativado não renova', function () {
        $a = _assinaturaPixAtiva($this->usuario);
        Plano::query()->whereKey($a->plano_id)->update(['ativo' => false]);
        _viajarParaAntesDoFim($a, 1);

        expect(fn () => app(IniciarRenovacaoPix::class)->executar($this->usuario, null, null))->toThrow(CompraNaoPermitida::class);
    });

    it('falha do provedor na renovação não renova nada', function () {
        $a = _assinaturaPixAtiva($this->usuario);
        _viajarParaAntesDoFim($a, 1);
        FakePaymentProvider::falharProximoCheckout();

        expect(fn () => app(IniciarRenovacaoPix::class)->executar($this->usuario, null, null))->toThrow(ProvedorIndisponivel::class);
        expect($a->fresh()->periodos_pagos)->toBe(1);
    });

    it('Pix da renovação pago depois do fim vira uma assinatura nova', function () {
        $a = _assinaturaPixAtiva($this->usuario);
        _viajarParaAntesDoFim($a, 0);
        $this->travelTo($a->proximo_vencimento->copy()->subMinutes(5));
        $renov = app(IniciarRenovacaoPix::class)->executar($this->usuario, null, null);
        $this->travelTo($a->proximo_vencimento->copy()->addMinutes(5));
        $this->artisan('assinaturas:pix-anual')->assertSuccessful();
        expect($a->fresh()->status)->toBe(StatusAssinatura::Expirada);

        _entregar(_fake()->simularPagamento((string) $renov->checkout_id_externo));

        $nova = Assinatura::query()->where('status', 'ativa')->sole();
        expect($nova->id)->not->toBe($a->id)->and($renov->fresh()->assinatura_id)->toBe($nova->id);
    });

    it('o aviso sai uma vez por ciclo, 7 dias antes, com a data e o link', function () {
        $a = _assinaturaPixAtiva($this->usuario);

        _viajarParaAntesDoFim($a, 8);
        $this->artisan('assinaturas:pix-anual')->assertSuccessful();
        Mail::assertNothingSent();

        _viajarParaAntesDoFim($a, 6);
        $this->artisan('assinaturas:pix-anual')->assertSuccessful();
        $this->artisan('assinaturas:pix-anual')->assertSuccessful();

        Mail::assertSent(RenovacaoPixMessage::class, 1);
        Mail::assertSent(RenovacaoPixMessage::class, fn (RenovacaoPixMessage $m) => $m->hasTo($this->usuario->email)
            && $m->dataFim === $a->proximo_vencimento->copy()->setTimezone('America/Sao_Paulo')->format('d/m/Y')
            && $m->link === route('assinatura.renovar') && str_contains($m->valor, 'R$')
            && str_contains($m->envelope()->subject, $m->dataFim));
        $html = (new RenovacaoPixMessage('Ana', 'Básico', '05/11/2027', '05/11/2028', 'R$ 399,00', 'https://x'))->render();
        expect($html)->toContain('não renova sozinho')->toContain('05/11/2028')->toContain('Pagar renovação');
    });

    it('depois de renovar, o próximo ciclo ganha um novo aviso', function () {
        $a = _assinaturaPixAtiva($this->usuario);
        _viajarParaAntesDoFim($a, 5);
        $this->artisan('assinaturas:pix-anual')->assertSuccessful();
        $renov = app(IniciarRenovacaoPix::class)->executar($this->usuario, null, null);
        _entregar(_fake()->simularPagamento((string) $renov->checkout_id_externo));

        $this->travelTo($a->fresh()->proximo_vencimento->copy()->subDays(4));
        $this->artisan('assinaturas:pix-anual')->assertSuccessful();

        Mail::assertSent(RenovacaoPixMessage::class, 2);
    });

    it('cartão, mensal, trial e plano já vencido não recebem o aviso', function () {
        $cartao = Usuario::factory()->create(['email_confirmed_at' => now()]);
        $c = Assinatura::query()->findOrFail(_pagarAssinatura(_assinaturaPendente($cartao, null, CicloCobranca::Anual))->assinatura_id);
        $this->travelTo($c->proximo_vencimento->copy()->subDays(2));

        $this->artisan('assinaturas:pix-anual')->assertSuccessful();

        Mail::assertNothingSent();
    });

    it('falha no envio não marca o aviso e a rotina acusa falha', function () {
        $a = _assinaturaPixAtiva($this->usuario);
        _viajarParaAntesDoFim($a, 2);
        Mail::shouldReceive('to')->andThrow(new RuntimeException('smtp fora'));

        $this->artisan('assinaturas:pix-anual')->assertFailed();

        expect($a->fresh()->aviso_renovacao_pix_para)->toBeNull();
    });

    it('assinatura sem plano conta como falha sem derrubar a rotina', function () {
        $a = _assinaturaPixAtiva($this->usuario);
        _viajarParaAntesDoFim($a, 2);
        Assinatura::query()->whereKey($a->id)->update(['plano_id' => null]);

        $this->artisan('assinaturas:pix-anual')->assertFailed();
    });
});

describe('CA-5 sem pagamento até o fim', function () {
    it('o acesso acaba no fim, o estado fecha e o histórico fica', function () {
        Http::preventStrayRequests();
        $a = _assinaturaPixAtiva($this->usuario);
        $invoices = Invoice::query()->count();

        $this->travelTo($a->proximo_vencimento->copy()->subMinute());
        expect(app(Carteira::class)->verificar($this->usuario->id)->bloqueio)->toBeNull();

        $this->travelTo($a->proximo_vencimento->copy()->addMinute());
        // Antes da rotina: o ciclo vencido já não dá acesso.
        expect(app(Carteira::class)->verificar($this->usuario->id)->bloqueio?->motivo)->toBe(MotivoBloqueio::PlanoEncerrado);

        $this->artisan('assinaturas:pix-anual')->assertSuccessful();
        $this->artisan('assinaturas:pix-anual')->assertSuccessful();

        expect($a->fresh()->status)->toBe(StatusAssinatura::Expirada)
            ->and(Invoice::query()->count())->toBe($invoices)
            ->and(AuditLog::query()->where('action', 'cobranca.assinatura_pix_encerrada')->count())->toBe(1)
            ->and(IniciarAssinatura::assinaturaVigente($this->usuario->id))->toBeNull();
    });

    it('depois de encerrado, o usuário contrata de novo com cartão', function () {
        $a = _assinaturaPixAtiva($this->usuario);
        $this->travelTo($a->proximo_vencimento->copy()->addMinute());
        $this->artisan('assinaturas:pix-anual')->assertSuccessful();

        $invoice = _pagarAssinatura(_assinaturaPendente($this->usuario));

        expect(Assinatura::query()->findOrFail($invoice->assinatura_id)->status)->toBe(StatusAssinatura::Ativa);
    });

    it('antes do fim nada é encerrado', function () {
        $a = _assinaturaPixAtiva($this->usuario);
        $this->travelTo($a->proximo_vencimento->copy()->subHour());
        $this->artisan('assinaturas:pix-anual')->assertSuccessful();

        expect($a->fresh()->status)->toBe(StatusAssinatura::Ativa);
    });

    it('a assinatura de cartão não é tocada pela rotina do Pix', function () {
        $c = Assinatura::query()->findOrFail(_pagarAssinatura(_assinaturaPendente($this->usuario, null, CicloCobranca::Anual))->assinatura_id);
        $this->travelTo($c->proximo_vencimento->copy()->addDay());
        $this->artisan('assinaturas:pix-anual')->assertSuccessful();

        expect($c->fresh()->status)->toBe(StatusAssinatura::Ativa);
    });
});

describe('CA-7 interruptor', function () {
    it('desligado, a rotina não envia nem encerra e a tela de renovação é 404', function () {
        $a = _assinaturaPixAtiva($this->usuario);
        $this->travelTo($a->proximo_vencimento->copy()->subDays(2));
        CobrancaConfig::atual()->update(['ativa' => false]);

        $this->artisan('assinaturas:pix-anual')->expectsOutputToContain('desligada')->assertSuccessful();
        $this->actingAs($this->usuario)->get(route('assinatura.renovar'))->assertNotFound();

        Mail::assertNothingSent();
        expect($a->fresh()->status)->toBe(StatusAssinatura::Ativa);
    });
});

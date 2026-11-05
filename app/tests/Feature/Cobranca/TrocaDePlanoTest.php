<?php

declare(strict_types=1);

use App\Domain\Cobranca\CicloCobranca;
use App\Domain\Cobranca\EstadoCobranca;
use App\Domain\Cobranca\MeioPagamento;
use App\Domain\Cobranca\ProvedorIndisponivel;
use App\Domain\Cobranca\StatusAssinatura;
use App\Infrastructure\Cobranca\Fake\FakePaymentProvider;
use App\Models\Assinatura;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\Plano;
use App\Models\Usuario;
use App\Services\Cobranca\TrocaNegada;
use App\Services\Cobranca\TrocarDePlano;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * STORY-082 — troca de plano com o provedor fake e o relógio parado no meio do ciclo.
 * Catálogo: Básico R$ 49,90/mês (399,00/ano, cota 1) e Pro R$ 199,00/mês (1.599,00/ano, cota 10).
 */
beforeEach(function () {
    _usarProvedor('fake');
    Mail::fake();
    $this->usuario = _compradorConfirmado();
    $this->pro = _plano('Pro');
    $this->basico = _plano('Básico');
});

/** Assinatura mensal do Básico, no 10º dia do ciclo (20 dias restantes). */
function _basicoNoMeioDoCiclo(Usuario $usuario, CicloCobranca $ciclo = CicloCobranca::Mensal): Assinatura
{
    [$a] = _assinaturaAtivaNoFake($usuario, $ciclo);
    test()->travelTo($a->data_inicio->copy()->addDays(10));

    return $a->fresh();
}

function _trocas(): TrocarDePlano
{
    return app(TrocarDePlano::class);
}

describe('CA-1 e CA-2 — upgrade', function () {
    it('mostra a diferença proporcional antes de confirmar e não mexe em nada ao prever', function () {
        $a = _basicoNoMeioDoCiclo($this->usuario);

        $previa = _trocas()->previa(_trocas()->assinaturaDaTroca($this->usuario), $this->pro, CicloCobranca::Mensal);

        // 20 de 30 dias: (19900 − 4990) × 20/30 = 9940.
        expect($previa->imediata)->toBeTrue()->and($previa->diasRestantes)->toBe(20)->and($previa->aPagarCentavos)->toBe(9940)
            ->and($previa->parcelaNovaCentavos - $previa->creditoCentavos)->toBe(9940);
        expect($a->fresh()->plano_id)->toBe($this->basico->id)->and(Invoice::query()->where('tipo', 'troca_de_plano')->count())->toBe(0);
    });

    it('abre o checkout da diferença sem trocar o plano; pago, o plano e a cota valem na hora', function () {
        $a = _basicoNoMeioDoCiclo($this->usuario);
        $vencimento = $a->proximo_vencimento->toIso8601String();

        $feito = _trocas()->confirmar($this->usuario, $this->pro, CicloCobranca::Mensal);
        $invoice = $feito->invoice;

        expect($feito->tipo)->toBe('checkout')
            ->and($invoice->tipo)->toBe('troca_de_plano')->and($invoice->valor_centavos)->toBe(9940)->and($invoice->meio)->toBe('cartao')
            ->and($invoice->plano_id)->toBe($this->pro->id)->and($invoice->assinatura_id)->toBe($a->id)->and($invoice->checkout_url)->not->toBeNull();
        // Antes de pagar, nada mudou (CA-2).
        expect($a->fresh()->plano_id)->toBe($this->basico->id)->and($a->fresh()->cota_snapshot)->toBe(1);

        $paga = _pagarAssinatura($invoice);
        $a = $a->fresh();

        expect($paga->status)->toBe(EstadoCobranca::Paga)->and($paga->pago_em)->not->toBeNull()
            ->and($a->plano_id)->toBe($this->pro->id)->and($a->cota_snapshot)->toBe(10)->and($a->cota_restante_ciclo)->toBe(10)
            ->and($a->preco_snapshot_centavos)->toBe(19900)->and($a->ciclo)->toBe('mensal')
            ->and($a->proximo_vencimento->toIso8601String())->toBe($vencimento)->and($a->periodos_pagos)->toBe(1)
            ->and($a->ajuste_provedor_pendente)->toBeFalse();
        // O valor da assinatura no provedor passa a ser o do plano novo.
        expect(FakePaymentProvider::valorDaAssinatura((string) $a->id_externo))->toMatchArray(['valor' => 19900, 'ciclo' => 'mensal']);
        expect(AuditLog::query()->where('action', 'cobranca.troca_de_plano_aplicada')->count())->toBe(1);
    });

    it('o que já foi gasto no ciclo continua gasto: a cota nova vale a partir de agora', function () {
        $a = _basicoNoMeioDoCiclo($this->usuario);
        $a->forceFill(['cota_restante_ciclo' => 0])->save();

        _pagarAssinatura(_trocas()->confirmar($this->usuario, $this->pro, CicloCobranca::Mensal)->invoice);

        expect($a->fresh()->cota_restante_ciclo)->toBe(9);
    });

    it('upgrade recusado mantém o plano atual e não cobra', function () {
        $a = _basicoNoMeioDoCiclo($this->usuario);
        $invoice = _trocas()->confirmar($this->usuario, $this->pro, CicloCobranca::Mensal)->invoice;

        _entregar(_fake()->simularRecusa((string) $invoice->checkout_id_externo));

        expect($invoice->fresh()->status)->toBe(EstadoCobranca::Falhou)
            ->and($a->fresh()->plano_id)->toBe($this->basico->id)->and($a->fresh()->cota_snapshot)->toBe(1)
            ->and(FakePaymentProvider::valorDaAssinatura((string) $a->id_externo))->toBeNull();
    });

    it('checkout vencido ou cancelado também mantém o plano', function () {
        $a = _basicoNoMeioDoCiclo($this->usuario);
        $invoice = _trocas()->confirmar($this->usuario, $this->pro, CicloCobranca::Mensal)->invoice;

        _entregar(_fake()->simularExpiracao((string) $invoice->checkout_id_externo));

        expect($invoice->fresh()->status)->toBe(EstadoCobranca::Vencida)->and($a->fresh()->plano_id)->toBe($this->basico->id);
    });

    it('o mesmo pagamento avisado duas vezes aplica uma vez só', function () {
        $a = _basicoNoMeioDoCiclo($this->usuario);
        $invoice = _trocas()->confirmar($this->usuario, $this->pro, CicloCobranca::Mensal)->invoice;

        _entregar(_fake()->simularPagamento((string) $invoice->checkout_id_externo));
        $a->fresh()->forceFill(['cota_restante_ciclo' => 3])->save();
        _entregar(_fake()->simularPagamento((string) $invoice->checkout_id_externo));

        expect($a->fresh()->cota_restante_ciclo)->toBe(3)
            ->and(AuditLog::query()->where('action', 'cobranca.troca_de_plano_aplicada')->count())->toBe(1);
    });

    it('clique repetido reaproveita o checkout que ainda vale', function () {
        _basicoNoMeioDoCiclo($this->usuario);

        $um = _trocas()->confirmar($this->usuario, $this->pro, CicloCobranca::Mensal)->invoice;
        $dois = _trocas()->confirmar($this->usuario, $this->pro, CicloCobranca::Mensal)->invoice;

        expect($dois->id)->toBe($um->id)->and(Invoice::query()->where('tipo', 'troca_de_plano')->count())->toBe(1);
    });

    it('provedor fora do ar ao abrir o checkout: a Invoice falha e o plano continua', function () {
        $a = _basicoNoMeioDoCiclo($this->usuario);
        FakePaymentProvider::falharProximoCheckout();

        expect(fn () => _trocas()->confirmar($this->usuario, $this->pro, CicloCobranca::Mensal))->toThrow(ProvedorIndisponivel::class);

        expect(Invoice::query()->where('tipo', 'troca_de_plano')->sole()->status)->toBe(EstadoCobranca::Falhou)
            ->and($a->fresh()->plano_id)->toBe($this->basico->id);
    });

    it('pago depois de o ciclo ter mudado: o dinheiro entrou, a troca não é aplicada e há alerta', function () {
        $a = _basicoNoMeioDoCiclo($this->usuario);
        $invoice = _trocas()->confirmar($this->usuario, $this->pro, CicloCobranca::Mensal)->invoice;
        $a->forceFill(['periodos_pagos' => 2])->save();
        Log::spy();

        _entregar(_fake()->simularPagamento((string) $invoice->checkout_id_externo));

        expect($invoice->fresh()->status)->toBe(EstadoCobranca::Paga)->and($a->fresh()->plano_id)->toBe($this->basico->id);
        Log::shouldHaveReceived('error')->with('cobranca.troca_paga_sem_efeito', Mockery::any());
    });

    it('valor pago diferente do da Invoice não aplica a troca', function () {
        $a = _basicoNoMeioDoCiclo($this->usuario);
        $invoice = _trocas()->confirmar($this->usuario, $this->pro, CicloCobranca::Mensal)->invoice;
        $invoice->forceFill(['valor_centavos' => 1])->save();

        Log::spy();

        // O webhook que falha fica como `erro` para a equipe (o reprocessador tenta de novo), nunca aplica.
        _entregar(_fake()->simularPagamento((string) $invoice->checkout_id_externo));

        expect($a->fresh()->plano_id)->toBe($this->basico->id)->and($invoice->fresh()->status)->toBe(EstadoCobranca::Pendente);
        Log::shouldHaveReceived('error')->with('cobranca.valor_divergente', Mockery::any());
    });
});

describe('diferença abaixo de R$ 5,00 (ajuste do ADR-010)', function () {
    it('é aplicada sem cobrança e sem checkout', function () {
        $a = _basicoNoMeioDoCiclo($this->usuario);
        // Faltam 2 horas: a diferença proporcional é de poucos centavos.
        $this->travelTo($a->proximo_vencimento->copy()->subHours(2));

        $feito = _trocas()->confirmar($this->usuario, $this->pro, CicloCobranca::Mensal);
        $a = $a->fresh();

        expect($feito->tipo)->toBe('aplicada')->and($feito->invoice)->toBeNull()->and($feito->previa->semCobranca)->toBeTrue()
            ->and(Invoice::query()->where('tipo', 'troca_de_plano')->count())->toBe(0)
            ->and($a->plano_id)->toBe($this->pro->id)->and($a->cota_snapshot)->toBe(10)->and($a->preco_snapshot_centavos)->toBe(19900)
            ->and(FakePaymentProvider::valorDaAssinatura((string) $a->id_externo)['valor'])->toBe(19900);
    });

    it('se o provedor recusar o ajuste de valor, o plano vale e a rotina horária repete até conseguir', function () {
        $a = _basicoNoMeioDoCiclo($this->usuario);
        $this->travelTo($a->proximo_vencimento->copy()->subHours(2));
        FakePaymentProvider::falharProximaOperacaoDeAssinatura();
        Log::spy();

        _trocas()->confirmar($this->usuario, $this->pro, CicloCobranca::Mensal);

        expect($a->fresh()->plano_id)->toBe($this->pro->id)->and($a->fresh()->ajuste_provedor_pendente)->toBeTrue()
            ->and(FakePaymentProvider::valorDaAssinatura((string) $a->id_externo))->toBeNull();
        Log::shouldHaveReceived('error')->with('cobranca.ajuste_de_valor_pendente', Mockery::any());

        expect(Artisan::call('assinaturas:ajustar-no-provedor'))->toBe(0);

        expect($a->fresh()->ajuste_provedor_pendente)->toBeFalse()
            ->and(FakePaymentProvider::valorDaAssinatura((string) $a->id_externo)['valor'])->toBe(19900);
    });
});

describe('CA-4 — downgrade agendado', function () {
    beforeEach(function () {
        [$a] = _assinaturaAtivaNoFake($this->usuario);
        $this->a = $a;
        // Sobe para o Pro de graça (fim do ciclo) para ter de onde descer.
        $this->travelTo($a->proximo_vencimento->copy()->subHours(1));
        _trocas()->confirmar($this->usuario, $this->pro, CicloCobranca::Mensal);
        $this->travelTo($a->data_inicio->copy()->addDays(10));
    });

    it('não cobra nem muda nada hoje: fica agendado para o fim do ciclo e o provedor já tem o valor novo', function () {
        $antes = $this->a->fresh();

        $feito = _trocas()->confirmar($this->usuario, $this->basico, CicloCobranca::Mensal);
        $a = $this->a->fresh();

        expect($feito->tipo)->toBe('agendada')->and($feito->previa->aPagarCentavos)->toBe(0)->and($feito->invoice)->toBeNull()
            ->and($a->plano_id)->toBe($this->pro->id)->and($a->cota_snapshot)->toBe(10)->and($a->preco_snapshot_centavos)->toBe(19900)
            ->and($a->troca_plano_id)->toBe($this->basico->id)->and($a->troca_ciclo)->toBe('mensal')->and($a->troca_preco_centavos)->toBe(4990)
            ->and($a->proximo_vencimento->toIso8601String())->toBe($antes->proximo_vencimento->toIso8601String())
            ->and(FakePaymentProvider::valorDaAssinatura((string) $a->id_externo)['valor'])->toBe(4990)
            ->and(TrocarDePlano::agendada($a)?->id)->toBe($this->basico->id);
    });

    it('pode ser desfeito antes de valer: tudo volta e o provedor recupera o valor', function () {
        _trocas()->confirmar($this->usuario, $this->basico, CicloCobranca::Mensal);

        expect(_trocas()->desfazer($this->usuario))->toBeTrue();
        $a = $this->a->fresh();

        expect($a->troca_plano_id)->toBeNull()->and($a->troca_ciclo)->toBeNull()->and($a->troca_preco_centavos)->toBeNull()
            ->and(FakePaymentProvider::valorDaAssinatura((string) $a->id_externo)['valor'])->toBe(19900)
            ->and(_trocas()->desfazer($this->usuario))->toBeFalse();
    });

    it('se o provedor falhar ao agendar ou ao desfazer, nada muda do nosso lado', function () {
        FakePaymentProvider::falharProximaOperacaoDeAssinatura();
        expect(fn () => _trocas()->confirmar($this->usuario, $this->basico, CicloCobranca::Mensal))->toThrow(ProvedorIndisponivel::class);
        expect($this->a->fresh()->troca_plano_id)->toBeNull();

        _trocas()->confirmar($this->usuario, $this->basico, CicloCobranca::Mensal);
        FakePaymentProvider::falharProximaOperacaoDeAssinatura();
        expect(fn () => _trocas()->desfazer($this->usuario))->toThrow(ProvedorIndisponivel::class);
        expect($this->a->fresh()->troca_plano_id)->toBe($this->basico->id);
    });

    it('vale na renovação: plano, cota e preço novos, e a troca deixa de estar agendada', function () {
        _trocas()->confirmar($this->usuario, $this->basico, CicloCobranca::Mensal);
        $a = $this->a->fresh();
        $this->travelTo($a->proximo_vencimento);

        _entregar(_fake()->simularRenovacao((string) _checkoutDaAssinatura($a)));
        $a = $a->fresh();

        expect($a->plano_id)->toBe($this->basico->id)->and($a->cota_snapshot)->toBe(1)->and($a->cota_restante_ciclo)->toBe(1)
            ->and($a->preco_snapshot_centavos)->toBe(4990)->and($a->periodos_pagos)->toBe(2)
            ->and($a->troca_plano_id)->toBeNull()->and($a->troca_preco_centavos)->toBeNull();
        $renovacao = Invoice::query()->where('assinatura_id', $a->id)->where('renovacao', true)->sole();
        expect($renovacao->valor_centavos)->toBe(4990)->and($renovacao->plano_id)->toBe($this->basico->id)->and($renovacao->cota_snapshot)->toBe(1);
    });

    it('sem downgrade agendado a renovação segue igual (plano e preço do ciclo)', function () {
        $a = $this->a->fresh();
        $this->travelTo($a->proximo_vencimento);

        _entregar(_fake()->simularRenovacao((string) _checkoutDaAssinatura($a)));

        expect($a->fresh()->plano_id)->toBe($this->pro->id)->and($a->fresh()->preco_snapshot_centavos)->toBe(19900)->and($a->fresh()->periodos_pagos)->toBe(2);
    });

    it('com troca de ciclo (mensal para o anual do mesmo plano) o ano começa na renovação', function () {
        _trocas()->confirmar($this->usuario, $this->pro, CicloCobranca::Anual);
        // Pro mensal 199,00 x Pro anual 1.599,00/12 = 133,25: é mais barato por mês, logo agendado.
        $a = $this->a->fresh();
        expect($a->troca_ciclo)->toBe('anual')->and($a->troca_preco_centavos)->toBe(159900)->and(FakePaymentProvider::valorDaAssinatura((string) $a->id_externo))->toMatchArray(['valor' => 159900, 'ciclo' => 'anual']);
        $fim = $a->proximo_vencimento;
        $this->travelTo($fim);

        _entregar(_fake()->simularRenovacao((string) _checkoutDaAssinatura($a)));
        $a = $a->fresh();

        expect($a->ciclo)->toBe('anual')->and($a->periodos_pagos)->toBe(1)->and($a->data_inicio->toIso8601String())->toBe($fim->toIso8601String())
            ->and($a->proximo_vencimento->toIso8601String())->toBe($fim->copy()->addYear()->toIso8601String())
            ->and($a->proxima_cota_em)->not->toBeNull()->and($a->troca_plano_id)->toBeNull();
    });

    it('um upgrade posterior cancela o downgrade que estava agendado', function () {
        _trocas()->confirmar($this->usuario, $this->basico, CicloCobranca::Mensal);
        $this->travelTo($this->a->fresh()->proximo_vencimento->copy()->subHours(1));

        // Mesmo plano Pro, agora pelo anual: mais barato por mês => agendado de novo, substitui o anterior.
        _trocas()->confirmar($this->usuario, $this->pro, CicloCobranca::Anual);

        expect($this->a->fresh()->troca_plano_id)->toBe($this->pro->id)->and($this->a->fresh()->troca_ciclo)->toBe('anual');
    });
});

function _checkoutDaAssinatura(Assinatura $a): string
{
    return (string) Invoice::query()->where('assinatura_id', $a->id)->where('renovacao', false)->where('tipo', 'assinatura')->value('checkout_id_externo');
}

describe('troca de ciclo imediata (mensal para anual mais caro por mês)', function () {
    it('o ano começa hoje, pelo preço cheio menos o crédito do mês', function () {
        $a = _basicoNoMeioDoCiclo($this->usuario);

        $feito = _trocas()->confirmar($this->usuario, $this->pro, CicloCobranca::Anual);

        // Crédito do Básico: 4990 × 20/30 = 3327; a pagar 159900 − 3327.
        expect($feito->tipo)->toBe('checkout')->and($feito->invoice->valor_centavos)->toBe(156573)->and($feito->previa->mesmoCiclo)->toBeFalse();

        _pagarAssinatura($feito->invoice);
        $a = $a->fresh();

        expect($a->ciclo)->toBe('anual')->and($a->plano_id)->toBe($this->pro->id)->and($a->cota_restante_ciclo)->toBe(10)
            ->and($a->periodos_pagos)->toBe(1)->and($a->preco_snapshot_centavos)->toBe(159900)
            ->and($a->data_inicio->toIso8601String())->toBe(now()->toIso8601String())
            ->and($a->proximo_vencimento->toIso8601String())->toBe(now()->addYear()->toIso8601String())
            ->and($a->proxima_cota_em)->not->toBeNull()
            ->and(FakePaymentProvider::valorDaAssinatura((string) $a->id_externo))->toMatchArray(['valor' => 159900, 'ciclo' => 'anual']);
    });
});

describe('quem pode trocar (CA-5 e spec §4)', function () {
    it('só planos ativos são destino; o plano atual desativado segue valendo', function () {
        $a = _basicoNoMeioDoCiclo($this->usuario);
        $this->pro->forceFill(['ativo' => false])->save();

        expect(fn () => _trocas()->confirmar($this->usuario, $this->pro, CicloCobranca::Mensal))
            ->toThrow(fn (TrocaNegada $e) => expect($e->motivo)->toBe(TrocaNegada::PLANO_INDISPONIVEL));

        $this->basico->forceFill(['ativo' => false])->save();
        expect($a->fresh()->status)->toBe(StatusAssinatura::Ativa)->and($a->fresh()->plano_id)->toBe($this->basico->id)
            ->and(_trocas()->assinaturaDaTroca($this->usuario)->id)->toBe($a->id);
    });

    it('o mesmo plano e ciclo não é uma troca', function () {
        _basicoNoMeioDoCiclo($this->usuario);

        expect(fn () => _trocas()->confirmar($this->usuario, $this->basico, CicloCobranca::Mensal))
            ->toThrow(fn (TrocaNegada $e) => expect($e->motivo)->toBe(TrocaNegada::IGUAL));
    });

    it('em atraso não troca: atualize o cartão antes', function () {
        [$a, $primeira] = _assinaturaAtivaNoFake($this->usuario);
        _recusarRenovacao($a, $primeira);

        expect(fn () => _trocas()->confirmar($this->usuario, $this->pro, CicloCobranca::Mensal))
            ->toThrow(fn (TrocaNegada $e) => expect($e->motivo)->toBe(TrocaNegada::EM_ATRASO));
        expect(Invoice::query()->where('tipo', 'troca_de_plano')->count())->toBe(0);
    });

    it('com a renovação cancelada, não troca', function () {
        [$a] = _assinaturaAtivaNoFake($this->usuario);
        $a->forceFill(['status' => StatusAssinatura::Cancelada])->save();

        expect(fn () => _trocas()->confirmar($this->usuario, $this->pro, CicloCobranca::Mensal))
            ->toThrow(fn (TrocaNegada $e) => expect($e->motivo)->toBe(TrocaNegada::CANCELADA));
    });

    it('sem assinatura paga (nem o teste conta) não troca', function () {
        expect(fn () => _trocas()->confirmar($this->usuario, $this->pro, CicloCobranca::Mensal))
            ->toThrow(fn (TrocaNegada $e) => expect($e->motivo)->toBe(TrocaNegada::SEM_ASSINATURA));
    });

    it('no "Logar como" não troca nem desfaz', function () {
        _basicoNoMeioDoCiclo($this->usuario);
        _personificar(_adminDaEquipe(), $this->usuario);

        expect(fn () => _trocas()->confirmar($this->usuario, $this->pro, CicloCobranca::Mensal))
            ->toThrow(fn (TrocaNegada $e) => expect($e->motivo)->toBe(TrocaNegada::ADMIN_PERSONIFICANDO));
        expect(fn () => _trocas()->desfazer($this->usuario))->toThrow(TrocaNegada::class);
    });
});

describe('plano anual pago com Pix (pergunta 1 da spec)', function () {
    beforeEach(function () {
        $invoice = _assinaturaPendente($this->usuario, null, CicloCobranca::Anual, MeioPagamento::Pix);
        _pagarAssinatura($invoice);
        $this->a = Assinatura::query()->where('usuario_id', $this->usuario->id)->sole();
        $this->travelTo($this->a->data_inicio->copy()->addMonths(3));
    });

    it('o upgrade gera um QR Code Pix da diferença e, pago, o plano novo vale', function () {
        $feito = _trocas()->confirmar($this->usuario, $this->pro, CicloCobranca::Anual);
        $invoice = $feito->invoice;

        expect($feito->tipo)->toBe('checkout')->and($invoice->meio)->toBe('pix')->and($invoice->tipo)->toBe('troca_de_plano')
            ->and($invoice->valor_centavos)->toBe($feito->previa->aPagarCentavos)->and($invoice->valor_centavos)->toBeGreaterThan(5000)
            ->and($invoice->checkout_url)->not->toBeNull()->and($invoice->checkout_expira_em)->not->toBeNull();
        expect($this->a->fresh()->plano_id)->toBe($this->basico->id);

        _pagarAssinatura($invoice);
        $a = $this->a->fresh();

        expect($a->plano_id)->toBe($this->pro->id)->and($a->cota_snapshot)->toBe(10)->and($a->preco_snapshot_centavos)->toBe(159900)
            ->and($a->pagoComPix())->toBeTrue()->and($a->id_externo)->toBeNull()->and($a->ajuste_provedor_pendente)->toBeFalse();
    });

    it('o Pix só troca para planos anuais', function () {
        expect(fn () => _trocas()->confirmar($this->usuario, $this->pro, CicloCobranca::Mensal))
            ->toThrow(fn (TrocaNegada $e) => expect($e->motivo)->toBe(TrocaNegada::PIX_SO_ANUAL));
    });
});

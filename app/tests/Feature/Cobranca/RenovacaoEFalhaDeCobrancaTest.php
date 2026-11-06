<?php

declare(strict_types=1);

use App\Domain\Cobranca\AcaoDaRegua;
use App\Domain\Cobranca\Carteira;
use App\Domain\Cobranca\CicloCobranca;
use App\Domain\Cobranca\EstadoCobranca;
use App\Domain\Cobranca\MotivoBloqueio;
use App\Domain\Cobranca\PaymentProvider;
use App\Domain\Cobranca\ProvedorIndisponivel;
use App\Domain\Cobranca\ReguaDeCobranca;
use App\Domain\Cobranca\StatusAssinatura;
use App\Domain\Cobranca\TentativaNaoRealizada;
use App\Domain\Cobranca\TipoEvento;
use App\Infrastructure\Cobranca\Fake\FakePaymentProvider;
use App\Mail\AssinaturaEncerradaMessage;
use App\Mail\FalhaDeCobrancaMessage;
use App\Mail\RenovacaoProximaMessage;
use App\Models\Assinatura;
use App\Models\CobrancaConfig;
use App\Models\Diagnostico;
use App\Models\EmpresaAnalisada;
use App\Models\Invoice;
use App\Models\ProviderEvent;
use App\Services\Cobranca\CompraNaoPermitida;
use App\Services\Cobranca\ExecutarReguaDeCobranca;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * STORY-079 — renovação e falha de cobrança, de ponta a ponta no domínio, com o provedor fake e o relógio
 * viajando (a régua é de 48 h). O cartão nunca passa por aqui.
 */
beforeEach(function () {
    _usarProvedor('fake');
    Mail::fake();
    $this->usuario = _compradorConfirmado();
});

describe('CA-1 lembrete de renovação (D-7)', function () {
    it('avisa quando faltam 7 dias e não duplica se a rotina rodar de novo', function () {
        [$a] = _assinaturaAtivaNoFake($this->usuario);
        $this->travelTo($a->proximo_vencimento->copy()->subDays(7)->addMinutes(5));

        expect(Artisan::call('assinaturas:avisar-renovacao'))->toBe(0);
        Artisan::call('assinaturas:avisar-renovacao');
        $this->travelTo(now()->addHours(30));
        Artisan::call('assinaturas:avisar-renovacao');

        Mail::assertSent(RenovacaoProximaMessage::class, 1);
        Mail::assertSent(RenovacaoProximaMessage::class, fn (RenovacaoProximaMessage $m) => $m->hasTo($this->usuario->email)
            && $m->valor === 'R$ 49,90'
            && $m->data === $a->proximo_vencimento->copy()->setTimezone('America/Sao_Paulo')->format('d/m/Y')
            && $m->plano === 'Básico');
        expect($a->fresh()->aviso_renovacao_periodo)->toBe(1);
    });

    it('não avisa antes da janela de 7 dias nem depois do fim do ciclo', function () {
        [$a] = _assinaturaAtivaNoFake($this->usuario);

        $this->travelTo($a->proximo_vencimento->copy()->subDays(8));
        Artisan::call('assinaturas:avisar-renovacao');
        $this->travelTo($a->proximo_vencimento->copy()->addMinute());
        Artisan::call('assinaturas:avisar-renovacao');

        Mail::assertNothingSent();
    });

    it('cada ciclo renovado recebe o seu lembrete', function () {
        [$a, $primeira] = _assinaturaAtivaNoFake($this->usuario);
        $this->travelTo($a->proximo_vencimento->copy()->subDays(6));
        Artisan::call('assinaturas:avisar-renovacao');

        $this->travelTo($a->proximo_vencimento);
        _entregar(_fake()->simularRenovacao((string) $primeira->checkout_id_externo));
        $this->travelTo($a->fresh()->proximo_vencimento->copy()->subDays(3));
        Artisan::call('assinaturas:avisar-renovacao');
        Artisan::call('assinaturas:avisar-renovacao');

        Mail::assertSent(RenovacaoProximaMessage::class, 2);
    });

    it('não avisa quem não vai renovar sozinho: teste, cancelada ou em atraso', function () {
        [$a] = _assinaturaAtivaNoFake($this->usuario);
        $this->travelTo($a->proximo_vencimento->copy()->subDays(2));

        foreach ([StatusAssinatura::Cancelada, StatusAssinatura::EmAtraso, StatusAssinatura::Expirada] as $status) {
            $a->forceFill(['status' => $status])->save();
            Artisan::call('assinaturas:avisar-renovacao');
        }

        $a->forceFill(['status' => StatusAssinatura::Ativa, 'trial' => true])->save();
        Artisan::call('assinaturas:avisar-renovacao');

        Mail::assertNothingSent();
    });

    it('com a cobrança desligada não manda nada (CA-8)', function () {
        [$a] = _assinaturaAtivaNoFake($this->usuario);
        CobrancaConfig::atual()->update(['ativa' => false]);
        $this->travelTo($a->proximo_vencimento->copy()->subDays(2));

        Artisan::call('assinaturas:avisar-renovacao');

        Mail::assertNothingSent();
        expect($a->fresh()->aviso_renovacao_periodo)->toBeNull();
    });

    it('se o envio falhar, devolve a vaga e a próxima rodada tenta de novo, com alerta', function () {
        [$a] = _assinaturaAtivaNoFake($this->usuario);
        $this->travelTo($a->proximo_vencimento->copy()->subDays(2));
        Log::spy();
        _emailForaDoAr();

        expect(Artisan::call('assinaturas:avisar-renovacao'))->toBe(1);
        expect($a->fresh()->aviso_renovacao_periodo)->toBeNull();
        Log::shouldHaveReceived('error')->withArgs(fn (string $m, array $c) => $m === 'cobranca.aviso_nao_enviado' && $c['aviso'] === 'renovacao_proxima')->once();

        _emailVoltou();
        expect(Artisan::call('assinaturas:avisar-renovacao'))->toBe(0);
        Mail::assertSent(RenovacaoProximaMessage::class, 1);
    });
});

describe('CA-2 renovação paga', function () {
    it('o ciclo avança com a cota de hoje e o preço contratado, mesmo que o catálogo tenha subido', function () {
        [$a, $primeira] = _assinaturaAtivaNoFake($this->usuario);
        $contratado = $a->preco_snapshot_centavos;
        $plano = _plano();
        $plano->update(['preco_mensal_centavos' => $contratado + 2000, 'cota_mensal' => $plano->cota_mensal + 3]);
        $this->travelTo($a->proximo_vencimento);

        _entregar(_fake()->simularRenovacao((string) $primeira->checkout_id_externo));
        $a->refresh();

        // A mudança de preço do catálogo não chega ao assinante sem o aviso de 30 dias: o provedor segue cobrando o contratado.
        expect($a->periodos_pagos)->toBe(2)
            ->and($a->preco_snapshot_centavos)->toBe($contratado)
            ->and($a->cota_snapshot)->toBe($plano->cota_mensal)
            ->and($a->cota_restante_ciclo)->toBe($plano->cota_mensal)
            ->and(Invoice::query()->where('renovacao', true)->sole()->valor_centavos)->toBe($contratado);
    });
});

describe('CA-3 cobrança recusada', function () {
    it('põe a assinatura em atraso, avisa com o link e agenda a 1ª retentativa para 48 h depois', function () {
        [$a, $primeira] = _assinaturaAtivaNoFake($this->usuario);
        $a = _recusarRenovacao($a, $primeira);

        expect($a->status)->toBe(StatusAssinatura::EmAtraso)
            ->and($a->tentativas_cobranca)->toBe(0)
            ->and($a->atraso_cobranca_id)->toStartWith('fakepay_')
            ->and($a->atraso_desde?->getTimestamp())->toBe(now()->getTimestamp())
            ->and($a->proxima_tentativa_em?->getTimestamp())->toBe(now()->addHours(48)->getTimestamp())
            ->and($a->atraso_aviso_em)->not->toBeNull();

        Mail::assertSent(FalhaDeCobrancaMessage::class, fn (FalhaDeCobrancaMessage $m) => $m->hasTo($this->usuario->email)
            && str_starts_with($m->link, 'http')
            && $m->plano === 'Básico'
            && $m->valor === 'R$ 49,90'
            && $m->proximaTentativa === now()->addHours(48)->setTimezone('America/Sao_Paulo')->format('d/m/Y')
            && $m->ultimaTentativa === now()->addHours(144)->setTimezone('America/Sao_Paulo')->format('d/m/Y'));
        // STORY-092 CA-4: em atraso o acesso segue durante as retentativas; o bloqueio vem só ao encerrar o ciclo.
        expect(app(Carteira::class)->verificar($this->usuario->id)->bloqueio)->toBeNull();
    });

    it('a recusa e o vencimento da mesma cobrança, e o evento repetido, avisam uma vez e não reiniciam a régua', function () {
        [$a, $primeira] = _assinaturaAtivaNoFake($this->usuario);
        $a = _recusarRenovacao($a, $primeira, 'evt-recusa');
        $cobranca = $a->atraso_cobranca_id;
        $primeiraProxima = $a->proxima_tentativa_em;
        $this->travelTo(now()->addHours(5));

        _entregar(_fake()->simularRecusaDeRenovacao((string) $primeira->checkout_id_externo, 'evt-recusa'));
        _entregar(_fake()->webhookDaCobranca(TipoEvento::CobrancaVencida, $cobranca, 'evt-vencida'));

        $a->refresh();
        expect($a->proxima_tentativa_em?->getTimestamp())->toBe($primeiraProxima?->getTimestamp());
        Mail::assertSent(FalhaDeCobrancaMessage::class, 1);
    });

    it('um aviso de falha que chega depois do pagamento não derruba a assinatura (fora de ordem)', function () {
        [$a, $primeira] = _assinaturaAtivaNoFake($this->usuario);
        $this->travelTo($a->proximo_vencimento);
        $cobranca = _fake()->criarCobrancaDeRenovacao((string) $primeira->checkout_id_externo, EstadoCobranca::Paga);

        _entregar(_fake()->webhookDaCobranca(TipoEvento::CobrancaFalhou, $cobranca));

        $a->refresh();
        expect($a->status)->toBe(StatusAssinatura::Ativa)->and($a->periodos_pagos)->toBe(2);
        Mail::assertNotSent(FalhaDeCobrancaMessage::class);
    });

    it('cobrança antiga reenviada fora de ordem não põe em atraso uma assinatura em dia', function () {
        [$a, $primeira] = _assinaturaAtivaNoFake($this->usuario);
        // Ainda faltam semanas para o fim do ciclo pago: a "cobrança" recusada é de um ciclo que já passou.
        $cobranca = _fake()->criarCobrancaDeRenovacao((string) $primeira->checkout_id_externo, EstadoCobranca::Vencida);

        _entregar(_fake()->webhookDaCobranca(TipoEvento::CobrancaFalhou, $cobranca));

        expect($a->fresh()->status)->toBe(StatusAssinatura::Ativa);
        Mail::assertNotSent(FalhaDeCobrancaMessage::class);
    });

    it('cobrança cancelada no provedor não põe ninguém em atraso', function () {
        [$a, $primeira] = _assinaturaAtivaNoFake($this->usuario);
        $this->travelTo($a->proximo_vencimento);
        $cobranca = _fake()->criarCobrancaDeRenovacao((string) $primeira->checkout_id_externo, EstadoCobranca::Cancelada);

        _entregar(_fake()->webhookDaCobranca(TipoEvento::CobrancaFalhou, $cobranca));

        expect($a->fresh()->status)->toBe(StatusAssinatura::Ativa);
    });

    it('com a cobrança desligada o aviso de falha não tem efeito nenhum (CA-8)', function () {
        [$a, $primeira] = _assinaturaAtivaNoFake($this->usuario);
        CobrancaConfig::atual()->update(['ativa' => false]);

        $a = _recusarRenovacao($a, $primeira);

        expect($a->status)->toBe(StatusAssinatura::Ativa)->and($a->proxima_tentativa_em)->toBeNull();
        Mail::assertNothingSent();
    });

    it('a recusa do checkout inicial continua só encerrando a Invoice, sem atraso', function () {
        $invoice = _assinaturaPendente($this->usuario);
        _entregar(_fake()->simularRecusa((string) $invoice->checkout_id_externo));

        expect($invoice->fresh()->status)->toBe(EstadoCobranca::Falhou)->and(Assinatura::query()->count())->toBe(0);
        Mail::assertNotSent(FalhaDeCobrancaMessage::class);
    });

    it('o vencimento de uma cobrança que não é de assinatura nossa é ignorado', function () {
        $invoice = _assinaturaPendente($this->usuario);
        _entregar(_fake()->webhook(TipoEvento::CobrancaVencida, (string) $invoice->checkout_id_externo));

        expect(ProviderEvent::query()->sole()->status)->toBe('ignorado');
    });

    it('trial e assinatura cancelada não entram em atraso por aviso de falha', function () {
        [$a, $primeira] = _assinaturaAtivaNoFake($this->usuario);
        $a->forceFill(['status' => StatusAssinatura::Cancelada])->save();

        $a = _recusarRenovacao($a, $primeira);

        expect($a->status)->toBe(StatusAssinatura::Cancelada);
        Mail::assertNotSent(FalhaDeCobrancaMessage::class);
    });
});

describe('régua: 3 retentativas, uma a cada 48 h, sobre a mesma cobrança', function () {
    it('retenta em +48 h, +96 h e +144 h, nem antes nem em dobro, e nunca cria cobrança nova', function () {
        [$a, $primeira] = _assinaturaAtivaNoFake($this->usuario);
        $a = _recusarRenovacao($a, $primeira);
        $cobranca = (string) $a->atraso_cobranca_id;
        $cobrancasNoProvedor = count(FakePaymentProvider::cobrancasDaAssinatura((string) $a->id_externo));

        // Antes dos 48 h nada acontece, quantas vezes a rotina rodar.
        _rodarRegua(47);
        _rodarRegua();
        expect(FakePaymentProvider::tentativasDe($cobranca))->toBe(0);

        foreach ([1 => 1, 2 => 49, 3 => 49] as $n => $horas) {
            _rodarRegua($horas === 1 ? 1 : 48);
            _rodarRegua();
            _rodarRegua();
            expect(FakePaymentProvider::tentativasDe($cobranca))->toBe($n)
                ->and($a->fresh()->tentativas_cobranca)->toBe($n)
                ->and($a->fresh()->status)->toBe(StatusAssinatura::EmAtraso);
        }

        // Nenhuma cobrança nova no provedor: a régua só reapresenta a mesma.
        expect(count(FakePaymentProvider::cobrancasDaAssinatura((string) $a->id_externo)))->toBe($cobrancasNoProvedor)
            ->and(Invoice::query()->where('renovacao', true)->count())->toBe(0);
    });

    it('duas rodadas ao mesmo tempo pedem a tentativa uma vez só (reserva sob lock)', function () {
        [$a, $primeira] = _assinaturaAtivaNoFake($this->usuario);
        $a = _recusarRenovacao($a, $primeira);
        $cobranca = (string) $a->atraso_cobranca_id;
        $this->travelTo(now()->addHours(48));

        // A "outra rodada" já reservou a tentativa e caiu antes de pedir ao provedor.
        $a->forceFill(['tentativas_cobranca' => 1, 'proxima_tentativa_em' => now()->addHours(48)])->save();

        expect(_rodarRegua())->toBe(0)
            ->and(FakePaymentProvider::tentativasDe($cobranca))->toBe(0)
            ->and($a->fresh()->tentativas_cobranca)->toBe(1);
    });

    it('a rodada que perde a corrida pelo lock não retenta', function () {
        [$a, $primeira] = _assinaturaAtivaNoFake($this->usuario);
        $a = _recusarRenovacao($a, $primeira);
        $cobranca = (string) $a->atraso_cobranca_id;
        $this->travelTo(now()->addHours(48));

        // Entre a consulta da rodada e o lock, o webhook já tirou a assinatura do atraso.
        $regua = app(ExecutarReguaDeCobranca::class);
        $a->forceFill(['status' => StatusAssinatura::Ativa])->save();
        $metodo = new ReflectionMethod($regua, 'reservar');
        $reserva = $metodo->invoke($regua, $a->id, false);

        expect($reserva['acao'])->toBe(AcaoDaRegua::Aguardar)
            ->and(FakePaymentProvider::tentativasDe($cobranca))->toBe(0);
    });

    it('mostra progresso com total, percentual, decorrido e ETA', function () {
        [$a, $primeira] = _assinaturaAtivaNoFake($this->usuario);
        _recusarRenovacao($a, $primeira);
        $this->travelTo(now()->addHours(48));

        Artisan::call('cobranca:regua');
        $saida = Artisan::output();

        expect($saida)->toContain('[regua] 1/1 (100%)')->toContain('ETA')->toContain('decorrido')->toContain('Concluído: retentadas 1');
    });
});

describe('CA-4 pagamento durante as retentativas', function () {
    it('o webhook de pagamento restabelece a assinatura, renova a cota e interrompe a régua', function () {
        [$a, $primeira] = _assinaturaAtivaNoFake($this->usuario);
        $a = _recusarRenovacao($a, $primeira);
        $cobranca = (string) $a->atraso_cobranca_id;
        $a->forceFill(['cota_restante_ciclo' => 0])->save();
        $this->travelTo(now()->addHours(10));

        _entregar(_fake()->simularPagamentoDaCobranca($cobranca));
        $a->refresh();

        expect($a->status)->toBe(StatusAssinatura::Ativa)
            ->and($a->periodos_pagos)->toBe(2)
            ->and($a->cota_restante_ciclo)->toBe($a->cota_snapshot)
            ->and($a->atraso_desde)->toBeNull()
            ->and($a->atraso_cobranca_id)->toBeNull()
            ->and($a->proxima_tentativa_em)->toBeNull()
            ->and($a->tentativas_cobranca)->toBe(0)
            ->and($a->proximo_vencimento->toDateString())->toBe($a->data_inicio->addMonthsNoOverflow(2)->toDateString())
            ->and(Invoice::query()->where('renovacao', true)->sole()->status)->toBe(EstadoCobranca::Paga)
            ->and(app(Carteira::class)->verificar($this->usuario->id)->bloqueio)->toBeNull();

        // A régua não retenta o que já foi pago.
        _rodarRegua(100);
        expect(FakePaymentProvider::tentativasDe($cobranca))->toBe(0);
    });

    it('a retentativa que passa restabelece na hora, sem esperar o webhook', function () {
        [$a, $primeira] = _assinaturaAtivaNoFake($this->usuario);
        $a = _recusarRenovacao($a, $primeira);
        $cobranca = (string) $a->atraso_cobranca_id;
        FakePaymentProvider::aprovarProximaTentativa($cobranca);

        _rodarRegua(48);

        expect($a->fresh()->status)->toBe(StatusAssinatura::Ativa)
            ->and($a->fresh()->periodos_pagos)->toBe(2)
            ->and(FakePaymentProvider::tentativasDe($cobranca))->toBe(1);

        _rodarRegua(48);
        expect(FakePaymentProvider::tentativasDe($cobranca))->toBe(1)->and(Invoice::query()->where('renovacao', true)->count())->toBe(1);
    });

    it('pagamento cujo webhook se perdeu é descoberto pela régua e não gera nova tentativa', function () {
        [$a, $primeira] = _assinaturaAtivaNoFake($this->usuario);
        $a = _recusarRenovacao($a, $primeira);
        $cobranca = (string) $a->atraso_cobranca_id;
        _fake()->simularPagamentoDaCobranca($cobranca); // paga no provedor; o webhook nunca chega

        _rodarRegua(48);

        expect($a->fresh()->status)->toBe(StatusAssinatura::Ativa)->and(FakePaymentProvider::tentativasDe($cobranca))->toBe(0);
    });

    it('o pagamento chegando duas vezes (confirmada e recebida) restabelece uma vez só', function () {
        [$a, $primeira] = _assinaturaAtivaNoFake($this->usuario);
        $a = _recusarRenovacao($a, $primeira);
        $cobranca = (string) $a->atraso_cobranca_id;

        _entregar(_fake()->simularPagamentoDaCobranca($cobranca, 'evt-confirmada'));
        _entregar(_fake()->webhookDaCobranca(TipoEvento::CobrancaPaga, $cobranca, 'evt-recebida'));

        expect($a->fresh()->periodos_pagos)->toBe(2)->and(Invoice::query()->where('renovacao', true)->count())->toBe(1);
    });

    it('anual: o pagamento durante o atraso reagenda a cota mensal', function () {
        [$a, $primeira] = _assinaturaAtivaNoFake($this->usuario, CicloCobranca::Anual);
        $a = _recusarRenovacao($a, $primeira);

        _entregar(_fake()->simularPagamentoDaCobranca((string) $a->atraso_cobranca_id));
        $a->refresh();

        expect($a->status)->toBe(StatusAssinatura::Ativa)
            ->and($a->proximo_vencimento->toDateString())->toBe($a->data_inicio->addYears(2)->toDateString())
            ->and($a->proxima_cota_em)->not->toBeNull();
    });
});

describe('CA-5 depois da terceira falha', function () {
    it('encerra o ciclo, bloqueia as funções pagas, avisa, cancela a recorrência e preserva o histórico', function () {
        $empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $this->usuario->id]);
        $diagnostico = Diagnostico::factory()->create(['usuario_id' => $this->usuario->id, 'empresa_analisada_id' => $empresa->id]);
        [$a, $primeira] = _assinaturaAtivaNoFake($this->usuario);
        $a = _recusarRenovacao($a, $primeira);
        $cobranca = (string) $a->atraso_cobranca_id;

        foreach ([48, 48, 48] as $horas) {
            _rodarRegua($horas);
            expect($a->fresh()->status)->toBe(StatusAssinatura::EmAtraso);
        }

        // 48 h depois da última tentativa, sem pagamento: fim.
        _rodarRegua(48);
        $a->refresh();

        expect($a->status)->toBe(StatusAssinatura::Expirada)
            ->and($a->encerrada_em)->not->toBeNull()
            ->and($a->proxima_tentativa_em)->toBeNull()
            ->and($a->encerramento_pendente_no_provedor)->toBeFalse()
            ->and(FakePaymentProvider::tentativasDe($cobranca))->toBe(ReguaDeCobranca::MAX_TENTATIVAS)
            ->and(FakePaymentProvider::assinaturaCancelada((string) $a->id_externo))->toBeTrue()
            ->and(app(Carteira::class)->verificar($this->usuario->id)->bloqueio?->motivo)->toBe(MotivoBloqueio::PlanoEncerrado)
            ->and(app(Carteira::class)->bloqueioDoDrm($this->usuario->id)?->motivo)->toBe(MotivoBloqueio::PlanoEncerrado);
        Mail::assertSent(AssinaturaEncerradaMessage::class, fn (AssinaturaEncerradaMessage $m) => $m->hasTo($this->usuario->email) && $m->plano === 'Básico');

        // Histórico intacto: assinatura, cobranças e diagnósticos continuam lá.
        expect(Assinatura::query()->count())->toBe(1)
            ->and(Invoice::query()->where('usuario_id', $this->usuario->id)->count())->toBe(1)
            ->and(Diagnostico::query()->find($diagnostico->id))->not->toBeNull();

        // Nada mais acontece nas rodadas seguintes e o usuário pode assinar de novo.
        _rodarRegua(500);
        expect(FakePaymentProvider::tentativasDe($cobranca))->toBe(3)->and(_assinaturaPendente($this->usuario)->status)->toBe(EstadoCobranca::Pendente);
        Mail::assertSent(AssinaturaEncerradaMessage::class, 1);
    });

    it('se o provedor estiver fora na hora de cancelar a recorrência, o encerramento vale e o cancelamento é refeito depois', function () {
        [$a, $primeira] = _assinaturaAtivaNoFake($this->usuario);
        $a = _recusarRenovacao($a, $primeira);
        $a->forceFill(['tentativas_cobranca' => 3, 'proxima_tentativa_em' => now()->addHours(48)])->save();
        $this->travelTo(now()->addHours(48));
        Log::spy();
        FakePaymentProvider::ficarIndisponivel();

        // Com o provedor fora, a régua nem consegue consultar a cobrança: nada muda.
        _rodarRegua();
        expect($a->fresh()->status)->toBe(StatusAssinatura::EmAtraso);

        FakePaymentProvider::ficarIndisponivel(false);
        $reflexo = new ReflectionMethod(ExecutarReguaDeCobranca::class, 'reservar');
        $regua = app(ExecutarReguaDeCobranca::class);
        $reflexo->invoke($regua, $a->id, false); // encerra
        FakePaymentProvider::ficarIndisponivel();

        expect(_rodarRegua())->toBe(0);
        expect($a->fresh()->status)->toBe(StatusAssinatura::Expirada)->and($a->fresh()->encerramento_pendente_no_provedor)->toBeTrue()
            ->and(FakePaymentProvider::assinaturaCancelada((string) $a->id_externo))->toBeFalse();
        Log::shouldHaveReceived('error')->withArgs(fn (string $m, array $c) => $m === 'cobranca.provedor_indisponivel' && $c['passo'] === 'cancelar_recorrencia')->once();

        FakePaymentProvider::ficarIndisponivel(false);
        _rodarRegua();
        expect($a->fresh()->encerramento_pendente_no_provedor)->toBeFalse()->and(FakePaymentProvider::assinaturaCancelada((string) $a->id_externo))->toBeTrue();
    });

    it('cobrança cancelada no provedor durante o atraso encerra na hora: não há o que retentar', function () {
        [$a, $primeira] = _assinaturaAtivaNoFake($this->usuario);
        $a = _recusarRenovacao($a, $primeira);
        $cache = 'fake-pagamento:pay:'.$a->atraso_cobranca_id;
        $c = Cache::get($cache);
        $c['estado'] = EstadoCobranca::Cancelada->value;
        Cache::put($cache, $c, now()->addDays(10));

        _rodarRegua(48);

        expect($a->fresh()->status)->toBe(StatusAssinatura::Expirada)->and(FakePaymentProvider::tentativasDe((string) $a->atraso_cobranca_id))->toBe(0);
    });

    it('pagamento que chega depois do encerramento não reativa: vira alerta para a equipe', function () {
        [$a, $primeira] = _assinaturaAtivaNoFake($this->usuario);
        $a = _recusarRenovacao($a, $primeira);
        $cobranca = (string) $a->atraso_cobranca_id;
        foreach ([48, 48, 48, 48] as $h) {
            _rodarRegua($h);
        }
        Log::spy();

        _entregar(_fake()->simularPagamentoDaCobranca($cobranca));

        expect($a->fresh()->status)->toBe(StatusAssinatura::Expirada)->and(Invoice::query()->where('renovacao', true)->count())->toBe(0);
        Log::shouldHaveReceived('error')->withArgs(fn (string $m) => $m === 'cobranca.renovacao_de_assinatura_inativa')->once();
    });
});

describe('CA-7 indisponibilidade do provedor não é recusa do cliente', function () {
    it('webhook de falha com o provedor fora: não entra em atraso, alerta, e o reprocessamento aplica depois', function () {
        [$a, $primeira] = _assinaturaAtivaNoFake($this->usuario);
        $this->travelTo($a->proximo_vencimento);
        $webhook = _fake()->simularRecusaDeRenovacao((string) $primeira->checkout_id_externo);
        Log::spy();
        FakePaymentProvider::ficarIndisponivel();

        _entregar($webhook);

        expect($a->fresh()->status)->toBe(StatusAssinatura::Ativa)->and(ProviderEvent::query()->latest('recebido_em')->first()?->status)->toBe('erro');
        Log::shouldHaveReceived('error')->withArgs(fn (string $m) => $m === 'cobranca.provedor_indisponivel')->once();

        // O provedor volta e reenvia o webhook (ou o reprocessador retoma): o evento ainda em `erro` é aplicado.
        FakePaymentProvider::ficarIndisponivel(false);
        expect(_entregar($webhook))->toBe('reenfileirado');

        expect($a->fresh()->status)->toBe(StatusAssinatura::EmAtraso);
    });

    it('a régua com o provedor fora não conta tentativa, alerta e retoma quando ele volta', function () {
        [$a, $primeira] = _assinaturaAtivaNoFake($this->usuario);
        $a = _recusarRenovacao($a, $primeira);
        $cobranca = (string) $a->atraso_cobranca_id;
        Log::spy();
        FakePaymentProvider::ficarIndisponivel();

        expect(_rodarRegua(48))->toBe(1);

        expect($a->fresh()->tentativas_cobranca)->toBe(0)->and($a->fresh()->status)->toBe(StatusAssinatura::EmAtraso);
        Log::shouldHaveReceived('error')->withArgs(fn (string $m, array $c) => $m === 'cobranca.provedor_indisponivel' && $c['passo'] === 'consultar_cobranca')->once();

        FakePaymentProvider::ficarIndisponivel(false);
        _rodarRegua(1);
        expect($a->fresh()->tentativas_cobranca)->toBe(1)->and(FakePaymentProvider::tentativasDe($cobranca))->toBe(1);
    });

    it('se o provedor cai bem na hora de pedir a retentativa, a reserva é desfeita', function () {
        [$a, $primeira] = _assinaturaAtivaNoFake($this->usuario);
        $a = _recusarRenovacao($a, $primeira);
        $proxima = $a->proxima_tentativa_em;
        $this->travelTo(now()->addHours(48));
        Log::spy();

        // getCharge passa; retryCharge falha: provedor que responde a leitura e cai na escrita.
        app()->instance(PaymentProvider::class, _provedorQueFalhaNaRetentativa(new ProvedorIndisponivel('caiu')));

        expect(app(ExecutarReguaDeCobranca::class)->executar()['indisponivel'])->toBe(1);

        $a->refresh();
        expect($a->tentativas_cobranca)->toBe(0)->and($a->proxima_tentativa_em?->getTimestamp())->toBe($proxima?->getTimestamp());
        Log::shouldHaveReceived('error')->withArgs(fn (string $m, array $c) => $m === 'cobranca.provedor_indisponivel' && $c['passo'] === 'retentar_cobranca')->once();
    });

    it('o provedor que responde e recusa reagendar conta como tentativa sem sucesso', function () {
        [$a, $primeira] = _assinaturaAtivaNoFake($this->usuario);
        $a = _recusarRenovacao($a, $primeira);
        $this->travelTo(now()->addHours(48));
        Log::spy();

        app()->instance(PaymentProvider::class, _provedorQueFalhaNaRetentativa(new TentativaNaoRealizada('não editável')));

        expect(app(ExecutarReguaDeCobranca::class)->executar()['retentadas'])->toBe(1);

        expect($a->fresh()->tentativas_cobranca)->toBe(1);
        Log::shouldHaveReceived('error')->withArgs(fn (string $m) => $m === 'cobranca.retentativa_recusada_pelo_provedor')->once();
    });
});

describe('CA-8 interruptor desligado', function () {
    it('a régua e a conciliação não fazem nada', function () {
        [$a, $primeira] = _assinaturaAtivaNoFake($this->usuario);
        $a = _recusarRenovacao($a, $primeira);
        CobrancaConfig::atual()->update(['ativa' => false]);

        _rodarRegua(48);
        Artisan::call('cobranca:conciliar', ['--modo' => 'diaria']);

        expect($a->fresh()->tentativas_cobranca)->toBe(0)->and(FakePaymentProvider::tentativasDe((string) $a->atraso_cobranca_id))->toBe(0);
    });
});

describe('avisos que não saíram', function () {
    it('a régua reenvia o aviso de falha que não saiu na hora', function () {
        [$a, $primeira] = _assinaturaAtivaNoFake($this->usuario);
        $this->travelTo($a->proximo_vencimento);
        _emailForaDoAr();
        _entregar(_fake()->simularRecusaDeRenovacao((string) $primeira->checkout_id_externo));
        expect($a->fresh()->status)->toBe(StatusAssinatura::EmAtraso)->and($a->fresh()->atraso_aviso_em)->toBeNull();

        _emailVoltou();
        Artisan::call('cobranca:regua');

        Mail::assertSent(FalhaDeCobrancaMessage::class, 1);
        expect($a->fresh()->atraso_aviso_em)->not->toBeNull();
    });

    it('assinatura em atraso não pode contratar outra (CA-7 da 077 segue valendo)', function () {
        [$a, $primeira] = _assinaturaAtivaNoFake($this->usuario);
        _recusarRenovacao($a, $primeira);

        expect(fn () => _assinaturaPendente($this->usuario))->toThrow(CompraNaoPermitida::class);
    });
});

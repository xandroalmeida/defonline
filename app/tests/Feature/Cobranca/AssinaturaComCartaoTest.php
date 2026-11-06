<?php

declare(strict_types=1);

use App\Domain\Cobranca\AssinaturaVigenteJaExiste;
use App\Domain\Cobranca\Carteira;
use App\Domain\Cobranca\CicloCobranca;
use App\Domain\Cobranca\EstadoCobranca;
use App\Domain\Cobranca\ProvedorIndisponivel;
use App\Domain\Cobranca\StatusAssinatura;
use App\Domain\Cobranca\TipoEvento;
use App\Domain\Cobranca\WebhookBruto;
use App\Infrastructure\Cobranca\Fake\FakePaymentProvider;
use App\Models\Assinatura;
use App\Models\Invoice;
use App\Models\ProviderEvent;
use App\Models\TermAcceptance;
use App\Models\Usuario;
use App\Services\Cobranca\CompraNaoPermitida;
use App\Services\Cobranca\IniciarAssinatura;
use App\Support\DataExibicao;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * STORY-077 — assinatura com cartão recorrente no checkout hospedado, de ponta a ponta no domínio,
 * com o provedor fake. O cartão nunca passa por aqui: a "página do provedor" é simulada.
 */
beforeEach(function () {
    _usarProvedor('fake');
    Mail::fake();
    $this->usuario = _compradorConfirmado();
});

describe('CA-1 checkout e aceite', function () {
    it('registra o aceite do Termo vigente e abre o checkout recorrente com o retrato do plano', function () {
        $plano = _plano();
        $invoice = _assinaturaPendente($this->usuario, $plano, CicloCobranca::Anual);

        expect($invoice->tipo)->toBe('assinatura')
            ->and($invoice->status)->toBe(EstadoCobranca::Pendente)
            ->and($invoice->meio)->toBe('cartao')
            ->and($invoice->valor_centavos)->toBe($plano->preco_anual_centavos)
            ->and($invoice->cota_snapshot)->toBe($plano->cota_mensal)
            ->and($invoice->plano_id)->toBe($plano->id)
            ->and($invoice->ciclo)->toBe('anual')
            ->and($invoice->checkout_url)->not->toBeNull()
            ->and($invoice->aceite_termo_id)->not->toBeNull()
            ->and(TermAcceptance::query()->where('usuario_id', $this->usuario->id)->where('termo_tipo', 'termo_adesao')->value('versao'))->toBe('v2');
    });

    it('reaproveita o checkout ainda válido do mesmo plano e ciclo, e abre outro se o ciclo mudou', function () {
        $a = _assinaturaPendente($this->usuario);
        $b = _assinaturaPendente($this->usuario);
        $c = _assinaturaPendente($this->usuario, null, CicloCobranca::Anual);

        expect($b->id)->toBe($a->id)->and($c->id)->not->toBe($a->id)->and(Invoice::query()->count())->toBe(2);
    });

    it('abre outro checkout se o preço do catálogo mudou desde o anterior', function () {
        $a = _assinaturaPendente($this->usuario);
        _plano()->update(['preco_mensal_centavos' => 5990]);
        $b = _assinaturaPendente($this->usuario);

        expect($b->id)->not->toBe($a->id)->and($b->valor_centavos)->toBe(5990);
    });
});

describe('CA-2 ativação', function () {
    it('só abrir ou voltar do checkout não ativa nada', function () {
        _assinaturaPendente($this->usuario);

        expect(Assinatura::query()->count())->toBe(0);
    });

    it('com o pagamento aprovado a assinatura fica ativa e a cota está disponível na hora', function () {
        $plano = _plano();
        $invoice = _pagarAssinatura(_assinaturaPendente($this->usuario, $plano));
        $a = Assinatura::query()->where('usuario_id', $this->usuario->id)->sole();

        expect($invoice->status)->toBe(EstadoCobranca::Paga)
            ->and($invoice->assinatura_id)->toBe($a->id)
            ->and($a->status)->toBe(StatusAssinatura::Ativa)
            ->and($a->trial)->toBeFalse()
            ->and($a->plano_id)->toBe($plano->id)
            ->and($a->ciclo)->toBe('mensal')
            ->and($a->cota_restante_ciclo)->toBe($plano->cota_mensal)
            ->and($a->proximo_vencimento->toDateString())->toBe(now()->addMonthNoOverflow()->toDateString())
            ->and($a->proxima_cota_em)->toBeNull()
            ->and($a->id_externo)->toStartWith('fakesub_')
            ->and($a->cartao_bandeira)->toBe('VISA')
            ->and($a->cartao_ultimos4)->toBe('4242')
            ->and(app(Carteira::class)->verificar($this->usuario->id)->bloqueio)->toBeNull();
    });

    it('no ciclo anual o vencimento é daqui a 12 meses e a cota do mês seguinte fica agendada', function () {
        _pagarAssinatura(_assinaturaPendente($this->usuario, null, CicloCobranca::Anual));
        $a = Assinatura::query()->sole();

        expect(DataExibicao::converter($a->proximo_vencimento)->toDateString())->toBe(DataExibicao::hoje()->addYear()->toDateString())
            ->and(DataExibicao::converter($a->proxima_cota_em)->toDateString())->toBe(DataExibicao::hoje()->addMonthNoOverflow()->toDateString());
    });

    it('quem estava no teste passa à assinatura e o teste termina', function () {
        $teste = app(Carteira::class)->concederCota($this->usuario->id, 1, now(), now()->addDays(7), null, null, true);

        _pagarAssinatura(_assinaturaPendente($this->usuario));

        expect($teste->fresh()->status)->toBe(StatusAssinatura::Expirada)
            ->and(Assinatura::query()->where('trial', false)->sole()->status)->toBe(StatusAssinatura::Ativa);
    });
});

describe('CA-3 recusa', function () {
    it('cartão recusado mantém o usuário sem assinatura e permite nova tentativa', function () {
        $invoice = _assinaturaPendente($this->usuario);
        _entregar(_fake()->simularRecusa((string) $invoice->checkout_id_externo));

        expect($invoice->fresh()->status)->toBe(EstadoCobranca::Falhou)
            ->and($invoice->fresh()->motivo_encerramento)->toBe('recusado')
            ->and(Assinatura::query()->count())->toBe(0);

        $nova = _assinaturaPendente($this->usuario);
        expect($nova->id)->not->toBe($invoice->id)->and($nova->status)->toBe(EstadoCobranca::Pendente);
    });

    it('se o usuário tentar outro cartão na mesma página do provedor e passar, a assinatura nasce', function () {
        $invoice = _assinaturaPendente($this->usuario);
        _entregar(_fake()->simularRecusa((string) $invoice->checkout_id_externo));
        $invoice = _pagarAssinatura($invoice);

        expect($invoice->status)->toBe(EstadoCobranca::Paga)->and(Assinatura::query()->count())->toBe(1);
    });

    it('prazo vencido sem pagamento não cria assinatura', function () {
        $invoice = _assinaturaPendente($this->usuario);
        _entregar(_fake()->simularExpiracao((string) $invoice->checkout_id_externo));

        expect($invoice->fresh()->status)->toBe(EstadoCobranca::Vencida)->and(Assinatura::query()->count())->toBe(0);
    });

    it('falha do provedor ao abrir o checkout deixa a Invoice como falhou, alerta e não cria assinatura', function () {
        Log::spy();
        FakePaymentProvider::falharProximoCheckout();

        expect(fn () => _assinaturaPendente($this->usuario))->toThrow(ProvedorIndisponivel::class);

        $invoice = Invoice::query()->sole();
        expect($invoice->status)->toBe(EstadoCobranca::Falhou)->and($invoice->motivo_encerramento)->toBe('provedor')->and(Assinatura::query()->count())->toBe(0);
        Log::shouldHaveReceived('error')->withArgs(fn (string $m, array $c) => $m === 'cobranca.provedor_falhou' && $c['action'] === 'iniciar_checkout_assinatura')->once();
    });
});

describe('CA-5 retrato de preço e cota', function () {
    it('mudar o catálogo depois da escolha não altera o que foi contratado', function () {
        $plano = _plano();
        $invoice = _assinaturaPendente($this->usuario, $plano);
        $preco = $invoice->valor_centavos;
        $cota = $plano->cota_mensal;

        $plano->update(['preco_mensal_centavos' => $preco + 1000, 'cota_mensal' => $cota + 5]);
        _pagarAssinatura($invoice);
        $a = Assinatura::query()->sole();

        expect($a->preco_snapshot_centavos)->toBe($preco)->and($a->cota_snapshot)->toBe($cota)->and($a->cota_restante_ciclo)->toBe($cota);
    });

    it('cobrança de valor diferente do combinado não ativa e alerta', function () {
        $invoice = _assinaturaPendente($this->usuario);
        Log::spy();
        // O fake cobra exatamente o valor do pedido; adultera o valor na "conta do provedor".
        $chave = 'fake-pagamento:pay:fakepay_'.substr((string) $invoice->checkout_id_externo, strlen('fakechk_'));
        $webhook = _fake()->simularPagamento((string) $invoice->checkout_id_externo);
        $c = Cache::get($chave);
        $c['valor'] += 1;
        Cache::put($chave, $c, now()->addDay());

        // O webhook já está gravado: a falha do processamento não vira erro para o provedor (retomada pelo reprocessador).
        _entregar($webhook);

        expect(Assinatura::query()->count())->toBe(0)->and($invoice->fresh()->status)->toBe(EstadoCobranca::Pendente)
            ->and(ProviderEvent::query()->sole()->status)->toBe('erro');
        Log::shouldHaveReceived('error')->withArgs(fn (string $m) => $m === 'cobranca.valor_divergente')->once();
    });
});

describe('CA-6 renovação da cota', function () {
    it('a cobrança do mês seguinte estende o período e devolve a cota cheia, sem acumular', function () {
        $invoice = _pagarAssinatura(_assinaturaPendente($this->usuario));
        $a = Assinatura::query()->sole();
        $a->forceFill(['cota_restante_ciclo' => 0])->save();

        _entregar(_fake()->simularRenovacao((string) $invoice->checkout_id_externo));
        $a->refresh();

        expect($a->periodos_pagos)->toBe(2)
            ->and($a->cota_restante_ciclo)->toBe($a->cota_snapshot)
            ->and(DataExibicao::converter($a->proximo_vencimento)->toDateString())->toBe(DataExibicao::converter($a->data_inicio)->addMonthsNoOverflow(2)->toDateString())
            ->and(Invoice::query()->where('renovacao', true)->sole()->status)->toBe(EstadoCobranca::Paga);
    });

    it('a cota que sobrou não soma à do novo ciclo', function () {
        $invoice = _pagarAssinatura(_assinaturaPendente($this->usuario, _plano('Pro')));
        $a = Assinatura::query()->sole();

        _entregar(_fake()->simularRenovacao((string) $invoice->checkout_id_externo));

        expect($a->fresh()->cota_restante_ciclo)->toBe($a->cota_snapshot);
    });

    it('na renovação vale a cota contratada (retrato, STORY-091 CA-2), e o preço é o que o provedor cobrou', function () {
        $plano = _plano();
        $invoice = _pagarAssinatura(_assinaturaPendente($this->usuario, $plano));
        $contratada = $plano->cota_mensal;
        $plano->update(['cota_mensal' => $plano->cota_mensal + 2]);

        _entregar(_fake()->simularRenovacao((string) $invoice->checkout_id_externo));
        $a = Assinatura::query()->sole();

        expect($a->cota_snapshot)->toBe($contratada)->and($a->preco_snapshot_centavos)->toBe($invoice->valor_centavos);
    });

    it('no anual a cota volta cheia no aniversário do mês, uma vez só, e o último ano não agenda mais', function () {
        $this->travelTo(now()->setDate(2026, 1, 31)->setTime(10, 0));
        _pagarAssinatura(_assinaturaPendente($this->usuario, null, CicloCobranca::Anual));
        $a = Assinatura::query()->sole();
        $a->forceFill(['cota_restante_ciclo' => 0])->save();

        // Antes do aniversário nada acontece.
        $this->travelTo(now()->setDate(2026, 2, 27));
        Artisan::call('assinaturas:renovar-cota');
        expect($a->fresh()->cota_restante_ciclo)->toBe(0);

        // Fevereiro não tem dia 31: vale o dia 28. Rodar duas vezes renova uma vez.
        $this->travelTo(now()->setDate(2026, 2, 28)->setTime(11, 0));
        expect(Artisan::call('assinaturas:renovar-cota'))->toBe(0);
        $a->refresh();
        expect($a->cota_restante_ciclo)->toBe($a->cota_snapshot)->and($a->proxima_cota_em?->toDateString())->toBe('2026-03-31');

        $a->forceFill(['cota_restante_ciclo' => 0])->save();
        Artisan::call('assinaturas:renovar-cota');
        expect($a->fresh()->cota_restante_ciclo)->toBe(0);

        // Março volta ao dia 31.
        $this->travelTo(now()->setDate(2026, 3, 31)->setTime(11, 0));
        expect(app(Carteira::class)->renovarCotasMensais())->toBe(1);
        expect($a->fresh()->cota_restante_ciclo)->toBe($a->cota_snapshot);

        // Último aniversário antes do fim do ano pago: depois dele não há mais agendamento.
        $this->travelTo(now()->setDate(2026, 12, 31)->setTime(11, 0));
        app(Carteira::class)->renovarCotasMensais();
        expect($a->fresh()->proxima_cota_em)->toBeNull();
    });

    it('assinatura que não está ativa não ganha cota no aniversário', function () {
        _pagarAssinatura(_assinaturaPendente($this->usuario, null, CicloCobranca::Anual));
        $a = Assinatura::query()->sole();
        $a->forceFill(['cota_restante_ciclo' => 0, 'status' => StatusAssinatura::EmAtraso])->save();

        $this->travelTo(now()->addMonths(2));

        expect(app(Carteira::class)->renovarCotasMensais())->toBe(0)->and($a->fresh()->cota_restante_ciclo)->toBe(0);
    });

    it('o ciclo anual renovado depois de 12 meses estende mais 12 e reagenda a cota mensal', function () {
        $invoice = _pagarAssinatura(_assinaturaPendente($this->usuario, null, CicloCobranca::Anual));
        // Doze meses depois (o cache do fake só dura dias, então recua a assinatura em vez de viajar no tempo).
        Assinatura::query()->update(['data_inicio' => now()->subYear()->subHour(), 'proximo_vencimento' => now()->subHour(), 'proxima_cota_em' => null]);
        $a = Assinatura::query()->sole();

        _entregar(_fake()->simularRenovacao((string) $invoice->checkout_id_externo));
        $a->refresh();

        expect($a->periodos_pagos)->toBe(2)
            ->and(DataExibicao::converter($a->proximo_vencimento)->toDateString())->toBe(DataExibicao::converter($a->data_inicio)->addYears(2)->toDateString())
            ->and($a->proxima_cota_em)->not->toBeNull();
    });
});

describe('CA-7 uma assinatura por usuário', function () {
    it('quem já tem assinatura ativa não abre outro checkout', function () {
        _pagarAssinatura(_assinaturaPendente($this->usuario));

        expect(fn () => _assinaturaPendente($this->usuario, _plano('Pro')))
            ->toThrow(CompraNaoPermitida::class, 'ja_assinante');
    });

    it('assinatura em atraso também conta como vigente', function () {
        _pagarAssinatura(_assinaturaPendente($this->usuario));
        Assinatura::query()->update(['status' => StatusAssinatura::EmAtraso]);

        expect(fn () => _assinaturaPendente($this->usuario))->toThrow(CompraNaoPermitida::class);
    });

    it('depois de cancelada e vencida o usuário pode assinar de novo', function () {
        _pagarAssinatura(_assinaturaPendente($this->usuario));
        Assinatura::query()->update(['status' => StatusAssinatura::Expirada]);

        expect(_assinaturaPendente($this->usuario)->status)->toBe(EstadoCobranca::Pendente);
    });

    it('dois checkouts abertos e pagos: a segunda assinatura não nasce e o caso vira alerta', function () {
        Log::spy();
        $a = _assinaturaPendente($this->usuario);
        $b = _assinaturaPendente($this->usuario, _plano('Pro'));
        _pagarAssinatura($a);
        $b = _pagarAssinatura($b);

        expect(Assinatura::query()->count())->toBe(1)->and($b->status)->toBe(EstadoCobranca::Paga)->and($b->assinatura_id)->toBeNull();
        Log::shouldHaveReceived('error')->withArgs(fn (string $m) => $m === 'cobranca.assinatura_duplicada')->once();
    });

    it('o domínio também recusa conceder uma segunda cota paga', function () {
        _pagarAssinatura(_assinaturaPendente($this->usuario));

        expect(fn () => app(Carteira::class)->concederCota($this->usuario->id, 1, now(), now()->addMonth(), _plano()->id, 'mensal'))
            ->toThrow(AssinaturaVigenteJaExiste::class);
    });
});

describe('CA-8 idempotência', function () {
    it('o mesmo evento recebido duas vezes ativa uma vez só', function () {
        $invoice = _assinaturaPendente($this->usuario);
        $webhook = _fake()->simularPagamento((string) $invoice->checkout_id_externo, 'evt-fixo');

        expect(_entregar($webhook))->toBe('novo')->and(_entregar($webhook))->toBe('duplicado');
        expect(Assinatura::query()->count())->toBe(1)->and(ProviderEvent::query()->count())->toBe(1);
    });

    it('dois eventos distintos para a mesma cobrança (confirmada e recebida) ativam uma vez só', function () {
        $invoice = _assinaturaPendente($this->usuario);
        _entregar(_fake()->simularPagamento((string) $invoice->checkout_id_externo, 'evt-1'));
        _entregar(_fake()->webhook(TipoEvento::CobrancaPaga, (string) $invoice->checkout_id_externo, 'evt-2'));

        expect(Assinatura::query()->count())->toBe(1)->and(Assinatura::query()->sole()->periodos_pagos)->toBe(1);
    });

    it('o mesmo evento de renovação duas vezes renova uma vez; e a mesma cobrança em eventos distintos também', function () {
        $invoice = _pagarAssinatura(_assinaturaPendente($this->usuario));
        $renovacao = _fake()->simularRenovacao((string) $invoice->checkout_id_externo, 'evt-ren');
        $outroEvento = new WebhookBruto($renovacao->cabecalhos, str_replace('evt-ren', 'evt-ren-2', $renovacao->corpo));

        _entregar($renovacao);
        _entregar($renovacao);
        _entregar($outroEvento);

        expect(Assinatura::query()->sole()->periodos_pagos)->toBe(2)->and(Invoice::query()->where('renovacao', true)->count())->toBe(1);
    });

    it('evento fora de ordem: pagamento depois de cancelamento do checkout não ativa', function () {
        $invoice = _assinaturaPendente($this->usuario);
        _entregar(_fake()->simularCancelamento((string) $invoice->checkout_id_externo));
        _entregar(_fake()->webhook(TipoEvento::CobrancaPaga, (string) $invoice->checkout_id_externo));

        expect(Assinatura::query()->count())->toBe(0)->and($invoice->fresh()->status)->toBe(EstadoCobranca::Cancelada);
    });
});

describe('renovação de assinatura que não é nossa ou não está em dia', function () {
    it('pagamento de assinatura desconhecida é ignorado e vira alerta', function () {
        Log::spy();
        $invoice = _pagarAssinatura(_assinaturaPendente($this->usuario));
        Assinatura::query()->update(['id_externo' => 'outra']);

        _entregar(_fake()->simularRenovacao((string) $invoice->checkout_id_externo));

        expect(Assinatura::query()->sole()->periodos_pagos)->toBe(1);
        Log::shouldHaveReceived('error')->withArgs(fn (string $m) => $m === 'cobranca.evento_sem_invoice')->once();
    });

    it('renovação de assinatura cancelada ou encerrada não estende o ciclo: o dinheiro entrou e vira alerta', function (StatusAssinatura $status) {
        Log::spy();
        $invoice = _pagarAssinatura(_assinaturaPendente($this->usuario));
        Assinatura::query()->update(['status' => $status]);

        _entregar(_fake()->simularRenovacao((string) $invoice->checkout_id_externo));

        expect(Assinatura::query()->sole()->periodos_pagos)->toBe(1);
        Log::shouldHaveReceived('error')->withArgs(fn (string $m) => $m === 'cobranca.renovacao_de_assinatura_inativa')->once();
    })->with([StatusAssinatura::Cancelada, StatusAssinatura::Expirada]);

    it('alerta a equipe quando o valor da renovação mudou, marca a Invoice e passa a valer o cobrado (STORY-090 CA-2)', function () {
        Log::spy();
        $invoice = _pagarAssinatura(_assinaturaPendente($this->usuario));
        $webhook = _fake()->simularRenovacao((string) $invoice->checkout_id_externo);
        $cobrancaId = json_decode($webhook->corpo, true)['cobranca_id'];
        $c = Cache::get('fake-pagamento:pay:'.$cobrancaId);
        $c['valor'] = 3990;
        Cache::put('fake-pagamento:pay:'.$cobrancaId, $c, now()->addDay());

        _entregar($webhook);

        expect(Assinatura::query()->sole()->preco_snapshot_centavos)->toBe(3990);
        Log::shouldHaveReceived('error')->withArgs(fn (string $m, array $c) => $m === 'cobranca.valor_da_renovacao_divergente' && $c['module'] === 'cobranca' && $c['esperado_centavos'] === $invoice->valor_centavos && $c['recebido_centavos'] === 3990)->once();
        expect(Invoice::query()->where('renovacao', true)->sole()->valor_esperado_centavos)->toBe($invoice->valor_centavos)
            ->and($invoice->fresh()->valor_esperado_centavos)->toBeNull();
    });
});

describe('CA-9 Logar como', function () {
    it('o admin personificando não consegue contratar', function () {
        $admin = _adminDaEquipe();
        _personificar($admin, $this->usuario);

        expect(fn () => _assinaturaPendente($this->usuario))->toThrow(CompraNaoPermitida::class, 'admin_personificando');
        expect(Invoice::query()->count())->toBe(0);
    });
});

describe('quem pode contratar', function () {
    it('e-mail não confirmado não contrata', function () {
        $u = Usuario::factory()->unconfirmed()->create();

        expect(fn () => _assinaturaPendente($u))->toThrow(CompraNaoPermitida::class, 'email_nao_confirmado');
    });

    it('plano desativado não é vendido', function () {
        $plano = _plano();
        $plano->update(['ativo' => false]);

        expect(fn () => app(IniciarAssinatura::class)->executar($this->usuario, $plano, CicloCobranca::Mensal, null, null))
            ->toThrow(CompraNaoPermitida::class, 'plano_indisponivel');
    });
});

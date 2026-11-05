<?php

declare(strict_types=1);

use App\Domain\Cobranca\Carteira;
use App\Domain\Cobranca\CicloCobranca;
use App\Domain\Cobranca\MeioPagamento;
use App\Domain\Cobranca\MotivoBloqueio;
use App\Domain\Cobranca\ProvedorIndisponivel;
use App\Domain\Cobranca\Trial;
use App\Domain\Cobranca\TrialIndisponivel;
use App\Infrastructure\Cobranca\Fake\FakePaymentProvider;
use App\Livewire\Cobranca\AcompanharPagamento;
use App\Livewire\Diagnostico\Quiz;
use App\Models\Assinatura;
use App\Models\CobrancaConfig;
use App\Models\EmpresaAnalisada;
use App\Models\EventoProduto;
use App\Models\Invoice;
use App\Models\TrialConfig;
use App\Models\Usuario;
use App\Observabilidade\EventLogger;
use App\Observabilidade\EventosCobranca;
use App\Observabilidade\Excecoes\PiiEmEventoException;
use App\Observabilidade\MetricasDeCobranca;
use App\Services\Cobranca\GerirRenovacao;
use App\Services\Cobranca\IniciarCompraDeCreditos;
use App\Services\Cobranca\IniciarRenovacaoPix;
use App\Services\Cobranca\ReembolsarCobranca;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Livewire;

/*
|-------------------------------------------------------------------------
| STORY-086 CA-1 e CA-2 — eventos de produto da cobrança, ponta a ponta no domínio (provedor fake).
| Cada evento sai pelo EventLogger, com o esquema exato de EventosCobranca::ESQUEMA e sem dado pessoal.
|-------------------------------------------------------------------------
*/

beforeEach(function () {
    // O Postgres local roda em America/Sao_Paulo e a aplicação em UTC (IDR-018); produção é UTC.
    DB::statement("SET LOCAL TIME ZONE 'UTC'");
    _usarProvedor('fake');
    Mail::fake();
    TrialConfig::query()->firstOrCreate([], ['ativo' => true, 'dias' => 7, 'analises' => 1]);
    $this->usuario = _compradorConfirmado();
    // O instante do fato segue o relógio do teste (travelTo), não o `now()` do banco.
    EventoProduto::creating(function (EventoProduto $e): void {
        $e->ocorrido_em = now();
    });
});

afterEach(fn () => EventoProduto::flushEventListeners());

/**
 * Os eventos de um nome, em ordem, com as propriedades. Falha se algum sair com chave fora do esquema
 * (ou faltando): é assim que o esquema declarado e o que de fato é gravado não se afastam.
 *
 * @return list<array<string, mixed>>
 */
function _evCob(string $nome, ?string $usuarioId = null): array
{
    return EventoProduto::query()
        ->where('nome_evento', $nome)
        ->when($usuarioId !== null, fn ($q) => $q->where('usuario_id', $usuarioId))
        ->orderBy('id')
        ->get()
        ->map(function (EventoProduto $e) use ($nome): array {
            $props = (array) $e->propriedades;
            $esperadas = EventosCobranca::ESQUEMA[$nome];
            $chaves = array_keys($props);
            sort($chaves);
            sort($esperadas);
            expect($chaves)->toBe($esperadas, "esquema de {$nome}");

            return $props;
        })
        ->all();
}

describe('CA-1 trial ativado e encerrado', function () {
    it('emite trial_ativado na ativação, com dias e análises, uma vez', function () {
        $a = app(Trial::class)->ativar($this->usuario->id, '203.0.113.9');

        $eventos = _evCob('trial_ativado', $this->usuario->id);
        expect($eventos)->toHaveCount(1)
            ->and($eventos[0])->toEqual(['assinatura_id' => $a->id, 'dias' => 7, 'analises' => 1]);
    });

    it('emite trial_encerrado por prazo pela varredura, uma vez, sem mudar a assinatura', function () {
        $a = app(Trial::class)->ativar($this->usuario->id, null);

        $this->artisan('trial:registrar-encerramento')->assertSuccessful();
        expect(_evCob('trial_encerrado'))->toEqual([], 'trial vigente não encerrou');

        $this->travelTo($a->proximo_vencimento->copy()->addHours(3));
        $this->artisan('trial:registrar-encerramento')->assertSuccessful();
        $this->artisan('trial:registrar-encerramento')->assertSuccessful();

        expect(_evCob('trial_encerrado'))->toEqual([['assinatura_id' => $a->id, 'motivo' => 'prazo', 'dias_de_teste' => 7, 'analises_usadas' => 0]])
            ->and($a->fresh()->status->value)->toBe('ativa');
    });

    it('contratar um plano durante o teste encerra o trial com motivo contratou, e a varredura não repete', function () {
        $a = app(Trial::class)->ativar($this->usuario->id, null);
        $this->travelTo(now()->addDays(2));

        _pagarAssinatura(_assinaturaPendente($this->usuario));

        expect(_evCob('trial_encerrado'))->toEqual([['assinatura_id' => $a->id, 'motivo' => 'contratou', 'dias_de_teste' => 2, 'analises_usadas' => 0]]);

        $this->travelTo(now()->addDays(10));
        $this->artisan('trial:registrar-encerramento')->assertSuccessful();
        expect(_evCob('trial_encerrado'))->toHaveCount(1);
    });

    it('conta as análises usadas do trial no encerramento', function () {
        $a = app(Trial::class)->ativar($this->usuario->id, null);
        Assinatura::query()->whereKey($a->id)->update(['cota_restante_ciclo' => 0]);
        $this->travelTo($a->proximo_vencimento->copy()->addMinute());

        $this->artisan('trial:registrar-encerramento')->assertSuccessful();

        expect(_evCob('trial_encerrado')[0]['analises_usadas'])->toBe(1);
    });

    it('a ativação que falha não deixa evento', function () {
        app(Trial::class)->ativar($this->usuario->id, null);

        expect(fn () => app(Trial::class)->ativar($this->usuario->id, null))->toThrow(TrialIndisponivel::class)
            ->and(_evCob('trial_ativado'))->toHaveCount(1);
    });
});

describe('CA-1 checkout iniciado e contratação concluída', function () {
    it('assinatura com cartão: checkout_iniciado ao abrir, contratacao_concluida ao confirmar o pagamento', function () {
        app(Trial::class)->ativar($this->usuario->id, null);
        $plano = _plano();
        $invoice = _assinaturaPendente($this->usuario, $plano, CicloCobranca::Mensal);
        _assinaturaPendente($this->usuario, $plano, CicloCobranca::Mensal); // volta ao mesmo checkout: não conta de novo

        $base = [
            'invoice_id' => $invoice->id, 'tipo' => 'assinatura', 'plano_id' => $plano->id, 'pacote_credito_id' => null,
            'ciclo' => 'mensal', 'meio' => 'cartao', 'valor_centavos' => $plano->preco_mensal_centavos, 'renovacao' => false,
        ];
        expect(_evCob('checkout_iniciado'))->toEqual([$base])->and(_evCob('contratacao_concluida'))->toEqual([]);

        _pagarAssinatura($invoice);
        _entregar(_fake()->simularPagamento((string) $invoice->checkout_id_externo)); // webhook repetido

        $assinatura = Assinatura::query()->where('trial', false)->sole();
        expect(_evCob('contratacao_concluida'))->toEqual([$base + ['assinatura_id' => $assinatura->id, 'veio_do_trial' => true]]);
    });

    it('Pix no plano anual entra no funil como qualquer outro meio', function () {
        $plano = _plano();
        $invoice = _assinaturaPendente($this->usuario, $plano, CicloCobranca::Anual, MeioPagamento::Pix);
        _pagarAssinatura($invoice);

        $iniciado = _evCob('checkout_iniciado')[0];
        $concluida = _evCob('contratacao_concluida')[0];
        expect($iniciado['meio'])->toBe('pix')->and($iniciado['ciclo'])->toBe('anual')->and($iniciado['valor_centavos'])->toBe($plano->preco_anual_centavos)
            ->and($concluida['meio'])->toBe('pix')->and($concluida['veio_do_trial'])->toBeFalse()->and($concluida['renovacao'])->toBeFalse();
    });

    it('compra de créditos: checkout_iniciado e contratacao_concluida do tipo credito, sem assinatura', function () {
        $pacote = _pacote();
        $invoice = app(IniciarCompraDeCreditos::class)->executar($this->usuario, $pacote, null, null, MeioPagamento::Pix);
        _entregar(_fake()->simularPagamento((string) $invoice->checkout_id_externo));

        expect(_evCob('checkout_iniciado')[0])->toMatchArray(['tipo' => 'credito', 'pacote_credito_id' => $pacote->id, 'plano_id' => null, 'meio' => 'pix', 'valor_centavos' => $pacote->preco_centavos])
            ->and(_evCob('contratacao_concluida')[0])->toMatchArray(['invoice_id' => $invoice->id, 'tipo' => 'credito', 'assinatura_id' => null, 'renovacao' => false]);
    });

    it('renovação por Pix do plano anual: checkout e conclusão com renovacao verdadeira, na mesma assinatura', function () {
        $a = _assinaturaPixAtiva($this->usuario);
        $this->travelTo($a->proximo_vencimento->copy()->subDays(2));

        $renov = app(IniciarRenovacaoPix::class)->executar($this->usuario, null, null);
        _entregar(_fake()->simularPagamento((string) $renov->checkout_id_externo));

        $iniciados = _evCob('checkout_iniciado');
        $concluidas = _evCob('contratacao_concluida');
        expect($iniciados)->toHaveCount(2)->and($iniciados[1])->toMatchArray(['invoice_id' => $renov->id, 'renovacao' => true, 'meio' => 'pix'])
            ->and($concluidas)->toHaveCount(2)->and($concluidas[1])->toMatchArray(['invoice_id' => $renov->id, 'renovacao' => true, 'assinatura_id' => $a->id]);
    });

    it('Pix da renovação pago depois do fim vira assinatura nova: conta como contratação, não como renovação', function () {
        $a = _assinaturaPixAtiva($this->usuario);
        $this->travelTo($a->proximo_vencimento->copy()->subMinutes(5));
        $renov = app(IniciarRenovacaoPix::class)->executar($this->usuario, null, null);
        $this->travelTo($a->proximo_vencimento->copy()->addMinutes(5));
        $this->artisan('assinaturas:pix-anual')->assertSuccessful();

        _entregar(_fake()->simularPagamento((string) $renov->checkout_id_externo));

        $nova = Assinatura::query()->where('status', 'ativa')->sole();
        $conclusao = collect(_evCob('contratacao_concluida'))->firstWhere('invoice_id', $renov->id);
        expect($conclusao['renovacao'])->toBeFalse()->and($conclusao['assinatura_id'])->toBe($nova->id);
    });

    it('falha do provedor ao abrir o checkout não deixa checkout_iniciado', function () {
        FakePaymentProvider::falharProximoCheckout();

        expect(fn () => _assinaturaPendente($this->usuario))->toThrow(ProvedorIndisponivel::class)
            ->and(_evCob('checkout_iniciado'))->toEqual([]);
    });
});

describe('CA-1 contratação abandonada', function () {
    it('checkout expirado, cancelado ou recusado vira contratacao_concluida ausente e abandonada com o motivo', function (string $simular, string $motivo) {
        $invoice = _assinaturaPendente($this->usuario);
        _entregar(_fake()->{$simular}((string) $invoice->checkout_id_externo));
        _entregar(_fake()->{$simular}((string) $invoice->checkout_id_externo, 'evt-repetido'));

        $abandonos = _evCob('contratacao_abandonada');
        expect($abandonos)->toHaveCount(1)->and($abandonos[0])->toMatchArray(['invoice_id' => $invoice->id, 'motivo' => $motivo, 'tipo' => 'assinatura'])
            ->and(_evCob('contratacao_concluida'))->toEqual([]);
    })->with([
        'expirou' => ['simularExpiracao', 'expirado'],
        'cancelou no provedor' => ['simularCancelamento', 'cancelado'],
        'cartão recusado' => ['simularRecusa', 'recusado'],
    ]);

    it('o Usuário que cancela pela tela de pagamento abandona a compra de créditos', function () {
        $invoice = app(IniciarCompraDeCreditos::class)->executar($this->usuario, _pacote(), null, null, MeioPagamento::Pix);

        Livewire::actingAs($this->usuario)->test(AcompanharPagamento::class, ['invoice' => $invoice->id])->call('cancelar');

        expect(_evCob('contratacao_abandonada'))->toHaveCount(1)
            ->and(_evCob('contratacao_abandonada')[0])->toMatchArray(['invoice_id' => $invoice->id, 'tipo' => 'credito', 'motivo' => 'cancelado']);
    });

    it('o Pix vencido que o banco confirma depois fica abandonado E concluído na mesma Invoice', function () {
        $invoice = _assinaturaPendente($this->usuario, null, CicloCobranca::Anual, MeioPagamento::Pix);
        _entregar(_fake()->simularExpiracao((string) $invoice->checkout_id_externo));
        _entregar(_fake()->simularPagamento((string) $invoice->checkout_id_externo));

        expect(_evCob('contratacao_abandonada'))->toHaveCount(1)->and(_evCob('contratacao_concluida'))->toHaveCount(1)
            ->and(Invoice::query()->sole()->status->value)->toBe('paga');
    });

    it('falha do provedor (não é desistência do cliente) não conta como abandono', function () {
        FakePaymentProvider::falharProximoCheckout();
        try {
            _assinaturaPendente($this->usuario);
        } catch (ProvedorIndisponivel) {
        }

        expect(_evCob('contratacao_abandonada'))->toEqual([]);
    });
});

describe('CA-1 cupom aplicado', function () {
    it('está definido, sem dado pessoal e idempotente, pronto para o ponto de emissão da STORY-083', function () {
        $invoice = _assinaturaPendente($this->usuario);
        $cupomId = (string) Str::uuid7();

        EventosCobranca::cupomAplicado($this->usuario->id, $cupomId, 'assinatura', $invoice->id, 1500);
        EventosCobranca::cupomAplicado($this->usuario->id, $cupomId, 'assinatura', $invoice->id, 1500);

        expect(_evCob('cupom_aplicado', $this->usuario->id))->toEqual([['cupom_id' => $cupomId, 'tipo' => 'assinatura', 'invoice_id' => $invoice->id, 'desconto_centavos' => 1500]]);
    });

    it('aceita cupom aplicado antes de haver Invoice (revisão do pedido)', function () {
        EventosCobranca::cupomAplicado($this->usuario->id, (string) Str::uuid7(), 'credito');

        expect(_evCob('cupom_aplicado')[0])->toMatchArray(['invoice_id' => null, 'desconto_centavos' => null, 'tipo' => 'credito']);
    });
});

describe('CA-1 cancelamento', function () {
    it('cancelar a renovação emite cancelamento (origem cliente) e reativar emite cancelamento_revertido', function () {
        [$a] = _assinaturaAtivaNoFake($this->usuario);

        app(GerirRenovacao::class)->cancelar($this->usuario);
        app(GerirRenovacao::class)->cancelar($this->usuario); // clique repetido: já está cancelada

        $cancelamentos = _evCob('cancelamento');
        expect($cancelamentos)->toHaveCount(1)
            ->and($cancelamentos[0])->toMatchArray(['assinatura_id' => $a->id, 'origem' => 'cliente', 'ciclo' => 'mensal', 'meio' => 'cartao', 'periodos_pagos' => 1, 'dias_de_assinatura' => 0, 'plano_id' => $a->plano_id]);

        app(GerirRenovacao::class)->reativar($this->usuario);

        expect(_evCob('cancelamento_revertido'))->toEqual([['assinatura_id' => $a->id]]);
    });

    it('cancelar, reativar e cancelar de novo grava dois cancelamentos (cada decisão é um fato)', function () {
        _assinaturaAtivaNoFake($this->usuario);

        app(GerirRenovacao::class)->cancelar($this->usuario);
        $this->travelTo(now()->addSeconds(5));
        app(GerirRenovacao::class)->reativar($this->usuario);
        $this->travelTo(now()->addSeconds(5));
        app(GerirRenovacao::class)->cancelar($this->usuario);

        expect(_evCob('cancelamento'))->toHaveCount(2)->and(_evCob('cancelamento_revertido'))->toHaveCount(1);
    });

    it('cancelar em atraso emite cancelamento com a origem própria', function () {
        [$a, $primeira] = _assinaturaAtivaNoFake($this->usuario);
        _recusarRenovacao($a, $primeira);

        app(GerirRenovacao::class)->cancelar($this->usuario);

        expect(_evCob('cancelamento')[0])->toMatchArray(['assinatura_id' => $a->id, 'origem' => 'cliente_em_atraso']);
    });

    it('a régua que encerra por falta de pagamento emite cancelamento por inadimplência', function () {
        [$a, $primeira] = _assinaturaAtivaNoFake($this->usuario);
        _recusarRenovacao($a, $primeira);

        foreach (range(1, 4) as $_) {
            _rodarRegua(49);
        }

        expect($a->fresh()->status->value)->toBe('expirada')
            ->and(_evCob('cancelamento'))->toHaveCount(1)
            ->and(_evCob('cancelamento')[0])->toMatchArray(['assinatura_id' => $a->id, 'origem' => 'inadimplencia']);
    });

    it('Pix anual que chega ao fim sem renovar emite cancelamento pix_nao_renovado', function () {
        $a = _assinaturaPixAtiva($this->usuario);
        $this->travelTo($a->proximo_vencimento->copy()->addHour());

        $this->artisan('assinaturas:pix-anual')->assertSuccessful();
        $this->artisan('assinaturas:pix-anual')->assertSuccessful();

        expect(_evCob('cancelamento'))->toHaveCount(1)->and(_evCob('cancelamento')[0])->toMatchArray(['assinatura_id' => $a->id, 'origem' => 'pix_nao_renovado', 'meio' => 'pix', 'ciclo' => 'anual']);
    });

    it('reembolso que encerra a assinatura emite cancelamento por reembolso', function () {
        [$a, $invoice] = _assinaturaAtivaNoFake($this->usuario);

        app(ReembolsarCobranca::class)->solicitar($invoice, Usuario::factory()->create(), 'Arrependimento');

        expect(_evCob('cancelamento')[0])->toMatchArray(['assinatura_id' => $a->id, 'origem' => 'reembolso']);
    });

    it('reembolso de créditos não emite cancelamento', function () {
        $invoice = app(IniciarCompraDeCreditos::class)->executar($this->usuario, _pacote(), null, null, MeioPagamento::Cartao);
        _entregar(_fake()->simularPagamento((string) $invoice->checkout_id_externo));

        app(ReembolsarCobranca::class)->solicitar($invoice->fresh(), Usuario::factory()->create(), 'Arrependimento');

        expect(_evCob('cancelamento'))->toEqual([]);
    });
});

describe('CA-1 bloqueio por falta de saldo', function () {
    it('o quiz bloqueado emite bloqueio_sem_saldo uma vez por dia, com a causa', function () {
        CobrancaConfig::atual()->update(['ativa' => true]);
        $empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $this->usuario->id]);

        Livewire::actingAs($this->usuario)->test(Quiz::class, ['empresa' => $empresa]);
        Livewire::actingAs($this->usuario)->test(Quiz::class, ['empresa' => $empresa]);

        expect(_evCob('bloqueio_sem_saldo', $this->usuario->id))->toEqual([['motivo' => 'sem_nada', 'origem' => 'analise']]);

        $this->travelTo(now()->addDay());
        Livewire::actingAs($this->usuario)->test(Quiz::class, ['empresa' => $empresa]);

        expect(_evCob('bloqueio_sem_saldo'))->toHaveCount(2);
    });

    it('o Raio X (DRM) bloqueado emite o evento com origem drm', function () {
        CobrancaConfig::atual()->update(['ativa' => true]);
        $empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $this->usuario->id, 'cnae' => '4711301']);

        $this->actingAs($this->usuario)->get(route('drm.lancar', $empresa))->assertOk();

        expect(_evCob('bloqueio_sem_saldo'))->toEqual([['motivo' => 'sem_nada', 'origem' => 'drm']]);
    });

    it('quem tem saldo ou usa com a cobrança desligada não emite nada', function () {
        $empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $this->usuario->id]);
        CobrancaConfig::atual()->update(['ativa' => false]);
        Livewire::actingAs($this->usuario)->test(Quiz::class, ['empresa' => $empresa]);

        CobrancaConfig::atual()->update(['ativa' => true]);
        app(Carteira::class)->concederCota($this->usuario->id, 3, now(), now()->addMonth(), trial: true);
        Livewire::actingAs($this->usuario)->test(Quiz::class, ['empresa' => $empresa]);

        expect(_evCob('bloqueio_sem_saldo'))->toEqual([]);
    });

    it('cada motivo do enum é emitido sem quebrar o esquema', function (MotivoBloqueio $motivo) {
        EventosCobranca::bloqueioSemSaldo($this->usuario->id, $motivo, EventosCobranca::BLOQUEIO_ANALISE);

        expect(_evCob('bloqueio_sem_saldo')[0]['motivo'])->toBe($motivo->value);
    })->with(MotivoBloqueio::cases());

    it('falha ao gravar o evento não impede a tela de explicar o bloqueio', function () {
        EventoProduto::creating(fn () => throw new RuntimeException('banco de eventos fora'));
        CobrancaConfig::atual()->update(['ativa' => true]);
        $empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $this->usuario->id]);

        Livewire::actingAs($this->usuario)->test(Quiz::class, ['empresa' => $empresa])->assertSee('Você ainda não tem análises');

        EventoProduto::flushEventListeners();
    });
});

describe('CA-3 as métricas saem dos eventos que o fluxo real grava', function () {
    it('trial → assinatura em 30 dias, conversão do checkout (Pix anual incluso) e churn do mês', function () {
        $this->travelTo(CarbonImmutable::parse('2026-08-01 12:00:00', 'UTC'));
        app(Trial::class)->ativar($this->usuario->id, null);
        $cartao = _assinaturaPendente($this->usuario, null, CicloCobranca::Anual);
        $anual = Usuario::factory()->create(['email_confirmed_at' => now()]);
        $pix = _assinaturaPendente($anual, null, CicloCobranca::Anual, MeioPagamento::Pix);
        $abandona = Usuario::factory()->create(['email_confirmed_at' => now()]);
        _assinaturaPendente($abandona, null, CicloCobranca::Anual); // abre o checkout e não conclui
        $this->travelTo(now()->addDays(3));
        _pagarAssinatura($cartao);
        _pagarAssinatura($pix);
        $this->travelTo(CarbonImmutable::parse('2026-09-20 12:00:00', 'UTC'));
        app(GerirRenovacao::class)->cancelar($this->usuario);
        $this->travelTo(CarbonImmutable::parse('2026-11-05 10:00:00', 'UTC'));
        $m = app(MetricasDeCobranca::class);

        $trial = $m->conversaoTrialParaPago(CarbonImmutable::parse('2026-07-01', 'UTC'), now());
        $checkout = $m->conversaoDoCheckout(CarbonImmutable::parse('2026-07-01', 'UTC'), now());
        $setembro = $m->churnMensal(CarbonImmutable::parse('2026-09-15', 'UTC'));
        $agosto = $m->churnMensal(CarbonImmutable::parse('2026-08-15', 'UTC'));

        expect($trial)->toEqual(['coorte' => 1, 'convertidos' => 1, 'taxa' => 1.0])
            ->and($checkout['iniciados'])->toBe(3)->and($checkout['concluidos'])->toBe(2)
            ->and(collect($checkout['segmentos'])->firstWhere('meio', 'pix'))->toMatchArray(['ciclo' => 'anual', 'iniciados' => 1, 'concluidos' => 1])
            ->and(collect($checkout['segmentos'])->firstWhere('meio', 'cartao'))->toMatchArray(['ciclo' => 'anual', 'iniciados' => 2, 'concluidos' => 1])
            ->and($setembro)->toEqual(['mes' => '2026-09', 'base' => 2, 'cancelados' => 1, 'taxa' => 0.5])
            ->and($agosto['base'])->toBe(0);
    });
});

describe('CA-2 canal oficial e sem dado pessoal', function () {

    it('todo evento da cobrança tem esquema declarado, nome único e nenhuma chave proibida pelo EventLogger', function () {
        $proibidas = (new ReflectionClass(EventLogger::class))->getConstant('FORBIDDEN_KEYS');

        foreach (EventosCobranca::ESQUEMA as $nome => $chaves) {
            expect($chaves)->not->toContain(...$proibidas, ...['email', 'cpf', 'cnpj', 'ip', 'nome', 'telefone', 'bandeira', 'ultimos4']);
            expect(preg_match('/^[a-z_]+$/', $nome))->toBe(1);
        }

        expect(array_keys(EventosCobranca::ESQUEMA))->toHaveCount(count(array_unique(array_keys(EventosCobranca::ESQUEMA))));
    });

    it('um fluxo completo (trial, assinatura, pagamento, cancelamento, bloqueio) não grava e-mail, CPF, IP nem dado de cartão', function () {
        app(Trial::class)->ativar($this->usuario->id, '203.0.113.9');
        $invoice = _pagarAssinatura(_assinaturaPendente($this->usuario));
        $a = Assinatura::query()->where('trial', false)->sole();
        app(GerirRenovacao::class)->cancelar($this->usuario);
        EventosCobranca::bloqueioSemSaldo($this->usuario->id, MotivoBloqueio::SemNada, 'analise');

        $todos = EventoProduto::query()->get();
        expect($todos->count())->toBeGreaterThan(6);

        foreach ($todos as $e) {
            $texto = json_encode($e->propriedades->getArrayCopy(), JSON_THROW_ON_ERROR);
            foreach ([$this->usuario->email, $this->usuario->cpf, preg_replace('/\D/', '', (string) $this->usuario->cpf), '203.0.113.9', 'PestAgent', (string) $a->cartao_ultimos4 !== '' ? (string) $a->cartao_ultimos4 : 'sem-cartao', (string) $a->cartao_bandeira !== '' ? (string) $a->cartao_bandeira : 'sem-bandeira'] as $proibido) {
                expect($texto)->not->toContain((string) $proibido);
            }
            expect($texto)->not->toMatch('/@|\b\d{11}\b|\b\d{14}\b/');
        }
    });

    it('o EventLogger recusa, em execução, propriedade pessoal que alguém tente acrescentar a um evento da cobrança', function () {
        expect(fn () => EventLogger::emit(EventosCobranca::CHECKOUT_INICIADO, ['invoice_id' => 'x', 'email' => 'a@b.com']))->toThrow(PiiEmEventoException::class)
            ->and(fn () => EventLogger::emitOnce('k', EventosCobranca::CANCELAMENTO, ['cpf' => '123']))->toThrow(PiiEmEventoException::class);
    });

    it('os eventos da cobrança só saem pelo EventLogger (nenhum acesso direto à tabela)', function () {
        $fonte = (string) file_get_contents(dirname(__DIR__, 3).'/app/Observabilidade/EventosCobranca.php');

        expect($fonte)->not->toMatch('/EventoProduto::(create|insert|firstOrCreate|updateOrCreate|forceCreate)|DB::table\(.evento_produto/')
            ->and($fonte)->toContain('EventLogger::emitOnce');
    });

    it('os eventos levam o request_id do contexto (correlação ADR-004)', function () {
        app(Trial::class)->ativar($this->usuario->id, null);

        expect(EventoProduto::query()->where('nome_evento', 'trial_ativado')->value('request_id'))->not->toBeEmpty();
    });
});

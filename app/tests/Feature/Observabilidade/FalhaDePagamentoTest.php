<?php

declare(strict_types=1);

use App\Domain\Cobranca\EstadoCobranca;
use App\Domain\Cobranca\MeioPagamento;
use App\Domain\Cobranca\TipoEvento;
use App\Models\CobrancaConfig;
use App\Models\EventoProduto;
use App\Observabilidade\EventLogger;
use App\Services\Cobranca\IniciarCompraDeCreditos;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

/*
|-------------------------------------------------------------------------
| STORY-086 CA-4 — taxa de falha de pagamento acima de 10% em 1 hora gera alerta P2.
| A taxa sai dos fatos pagamento_confirmado e pagamento_recusado (um por cobrança).
|-------------------------------------------------------------------------
*/

beforeEach(function () {
    DB::statement("SET LOCAL TIME ZONE 'UTC'");
    _usarProvedor('fake');
    Mail::fake();
    Cache::flush();
    config(['services.telegram.bot_token' => 'tok-do-bot', 'services.telegram.chat_id' => '-100']);
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
    $this->usuario = _compradorConfirmado();
    // O instante do fato segue o relógio do teste (travelTo), não o `now()` do banco.
    EventoProduto::creating(function (EventoProduto $e): void {
        $e->ocorrido_em = now();
    });
});

afterEach(fn () => EventoProduto::flushEventListeners());

/** @return list<array<string, mixed>> */
function _pagamentos(string $nome): array
{
    return EventoProduto::query()->where('nome_evento', $nome)->orderBy('id')->get()->map(fn (EventoProduto $e): array => (array) $e->propriedades)->all();
}

/** Grava `$n` fatos de pagamento direto pelo catálogo da cobrança (cada um com cobrança própria). */
function _fatos(string $nome, int $n): void
{
    for ($i = 0; $i < $n; $i++) {
        EventLogger::emitOnce('teste:'.$nome.':'.$i.':'.uniqid(), $nome, ['tipo' => 'credito', 'meio' => 'cartao', 'invoice_id' => null], null);
    }
}

describe('fatos de pagamento a partir dos eventos do provedor', function () {
    it('cobrança paga vira pagamento_confirmado (uma vez, mesmo com webhook repetido), com tipo e meio', function () {
        $invoice = app(IniciarCompraDeCreditos::class)->executar($this->usuario, _pacote(), null, null, MeioPagamento::Pix);
        _entregar(_fake()->simularPagamento((string) $invoice->checkout_id_externo, 'evt-1'));
        _entregar(_fake()->simularPagamento((string) $invoice->checkout_id_externo, 'evt-2'));

        expect(_pagamentos('pagamento_confirmado'))->toEqual([['tipo' => 'credito', 'meio' => 'pix', 'invoice_id' => $invoice->id]])
            ->and(_pagamentos('pagamento_recusado'))->toBe([]);
    });

    it('cartão recusado no checkout vira pagamento_recusado, ligado à Invoice', function () {
        $invoice = _assinaturaPendente($this->usuario);
        _entregar(_fake()->simularRecusa((string) $invoice->checkout_id_externo));

        expect(_pagamentos('pagamento_recusado'))->toEqual([['tipo' => 'assinatura', 'meio' => 'cartao', 'invoice_id' => $invoice->id]]);
    });

    it('renovação recusada vira pagamento_recusado do tipo renovacao, mesmo sem Invoice', function () {
        [$a, $primeira] = _assinaturaAtivaNoFake($this->usuario);
        _recusarRenovacao($a, $primeira);

        expect(_pagamentos('pagamento_confirmado'))->toHaveCount(1)
            ->and(_pagamentos('pagamento_recusado'))->toEqual([['tipo' => 'renovacao', 'meio' => 'cartao', 'invoice_id' => null]]);
    });

    it('renovação paga (cartão) é pagamento_confirmado do tipo renovacao', function () {
        [$a, $primeira] = _assinaturaAtivaNoFake($this->usuario);
        $this->travelTo($a->proximo_vencimento);
        _entregar(_fake()->simularRenovacao((string) $primeira->checkout_id_externo));

        $tipos = array_column(_pagamentos('pagamento_confirmado'), 'tipo');
        expect($tipos)->toEqual(['assinatura', 'renovacao']);
    });

    it('Pix que apenas venceu NÃO é falha de pagamento (é abandono)', function () {
        $invoice = app(IniciarCompraDeCreditos::class)->executar($this->usuario, _pacote(), null, null, MeioPagamento::Pix);
        _entregar(_fake()->simularExpiracao((string) $invoice->checkout_id_externo));

        expect(_pagamentos('pagamento_recusado'))->toBe([]);
    });

    it('a recusa e o vencimento da MESMA cobrança de renovação contam uma falha só', function () {
        [$a, $primeira] = _assinaturaAtivaNoFake($this->usuario);
        $this->travelTo($a->proximo_vencimento);
        $cobranca = _fake()->criarCobrancaDeRenovacao((string) $primeira->checkout_id_externo, EstadoCobranca::Falhou);
        _entregar(_fake()->webhookDaCobranca(TipoEvento::CobrancaFalhou, $cobranca, 'evt-f'));
        _entregar(_fake()->webhookDaCobranca(TipoEvento::CobrancaVencida, $cobranca, 'evt-v'));

        expect(_pagamentos('pagamento_recusado'))->toHaveCount(1);
    });

    it('evento sem efeito (ignorado) ou de outro tipo não gera fato de pagamento', function () {
        $invoice = _assinaturaPendente($this->usuario);
        _entregar(_fake()->simularCancelamento((string) $invoice->checkout_id_externo));

        expect(_pagamentos('pagamento_confirmado'))->toBe([])->and(_pagamentos('pagamento_recusado'))->toBe([]);
    });

    it('falha ao gravar o fato não derruba o processamento do pagamento', function () {
        $invoice = app(IniciarCompraDeCreditos::class)->executar($this->usuario, _pacote(), null, null, MeioPagamento::Pix);
        EventoProduto::creating(function (EventoProduto $e) {
            if ($e->nome_evento === 'pagamento_confirmado') {
                throw new RuntimeException('eventos fora');
            }
        });

        _entregar(_fake()->simularPagamento((string) $invoice->checkout_id_externo));
        EventoProduto::flushEventListeners();

        expect($invoice->fresh()->status->value)->toBe('paga')->and(_pagamentos('pagamento_confirmado'))->toBe([]);
    });
});

describe('CA-4 alerta P2 acima de 10% em 1 hora', function () {
    it('9 de 10 confirmados e 1 recusado (10%) não alerta: o limite é "acima de"', function () {
        _fatos('pagamento_confirmado', 9);
        _fatos('pagamento_recusado', 1);

        $this->artisan('cobranca:alertar-falha-de-pagamento')->assertSuccessful();

        Http::assertNothingSent();
    });

    it('2 recusados em 10 (20%) alerta P2 com contagens e taxa, sem dado pessoal', function () {
        _fatos('pagamento_confirmado', 8);
        _fatos('pagamento_recusado', 2);

        $this->artisan('cobranca:alertar-falha-de-pagamento')->assertSuccessful();

        Http::assertSent(fn (Request $r) => str_starts_with($r['text'], '[P2] Falha de pagamento acima de 10% na última hora')
            && str_contains($r['text'], '2 de 10 pagamentos foram recusados em 60 min (20.0%)')
            && ! preg_match('/@|\d{11}/', $r['text']));
    });

    it('abaixo do mínimo de tentativas não alerta (1 recusa em 1 tentativa é 100%, não incidente)', function () {
        _fatos('pagamento_recusado', 4);

        $this->artisan('cobranca:alertar-falha-de-pagamento')->assertSuccessful();

        Http::assertNothingSent();
    });

    it('só a última hora conta: fatos antigos ficam de fora', function () {
        _fatos('pagamento_recusado', 6);
        $this->travelTo(now()->addMinutes(61));
        _fatos('pagamento_confirmado', 6);

        $this->artisan('cobranca:alertar-falha-de-pagamento')->assertSuccessful();

        Http::assertNothingSent();
    });

    it('fatos de 59 minutos atrás ainda contam', function () {
        _fatos('pagamento_recusado', 6);
        $this->travelTo(now()->addMinutes(59));
        _fatos('pagamento_confirmado', 4);

        $this->artisan('cobranca:alertar-falha-de-pagamento')->assertSuccessful();

        Http::assertSent(fn (Request $r) => str_contains($r['text'], '6 de 10 pagamentos'));
    });

    it('a hora ruim alerta uma vez por silêncio de 30 minutos, e de novo depois dele', function () {
        _fatos('pagamento_recusado', 6);

        $this->artisan('cobranca:alertar-falha-de-pagamento')->assertSuccessful();
        $this->artisan('cobranca:alertar-falha-de-pagamento')->expectsOutputToContain('em silêncio')->assertSuccessful();
        Http::assertSentCount(1);

        $this->travelTo(now()->addMinutes(31));
        $this->artisan('cobranca:alertar-falha-de-pagamento')->assertSuccessful();
        Http::assertSentCount(2);
    });

    it('sem pagamento nenhum, ou com a cobrança desligada, não faz nada', function () {
        $this->artisan('cobranca:alertar-falha-de-pagamento')->expectsOutputToContain('taxa=-')->assertSuccessful();

        _fatos('pagamento_recusado', 6);
        CobrancaConfig::atual()->update(['ativa' => false]);
        $this->artisan('cobranca:alertar-falha-de-pagamento')->expectsOutputToContain('desligada')->assertSuccessful();

        Http::assertNothingSent();
    });

    it('o limite, a janela e o mínimo vêm da configuração', function () {
        config(['cobranca.observabilidade.falha_de_pagamento.limite' => 0.5, 'cobranca.observabilidade.falha_de_pagamento.minimo_de_tentativas' => 2]);
        _fatos('pagamento_confirmado', 2);
        _fatos('pagamento_recusado', 2);
        $this->artisan('cobranca:alertar-falha-de-pagamento')->assertSuccessful();
        Http::assertNothingSent();

        config(['cobranca.observabilidade.falha_de_pagamento.limite' => 0.4]);
        $this->artisan('cobranca:alertar-falha-de-pagamento')->assertSuccessful();
        Http::assertSentCount(1);
    });

    it('está agendado a cada 5 minutos, junto com o registro do fim do trial', function () {
        $agendados = collect(app(Schedule::class)->events())->mapWithKeys(fn ($e) => [$e->command => $e->expression]);

        expect($agendados->first(fn ($expr, $cmd) => str_contains($cmd, 'cobranca:alertar-falha-de-pagamento')))->toBe('*/5 * * * *')
            ->and($agendados->first(fn ($expr, $cmd) => str_contains($cmd, 'trial:registrar-encerramento')))->toBe('0 * * * *');
    });
});

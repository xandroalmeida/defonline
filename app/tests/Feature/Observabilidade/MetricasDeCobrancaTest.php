<?php

declare(strict_types=1);

use App\Models\EventoProduto;
use App\Models\Usuario;
use App\Observabilidade\EventLogger;
use App\Observabilidade\MetricasDeCobranca;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
|-------------------------------------------------------------------------
| STORY-086 CA-3 — dá para calcular a conversão trial → pago em 30 dias, a conversão do checkout e o
| churn mensal a partir de evento_produto. Os fatos são gravados com o relógio do teste.
|-------------------------------------------------------------------------
*/

beforeEach(function () {
    DB::statement("SET LOCAL TIME ZONE 'UTC'");
    EventoProduto::creating(function (EventoProduto $e): void {
        $e->ocorrido_em = now();
    });
    $this->agora = CarbonImmutable::parse('2026-11-05 10:00:00', 'UTC');
    $this->travelTo($this->agora);
    $this->metricas = app(MetricasDeCobranca::class);
});

afterEach(function () {
    EventoProduto::flushEventListeners();
    Carbon::setTestNow();
    CarbonImmutable::setTestNow();
});

/** Grava o evento "em" `$quando`. */
function _fato(string $quando, string $nome, ?string $usuarioId, array $props = []): void
{
    test()->travelTo(CarbonImmutable::parse($quando, 'UTC'));
    EventLogger::emit($nome, $props, $usuarioId);
}

function _usuarioId(): string
{
    return Usuario::factory()->create()->id;
}

function _contratou(string $quando, string $usuarioId, string $tipo = 'assinatura', bool $renovacao = false, ?string $assinaturaId = null, ?string $invoiceId = null): string
{
    $assinaturaId ??= $tipo === 'assinatura' ? (string) Str::uuid7() : null;
    _fato($quando, 'contratacao_concluida', $usuarioId, ['invoice_id' => $invoiceId ?? (string) Str::uuid7(), 'tipo' => $tipo, 'renovacao' => $renovacao, 'assinatura_id' => $assinaturaId]);

    return (string) $assinaturaId;
}

describe('conversão trial → pago em 30 dias', function () {
    it('conta quem contratou um PLANO dentro de 30 dias da ativação, e só a coorte já madura', function () {
        [$u1, $u2, $u3, $u4, $u5, $u6, $u7] = array_map(fn () => _usuarioId(), range(1, 7));
        foreach ([$u1, $u2, $u3, $u4, $u7] as $u) {
            _fato('2026-08-01 12:00:00', 'trial_ativado', $u);
        }
        _fato('2026-10-20 12:00:00', 'trial_ativado', $u5);   // ainda não teve 30 dias
        _fato('2026-07-01 12:00:00', 'trial_ativado', $u6);   // fora do período pedido
        _contratou('2026-08-20 09:00:00', $u1);                // 19 dias: converteu
        _contratou('2026-08-31 11:59:59', $u7);                // 29 dias e 23h: converteu
        _contratou('2026-09-05 12:00:00', $u2);                // 35 dias: tarde demais
        _contratou('2026-08-10 12:00:00', $u3, 'credito');     // créditos não são plano
        _contratou('2026-08-10 12:00:00', $u4, 'assinatura', renovacao: true); // renovação não é contratação
        _contratou('2026-07-20 12:00:00', $u5);                // antes de ativar o trial: não conta para ele
        $this->travelTo($this->agora);

        $r = $this->metricas->conversaoTrialParaPago(CarbonImmutable::parse('2026-07-15', 'UTC'), $this->agora);

        expect($r)->toBe(['coorte' => 5, 'convertidos' => 2, 'taxa' => 0.4]);
    });

    it('a coorte inteira aparece quando se pede sem a maturidade', function () {
        $u = _usuarioId();
        _fato('2026-10-20 12:00:00', 'trial_ativado', $u);
        _contratou('2026-10-25 12:00:00', $u);
        $this->travelTo($this->agora);

        expect($this->metricas->conversaoTrialParaPago(CarbonImmutable::parse('2026-10-01', 'UTC'), $this->agora)['coorte'])->toBe(0)
            ->and($this->metricas->conversaoTrialParaPago(CarbonImmutable::parse('2026-10-01', 'UTC'), $this->agora, apenasMaduros: false))->toBe(['coorte' => 1, 'convertidos' => 1, 'taxa' => 1.0]);
    });

    it('a janela de dias é parâmetro', function () {
        $u = _usuarioId();
        _fato('2026-08-01 12:00:00', 'trial_ativado', $u);
        _contratou('2026-08-11 12:00:00', $u);
        $this->travelTo($this->agora);
        $de = CarbonImmutable::parse('2026-07-15', 'UTC');

        expect($this->metricas->conversaoTrialParaPago($de, $this->agora, dias: 7)['convertidos'])->toBe(0)
            ->and($this->metricas->conversaoTrialParaPago($de, $this->agora, dias: 14)['convertidos'])->toBe(1);
    });

    it('sem nenhum trial na coorte a taxa é nula, não zero', function () {
        expect($this->metricas->conversaoTrialParaPago(CarbonImmutable::parse('2026-01-01', 'UTC'), $this->agora))->toBe(['coorte' => 0, 'convertidos' => 0, 'taxa' => null]);
    });
});

describe('conversão do checkout', function () {
    it('liga checkout_iniciado a contratacao_concluida por invoice e separa Pix, cartão, anual e renovação', function () {
        $u = _usuarioId();
        $inv = fn () => (string) Str::uuid7();
        [$cartaoOk, $cartaoNao, $pixOk, $pixNao, $creditoOk, $renovOk, $foraDoPeriodo] = [$inv(), $inv(), $inv(), $inv(), $inv(), $inv(), $inv()];
        $iniciar = fn (string $quando, string $id, string $tipo, string $meio, ?string $ciclo, bool $renov = false) => _fato($quando, 'checkout_iniciado', $u, ['invoice_id' => $id, 'tipo' => $tipo, 'plano_id' => null, 'pacote_credito_id' => null, 'ciclo' => $ciclo, 'meio' => $meio, 'valor_centavos' => 4990, 'renovacao' => $renov]);

        $iniciar('2026-10-01 10:00:00', $cartaoOk, 'assinatura', 'cartao', 'mensal');
        $iniciar('2026-10-01 10:00:00', $cartaoNao, 'assinatura', 'cartao', 'mensal');
        $iniciar('2026-10-02 10:00:00', $pixOk, 'assinatura', 'pix', 'anual');
        $iniciar('2026-10-02 10:00:00', $pixNao, 'assinatura', 'pix', 'anual');
        $iniciar('2026-10-03 10:00:00', $creditoOk, 'credito', 'pix', null);
        $iniciar('2026-10-04 10:00:00', $renovOk, 'assinatura', 'pix', 'anual', true);
        $iniciar('2026-08-01 10:00:00', $foraDoPeriodo, 'assinatura', 'cartao', 'mensal');
        _contratou('2026-10-01 10:05:00', $u, invoiceId: $cartaoOk);
        _contratou('2026-10-02 11:00:00', $u, invoiceId: $pixOk);
        _contratou('2026-10-03 10:01:00', $u, 'credito', invoiceId: $creditoOk);
        _contratou('2026-10-04 10:01:00', $u, renovacao: true, invoiceId: $renovOk);
        $this->travelTo($this->agora);

        $r = $this->metricas->conversaoDoCheckout(CarbonImmutable::parse('2026-09-01', 'UTC'), $this->agora);

        expect($r['iniciados'])->toBe(6)->and($r['concluidos'])->toBe(4)->and($r['taxa'])->toEqualWithDelta(4 / 6, 0.0001)
            ->and($r['segmentos'])->toBe([
                ['tipo' => 'assinatura', 'meio' => 'cartao', 'ciclo' => 'mensal', 'renovacao' => false, 'iniciados' => 2, 'concluidos' => 1, 'taxa' => 0.5],
                ['tipo' => 'assinatura', 'meio' => 'pix', 'ciclo' => 'anual', 'renovacao' => false, 'iniciados' => 2, 'concluidos' => 1, 'taxa' => 0.5],
                ['tipo' => 'assinatura', 'meio' => 'pix', 'ciclo' => 'anual', 'renovacao' => true, 'iniciados' => 1, 'concluidos' => 1, 'taxa' => 1.0],
                ['tipo' => 'credito', 'meio' => 'pix', 'ciclo' => null, 'renovacao' => false, 'iniciados' => 1, 'concluidos' => 1, 'taxa' => 1.0],
            ]);
    });

    it('sem checkout nenhum no período devolve zeros e taxa nula', function () {
        expect($this->metricas->conversaoDoCheckout(CarbonImmutable::parse('2026-01-01', 'UTC'), CarbonImmutable::parse('2026-02-01', 'UTC')))->toBe(['iniciados' => 0, 'concluidos' => 0, 'taxa' => null, 'segmentos' => []]);
    });
});

describe('churn mensal', function () {
    it('conta, dos assinantes que abriram o mês, os que cancelaram nele; revertidos, novos e já cancelados ficam de fora', function () {
        [$u1, $u2, $u3, $u4, $u5, $u6] = array_map(fn () => _usuarioId(), range(1, 6));
        $a1 = _contratou('2026-07-10 12:00:00', $u1);
        $a2 = _contratou('2026-07-10 12:00:00', $u2);
        $a3 = _contratou('2026-07-10 12:00:00', $u3);
        $a4 = _contratou('2026-07-10 12:00:00', $u4);
        $a6 = _contratou('2026-08-10 12:00:00', $u6);
        _contratou('2026-10-05 12:00:00', $u1, renovacao: true, assinaturaId: $a1); // renovação não cria base
        $a5 = _contratou('2026-10-05 12:00:00', $u5);                                // nova no mês: fora da base de abertura
        _contratou('2026-09-01 12:00:00', $u1, 'credito');                           // crédito não é assinatura

        _fato('2026-09-10 12:00:00', 'cancelamento', $u2, ['assinatura_id' => $a2, 'origem' => 'cliente']);        // já cancelada na abertura
        _fato('2026-10-12 12:00:00', 'cancelamento', $u3, ['assinatura_id' => $a3, 'origem' => 'cliente']);        // churn
        _fato('2026-10-12 12:00:00', 'cancelamento', $u4, ['assinatura_id' => $a4, 'origem' => 'cliente']);
        _fato('2026-10-20 12:00:00', 'cancelamento_revertido', $u4, ['assinatura_id' => $a4]);                      // reativou: não é churn
        _fato('2026-10-25 12:00:00', 'cancelamento', $u5, ['assinatura_id' => $a5, 'origem' => 'cliente']);        // não estava na abertura
        _fato('2026-09-10 12:00:00', 'cancelamento', $u6, ['assinatura_id' => $a6, 'origem' => 'cliente']);
        _fato('2026-09-15 12:00:00', 'cancelamento_revertido', $u6, ['assinatura_id' => $a6]);                      // cancelou e voltou antes da abertura
        _fato('2026-11-02 12:00:00', 'cancelamento', $u1, ['assinatura_id' => $a1, 'origem' => 'cliente']);        // novembro: fora de outubro
        $this->travelTo($this->agora);

        $r = $this->metricas->churnMensal(CarbonImmutable::parse('2026-10-15', 'UTC'));

        expect($r)->toBe(['mes' => '2026-10', 'base' => 4, 'cancelados' => 1, 'taxa' => 0.25]);
    });

    it('o mês conta em Brasília: 22h do dia 31 em São Paulo já é novembro em UTC, mas é outubro para o churn', function () {
        $u = _usuarioId();
        $a = _contratou('2026-07-10 12:00:00', $u);
        _fato('2026-11-01 01:00:00', 'cancelamento', $u, ['assinatura_id' => $a, 'origem' => 'cliente']); // 31/10 22h em Brasília
        $this->travelTo($this->agora);

        expect($this->metricas->churnMensal(CarbonImmutable::parse('2026-10-10', 'UTC'))['cancelados'])->toBe(1)
            ->and($this->metricas->churnMensal(CarbonImmutable::parse('2026-11-10', 'UTC'))['base'])->toBe(0);
    });

    it('inadimplência, Pix não renovado e reembolso contam como churn tanto quanto a decisão do cliente', function (string $origem) {
        $u = _usuarioId();
        $a = _contratou('2026-07-10 12:00:00', $u);
        _fato('2026-10-12 12:00:00', 'cancelamento', $u, ['assinatura_id' => $a, 'origem' => $origem]);
        $this->travelTo($this->agora);

        expect($this->metricas->churnMensal(CarbonImmutable::parse('2026-10-10', 'UTC'))['taxa'])->toBe(1.0);
    })->with(['cliente', 'cliente_em_atraso', 'inadimplencia', 'pix_nao_renovado', 'reembolso']);

    it('sem assinante na abertura do mês a taxa é nula', function () {
        expect($this->metricas->churnMensal(CarbonImmutable::parse('2026-10-10', 'UTC')))->toBe(['mes' => '2026-10', 'base' => 0, 'cancelados' => 0, 'taxa' => null]);
    });

    it('assinante da base sem nenhum evento de cancelamento: base 1, churn zero', function () {
        _contratou('2026-07-10 12:00:00', _usuarioId());

        expect($this->metricas->churnMensal(CarbonImmutable::parse('2026-10-10', 'UTC')))->toBe(['mes' => '2026-10', 'base' => 1, 'cancelados' => 0, 'taxa' => 0.0]);
    });
});

describe('comando cobranca:metricas', function () {
    it('imprime as três métricas, com Pix anual no corte do checkout', function () {
        $u = _usuarioId();
        _fato('2026-08-01 12:00:00', 'trial_ativado', $u);
        $a = _contratou('2026-08-20 09:00:00', $u);
        _fato('2026-10-12 12:00:00', 'cancelamento', $u, ['assinatura_id' => $a, 'origem' => 'cliente']);
        _fato('2026-10-15 10:00:00', 'checkout_iniciado', $u, ['invoice_id' => 'i-1', 'tipo' => 'assinatura', 'plano_id' => null, 'pacote_credito_id' => null, 'ciclo' => 'anual', 'meio' => 'pix', 'valor_centavos' => 1, 'renovacao' => false]);
        $this->travelTo($this->agora);

        $this->artisan('cobranca:metricas', ['--dias' => 120])
            ->expectsOutputToContain('1 de 1 (100.0%)')
            ->expectsOutputToContain('0 concluídos de 1 iniciados (0.0%)')
            ->expectsOutputToContain('assinatura · pix · anual: 0 de 1 (0.0%)')
            ->expectsOutputToContain('Churn de 2026-10: 1 cancelados de 1 na base de abertura (100.0%)')
            ->assertSuccessful();
    });

    it('sem dados mostra "sem base", e o mês pode ser escolhido; mês inválido é recusado', function () {
        $this->artisan('cobranca:metricas', ['--mes' => '2026-09'])->expectsOutputToContain('Churn de 2026-09: 0 cancelados de 0 na base de abertura (sem base)')->assertSuccessful();
        $this->artisan('cobranca:metricas', ['--mes' => 'setembro'])->expectsOutputToContain('Mês inválido')->assertExitCode(2);
    });
});

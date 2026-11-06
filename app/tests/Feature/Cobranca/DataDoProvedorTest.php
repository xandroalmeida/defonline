<?php

declare(strict_types=1);

use App\Domain\Cobranca\Carteira;
use App\Domain\Cobranca\CotaMensal;
use App\Domain\Cobranca\MotivoBloqueio;
use App\Domain\Cobranca\PaymentProvider;
use App\Domain\Cobranca\ReferenciaProvedor;
use App\Domain\Cobranca\RenovacaoPix;
use App\Domain\Cobranca\Trial;
use App\Models\Assinatura;
use App\Models\TrialConfig;
use App\Support\DataExibicao;
use Carbon\CarbonImmutable;

/**
 * STORY-089 — o Asaas devolve data só com o dia (`2026-11-05`). Lida como meia-noite UTC, ela cai às 21h do dia
 * anterior em Brasília e o acesso acabava um dia antes da cobrança, a cada ciclo (V087 D-04, cenário E7).
 */
beforeEach(function () {
    _asaasSimulado();
    $this->u = _compradorConfirmado();
});

/** Ativa no pay_1 e renova no pay_2 com a data do pagamento como o Asaas manda: só o dia. */
function _asaasDoisCiclosSoComODia($t): void
{
    $i = _assinaturaPendente($t->u);
    $cob = fn (string $id) => AsaasSimulado::$cobrancas[$id] = [
        'id' => $id, 'status' => 'CONFIRMED', 'deleted' => false, 'value' => $i->valor_centavos / 100, 'subscription' => 'sub_1',
        'checkoutSession' => $id === 'pay_1' ? 'chk-1' : null, 'externalReference' => $i->id, 'paymentDate' => DataExibicao::agora()->format('Y-m-d'),
    ];
    $cob('pay_1');
    _postarWebhookAsaas($t, 'PAYMENT_CONFIRMED', 'evt_1', ['payment' => ['id' => 'pay_1', 'subscription' => 'sub_1', 'checkoutSession' => 'chk-1', 'externalReference' => $i->id]])->assertOk();
}

it('CA-1 / E7: data só com o dia é o dia de Brasília; paga em 05/11, vence em 05/12 e o acesso vai até o fim do dia 05/12', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-05 15:00:00', 'America/Sao_Paulo'));
    _asaasDoisCiclosSoComODia($this);
    $a = Assinatura::query()->sole();

    expect(DataExibicao::dataHora($a->data_inicio))->toBe('05/11/2026 00:00')
        ->and(DataExibicao::dataHora($a->proximo_vencimento))->toBe('05/12/2026 23:59');

    $this->travelTo(CarbonImmutable::parse('2026-12-04 22:00:00', 'America/Sao_Paulo'));
    expect(app(Carteira::class)->verificar($this->u->id)->bloqueio)->toBeNull();

    $this->travelTo(CarbonImmutable::parse('2026-12-05 23:30:00', 'America/Sao_Paulo'));
    expect(app(Carteira::class)->verificar($this->u->id)->bloqueio)->toBeNull();

    $this->travelTo(CarbonImmutable::parse('2026-12-06 00:00:01', 'America/Sao_Paulo'));
    expect(app(Carteira::class)->verificar($this->u->id)->bloqueio?->motivo)->toBe(MotivoBloqueio::PlanoEncerrado);
});

it('a cobrança do adaptador traz pagamento e vencimento no dia de Brasília, com ou sem hora', function () {
    AsaasSimulado::$cobrancas['pay_9'] = ['id' => 'pay_9', 'status' => 'CONFIRMED', 'deleted' => false, 'value' => 49.9, 'paymentDate' => '2026-11-05', 'dueDate' => '2026-12-05'];
    AsaasSimulado::$cobrancas['pay_8'] = ['id' => 'pay_8', 'status' => 'CONFIRMED', 'deleted' => false, 'value' => 49.9, 'paymentDate' => '2026-11-05T18:00:00-03:00'];

    $dia = app(PaymentProvider::class)->getCharge(new ReferenciaProvedor('asaas', 'pay_9'));
    $hora = app(PaymentProvider::class)->getCharge(new ReferenciaProvedor('asaas', 'pay_8'));

    expect(DataExibicao::dataHora($dia->pagoEm))->toBe('05/11/2026 00:00')
        ->and(DataExibicao::dataHora($dia->vencimento))->toBe('05/12/2026 00:00')
        ->and(DataExibicao::dataHora($hora->pagoEm))->toBe('05/11/2026 18:00');
});

it('CA-2: Pix anual e trial também vencem no último segundo do dia, e a tela mostra esse mesmo dia', function () {
    $inicio = CarbonImmutable::parse('2026-11-05 15:00:00', 'America/Sao_Paulo');
    $v = RenovacaoPix::novoVencimento($inicio, 0);

    expect(DataExibicao::dataHora($v))->toBe('05/11/2027 23:59')->and(DataExibicao::data($v))->toBe('05/11/2027');

    $this->travelTo($inicio);
    $trial = app(Trial::class)->ativar($this->u->id, '203.0.113.7');

    expect(DataExibicao::dataHora($trial->proximo_vencimento))->toBe(DataExibicao::data($inicio->addDays(TrialConfig::atual()->dias)).' 23:59');
});

it('CA-2: assinatura com cartão e renovação paga depois das 21h em Brasília vencem no dia certo, não no dia seguinte do UTC', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-05 23:30:00', 'America/Sao_Paulo'));
    _asaasDoisCiclosSoComODia($this);
    $a = Assinatura::query()->sole();

    expect(DataExibicao::dataHora($a->proximo_vencimento))->toBe('05/12/2026 23:59');

    $cob = AsaasSimulado::$cobrancas['pay_1'];
    AsaasSimulado::$cobrancas['pay_2'] = [...$cob, 'id' => 'pay_2', 'checkoutSession' => null, 'paymentDate' => '2026-12-05'];
    $this->travelTo(CarbonImmutable::parse('2026-12-05 12:00:00', 'America/Sao_Paulo'));
    _postarWebhookAsaas($this, 'PAYMENT_CONFIRMED', 'evt_2', ['payment' => ['id' => 'pay_2', 'subscription' => 'sub_1', 'externalReference' => $cob['externalReference']]])->assertOk();

    expect(DataExibicao::dataHora($a->fresh()->proximo_vencimento))->toBe('05/01/2027 23:59');
});

it('o aniversário da cota no dia do vencimento é do ciclo seguinte; os anteriores seguem valendo', function () {
    $inicio = CarbonImmutable::parse('2026-11-05 00:00:00', 'America/Sao_Paulo');
    $fim = RenovacaoPix::novoVencimento($inicio, 0);

    expect(CotaMensal::proxima($inicio, CarbonImmutable::parse('2027-09-10', 'America/Sao_Paulo'), $fim)?->format('Y-m-d'))->toBe('2027-10-05')
        ->and(CotaMensal::proxima($inicio, CarbonImmutable::parse('2027-10-10', 'America/Sao_Paulo'), $fim))->toBeNull();
});

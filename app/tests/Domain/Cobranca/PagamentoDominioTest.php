<?php

declare(strict_types=1);

use App\Domain\Cobranca\EstadoCobranca;
use App\Domain\Cobranca\MeioPagamento;
use App\Domain\Cobranca\PedidoCheckout;
use App\Domain\Cobranca\WebhookBruto;

/**
 * STORY-076 / ADR-010 §1 — os tipos canônicos da fronteira com o provedor.
 */
it('só estornada, contestada e cancelada são estados finais', function () {
    foreach (EstadoCobranca::cases() as $estado) {
        expect($estado->final())->toBe(in_array($estado, [EstadoCobranca::Estornada, EstadoCobranca::Contestada, EstadoCobranca::Cancelada], true));
    }
});

it('estado final nunca muda e o mesmo estado não é transição', function () {
    foreach ([EstadoCobranca::Estornada, EstadoCobranca::Contestada, EstadoCobranca::Cancelada] as $final) {
        foreach (EstadoCobranca::cases() as $destino) {
            expect($final->podeIrPara($destino))->toBeFalse();
        }
    }

    expect(EstadoCobranca::Pendente->podeIrPara(EstadoCobranca::Pendente))->toBeFalse();
});

it('pendente, vencida e falhou podem virar paga (o banco confirma depois do prazo)', function () {
    foreach ([EstadoCobranca::Pendente, EstadoCobranca::Vencida, EstadoCobranca::Falhou] as $origem) {
        expect($origem->podeIrPara(EstadoCobranca::Paga))->toBeTrue();
    }
});

it('paga só avança para estorno ou contestação, e estorno parcial só para estorno total ou contestação', function () {
    expect(EstadoCobranca::Paga->podeIrPara(EstadoCobranca::Vencida))->toBeFalse()
        ->and(EstadoCobranca::Paga->podeIrPara(EstadoCobranca::Cancelada))->toBeFalse()
        ->and(EstadoCobranca::Paga->podeIrPara(EstadoCobranca::Estornada))->toBeTrue()
        ->and(EstadoCobranca::Paga->podeIrPara(EstadoCobranca::EstornadaParcial))->toBeTrue()
        ->and(EstadoCobranca::Paga->podeIrPara(EstadoCobranca::Contestada))->toBeTrue()
        ->and(EstadoCobranca::EstornadaParcial->podeIrPara(EstadoCobranca::Estornada))->toBeTrue()
        ->and(EstadoCobranca::EstornadaParcial->podeIrPara(EstadoCobranca::Paga))->toBeFalse();
});

function _pedido(array $sobrescrever = []): PedidoCheckout
{
    $a = [
        'referenciaInterna' => 'ref-1', 'descricao' => 'Pacote', 'valorCentavos' => 1000, 'meios' => [MeioPagamento::Pix],
        'expiraEmMinutos' => 30, 'urlSucesso' => 'https://x/ok', 'urlCancelamento' => 'https://x/c', 'urlExpirado' => 'https://x/e',
    ];

    return new PedidoCheckout(...array_merge($a, $sobrescrever));
}

it('aceita um pedido de checkout válido', function () {
    expect(_pedido()->valorCentavos)->toBe(1000);
    expect(_pedido(['expiraEmMinutos' => 10])->expiraEmMinutos)->toBe(10);
    expect(_pedido(['expiraEmMinutos' => 1440])->expiraEmMinutos)->toBe(1440);
});

it('recusa valor zero ou negativo, sem meios e expiração fora de 10 a 1440 minutos', function (array $sobrescrever) {
    expect(fn () => _pedido($sobrescrever))->toThrow(InvalidArgumentException::class);
})->with([
    'valor zero' => [['valorCentavos' => 0]],
    'valor negativo' => [['valorCentavos' => -5]],
    'sem meios' => [['meios' => []]],
    'expira cedo demais' => [['expiraEmMinutos' => 9]],
    'expira tarde demais' => [['expiraEmMinutos' => 1441]],
]);

it('lê cabeçalhos do webhook sem diferenciar maiúsculas na consulta', function () {
    $w = new WebhookBruto(['asaas-access-token' => 'abc'], '{}');

    expect($w->cabecalho('Asaas-Access-Token'))->toBe('abc')
        ->and($w->cabecalho('outro'))->toBeNull();
});

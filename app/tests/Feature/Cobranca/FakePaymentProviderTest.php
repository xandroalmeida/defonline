<?php

declare(strict_types=1);

use App\Domain\Cobranca\EstadoCobranca;
use App\Domain\Cobranca\MeioPagamento;
use App\Domain\Cobranca\PedidoCheckout;
use App\Domain\Cobranca\ProvedorIndisponivel;
use App\Domain\Cobranca\ReferenciaProvedor;
use App\Domain\Cobranca\TipoEvento;
use App\Domain\Cobranca\WebhookBruto;
use App\Domain\Cobranca\WebhookInvalido;
use App\Infrastructure\Cobranca\Fake\FakePaymentProvider;

/**
 * STORY-076 CA-8 — o adaptador de teste: determinístico, sem rede, com gatilhos de falha.
 */
function _pedidoFake(string $ref = '22222222-2222-4222-8222-222222222222'): PedidoCheckout
{
    return new PedidoCheckout($ref, 'Pacote', 5000, [MeioPagamento::Pix], 30, 'https://app/ok', 'https://app/c', 'https://app/e');
}

it('abre o checkout, paga e informa a cobrança pelo mesmo caminho do provedor real', function () {
    $fake = new FakePaymentProvider('token-fake');
    $sessao = $fake->startCheckout(_pedidoFake());

    $webhook = $fake->simularPagamento($sessao->referencia->id);
    $evento = $fake->parseWebhook($webhook);

    expect($evento->tipo)->toBe(TipoEvento::CobrancaPaga)
        ->and($evento->checkoutId)->toBe($sessao->referencia->id)
        ->and($evento->referenciaInterna)->toBe('22222222-2222-4222-8222-222222222222');

    $c = $fake->getCharge(new ReferenciaProvedor('fake', $evento->cobrancaId));
    expect($c->estado)->toBe(EstadoCobranca::Paga)->and($c->valorCentavos)->toBe(5000)->and($c->pagoEm)->not->toBeNull();
});

it('devolve cobrança pendente antes de pagar e cancelada depois do cancelamento', function () {
    $fake = new FakePaymentProvider('token-fake');
    $sessao = $fake->startCheckout(_pedidoFake());
    $cobranca = new ReferenciaProvedor('fake', 'fakepay_'.substr($sessao->referencia->id, strlen('fakechk_')));

    expect($fake->getCharge($cobranca)->estado)->toBe(EstadoCobranca::Pendente);

    $fake->cancelCheckout($sessao->referencia);
    expect($fake->getCharge($cobranca)->estado)->toBe(EstadoCobranca::Cancelada);

    $fake->cancelCheckout(new ReferenciaProvedor('fake', 'fakechk_inexistente')); // sem efeito, sem erro
});

it('gera webhooks de expiração e cancelamento, e aceita id de evento repetido', function () {
    $fake = new FakePaymentProvider('token-fake');
    $sessao = $fake->startCheckout(_pedidoFake());

    expect($fake->parseWebhook($fake->simularExpiracao($sessao->referencia->id, 'evt-fixo'))->tipo)->toBe(TipoEvento::CheckoutExpirado)
        ->and($fake->parseWebhook($fake->simularCancelamento($sessao->referencia->id, 'evt-fixo'))->eventoId)->toBe('evt-fixo');
});

it('falha o próximo checkout sob demanda, uma vez só', function () {
    $fake = new FakePaymentProvider('token-fake');
    FakePaymentProvider::falharProximoCheckout();

    expect(fn () => $fake->startCheckout(_pedidoFake()))->toThrow(ProvedorIndisponivel::class);
    expect($fake->startCheckout(_pedidoFake())->url)->toContain('/pagamentos/fake/');
});

it('recusa cobrança desconhecida', function () {
    expect(fn () => (new FakePaymentProvider('t'))->getCharge(new ReferenciaProvedor('fake', 'fakepay_nada')))
        ->toThrow(ProvedorIndisponivel::class);
});

it('recusa webhook sem token, com token errado ou corpo ilegível', function (array $cabecalhos, string $corpo) {
    expect(fn () => (new FakePaymentProvider('token-fake'))->parseWebhook(new WebhookBruto($cabecalhos, $corpo)))
        ->toThrow(WebhookInvalido::class);
})->with([
    'sem token' => [[], '{"id":"e","tipo":"cobranca.paga"}'],
    'token errado' => [['x-fake-token' => 'outro'], '{"id":"e","tipo":"cobranca.paga"}'],
    'corpo ilegível' => [['x-fake-token' => 'token-fake'], 'xx'],
    'tipo desconhecido' => [['x-fake-token' => 'token-fake'], '{"id":"e","tipo":"nada"}'],
    'sem id' => [['x-fake-token' => 'token-fake'], '{"tipo":"cobranca.paga"}'],
]);

it('deriva um token estável da chave da aplicação quando nenhum é configurado', function () {
    $a = new FakePaymentProvider;
    $sessao = $a->startCheckout(_pedidoFake());

    expect($a->parseWebhook((new FakePaymentProvider(''))->webhook(TipoEvento::CobrancaPaga, $sessao->referencia->id))->tipo)
        ->toBe(TipoEvento::CobrancaPaga);
});

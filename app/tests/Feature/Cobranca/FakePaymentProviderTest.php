<?php

declare(strict_types=1);

use App\Domain\Cobranca\CicloCobranca;
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

// STORY-079 — gatilhos do fake para retentativa, cancelamento, listagem e indisponibilidade.
describe('STORY-079', function () {
    afterEach(fn () => FakePaymentProvider::ficarIndisponivel(false));

    function _fakeComAssinatura(FakePaymentProvider $fake): array
    {
        $pedido = new PedidoCheckout('33333333-3333-4333-8333-333333333333', 'Plano', 4990, [MeioPagamento::Cartao], 30, 'https://app/ok', 'https://app/c', 'https://app/e', CicloCobranca::Mensal);
        $sessao = $fake->startCheckout($pedido);
        $fake->simularPagamento($sessao->referencia->id);
        $c = $fake->getCharge(new ReferenciaProvedor('fake', 'fakepay_'.substr($sessao->referencia->id, strlen('fakechk_'))));

        return [$sessao, $c];
    }

    it('a retentativa não cria cobrança, conta a tentativa e só paga quando o teste manda', function () {
        $fake = new FakePaymentProvider('token-fake');
        [$sessao, $paga] = _fakeComAssinatura($fake);
        $nova = $fake->criarCobrancaDeRenovacao($sessao->referencia->id, EstadoCobranca::Vencida);
        $ref = new ReferenciaProvedor('fake', $nova);
        $antes = count(FakePaymentProvider::cobrancasDaAssinatura((string) $paga->assinaturaId));

        expect($fake->retryCharge($ref)->estado)->toBe(EstadoCobranca::Vencida);

        FakePaymentProvider::aprovarProximaTentativa($nova);
        expect($fake->retryCharge($ref)->estado)->toBe(EstadoCobranca::Paga)
            ->and($fake->retryCharge($ref)->estado)->toBe(EstadoCobranca::Paga)
            ->and(FakePaymentProvider::tentativasDe($nova))->toBe(3)
            ->and(count(FakePaymentProvider::cobrancasDaAssinatura((string) $paga->assinaturaId)))->toBe($antes);
    });

    it('lista as cobranças da assinatura e a do checkout só depois que existe pagamento ou recusa', function () {
        $fake = new FakePaymentProvider('token-fake');
        $sessao = $fake->startCheckout(_pedidoFake('44444444-4444-4444-8444-444444444444'));
        expect($fake->listCheckoutCharges($sessao->referencia))->toBe([]);

        $fake->simularPagamento($sessao->referencia->id);
        expect($fake->listCheckoutCharges($sessao->referencia))->toHaveCount(1);

        [$s, $paga] = _fakeComAssinatura($fake);
        $fake->criarCobrancaDeRenovacao($s->referencia->id, EstadoCobranca::Pendente);
        expect($fake->listSubscriptionCharges(new ReferenciaProvedor('fake', (string) $paga->assinaturaId), new DateTimeImmutable('-1 day')))->toHaveCount(2);
    });

    it('cancela a recorrência e lembra que cancelou', function () {
        $fake = new FakePaymentProvider('token-fake');
        [, $paga] = _fakeComAssinatura($fake);

        expect(FakePaymentProvider::assinaturaCancelada((string) $paga->assinaturaId))->toBeFalse();
        $fake->cancelSubscription(new ReferenciaProvedor('fake', (string) $paga->assinaturaId));
        expect(FakePaymentProvider::assinaturaCancelada((string) $paga->assinaturaId))->toBeTrue();
    });

    it('indisponível: toda chamada que precisa do provedor falha até ele voltar', function () {
        $fake = new FakePaymentProvider('token-fake');
        [$sessao, $paga] = _fakeComAssinatura($fake);
        $ref = new ReferenciaProvedor('fake', $paga->referencia->id);
        FakePaymentProvider::ficarIndisponivel();

        foreach ([
            fn () => $fake->getCharge($ref),
            fn () => $fake->retryCharge($ref),
            fn () => $fake->cancelSubscription(new ReferenciaProvedor('fake', 'fakesub_x')),
            fn () => $fake->listSubscriptionCharges(new ReferenciaProvedor('fake', 'fakesub_x'), new DateTimeImmutable),
            fn () => $fake->listCheckoutCharges($sessao->referencia),
        ] as $chamada) {
            expect($chamada)->toThrow(ProvedorIndisponivel::class, 'indisponível');
        }

        FakePaymentProvider::ficarIndisponivel(false);
        expect($fake->getCharge($ref)->estado)->toBe(EstadoCobranca::Paga);
    });

    it('cobrança de renovação perdida e webhook de cobrança desconhecida', function () {
        $fake = new FakePaymentProvider('token-fake');
        [$sessao] = _fakeComAssinatura($fake);

        expect($fake->criarCobrancaDeRenovacao($sessao->referencia->id, EstadoCobranca::Paga))->toStartWith('fakepay_')
            ->and(fn () => $fake->webhookDaCobranca(TipoEvento::CobrancaPaga, 'fakepay_inexistente'))->toThrow(ProvedorIndisponivel::class);
    });
});

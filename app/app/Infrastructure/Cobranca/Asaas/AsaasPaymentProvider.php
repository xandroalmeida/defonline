<?php

declare(strict_types=1);

namespace App\Infrastructure\Cobranca\Asaas;

use App\Domain\Cobranca\CicloCobranca;
use App\Domain\Cobranca\CobrancaNoProvedor;
use App\Domain\Cobranca\EstadoCobranca;
use App\Domain\Cobranca\EventoDoProvedor;
use App\Domain\Cobranca\MeioPagamento;
use App\Domain\Cobranca\PaymentProvider;
use App\Domain\Cobranca\PedidoCheckout;
use App\Domain\Cobranca\ProvedorIndisponivel;
use App\Domain\Cobranca\ReferenciaProvedor;
use App\Domain\Cobranca\SessaoCheckout;
use App\Domain\Cobranca\TipoEvento;
use App\Domain\Cobranca\WebhookBruto;
use App\Domain\Cobranca\WebhookInvalido;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * ADR-010 — adaptador do Asaas: Checkout hospedado (o pagador digita tudo na página do Asaas) e
 * webhook com `asaas-access-token`. Este é o ÚNICO lugar que conhece tipos, status e rotas do
 * Asaas; um teste de arquitetura vigia isso.
 *
 * Segurança: nunca loga corpo de requisição ou resposta, só status e códigos de erro; a chave não
 * aparece em mensagem nenhuma. Host e chave vêm do ambiente e o prefixo da chave tem que bater com
 * o host, para o desenvolvimento nunca cobrar de verdade nem a produção cair no sandbox.
 */
final class AsaasPaymentProvider implements PaymentProvider
{
    public const NOME = 'asaas';

    private const HOSTS = [
        'api-sandbox.asaas.com' => '$aact_hmlg_',
        'api.asaas.com' => '$aact_prod_',
    ];

    private const EVENTOS = [
        'PAYMENT_CREATED' => TipoEvento::CobrancaCriada,
        'PAYMENT_CONFIRMED' => TipoEvento::CobrancaPaga,
        'PAYMENT_RECEIVED' => TipoEvento::CobrancaPaga,
        'PAYMENT_OVERDUE' => TipoEvento::CobrancaVencida,
        'PAYMENT_REFUNDED' => TipoEvento::CobrancaEstornada,
        'PAYMENT_CHARGEBACK_REQUESTED' => TipoEvento::CobrancaContestada,
        'PAYMENT_CHARGEBACK_DISPUTE' => TipoEvento::CobrancaContestada,
        'PAYMENT_AWAITING_CHARGEBACK_REVERSAL' => TipoEvento::CobrancaContestada,
        'PAYMENT_DELETED' => TipoEvento::CobrancaCancelada,
        'PAYMENT_CREDIT_CARD_CAPTURE_REFUSED' => TipoEvento::CobrancaFalhou,
        'PAYMENT_REPROVED_BY_RISK_ANALYSIS' => TipoEvento::CobrancaFalhou,
        'CHECKOUT_PAID' => TipoEvento::CheckoutConcluido,
        'CHECKOUT_EXPIRED' => TipoEvento::CheckoutExpirado,
        'CHECKOUT_CANCELED' => TipoEvento::CheckoutCancelado,
    ];

    private const CHAVES_DE_CARTAO = ['creditCard', 'creditCardToken', 'creditCardNumber', 'creditCardBrand'];

    private string $baseUrl;

    public function __construct(
        ?string $baseUrl,
        private readonly ?string $apiKey,
        private readonly ?string $webhookToken,
        private readonly int $timeout = 30,
    ) {
        $this->baseUrl = rtrim((string) $baseUrl, '/');
        $host = (string) parse_url($this->baseUrl, PHP_URL_HOST);

        if ($baseUrl === null || ! str_starts_with($this->baseUrl, 'https://') || ! isset(self::HOSTS[$host])) {
            throw new ProvedorIndisponivel('ASAAS_BASE_URL ausente ou inválida: use o host do sandbox ou o de produção, sem padrão.');
        }

        if ($apiKey === null || ! str_starts_with($apiKey, self::HOSTS[$host])) {
            throw new ProvedorIndisponivel('A chave do Asaas está ausente ou não corresponde ao ambiente do ASAAS_BASE_URL.');
        }
    }

    public function nome(): string
    {
        return self::NOME;
    }

    public function startCheckout(PedidoCheckout $pedido): SessaoCheckout
    {
        $resposta = $this->enviar('post', '/checkouts', [
            'billingTypes' => array_map(fn (MeioPagamento $m): string => $m === MeioPagamento::Pix ? 'PIX' : 'CREDIT_CARD', $pedido->meios),
            'chargeTypes' => [$pedido->recorrencia === null ? 'DETACHED' : 'RECURRENT'],
            ...($pedido->recorrencia === null ? [] : ['subscription' => [
                'cycle' => $pedido->recorrencia === CicloCobranca::Mensal ? 'MONTHLY' : 'YEARLY',
                // A primeira cobrança é a do próprio checkout: hoje, no fuso do negócio.
                'nextDueDate' => (new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d').' 00:00:00',
            ]]),
            'minutesToExpire' => $pedido->expiraEmMinutos,
            'externalReference' => $pedido->referenciaInterna,
            'items' => [[
                'name' => mb_substr($pedido->descricao, 0, 30),
                'description' => $pedido->descricao,
                'quantity' => 1,
                'value' => round($pedido->valorCentavos / 100, 2),
            ]],
            'callback' => [
                'successUrl' => $pedido->urlSucesso,
                'cancelUrl' => $pedido->urlCancelamento,
                'expiredUrl' => $pedido->urlExpirado,
            ],
        ]);

        $id = $resposta->json('id');
        $link = $resposta->json('link');

        if (! is_string($id) || $id === '' || ! is_string($link) || $link === '') {
            throw new ProvedorIndisponivel('O Asaas respondeu ao checkout sem id ou link.');
        }

        return new SessaoCheckout(
            new ReferenciaProvedor(self::NOME, $id),
            $link,
            new DateTimeImmutable("+{$pedido->expiraEmMinutos} minutes"),
        );
    }

    public function cancelCheckout(ReferenciaProvedor $sessao): void
    {
        $this->enviar('post', '/checkouts/'.rawurlencode($sessao->id).'/cancel');
    }

    public function getCharge(ReferenciaProvedor $cobranca): CobrancaNoProvedor
    {
        $r = $this->enviar('get', '/payments/'.rawurlencode($cobranca->id));
        $valor = $r->json('value');
        $pago = $r->json('paymentDate') ?? $r->json('confirmedDate');

        return new CobrancaNoProvedor(
            $cobranca,
            $this->estado((string) $r->json('status'), (bool) $r->json('deleted')),
            is_numeric($valor) ? (int) round(((float) $valor) * 100) : 0,
            $this->texto($r->json('checkoutSession')),
            $this->texto($r->json('externalReference')),
            is_string($pago) && $pago !== '' ? new DateTimeImmutable($pago) : null,
            $this->texto($r->json('subscription')),
            $this->bandeira($r->json('creditCard.creditCardBrand')),
            $this->ultimos4($r->json('creditCard.creditCardNumber')),
        );
    }

    public function parseWebhook(WebhookBruto $requisicao): EventoDoProvedor
    {
        $enviado = $requisicao->cabecalho('asaas-access-token');

        if ($this->webhookToken === null || $this->webhookToken === '' || $enviado === null || ! hash_equals($this->webhookToken, $enviado)) {
            throw new WebhookInvalido('Token do webhook ausente ou inválido.');
        }

        $dados = json_decode($requisicao->corpo, true);

        if (! is_array($dados) || ! is_string($dados['id'] ?? null) || $dados['id'] === '' || ! is_string($dados['event'] ?? null)) {
            throw new WebhookInvalido('Corpo do webhook ilegível.');
        }

        $pagamento = is_array($dados['payment'] ?? null) ? $dados['payment'] : [];
        $checkout = is_array($dados['checkout'] ?? null) ? $dados['checkout'] : [];

        return new EventoDoProvedor(
            provedor: self::NOME,
            eventoId: $dados['id'],
            tipo: self::EVENTOS[$dados['event']] ?? TipoEvento::Outro,
            cobrancaId: $this->texto($pagamento['id'] ?? null),
            checkoutId: $this->texto($pagamento['checkoutSession'] ?? ($checkout['id'] ?? null)),
            referenciaInterna: $this->texto($pagamento['externalReference'] ?? ($checkout['externalReference'] ?? null)),
            assinaturaId: $this->texto($pagamento['subscription'] ?? null),
            payload: $this->semDadosDeCartao($dados),
        );
    }

    private function estado(string $status, bool $removida): EstadoCobranca
    {
        if ($removida) {
            return EstadoCobranca::Cancelada;
        }

        return match ($status) {
            'RECEIVED', 'CONFIRMED', 'RECEIVED_IN_CASH', 'REFUND_REQUESTED', 'REFUND_IN_PROGRESS' => EstadoCobranca::Paga,
            'OVERDUE' => EstadoCobranca::Vencida,
            'REFUNDED' => EstadoCobranca::Estornada,
            'CHARGEBACK_REQUESTED', 'CHARGEBACK_DISPUTE', 'AWAITING_CHARGEBACK_REVERSAL' => EstadoCobranca::Contestada,
            default => EstadoCobranca::Pendente,
        };
    }

    /** Só a bandeira, em maiúsculas e curta. Nunca o token do cartão. */
    private function bandeira(mixed $valor): ?string
    {
        $v = $this->texto($valor);

        return $v === null ? null : mb_substr(mb_strtoupper($v), 0, 20);
    }

    /** O Asaas devolve os 4 últimos dígitos; por garantia, só os últimos 4 dígitos do que vier. */
    private function ultimos4(mixed $valor): ?string
    {
        $digitos = preg_replace('/\D/', '', (string) (is_scalar($valor) ? $valor : ''));

        return strlen((string) $digitos) >= 4 ? substr((string) $digitos, -4) : null;
    }

    private function texto(mixed $valor): ?string
    {
        return is_string($valor) && $valor !== '' ? $valor : null;
    }

    /**
     * @param  array<array-key, mixed>  $dados
     * @return array<array-key, mixed>
     */
    private function semDadosDeCartao(array $dados): array
    {
        $limpo = [];

        foreach ($dados as $chave => $valor) {
            if (in_array($chave, self::CHAVES_DE_CARTAO, true)) {
                continue;
            }

            $limpo[$chave] = is_array($valor) ? $this->semDadosDeCartao($valor) : $valor;
        }

        return $limpo;
    }

    /** @param array<string, mixed>|null $corpo */
    private function enviar(string $metodo, string $caminho, ?array $corpo = null): Response
    {
        try {
            $r = $this->cliente()->{$metodo}($caminho, $corpo ?? []);
        } catch (ConnectionException) {
            throw new ProvedorIndisponivel('O Asaas não respondeu a tempo.');
        } catch (Throwable $e) {
            throw new ProvedorIndisponivel('Falha ao falar com o Asaas: '.$e::class);
        }

        if ($r->failed()) {
            $codigos = [];

            foreach ((array) ($r->json('errors') ?? []) as $erro) {
                if (is_array($erro) && is_string($erro['code'] ?? null)) {
                    $codigos[] = $erro['code'];
                }
            }

            $codigos = implode(',', $codigos);

            throw new ProvedorIndisponivel("O Asaas respondeu HTTP {$r->status()}".($codigos !== '' ? " ({$codigos})" : '').'.');
        }

        return $r;
    }

    private function cliente(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->withHeaders(['access_token' => (string) $this->apiKey, 'User-Agent' => 'DEFOnline/1.0'])
            ->acceptJson()
            ->asJson()
            ->timeout($this->timeout);
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Cobranca;

use DateTimeImmutable;

/**
 * ADR-010 §1 — a única porta entre o produto e o provedor de pagamento. Tipos, estados e eventos
 * são NOSSOS: nada do Asaas (ou de outro provedor) atravessa esta fronteira. Trocar de provedor
 * é escrever outra implementação e registrá-la; regra de plano, carteira e crédito não muda.
 *
 * Entram conforme as estórias precisam: checkout avulso (076), assinatura (077), retentativa,
 * cancelamento da recorrência e conciliação (079). `ensureCustomer` e estorno ficam para 087.
 */
interface PaymentProvider
{
    /** Nome estável gravado em `invoices.provedor` e `provider_events.provedor` (ex.: `asaas`, `fake`). */
    public function nome(): string;

    /**
     * Abre a sessão de pagamento hospedada pelo provedor.
     *
     * @throws ProvedorIndisponivel se o provedor não respondeu, recusou o pedido ou está mal configurado
     */
    public function startCheckout(PedidoCheckout $pedido): SessaoCheckout;

    /** @throws ProvedorIndisponivel */
    public function cancelCheckout(ReferenciaProvedor $sessao): void;

    /**
     * Estado ATUAL da cobrança no provedor. Eventos de webhook são só um aviso: quem libera
     * acesso confere aqui antes (ADR-010 §3, "fora de ordem").
     *
     * @throws ProvedorIndisponivel
     */
    public function getCharge(ReferenciaProvedor $cobranca): CobrancaNoProvedor;

    /**
     * STORY-079 — pede ao provedor uma nova tentativa de captura da MESMA cobrança, com o cartão que
     * ele já guarda (nós não temos o cartão). Nunca cria cobrança nova: por isso repetir a chamada
     * não cobra em dobro. Devolve o estado da cobrança logo depois do pedido; o resultado definitivo
     * costuma chegar por webhook.
     *
     * @throws ProvedorIndisponivel se o provedor não respondeu (isso NÃO é recusa do cliente)
     * @throws TentativaNaoRealizada se o provedor respondeu mas recusou o pedido de nova tentativa
     */
    public function retryCharge(ReferenciaProvedor $cobranca): CobrancaNoProvedor;

    /**
     * STORY-079 — encerra a recorrência no provedor (ele para de gerar cobranças).
     *
     * @throws ProvedorIndisponivel
     */
    public function cancelSubscription(ReferenciaProvedor $assinatura): void;

    /**
     * STORY-079, conciliação — cobranças já geradas de uma assinatura, criadas a partir de `$desde`.
     *
     * @return list<CobrancaNoProvedor>
     *
     * @throws ProvedorIndisponivel
     */
    public function listSubscriptionCharges(ReferenciaProvedor $assinatura, DateTimeImmutable $desde): array;

    /**
     * STORY-079, conciliação — cobranças geradas por uma sessão de checkout (vazio = ninguém pagou).
     *
     * @return list<CobrancaNoProvedor>
     *
     * @throws ProvedorIndisponivel
     */
    public function listCheckoutCharges(ReferenciaProvedor $sessao): array;

    /**
     * Autentica e traduz um webhook bruto para o evento canônico.
     *
     * @throws WebhookInvalido se a autenticação falhar ou o corpo não for legível
     */
    public function parseWebhook(WebhookBruto $requisicao): EventoDoProvedor;
}

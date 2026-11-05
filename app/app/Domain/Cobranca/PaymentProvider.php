<?php

declare(strict_types=1);

namespace App\Domain\Cobranca;

/**
 * ADR-010 §1 — a única porta entre o produto e o provedor de pagamento. Tipos, estados e eventos
 * são NOSSOS: nada do Asaas (ou de outro provedor) atravessa esta fronteira. Trocar de provedor
 * é escrever outra implementação e registrá-la; regra de plano, carteira e crédito não muda.
 *
 * `ensureCustomer`, estorno e conciliação entram com as estórias que precisam deles (079, 087).
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
     * STORY-081 — encerra a recorrência de vez (a assinatura antiga depois de uma troca de cartão).
     * Idempotente: cancelar o que já não existe não é erro.
     *
     * @throws ProvedorIndisponivel
     */
    public function cancelSubscription(ReferenciaProvedor $assinatura): void;

    /**
     * STORY-081 CA-4 — para de cobrar as próximas renovações sem apagar a assinatura, para que
     * {@see resumeSubscription()} possa religá-la (CA-5).
     *
     * @throws ProvedorIndisponivel
     */
    public function suspendSubscription(ReferenciaProvedor $assinatura): void;

    /**
     * STORY-081 CA-5 — volta a cobrar a renovação da assinatura suspensa.
     *
     * @throws ProvedorIndisponivel
     */
    public function resumeSubscription(ReferenciaProvedor $assinatura): void;

    /**
     * Autentica e traduz um webhook bruto para o evento canônico.
     *
     * @throws WebhookInvalido se a autenticação falhar ou o corpo não for legível
     */
    public function parseWebhook(WebhookBruto $requisicao): EventoDoProvedor;
}

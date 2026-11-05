<?php

declare(strict_types=1);

namespace App\Domain\Cobranca;

/**
 * ADR-010 §1 — a única porta entre o produto e o provedor de pagamento. Tipos, estados e eventos
 * são NOSSOS: nada do Asaas (ou de outro provedor) atravessa esta fronteira. Trocar de provedor
 * é escrever outra implementação e registrá-la; regra de plano, carteira e crédito não muda.
 *
 * Esta estória (076) usa o checkout hospedado avulso. `ensureCustomer`, assinatura, estorno e
 * conciliação entram com as estórias que precisam deles (077, 079, 087).
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
     * Autentica e traduz um webhook bruto para o evento canônico.
     *
     * @throws WebhookInvalido se a autenticação falhar ou o corpo não for legível
     */
    public function parseWebhook(WebhookBruto $requisicao): EventoDoProvedor;
}

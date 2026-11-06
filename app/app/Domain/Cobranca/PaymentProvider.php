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
 * cancelamento da recorrência e conciliação (079), suspender/religar a renovação (081).
 * `ensureCustomer` fica para a 087; o estorno entrou na 085.
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
     * STORY-079/081 — encerra a recorrência de vez no provedor (régua esgotada, ou a assinatura antiga
     * depois de uma troca de cartão). Idempotente: cancelar o que já não existe não é erro.
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
     * STORY-082 — muda o que a assinatura cobra daqui em diante: valor, ciclo e a data da próxima cobrança
     * (cobranças já geradas e ainda não pagas também passam ao valor novo). Repetir o mesmo pedido não
     * tem efeito a mais: é seguro tentar de novo.
     *
     * Também é o gancho do cupom (STORY-083 CA-7, ADR-010 4.7): quando o desconto acaba, o chamador passa o preço
     * cheio, o ciclo e a data da próxima cobrança atuais da assinatura.
     *
     * @throws ProvedorIndisponivel
     */
    public function changeSubscriptionValue(ReferenciaProvedor $assinatura, int $valorCentavos, CicloCobranca $ciclo, DateTimeImmutable $proximaCobrancaEm): void;

    /**
     * STORY-085 / ADR-010 §1 — pede o estorno de uma cobrança paga. Sem `$valorCentavos` é total (o que o
     * backoffice usa: o parcial do cartão só vale a partir do dia seguinte). Quem chama confere antes, por
     * {@see getCharge()}, que a cobrança ainda está paga: repetir o pedido não pode estornar duas vezes.
     *
     * @throws ProvedorIndisponivel se o provedor não respondeu (o resultado é inconclusivo: conferir antes de repetir)
     */
    public function refund(ReferenciaProvedor $cobranca, ?int $valorCentavos = null, string $motivo = ''): ReembolsoNoProvedor;

    /**
     * Autentica e traduz um webhook bruto para o evento canônico.
     *
     * @throws WebhookInvalido se a autenticação falhar ou o corpo não for legível
     */
    public function parseWebhook(WebhookBruto $requisicao): EventoDoProvedor;

    /**
     * Situação do cadastro do webhook com esta URL no provedor, ou `null` se não há cadastro com ela.
     *
     * @throws ProvedorIndisponivel se o provedor não respondeu
     */
    public function situacaoDoWebhook(string $url): ?SituacaoDoWebhook;

    /**
     * Retoma a fila de um webhook pausado. O provedor entrega de novo o que ficou guardado.
     *
     * @throws ProvedorIndisponivel se o provedor não respondeu ou recusou
     */
    public function retomarWebhook(string $id): void;
}

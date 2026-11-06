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
use App\Domain\Cobranca\ReembolsoNoProvedor;
use App\Domain\Cobranca\ReferenciaProvedor;
use App\Domain\Cobranca\ResultadoReembolso;
use App\Domain\Cobranca\SessaoCheckout;
use App\Domain\Cobranca\SituacaoDoWebhook;
use App\Domain\Cobranca\TentativaNaoRealizada;
use App\Domain\Cobranca\TipoEvento;
use App\Domain\Cobranca\WebhookBruto;
use App\Domain\Cobranca\WebhookInvalido;
use App\Support\DataExibicao;
use DateTimeImmutable;
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
        'PAYMENT_CHARGEBACK_REVERSED' => TipoEvento::ContestacaoRevertida,
        'PAYMENT_RESTORED' => TipoEvento::CobrancaRestaurada,
        'PAYMENT_PARTIALLY_REFUNDED' => TipoEvento::EstornoParcial,
        'PAYMENT_REFUND_DENIED' => TipoEvento::EstornoNegado,
        'PAYMENT_CREDIT_CARD_CAPTURE_REFUSED' => TipoEvento::CobrancaFalhou,
        'PAYMENT_REPROVED_BY_RISK_ANALYSIS' => TipoEvento::CobrancaFalhou,
        'CHECKOUT_PAID' => TipoEvento::CheckoutConcluido,
        'CHECKOUT_EXPIRED' => TipoEvento::CheckoutExpirado,
        'CHECKOUT_CANCELED' => TipoEvento::CheckoutCancelado,
    ];

    /**
     * O que nunca vai para `provider_events.payload` (STORY-093 CA-5, E14): cartão e qualquer dado do titular
     * (`creditCardHolderInfo` e os campos soltos de nome, documento, contato, endereço e IP). O checkout não envia
     * dado pessoal, mas se o provedor devolver, não guardamos.
     */
    private const CHAVES_DE_CARTAO = [
        'creditCard', 'creditCardToken', 'creditCardNumber', 'creditCardBrand', 'ccv',
        'creditCardHolderInfo', 'customerData', 'holderName', 'cpfCnpj', 'email', 'phone', 'mobilePhone',
        'postalCode', 'address', 'addressNumber', 'addressComplement', 'province', 'remoteIp',
    ];

    private const PAGINA = 100;

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
                // Por padrão a primeira cobrança é a do próprio checkout: hoje, no fuso do negócio. Na troca
                // de cartão no meio do ciclo (STORY-081) é o vencimento já pago, para não cobrar em dobro.
                'nextDueDate' => DataExibicao::converter($pedido->primeiraCobrancaEm ?? DataExibicao::agora())->format('Y-m-d').' 00:00:00',
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

    /**
     * `PUT /subscriptions/{id}` com `status`: INACTIVE pausa e ACTIVE religa (ADR-010, "Cancelamento").
     * PENDENTE DE CONFIRMAÇÃO NO SANDBOX: que `status` seja aceito no PUT e que religar não gere cobrança retroativa.
     */
    public function suspendSubscription(ReferenciaProvedor $assinatura): void
    {
        $this->enviar('put', '/subscriptions/'.rawurlencode($assinatura->id), ['status' => 'INACTIVE']);
    }

    public function resumeSubscription(ReferenciaProvedor $assinatura): void
    {
        $this->enviar('put', '/subscriptions/'.rawurlencode($assinatura->id), ['status' => 'ACTIVE']);
    }

    /**
     * STORY-082 — `PUT /subscriptions/{id}` com `value`, `cycle`, `nextDueDate` e `updatePendingPayments`
     * (ADR-010 4.7). PENDENTE DE CONFIRMAÇÃO COM PAGAMENTO REAL: que o valor de uma assinatura de cartão possa
     * mudar exige a tokenização habilitada na conta (ADR-010 Q5), que o sandbox não deixa provar sem pagar.
     */
    public function changeSubscriptionValue(ReferenciaProvedor $assinatura, int $valorCentavos, CicloCobranca $ciclo, DateTimeImmutable $proximaCobrancaEm): void
    {
        $this->enviar('put', '/subscriptions/'.rawurlencode($assinatura->id), [
            'value' => round($valorCentavos / 100, 2),
            'cycle' => $ciclo === CicloCobranca::Mensal ? 'MONTHLY' : 'YEARLY',
            'nextDueDate' => DataExibicao::converter($proximaCobrancaEm)->format('Y-m-d'),
            'updatePendingPayments' => true,
        ]);
    }

    public function getCharge(ReferenciaProvedor $cobranca): CobrancaNoProvedor
    {
        $r = $this->enviar('get', '/payments/'.rawurlencode($cobranca->id));

        return $this->cobranca($cobranca, (array) $r->json());
    }

    /**
     * STORY-079 — o Asaas tenta o cartão no vencimento da cobrança (doc: FAQ de assinaturas) e não
     * documenta retentativa nem endpoint para forçá-la sem o cartão. Por isso a retentativa reagenda a
     * MESMA cobrança para hoje (`PUT /payments/{id}` com `dueDate`), o que nunca cria cobrança nova.
     * Se o Asaas recusar o reagendamento (4xx, ex.: cobrança não editável) a tentativa não fez nada e
     * vira `TentativaNaoRealizada`: a régua conta como tentativa sem sucesso, com alerta. Rede e 5xx
     * continuam sendo `ProvedorIndisponivel`.
     */
    public function retryCharge(ReferenciaProvedor $cobranca): CobrancaNoProvedor
    {
        $hoje = DataExibicao::agora()->format('Y-m-d');
        $r = $this->requisitar('put', '/payments/'.rawurlencode($cobranca->id), ['dueDate' => $hoje]);

        if ($r->failed() && $r->serverError()) {
            throw $this->indisponibilidade($r);
        }

        if ($r->failed()) {
            throw new TentativaNaoRealizada("O Asaas recusou reagendar a cobrança (HTTP {$r->status()}).");
        }

        return $this->getCharge($cobranca);
    }

    /**
     * `POST /payments/{id}/refund` (ADR-010 §4.6, confirmado no sandbox): cartão estorna por inteiro na hora
     * (`REFUNDED`, estorno `DONE`); parcial só a partir do dia seguinte; Pix fica em
     * `AWAITING_CRITICAL_ACTION_AUTHORIZATION` até alguém autorizar no painel do Asaas. 4xx = o Asaas
     * respondeu e recusou (`Recusado`, com a descrição dele); rede e 5xx = `ProvedorIndisponivel`.
     */
    public function refund(ReferenciaProvedor $cobranca, ?int $valorCentavos = null, string $motivo = ''): ReembolsoNoProvedor
    {
        $corpo = array_filter([
            'value' => $valorCentavos === null ? null : round($valorCentavos / 100, 2),
            'description' => $motivo === '' ? null : mb_substr($motivo, 0, 200),
        ], fn (mixed $v): bool => $v !== null);

        $r = $this->requisitar('post', '/payments/'.rawurlencode($cobranca->id).'/refund', $corpo);

        if ($r->serverError()) {
            throw $this->indisponibilidade($r);
        }

        if ($r->failed()) {
            return new ReembolsoNoProvedor(ResultadoReembolso::Recusado, $this->descricaoDoErro($r));
        }

        $estornos = array_values(array_filter((array) $r->json('refunds'), is_array(...)));
        $situacoes = array_map(fn (array $e): string => (string) ($e['status'] ?? ''), $estornos);

        if (in_array('AWAITING_CRITICAL_ACTION_AUTHORIZATION', $situacoes, true)) {
            return new ReembolsoNoProvedor(ResultadoReembolso::AguardandoAutorizacao);
        }

        if ($r->json('status') === 'REFUNDED' || ($situacoes !== [] && array_unique($situacoes) === ['DONE'])) {
            return new ReembolsoNoProvedor(ResultadoReembolso::Concluido);
        }

        return new ReembolsoNoProvedor(ResultadoReembolso::EmAndamento);
    }

    private function descricaoDoErro(Response $r): string
    {
        $erro = ((array) $r->json('errors'))[0] ?? null;
        $descricao = is_array($erro) && is_string($erro['description'] ?? null) ? $erro['description'] : '';

        return mb_substr("O Asaas recusou o estorno (HTTP {$r->status()}). {$descricao}", 0, 300);
    }

    public function cancelSubscription(ReferenciaProvedor $assinatura): void
    {
        $r = $this->requisitar('delete', '/subscriptions/'.rawurlencode($assinatura->id));

        // Já removida lá (404) é o resultado desejado.
        if ($r->failed() && $r->status() !== 404) {
            throw $this->indisponibilidade($r);
        }
    }

    public function listSubscriptionCharges(ReferenciaProvedor $assinatura, DateTimeImmutable $desde): array
    {
        return $this->listar('/subscriptions/'.rawurlencode($assinatura->id).'/payments', ['dateCreated[ge]' => $desde->format('Y-m-d')], fn (array $p): bool => ($p['subscription'] ?? null) === $assinatura->id);
    }

    public function listCheckoutCharges(ReferenciaProvedor $sessao): array
    {
        // O filtro `checkoutSession` do Asaas não está documentado como garantido: o filtro local
        // abaixo impede que cobranças de outro checkout entrem se ele for ignorado.
        return $this->listar('/payments', ['checkoutSession' => $sessao->id], fn (array $p): bool => ($p['checkoutSession'] ?? null) === $sessao->id);
    }

    /**
     * @param  array<string, string>  $filtros  vão como query string (num GET o corpo do cliente É a query)
     * @param  callable(array<string, mixed>): bool  $pertence
     * @return list<CobrancaNoProvedor>
     */
    private function listar(string $caminho, array $filtros, callable $pertence): array
    {
        $achadas = [];
        $offset = 0;

        do {
            $r = $this->enviar('get', $caminho, [...$filtros, 'limit' => self::PAGINA, 'offset' => $offset]);

            foreach ((array) $r->json('data') as $p) {
                if (is_array($p) && is_string($p['id'] ?? null) && $pertence($p)) {
                    $achadas[] = $this->cobranca(new ReferenciaProvedor(self::NOME, $p['id']), $p);
                }
            }

            $offset += self::PAGINA;
        } while ($r->json('hasMore') === true && $offset < self::PAGINA * 50);

        return $achadas;
    }

    /** @param array<array-key, mixed> $r */
    private function cobranca(ReferenciaProvedor $referencia, array $r): CobrancaNoProvedor
    {
        $valor = $r['value'] ?? null;
        $pago = $r['paymentDate'] ?? $r['confirmedDate'] ?? null;
        $cartao = is_array($r['creditCard'] ?? null) ? $r['creditCard'] : [];
        $vence = $r['dueDate'] ?? null;

        return new CobrancaNoProvedor(
            $referencia,
            $this->estado((string) ($r['status'] ?? ''), (bool) ($r['deleted'] ?? false)),
            is_numeric($valor) ? (int) round(((float) $valor) * 100) : 0,
            $this->texto($r['checkoutSession'] ?? null),
            $this->texto($r['externalReference'] ?? null),
            is_string($pago) && $pago !== '' ? DataExibicao::doProvedor($pago)->toDateTimeImmutable() : null,
            $this->texto($r['subscription'] ?? null),
            $this->bandeira($cartao['creditCardBrand'] ?? null),
            $this->ultimos4($cartao['creditCardNumber'] ?? null),
            is_string($vence) && $vence !== '' ? DataExibicao::doProvedor($vence)->toDateTimeImmutable() : null,
        );
    }

    public function situacaoDoWebhook(string $url): ?SituacaoDoWebhook
    {
        $offset = 0;

        do {
            $r = $this->enviar('get', '/webhooks', ['limit' => self::PAGINA, 'offset' => $offset]);

            foreach ((array) $r->json('data') as $w) {
                if (is_array($w) && ($w['url'] ?? null) === $url && is_string($w['id'] ?? null)) {
                    return new SituacaoDoWebhook($w['id'], ($w['enabled'] ?? false) === true, ($w['interrupted'] ?? false) === true, ($w['penalizedAt'] ?? null) !== null);
                }
            }

            $offset += self::PAGINA;
        } while ($r->json('hasMore') === true && $offset < self::PAGINA * 10);

        return null;
    }

    public function retomarWebhook(string $id): void
    {
        $this->enviar('put', '/webhooks/'.rawurlencode($id), ['interrupted' => false]);
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
            // No CHECKOUT_PAID de uma recorrência a assinatura vem em `checkout.subscription`
            // (PENDENTE DE CONFIRMAÇÃO NO SANDBOX, ADR-010 pendência 6): sem ela a troca de cartão fica pendente.
            assinaturaId: $this->texto($pagamento['subscription'] ?? ($checkout['subscription'] ?? null)),
            payload: $this->semDadosDeCartao($dados),
            valorEstornadoCentavos: $dados['event'] === 'PAYMENT_PARTIALLY_REFUNDED' ? $this->valorEstornado($pagamento) : null,
        );
    }

    /**
     * O que o Asaas devolveu num estorno parcial: a soma de `refunds[].value`. NÃO CONFIRMADO NO SANDBOX o formato
     * real do evento (ADR-010): sem a lista, o valor fica desconhecido e o estorno é só registrado e alertado.
     *
     * @param  array<array-key, mixed>  $pagamento
     */
    private function valorEstornado(array $pagamento): ?int
    {
        $lista = $pagamento['refunds'] ?? null;

        if (! is_array($lista)) {
            return null;
        }

        $total = 0.0;

        foreach ($lista as $estorno) {
            $total += is_array($estorno) && is_numeric($estorno['value'] ?? null) ? (float) $estorno['value'] : 0.0;
        }

        return $total > 0 ? (int) round($total * 100) : null;
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
        $r = $this->requisitar($metodo, $caminho, $corpo);

        if ($r->failed()) {
            throw $this->indisponibilidade($r);
        }

        return $r;
    }

    /**
     * Faz a chamada e devolve a resposta mesmo com HTTP de erro; só rede e timeout viram exceção.
     *
     * @param  array<string, mixed>|null  $corpo
     */
    private function requisitar(string $metodo, string $caminho, ?array $corpo = null): Response
    {
        try {
            return $this->cliente()->{$metodo}($caminho, $corpo ?? []);
        } catch (ConnectionException) {
            throw new ProvedorIndisponivel('O Asaas não respondeu a tempo.');
        } catch (Throwable $e) {
            throw new ProvedorIndisponivel('Falha ao falar com o Asaas: '.$e::class);
        }
    }

    private function indisponibilidade(Response $r): ProvedorIndisponivel
    {
        $codigos = $this->codigosDeErro($r);

        return new ProvedorIndisponivel("O Asaas respondeu HTTP {$r->status()}".($codigos !== '' ? " ({$codigos})" : '').'.');
    }

    private function codigosDeErro(Response $r): string
    {
        $codigos = [];

        foreach ((array) ($r->json('errors') ?? []) as $erro) {
            if (is_array($erro) && is_string($erro['code'] ?? null)) {
                $codigos[] = $erro['code'];
            }
        }

        return implode(',', $codigos);
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

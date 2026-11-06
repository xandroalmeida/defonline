<?php

declare(strict_types=1);

namespace App\Infrastructure\Cobranca\Nenhum;

use App\Domain\Cobranca\CicloCobranca;
use App\Domain\Cobranca\CobrancaNoProvedor;
use App\Domain\Cobranca\EventoDoProvedor;
use App\Domain\Cobranca\PaymentProvider;
use App\Domain\Cobranca\PedidoCheckout;
use App\Domain\Cobranca\ProvedorIndisponivel;
use App\Domain\Cobranca\ReembolsoNoProvedor;
use App\Domain\Cobranca\ReferenciaProvedor;
use App\Domain\Cobranca\SessaoCheckout;
use App\Domain\Cobranca\WebhookBruto;
use App\Domain\Cobranca\WebhookInvalido;
use DateTimeImmutable;

/**
 * STORY-093 CA-1 / D-10 — o provedor de pagamento que ocupa o lugar do real quando a configuração falta ou está
 * errada (sem `PAYMENTS_PROVIDER`, `fake` em produção, Asaas sem URL, chave ou token). Com a cobrança desligada o
 * produto não precisa de provedor: os agendadores saem sem agir ({@see self::ativo()}) e o webhook responde 401,
 * em vez de o contêiner falhar ao montar o provedor (exit 1 nos agendadores, 500 no webhook).
 *
 * Qualquer operação de verdade falha com {@see ProvedorIndisponivel} e o motivo: nunca cobra, nunca finge. O
 * interruptor da cobrança se recusa a ligar enquanto este for o provedor ({@see self::motivo()}).
 */
final class ProvedorNaoConfigurado implements PaymentProvider
{
    public function __construct(private readonly string $motivo) {}

    /** O provedor que o contêiner entrega está desconfigurado? */
    public static function ativo(): bool
    {
        return self::doContainer() instanceof self;
    }

    /** Por que não há provedor; `null` se há um válido. */
    public static function motivo(): ?string
    {
        $provedor = self::doContainer();

        return $provedor instanceof self ? $provedor->motivo : null;
    }

    private static function doContainer(): PaymentProvider
    {
        /** @var PaymentProvider $provedor */
        $provedor = app()->make(PaymentProvider::class);

        return $provedor;
    }

    public function nome(): string
    {
        return 'nenhum';
    }

    public function startCheckout(PedidoCheckout $pedido): SessaoCheckout
    {
        throw $this->indisponivel();
    }

    public function cancelCheckout(ReferenciaProvedor $sessao): void
    {
        throw $this->indisponivel();
    }

    public function getCharge(ReferenciaProvedor $cobranca): CobrancaNoProvedor
    {
        throw $this->indisponivel();
    }

    public function retryCharge(ReferenciaProvedor $cobranca): CobrancaNoProvedor
    {
        throw $this->indisponivel();
    }

    public function cancelSubscription(ReferenciaProvedor $assinatura): void
    {
        throw $this->indisponivel();
    }

    public function listSubscriptionCharges(ReferenciaProvedor $assinatura, DateTimeImmutable $desde): array
    {
        throw $this->indisponivel();
    }

    public function listCheckoutCharges(ReferenciaProvedor $sessao): array
    {
        throw $this->indisponivel();
    }

    public function suspendSubscription(ReferenciaProvedor $assinatura): void
    {
        throw $this->indisponivel();
    }

    public function resumeSubscription(ReferenciaProvedor $assinatura): void
    {
        throw $this->indisponivel();
    }

    public function changeSubscriptionValue(ReferenciaProvedor $assinatura, int $valorCentavos, CicloCobranca $ciclo, DateTimeImmutable $proximaCobrancaEm): void
    {
        throw $this->indisponivel();
    }

    public function refund(ReferenciaProvedor $cobranca, ?int $valorCentavos = null, string $motivo = ''): ReembolsoNoProvedor
    {
        throw $this->indisponivel();
    }

    /** Sem provedor não há como autenticar o aviso: é rejeitado (401), nunca gravado. */
    public function parseWebhook(WebhookBruto $requisicao): EventoDoProvedor
    {
        throw new WebhookInvalido('Provedor de pagamento não configurado.');
    }

    private function indisponivel(): ProvedorIndisponivel
    {
        return new ProvedorIndisponivel('Provedor de pagamento não configurado: '.$this->motivo);
    }
}

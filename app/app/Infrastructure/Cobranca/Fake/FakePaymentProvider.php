<?php

declare(strict_types=1);

namespace App\Infrastructure\Cobranca\Fake;

use App\Domain\Cobranca\CobrancaNoProvedor;
use App\Domain\Cobranca\EstadoCobranca;
use App\Domain\Cobranca\EventoDoProvedor;
use App\Domain\Cobranca\PaymentProvider;
use App\Domain\Cobranca\PedidoCheckout;
use App\Domain\Cobranca\ProvedorIndisponivel;
use App\Domain\Cobranca\ReferenciaProvedor;
use App\Domain\Cobranca\SessaoCheckout;
use App\Domain\Cobranca\TipoEvento;
use App\Domain\Cobranca\WebhookBruto;
use App\Domain\Cobranca\WebhookInvalido;
use DateTimeImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * ADR-010 §6 — provedor de mentira para desenvolvimento e testes: sem rede, determinístico e com
 * estado no cache (compartilhado entre o processo de teste e o servidor do Dusk).
 *
 * Gatilhos: {@see falharProximoCheckout()}, {@see simularPagamento()}, {@see simularExpiracao()} e
 * {@see simularCancelamento()}. Os webhooks gerados passam por {@see parseWebhook()}, como os reais.
 */
final class FakePaymentProvider implements PaymentProvider
{
    public const NOME = 'fake';

    public const CABECALHO_TOKEN = 'x-fake-token';

    private const PREFIXO = 'fake-pagamento:';

    public function __construct(private readonly ?string $webhookToken = null) {}

    public static function falharProximoCheckout(): void
    {
        Cache::put(self::PREFIXO.'falhar', true, now()->addMinutes(10));
    }

    public function nome(): string
    {
        return self::NOME;
    }

    public function startCheckout(PedidoCheckout $pedido): SessaoCheckout
    {
        if (Cache::pull(self::PREFIXO.'falhar') === true) {
            throw new ProvedorIndisponivel('Falha simulada do provedor fake.');
        }

        $id = 'fakechk_'.Str::lower((string) Str::ulid());
        $expira = new DateTimeImmutable("+{$pedido->expiraEmMinutos} minutes");

        Cache::put(self::PREFIXO."chk:{$id}", [
            'referencia' => $pedido->referenciaInterna,
            'valor' => $pedido->valorCentavos,
        ], now()->addDays(2));
        Cache::put(self::PREFIXO."pay:{$this->cobrancaDe($id)}", [
            'checkout' => $id,
            'referencia' => $pedido->referenciaInterna,
            'valor' => $pedido->valorCentavos,
            'estado' => EstadoCobranca::Pendente->value,
            'pago_em' => null,
        ], now()->addDays(2));

        return new SessaoCheckout(
            new ReferenciaProvedor(self::NOME, $id),
            route('pagamentos.fake', ['invoice' => $pedido->referenciaInterna, 'checkout' => $id]),
            $expira,
        );
    }

    public function cancelCheckout(ReferenciaProvedor $sessao): void
    {
        $this->mudarEstado($sessao->id, EstadoCobranca::Cancelada);
    }

    public function getCharge(ReferenciaProvedor $cobranca): CobrancaNoProvedor
    {
        /** @var array{checkout: string, referencia: string, valor: int, estado: string, pago_em: string|null}|null $c */
        $c = Cache::get(self::PREFIXO."pay:{$cobranca->id}");

        if ($c === null) {
            throw new ProvedorIndisponivel('Cobrança desconhecida no provedor fake.');
        }

        return new CobrancaNoProvedor(
            $cobranca,
            EstadoCobranca::from($c['estado']),
            $c['valor'],
            $c['checkout'],
            $c['referencia'],
            $c['pago_em'] === null ? null : new DateTimeImmutable($c['pago_em']),
        );
    }

    public function parseWebhook(WebhookBruto $requisicao): EventoDoProvedor
    {
        $enviado = $requisicao->cabecalho(self::CABECALHO_TOKEN);

        if ($enviado === null || ! hash_equals($this->token(), $enviado)) {
            throw new WebhookInvalido('Token do webhook fake ausente ou inválido.');
        }

        $d = json_decode($requisicao->corpo, true);
        $tipo = is_array($d) && is_string($d['tipo'] ?? null) ? TipoEvento::tryFrom($d['tipo']) : null;

        if (! is_array($d) || ! is_string($d['id'] ?? null) || $tipo === null) {
            throw new WebhookInvalido('Corpo do webhook fake ilegível.');
        }

        return new EventoDoProvedor(
            self::NOME,
            $d['id'],
            $tipo,
            is_string($d['cobranca_id'] ?? null) ? $d['cobranca_id'] : null,
            is_string($d['checkout_id'] ?? null) ? $d['checkout_id'] : null,
            is_string($d['referencia_externa'] ?? null) ? $d['referencia_externa'] : null,
            $d,
        );
    }

    /** O banco confirma o Pix: muda a cobrança para paga e devolve o webhook que o provedor mandaria. */
    public function simularPagamento(string $checkoutId, ?string $eventoId = null): WebhookBruto
    {
        $this->mudarEstado($checkoutId, EstadoCobranca::Paga);

        return $this->webhook(TipoEvento::CobrancaPaga, $checkoutId, $eventoId);
    }

    public function simularExpiracao(string $checkoutId, ?string $eventoId = null): WebhookBruto
    {
        return $this->webhook(TipoEvento::CheckoutExpirado, $checkoutId, $eventoId);
    }

    public function simularCancelamento(string $checkoutId, ?string $eventoId = null): WebhookBruto
    {
        $this->mudarEstado($checkoutId, EstadoCobranca::Cancelada);

        return $this->webhook(TipoEvento::CheckoutCancelado, $checkoutId, $eventoId);
    }

    /** Webhook de qualquer tipo para um checkout, com id de evento escolhido (duplicado, fora de ordem...). */
    public function webhook(TipoEvento $tipo, string $checkoutId, ?string $eventoId = null): WebhookBruto
    {
        $c = Cache::get(self::PREFIXO."chk:{$checkoutId}");

        return new WebhookBruto(
            [self::CABECALHO_TOKEN => $this->token(), 'content-type' => 'application/json'],
            (string) json_encode([
                'id' => $eventoId ?? 'fakeevt_'.Str::lower((string) Str::ulid()),
                'tipo' => $tipo->value,
                'cobranca_id' => $this->cobrancaDe($checkoutId),
                'checkout_id' => $checkoutId,
                'referencia_externa' => is_array($c) ? $c['referencia'] : null,
            ]),
        );
    }

    private function cobrancaDe(string $checkoutId): string
    {
        return 'fakepay_'.Str::after($checkoutId, 'fakechk_');
    }

    private function mudarEstado(string $checkoutId, EstadoCobranca $estado): void
    {
        $chave = self::PREFIXO."pay:{$this->cobrancaDe($checkoutId)}";
        $c = Cache::get($chave);

        if (! is_array($c)) {
            return;
        }

        $c['estado'] = $estado->value;
        $c['pago_em'] = $estado === EstadoCobranca::Paga ? now()->toIso8601String() : $c['pago_em'];
        Cache::put($chave, $c, now()->addDays(2));
    }

    private function token(): string
    {
        return $this->webhookToken !== null && $this->webhookToken !== ''
            ? $this->webhookToken
            : hash('sha256', 'fake-webhook|'.(string) config('app.key'));
    }
}

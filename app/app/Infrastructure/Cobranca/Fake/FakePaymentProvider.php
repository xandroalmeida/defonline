<?php

declare(strict_types=1);

namespace App\Infrastructure\Cobranca\Fake;

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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * ADR-010 §6 — provedor de mentira para desenvolvimento e testes: sem rede, determinístico e com
 * estado no cache (compartilhado entre o processo de teste e o servidor do Dusk).
 *
 * Gatilhos: {@see falharProximoCheckout()}, {@see simularPagamento()}, {@see simularExpiracao()},
 * {@see simularCancelamento()} e, para a STORY-079, {@see ficarIndisponivel()},
 * {@see aprovarProximaTentativa()}, {@see criarCobrancaDeRenovacao()} e {@see simularRecusaDeRenovacao()}. Os webhooks gerados passam por {@see parseWebhook()}, como os reais.
 */
final class FakePaymentProvider implements PaymentProvider
{
    public const NOME = 'fake';

    public const CABECALHO_TOKEN = 'x-fake-token';

    private const PREFIXO = 'fake-pagamento:';

    /** Longo o bastante para testes que "viajam" até a renovação de um plano anual. */
    private const TTL_DIAS = 400;

    public function __construct(private readonly ?string $webhookToken = null) {}

    public static function falharProximoCheckout(): void
    {
        Cache::put(self::PREFIXO.'falhar', true, now()->addMinutes(10));
    }

    /** A próxima operação sobre assinatura (suspender, religar, cancelar) falha, como um provedor fora do ar. */
    public static function falharProximaOperacaoDeAssinatura(): void
    {
        Cache::put(self::PREFIXO.'falhar-assinatura', true, now()->addMinutes(10));
    }

    /** `ativa`, `suspensa` ou `cancelada`; `null` se o provedor nunca viu a assinatura. */
    public static function situacaoDaAssinatura(string $assinaturaId): ?string
    {
        $v = Cache::get(self::PREFIXO."sub:{$assinaturaId}");

        return is_string($v) ? $v : null;
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
        ], now()->addDays(self::TTL_DIAS));
        Cache::put(self::PREFIXO."pay:{$this->cobrancaDe($id)}", [
            'checkout' => $id,
            'referencia' => $pedido->referenciaInterna,
            'valor' => $pedido->valorCentavos,
            'estado' => EstadoCobranca::Pendente->value,
            'pago_em' => null,
            'assinatura' => $pedido->recorrencia === null ? null : 'fakesub_'.Str::after($id, 'fakechk_'),
            'vencimento' => now()->toDateString(),
            'cartao' => in_array(MeioPagamento::Cartao, $pedido->meios, true),
            // Troca de cartão no meio do ciclo (STORY-081): nada é cobrado agora, a 1ª cobrança é no vencimento.
            'cobra_agora' => $pedido->primeiraCobrancaEm === null || $pedido->primeiraCobrancaEm <= new DateTimeImmutable,
        ], now()->addDays(self::TTL_DIAS));
        $this->indexarDaAssinatura($pedido->recorrencia === null ? null : 'fakesub_'.Str::after($id, 'fakechk_'), $this->cobrancaDe($id));

        if ($pedido->recorrencia !== null) {
            Cache::put(self::PREFIXO.'sub:fakesub_'.Str::after($id, 'fakechk_'), 'ativa', now()->addDays(self::TTL_DIAS));
            Cache::put(self::PREFIXO.'sub-valor-inicial:fakesub_'.Str::after($id, 'fakechk_'), $pedido->valorCentavos, now()->addDays(self::TTL_DIAS));
        }

        // A referência é o id da Invoice, ou o da troca de cartão (STORY-081): cada uma tem a sua "página do provedor".
        $ehTroca = DB::table('trocas_de_cartao')->where('id', $pedido->referenciaInterna)->exists();

        return new SessaoCheckout(
            new ReferenciaProvedor(self::NOME, $id),
            $ehTroca
                ? route('pagamentos.fake.troca', ['troca' => $pedido->referenciaInterna])
                : route('pagamentos.fake', ['invoice' => $pedido->referenciaInterna, 'checkout' => $id]),
            $expira,
        );
    }

    public function cancelCheckout(ReferenciaProvedor $sessao): void
    {
        $this->mudarEstado($sessao->id, EstadoCobranca::Cancelada);
    }

    public function suspendSubscription(ReferenciaProvedor $assinatura): void
    {
        $this->mudarAssinatura($assinatura, 'suspensa');
    }

    public function resumeSubscription(ReferenciaProvedor $assinatura): void
    {
        $this->mudarAssinatura($assinatura, 'ativa');
    }

    public function changeSubscriptionValue(ReferenciaProvedor $assinatura, int $valorCentavos, CicloCobranca $ciclo, DateTimeImmutable $proximaCobrancaEm): void
    {
        $this->exigirDisponivel();

        if (Cache::pull(self::PREFIXO.'falhar-assinatura') === true) {
            throw new ProvedorIndisponivel('Falha simulada do provedor fake.');
        }

        Cache::put(self::PREFIXO."sub-valor:{$assinatura->id}", [
            'valor' => $valorCentavos,
            'ciclo' => $ciclo->value,
            'proxima' => $proximaCobrancaEm->format('Y-m-d H:i:s'),
        ], now()->addDays(self::TTL_DIAS));
    }

    /**
     * O valor (centavos) que o provedor cobra nas próximas renovações: o último pedido de
     * `changeSubscriptionValue` ou, se nunca mudou, o do checkout (STORY-083). `null` se nunca viu a assinatura.
     */
    public static function valorCobradoDaAssinatura(string $assinaturaId): ?int
    {
        $inicial = Cache::get(self::PREFIXO."sub-valor-inicial:{$assinaturaId}");

        return self::valorDaAssinatura($assinaturaId)['valor'] ?? (is_int($inicial) ? $inicial : null);
    }

    /**
     * O que o provedor fake cobra da assinatura agora (STORY-082); `null` se o valor nunca foi mudado.
     *
     * @return array{valor: int, ciclo: string, proxima: string}|null
     */
    public static function valorDaAssinatura(string $assinaturaId): ?array
    {
        $v = Cache::get(self::PREFIXO."sub-valor:{$assinaturaId}");

        return is_array($v) ? $v : null;
    }

    public function getCharge(ReferenciaProvedor $cobranca): CobrancaNoProvedor
    {
        $this->exigirDisponivel();

        return $this->retrato($cobranca->id);
    }

    /** @return array<string, mixed> */
    private function cobrancaGravada(string $id): array
    {
        $c = Cache::get(self::PREFIXO."pay:{$id}");

        if (! is_array($c)) {
            throw new ProvedorIndisponivel('Cobrança desconhecida no provedor fake.');
        }

        return $c;
    }

    private function retrato(string $id): CobrancaNoProvedor
    {
        /** @var array{checkout: string|null, referencia: string|null, valor: int, estado: string, pago_em: string|null, assinatura?: string|null, vencimento?: string|null, cartao?: bool} $c */
        $c = $this->cobrancaGravada($id);
        $cobranca = new ReferenciaProvedor(self::NOME, $id);

        $comCartao = ($c['assinatura'] ?? null) !== null || ($c['cartao'] ?? false) === true;

        return new CobrancaNoProvedor(
            $cobranca,
            EstadoCobranca::from($c['estado']),
            $c['valor'],
            $c['checkout'],
            $c['referencia'],
            $c['pago_em'] === null ? null : new DateTimeImmutable($c['pago_em']),
            $c['assinatura'] ?? null,
            $comCartao && $c['estado'] === EstadoCobranca::Paga->value ? 'VISA' : null,
            $comCartao && $c['estado'] === EstadoCobranca::Paga->value ? '4242' : null,
            isset($c['vencimento']) ? new DateTimeImmutable($c['vencimento']) : null,
        );
    }

    /**
     * Nova tentativa da mesma cobrança. Por padrão o banco continua recusando; {@see aprovarProximaTentativa()}
     * faz a próxima passar. Nunca cria cobrança: só conta a tentativa (para o teste provar que não há duplicidade).
     */
    public function retryCharge(ReferenciaProvedor $cobranca): CobrancaNoProvedor
    {
        $this->exigirDisponivel();
        $c = $this->cobrancaGravada($cobranca->id);

        $contador = self::PREFIXO."tentativas:{$cobranca->id}";
        Cache::put($contador, (int) Cache::get($contador, 0) + 1, now()->addDays(self::TTL_DIAS));

        if (Cache::pull(self::PREFIXO."aprovar:{$cobranca->id}") === true && $c['estado'] !== EstadoCobranca::Paga->value) {
            $this->gravarEstado($cobranca->id, EstadoCobranca::Paga);
        }

        return $this->retrato($cobranca->id);
    }

    public function cancelSubscription(ReferenciaProvedor $assinatura): void
    {
        $this->mudarAssinatura($assinatura, 'cancelada');
        Cache::put(self::PREFIXO."sub-cancelada:{$assinatura->id}", true, now()->addDays(self::TTL_DIAS));
    }

    public function listSubscriptionCharges(ReferenciaProvedor $assinatura, DateTimeImmutable $desde): array
    {
        $this->exigirDisponivel();

        /** @var list<string> $ids */
        $ids = Cache::get(self::PREFIXO."sub-pays:{$assinatura->id}", []);

        return array_map(fn (string $id): CobrancaNoProvedor => $this->retrato($id), $ids);
    }

    public function listCheckoutCharges(ReferenciaProvedor $sessao): array
    {
        $this->exigirDisponivel();
        $id = $this->cobrancaDe($sessao->id);
        $c = Cache::get(self::PREFIXO."pay:{$id}");

        // No provedor real a cobrança de um checkout só existe depois que alguém paga.
        return is_array($c) && $c['estado'] !== EstadoCobranca::Pendente->value ? [$this->retrato($id)] : [];
    }

    /** O provedor sai do ar (rede, 5xx): toda chamada que precisa dele falha até {@see ficarIndisponivel()}(false). */
    public static function ficarIndisponivel(bool $indisponivel = true): void
    {
        $indisponivel ? Cache::put(self::PREFIXO.'indisponivel', true, now()->addDays(self::TTL_DIAS)) : Cache::forget(self::PREFIXO.'indisponivel');
    }

    /** A próxima retentativa desta cobrança passa (o cliente acertou o cartão). */
    public static function aprovarProximaTentativa(string $cobrancaId): void
    {
        Cache::put(self::PREFIXO."aprovar:{$cobrancaId}", true, now()->addDays(self::TTL_DIAS));
    }

    public static function tentativasDe(string $cobrancaId): int
    {
        return (int) Cache::get(self::PREFIXO."tentativas:{$cobrancaId}", 0);
    }

    /** @return list<string> ids das cobranças que o provedor gerou para a assinatura */
    public static function cobrancasDaAssinatura(string $assinaturaId): array
    {
        /** @var list<string> $ids */
        $ids = Cache::get(self::PREFIXO."sub-pays:{$assinaturaId}", []);

        return $ids;
    }

    public static function assinaturaCancelada(string $assinaturaId): bool
    {
        return Cache::get(self::PREFIXO."sub-cancelada:{$assinaturaId}") === true;
    }

    private function exigirDisponivel(): void
    {
        if (Cache::get(self::PREFIXO.'indisponivel') === true) {
            throw new ProvedorIndisponivel('Provedor fake indisponível (simulado).');
        }
    }

    private function indexarDaAssinatura(?string $assinaturaId, string $cobrancaId): void
    {
        if ($assinaturaId === null) {
            return;
        }

        $chave = self::PREFIXO."sub-pays:{$assinaturaId}";
        /** @var list<string> $ids */
        $ids = Cache::get($chave, []);
        $ids[] = $cobrancaId;
        Cache::put($chave, array_values(array_unique($ids)), now()->addDays(self::TTL_DIAS));
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
            is_string($d['assinatura_id'] ?? null) ? $d['assinatura_id'] : null,
            $d,
        );
    }

    /** O banco confirma o Pix: muda a cobrança para paga e devolve o webhook que o provedor mandaria. */
    public function simularPagamento(string $checkoutId, ?string $eventoId = null): WebhookBruto
    {
        $this->mudarEstado($checkoutId, EstadoCobranca::Paga);

        return $this->webhook(TipoEvento::CobrancaPaga, $checkoutId, $eventoId);
    }

    /** O banco do cartão recusa: o provedor avisa que a cobrança falhou. */
    public function simularRecusa(string $checkoutId, ?string $eventoId = null): WebhookBruto
    {
        $this->mudarEstado($checkoutId, EstadoCobranca::Falhou);

        return $this->webhook(TipoEvento::CobrancaFalhou, $checkoutId, $eventoId);
    }

    /**
     * Próximo ciclo de uma assinatura já paga: o provedor cria outra cobrança (sem checkout e sem
     * nossa referência, como no Asaas) e avisa que foi paga.
     */
    public function simularRenovacao(string $checkoutId, ?string $eventoId = null): WebhookBruto
    {
        $nova = $this->criarCobrancaDeRenovacao($checkoutId, EstadoCobranca::Paga);

        return $this->webhookDaCobranca(TipoEvento::CobrancaPaga, $nova, $eventoId);
    }

    /** O banco recusa a cobrança do ciclo seguinte: o provedor cria a cobrança, ela não é paga e ele avisa. */
    public function simularRecusaDeRenovacao(string $checkoutId, ?string $eventoId = null): WebhookBruto
    {
        $nova = $this->criarCobrancaDeRenovacao($checkoutId, EstadoCobranca::Vencida);

        return $this->webhookDaCobranca(TipoEvento::CobrancaFalhou, $nova, $eventoId);
    }

    /**
     * Cria no provedor a cobrança do ciclo seguinte SEM avisar ninguém (webhook perdido): quem descobre
     * é a conciliação. Devolve o id da cobrança.
     */
    public function criarCobrancaDeRenovacao(string $checkoutId, EstadoCobranca $estado): string
    {
        /** @var array<string, mixed>|null $origem */
        $origem = Cache::get(self::PREFIXO."pay:{$this->cobrancaDe($checkoutId)}");
        $nova = 'fakepay_'.Str::lower((string) Str::ulid());
        $assinatura = is_array($origem) ? $origem['assinatura'] : null;
        // A renovação cobra o valor ATUAL da recorrência (STORY-083: muda quando o desconto acaba).
        $valorAtual = is_string($assinatura) ? self::valorCobradoDaAssinatura($assinatura) : null;

        Cache::put(self::PREFIXO."pay:{$nova}", [
            'checkout' => null,
            'referencia' => null,
            // Depois de `changeSubscriptionValue` (STORY-082/083) o provedor cobra o valor novo.
            'valor' => $valorAtual ?? (is_array($origem) ? $origem['valor'] : 0),
            'estado' => $estado->value,
            'pago_em' => $estado === EstadoCobranca::Paga ? now()->toIso8601String() : null,
            'assinatura' => $assinatura,
            'vencimento' => now()->toDateString(),
        ], now()->addDays(self::TTL_DIAS));
        $this->indexarDaAssinatura(is_string($assinatura) ? $assinatura : null, $nova);

        return $nova;
    }

    /** O banco confirma uma cobrança que já existia (por exemplo, a que a régua retentou). */
    public function simularPagamentoDaCobranca(string $cobrancaId, ?string $eventoId = null): WebhookBruto
    {
        $this->gravarEstado($cobrancaId, EstadoCobranca::Paga);

        return $this->webhookDaCobranca(TipoEvento::CobrancaPaga, $cobrancaId, $eventoId);
    }

    /** Webhook de qualquer tipo para uma cobrança de assinatura (sem checkout). */
    public function webhookDaCobranca(TipoEvento $tipo, string $cobrancaId, ?string $eventoId = null): WebhookBruto
    {
        $c = $this->cobrancaGravada($cobrancaId);

        return new WebhookBruto(
            [self::CABECALHO_TOKEN => $this->token(), 'content-type' => 'application/json'],
            (string) json_encode([
                'id' => $eventoId ?? 'fakeevt_'.Str::lower((string) Str::ulid()),
                'tipo' => $tipo->value,
                'cobranca_id' => $cobrancaId,
                'assinatura_id' => $c['assinatura'] ?? null,
            ]),
        );
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
                'assinatura_id' => $this->assinaturaDe($checkoutId),
            ]),
        );
    }

    /** A 1ª cobrança do checkout é hoje (e não no vencimento já pago)? Define se o pagamento do cartão novo chega junto. */
    public function cobraNaHora(string $checkoutId): bool
    {
        $c = Cache::get(self::PREFIXO."pay:{$this->cobrancaDe($checkoutId)}");

        return is_array($c) && ($c['cobra_agora'] ?? false) === true;
    }

    private function mudarAssinatura(ReferenciaProvedor $assinatura, string $situacao): void
    {
        $this->exigirDisponivel();

        if (Cache::pull(self::PREFIXO.'falhar-assinatura') === true) {
            throw new ProvedorIndisponivel('Falha simulada do provedor fake.');
        }

        Cache::put(self::PREFIXO."sub:{$assinatura->id}", $situacao, now()->addDays(self::TTL_DIAS));
    }

    private function assinaturaDe(string $checkoutId): ?string
    {
        $c = Cache::get(self::PREFIXO."pay:{$this->cobrancaDe($checkoutId)}");

        return is_array($c) && is_string($c['assinatura'] ?? null) ? $c['assinatura'] : null;
    }

    private function cobrancaDe(string $checkoutId): string
    {
        return 'fakepay_'.Str::after($checkoutId, 'fakechk_');
    }

    private function mudarEstado(string $checkoutId, EstadoCobranca $estado): void
    {
        $this->gravarEstado($this->cobrancaDe($checkoutId), $estado);
    }

    private function gravarEstado(string $cobrancaId, EstadoCobranca $estado): void
    {
        $chave = self::PREFIXO."pay:{$cobrancaId}";
        $c = Cache::get($chave);

        if (! is_array($c)) {
            return;
        }

        $c['estado'] = $estado->value;
        $c['pago_em'] = $estado === EstadoCobranca::Paga ? now()->toIso8601String() : $c['pago_em'];
        Cache::put($chave, $c, now()->addDays(self::TTL_DIAS));
    }

    private function token(): string
    {
        return $this->webhookToken !== null && $this->webhookToken !== ''
            ? $this->webhookToken
            : hash('sha256', 'fake-webhook|'.(string) config('app.key'));
    }
}

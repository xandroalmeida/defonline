<?php

declare(strict_types=1);

namespace App\Services\Cobranca;

use App\Domain\Cobranca\CobrancaNoProvedor;
use App\Domain\Cobranca\EstadoCobranca;
use App\Domain\Cobranca\PaymentProvider;
use App\Domain\Cobranca\ProvedorIndisponivel;
use App\Domain\Cobranca\ReferenciaProvedor;
use App\Domain\Cobranca\ReguaDeCobranca;
use App\Domain\Cobranca\StatusAssinatura;
use App\Domain\Cobranca\TipoEvento;
use App\Models\Assinatura;
use App\Models\CobrancaConfig;
use App\Models\Invoice;
use App\Models\ProviderEvent;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * STORY-079 CA-6 / ADR-010 §3 — conciliação com o provedor: a rede de segurança para webhook perdido.
 * O webhook continua sendo o caminho principal; isto só descobre o que ele não trouxe.
 *
 * - **Horária:** checkouts ainda pendentes há mais de 15 min (alguém pode ter pago e o aviso se perdeu;
 *   checkout vencido sem pagamento é encerrado) e assinaturas ativas cujo fim de ciclo já passou sem
 *   renovação registrada (a cobrança do mês foi paga ou recusada e ninguém soube).
 * - **Diária:** varre os últimos 14 dias de todas as assinaturas vigentes e dos checkouts em aberto
 *   (14 dias é quanto o provedor guarda os eventos de uma fila pausada, ADR-010 §3).
 *
 * O que se descobre vira um evento sintético em `provider_events` (`conciliacao:{cobrança}:{tipo}`,
 * único) e passa pelo MESMO {@see ProcessarEvento} dos webhooks: o estado atual do provedor é
 * conferido de novo, e rodar a conciliação duas vezes não aplica nada duas vezes. Indisponibilidade do
 * provedor gera alerta e não conta como nada contra o cliente.
 */
final class ConciliarCobrancas
{
    public const HORARIA = 'horaria';

    public const DIARIA = 'diaria';

    private const JANELA_DIARIA_DIAS = 14;

    private const PENDENTE_HA_MINUTOS = 15;

    /** Depois deste tanto de falhas seguidas do provedor a rodada para: insistir só gasta cota da API. */
    private const FALHAS_SEGUIDAS_PARA_ABORTAR = 5;

    public function __construct(
        private readonly PaymentProvider $provedor,
        private readonly ProcessarEvento $processar,
    ) {}

    /**
     * @param  (callable(int, int, array<string, int|bool>): void)|null  $progresso  recebe (feitas, total, contadores)
     * @return array{verificadas: int, corrigidas: int, indisponivel: int, erros: int, abortada: bool}
     */
    public function executar(string $modo, ?callable $progresso = null): array
    {
        $c = ['verificadas' => 0, 'corrigidas' => 0, 'indisponivel' => 0, 'erros' => 0, 'abortada' => false];

        if (! CobrancaConfig::ligada()) {
            return $c;
        }

        $invoices = $this->invoicesEmAberto($modo)->all();
        $assinaturas = $this->assinaturasSuspeitas($modo)->all();
        $total = count($invoices) + count($assinaturas);
        $seguidas = 0;
        $feitas = 0;

        foreach ([...array_map(fn (Invoice $i): array => ['invoice', $i], $invoices), ...array_map(fn (Assinatura $a): array => ['assinatura', $a], $assinaturas)] as [$tipo, $item]) {
            try {
                $c['corrigidas'] += $tipo === 'invoice' ? $this->conciliarInvoice($item) : $this->conciliarAssinatura($item, $modo);
                $seguidas = 0;
            } catch (ProvedorIndisponivel) {
                $c['indisponivel']++;
                $seguidas++;
                Log::error('cobranca.provedor_indisponivel', ['module' => 'cobranca', 'action' => 'conciliar', 'modo' => $modo]);
            } catch (Throwable $e) {
                $c['erros']++;
                Log::error('cobranca.conciliacao_com_erro', ['module' => 'cobranca', 'action' => 'conciliar', 'modo' => $modo, 'exception' => $e::class]);
            }

            $c['verificadas']++;
            $feitas++;

            if ($progresso !== null) {
                $progresso($feitas, $total, $c);
            }

            if ($seguidas >= self::FALHAS_SEGUIDAS_PARA_ABORTAR) {
                $c['abortada'] = true;

                break;
            }
        }

        return $c;
    }

    /** @return Collection<int, Invoice> */
    private function invoicesEmAberto(string $modo): Collection
    {
        $diaria = $modo === self::DIARIA;
        $agora = CarbonImmutable::now();

        return Invoice::query()
            ->where('provedor', $this->provedor->nome())
            ->whereNotNull('checkout_id_externo')
            ->whereIn('status', $diaria
                ? [EstadoCobranca::Pendente->value, EstadoCobranca::Vencida->value, EstadoCobranca::Falhou->value]
                : [EstadoCobranca::Pendente->value])
            ->where('created_at', '>=', $agora->subDays($diaria ? self::JANELA_DIARIA_DIAS : 3)->format('Y-m-d H:i:sP'))
            ->where('created_at', '<=', $agora->subMinutes(self::PENDENTE_HA_MINUTOS)->format('Y-m-d H:i:sP'))
            ->orderBy('created_at')
            ->get();
    }

    /** @return Collection<int, Assinatura> */
    private function assinaturasSuspeitas(string $modo): Collection
    {
        $agora = CarbonImmutable::now();
        $q = Assinatura::query()
            ->where('provedor', $this->provedor->nome())
            ->whereNotNull('id_externo')
            // Pix anual não tem assinatura no provedor; as cobranças dele entram pelas Invoices em aberto.
            ->where('meio_pagamento', '!=', 'pix')
            ->where('trial', false)
            ->whereIn('status', [StatusAssinatura::Ativa->value, StatusAssinatura::EmAtraso->value]);

        if ($modo === self::HORARIA) {
            // Ciclo pago acabou há mais de 2 h e a renovação não apareceu; depois de 10 dias é caso da diária.
            $q->where('status', StatusAssinatura::Ativa->value)
                ->where('proximo_vencimento', '<=', $agora->subHours(2)->format('Y-m-d H:i:sP'))
                ->where('proximo_vencimento', '>', $agora->subDays(10)->format('Y-m-d H:i:sP'));
        }

        return $q->orderBy('proximo_vencimento')->get();
    }

    private function conciliarInvoice(Invoice $invoice): int
    {
        $sessao = new ReferenciaProvedor($invoice->provedor, (string) $invoice->checkout_id_externo);
        $cobrancas = $this->provedor->listCheckoutCharges($sessao);
        $corrigidas = 0;

        foreach ($cobrancas as $cobranca) {
            if ($cobranca->estado === EstadoCobranca::Paga && $invoice->status !== EstadoCobranca::Paga) {
                $corrigidas += $this->aplicarSintetico(TipoEvento::CobrancaPaga, $cobranca, $invoice->checkout_id_externo, $invoice->id);
            }
        }

        // Ninguém pagou e o prazo do checkout acabou: encerra, como o evento `checkout.expirado` faria.
        if ($cobrancas === [] && $invoice->status === EstadoCobranca::Pendente && $invoice->expirada()) {
            $corrigidas += $this->registrar(
                "conciliacao:{$invoice->id}:expirado",
                TipoEvento::CheckoutExpirado,
                $invoice->provedor,
                null,
                $invoice->checkout_id_externo,
                $invoice->id,
                null,
            );
        }

        return $corrigidas;
    }

    private function conciliarAssinatura(Assinatura $a, string $modo): int
    {
        $desde = CarbonImmutable::now()->subDays($modo === self::DIARIA ? self::JANELA_DIARIA_DIAS : 10)->toDateTimeImmutable();
        $cobrancas = $this->provedor->listSubscriptionCharges(new ReferenciaProvedor((string) $a->provedor, (string) $a->id_externo), $desde);
        $agora = CarbonImmutable::now()->toDateTimeImmutable();
        $corrigidas = 0;

        foreach ($cobrancas as $cobranca) {
            $jaRegistrada = Invoice::query()->where('provedor', $a->provedor)->where('id_externo', $cobranca->referencia->id)->exists();

            if ($cobranca->estado === EstadoCobranca::Paga && ! $jaRegistrada) {
                $corrigidas += $this->aplicarSintetico(TipoEvento::CobrancaPaga, $cobranca, null, null);

                continue;
            }

            if ($a->status === StatusAssinatura::Ativa
                && $cobranca->semPagamento($agora)
                && ReguaDeCobranca::cobrancaDoCicloEmAberto($cobranca->vencimento, $a->proximo_vencimento)) {
                $corrigidas += $this->aplicarSintetico(TipoEvento::CobrancaFalhou, $cobranca, null, null);
            }
        }

        return $corrigidas;
    }

    private function aplicarSintetico(TipoEvento $tipo, CobrancaNoProvedor $cobranca, ?string $checkoutId, ?string $invoiceId): int
    {
        return $this->registrar(
            "conciliacao:{$cobranca->referencia->id}:{$tipo->value}",
            $tipo,
            $cobranca->referencia->provedor,
            $cobranca->referencia->id,
            $checkoutId ?? $cobranca->checkoutId,
            $invoiceId ?? $cobranca->referenciaInterna,
            $cobranca->assinaturaId,
        );
    }

    /** Grava o evento sintético (uma vez só) e o processa. Devolve 1 se o processamento mudou alguma coisa. */
    private function registrar(string $eventoId, TipoEvento $tipo, string $provedor, ?string $cobrancaId, ?string $checkoutId, ?string $referencia, ?string $assinaturaId): int
    {
        $id = (string) Str::uuid7();
        DB::table('provider_events')->insertOrIgnore([
            'id' => $id,
            'provedor' => $provedor,
            'evento_id' => $eventoId,
            'tipo' => $tipo->value,
            'cobranca_id_externo' => $cobrancaId,
            'checkout_id_externo' => $checkoutId,
            'referencia_externa' => $referencia,
            'assinatura_id_externo' => $assinaturaId,
            'payload' => json_encode(['origem' => 'conciliacao'], JSON_THROW_ON_ERROR),
            'status' => 'recebido',
            'recebido_em' => now()->format('Y-m-d H:i:sP'),
        ]);

        $evento = ProviderEvent::query()->where('provedor', $provedor)->where('evento_id', $eventoId)->firstOrFail();

        if (! in_array($evento->status, ['recebido', 'erro'], true)) {
            return 0;
        }

        $this->processar->executar($evento->id);

        return $evento->fresh()?->status === 'processado' ? 1 : 0;
    }
}

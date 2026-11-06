<?php

declare(strict_types=1);

namespace App\Services\Cobranca;

use App\Domain\Cobranca\Carteira;
use App\Domain\Cobranca\CobrancaNoProvedor;
use App\Domain\Cobranca\EstadoCobranca;
use App\Domain\Cobranca\PaymentProvider;
use App\Domain\Cobranca\ProvedorIndisponivel;
use App\Domain\Cobranca\ReferenciaProvedor;
use App\Domain\Cobranca\TipoEvento;
use App\Models\Assinatura;
use App\Models\Invoice;
use App\Models\ProviderEvent;
use App\Models\TrocaDeCartao;
use App\Observabilidade\AuditLogger;
use App\Observabilidade\EventosCobranca;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * STORY-076 CA-3, CA-4, CA-5 — aplica um evento já gravado. Regras do ADR-010 §3:
 *
 * - o crédito só nasce de `cobranca.paga`, e só depois de conferir o estado ATUAL no provedor
 *   (`getCharge`); o que o payload diz é só um aviso;
 * - a mesma Invoice paga duas vezes credita uma vez (lock da linha + índice único em
 *   `lotes_credito.invoice_id`);
 * - o valor que o provedor cobrou tem que ser o da Invoice, senão não credita e alerta;
 * - estado final (estornada, contestada, cancelada) nunca vira `paga`. Já `vencida` (Pix que
 *   passou do prazo) ainda aceita a confirmação do banco;
 * - evento sem efeito vira `ignorado`, não erro;
 * - pagamento de assinatura (ativação e renovação) é de {@see ConfirmarAssinatura} (STORY-077);
 * - recusa ou vencimento da cobrança de uma assinatura nossa é de {@see FalhaDeRenovacao} (STORY-079);
 * - provedor fora do ar NÃO é recusa do cliente: o evento fica em `erro`, a equipe é alertada e o
 *   reprocessador tenta de novo (STORY-079 CA-7);
 * - estorno e contestação (STORY-085): conferidos no provedor; o estorno retira o que a cobrança concedeu
 *   ({@see ReembolsarCobranca::concluir()}, que serve também ao estorno feito direto no painel do provedor);
 * - checkout de troca de cartão (STORY-081) não tem Invoice: é uma {@see TrocaDeCartao}, e o cartão novo
 *   só vale quando o provedor confirma (checkout concluído ou 1ª cobrança paga).
 */
final class ProcessarEvento
{
    public function __construct(
        private readonly PaymentProvider $provedor,
        private readonly Carteira $carteira,
        private readonly ConfirmarAssinatura $assinaturas,
        private readonly FalhaDeRenovacao $falhas,
        private readonly TrocarCartao $trocas,
        private readonly CupomDoCheckout $cupons,
        private readonly AjustarValorDaAssinatura $ajustes,
        private readonly ReembolsarCobranca $reembolsos,
    ) {}

    public function executar(string $providerEventId): void
    {
        $registro = ProviderEvent::query()->findOrFail($providerEventId);

        if (in_array($registro->status, ['processado', 'ignorado'], true)) {
            return;
        }

        try {
            [$status, $invoiceId] = $this->aplicar($registro);
        } catch (Throwable $e) {
            $registro->forceFill(['status' => 'erro', 'erro' => mb_substr($e::class.': '.$e->getMessage(), 0, 500)])->save();

            if ($e instanceof ProvedorIndisponivel) {
                Log::error('cobranca.provedor_indisponivel', [
                    'module' => 'cobranca',
                    'action' => 'processar_evento',
                    'provider_event_id' => $registro->id,
                    'tipo' => $registro->tipo,
                ]);
            }

            Log::error('cobranca.evento_com_erro', [
                'module' => 'cobranca',
                'action' => 'processar_evento',
                'provider_event_id' => $registro->id,
                'tipo' => $registro->tipo,
                'erro' => $e::class,
            ]);

            throw $e;
        }

        $registro->forceFill([
            'status' => $status,
            'invoice_id' => $invoiceId,
            'erro' => null,
            'processado_em' => now(),
        ])->save();

        // STORY-086 CA-4: cada cobrança paga ou recusada entra na conta da taxa de falha de pagamento.
        EventosCobranca::pagamentoDoEvento($registro, $status, $invoiceId);
    }

    /** @return array{0: 'processado'|'ignorado', 1: string|null} */
    private function aplicar(ProviderEvent $evento): array
    {
        return match (TipoEvento::from($evento->tipo)) {
            TipoEvento::CobrancaPaga => $this->aplicarPagamento($evento),
            TipoEvento::CheckoutConcluido => $this->concluirTroca($evento),
            TipoEvento::CheckoutExpirado => $this->encerrarCheckout($evento, EstadoCobranca::Vencida, 'expirado'),
            TipoEvento::CheckoutCancelado => $this->encerrarCheckout($evento, EstadoCobranca::Cancelada, 'cancelado'),
            TipoEvento::CobrancaFalhou, TipoEvento::CobrancaVencida => $this->aplicarFalha($evento),
            TipoEvento::CobrancaEstornada => $this->aplicarEstorno($evento),
            TipoEvento::CobrancaContestada => $this->aplicarContestacao($evento),
            default => ['ignorado', null],
        };
    }

    /** @return array{0: 'processado'|'ignorado', 1: string|null} */
    private function aplicarPagamento(ProviderEvent $evento): array
    {
        if ($evento->cobranca_id_externo === null) {
            return $this->semInvoice($evento, 'pagamento sem id de cobrança');
        }

        $cobranca = $this->provedor->getCharge(new ReferenciaProvedor($evento->provedor, $evento->cobranca_id_externo));
        $invoice = $this->localizar($evento, $cobranca);

        // Sem Invoice, só uma cobrança de assinatura nossa (renovação) ainda pode ter destino.
        if ($invoice === null && $cobranca->assinaturaId === null) {
            return $this->semInvoice($evento, 'pagamento sem invoice correspondente');
        }

        if ($cobranca->estado !== EstadoCobranca::Paga) {
            return ['ignorado', $invoice?->id];
        }

        // A 1ª cobrança do cartão novo: confirma a troca (se o aviso de checkout ainda não confirmou) e,
        // numa assinatura em atraso, é ela que a recupera.
        $troca = $invoice === null ? $this->localizarTroca($evento, $cobranca) : null;

        // Com `$invoice` nulo e a guarda acima, a cobrança tem assinatura: a troca sempre sabe a nova.
        if ($troca !== null) {
            $this->trocas->confirmar($troca, $cobranca->assinaturaId, $cobranca->cartaoBandeira, $cobranca->cartaoUltimos4);
        }

        // Daqui em diante, sem assinatura na cobrança, a Invoice existe (a guarda acima já devolveu o resto).
        if ($cobranca->assinaturaId !== null || in_array($invoice->tipo, ['assinatura', 'troca_de_plano'], true)) {
            $resultado = $this->assinaturas->aplicar($invoice, $cobranca, $evento->provedor);

            // STORY-083 CA-7: o último ciclo com desconto foi pago, o valor no provedor volta ao cheio (fora da transação).
            if ($resultado[0] === 'processado' && $cobranca->assinaturaId !== null) {
                $this->ajustarValor($evento->provedor, $cobranca->assinaturaId);
            }

            // A cobrança "de hoje" do cartão novo foi paga: a partir daqui o atraso não é mais dela.
            if ($troca !== null && $resultado[0] === 'processado') {
                TrocaDeCartao::query()->whereKey($troca->id)->update(['cobra_agora' => false]);
            }

            return $resultado;
        }

        return DB::transaction(function () use ($invoice, $cobranca): array {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);

            // STORY-092: checkout cancelado que o provedor confirma como pago: o dinheiro entrou, vai para a devolução.
            if ($invoice->status === EstadoCobranca::Cancelada) {
                return PagamentoSemEfeito::registrar($invoice, $cobranca, PagamentoSemEfeito::CHECKOUT_CANCELADO);
            }

            if (! in_array($invoice->status, [EstadoCobranca::Pendente, EstadoCobranca::Vencida, EstadoCobranca::Falhou], true)) {
                return ['ignorado', $invoice->id];
            }

            if ($cobranca->valorCentavos !== $invoice->valor_centavos) {
                Log::error('cobranca.valor_divergente', [
                    'module' => 'cobranca',
                    'action' => 'processar_evento',
                    'invoice_id' => $invoice->id,
                    'esperado_centavos' => $invoice->valor_centavos,
                    'recebido_centavos' => $cobranca->valorCentavos,
                ]);

                throw new \UnexpectedValueException('Valor pago diferente do valor da invoice.');
            }

            $pagoEm = $cobranca->pagoEm !== null ? CarbonImmutable::instance($cobranca->pagoEm) : CarbonImmutable::now();
            $invoice->forceFill([
                'status' => EstadoCobranca::Paga,
                'id_externo' => $cobranca->referencia->id,
                'checkout_id_externo' => $invoice->checkout_id_externo ?? $cobranca->checkoutId,
                'pago_em' => $pagoEm,
                'cartao_bandeira' => $cobranca->cartaoBandeira,
                'cartao_ultimos4' => $cobranca->cartaoUltimos4,
            ])->save();

            $this->cupons->registrarUso($invoice);

            $this->carteira->concederCreditos(
                $invoice->usuario_id,
                (int) $invoice->quantidade_creditos,
                $pagoEm->addMonthsNoOverflow((int) $invoice->validade_meses),
                $invoice->id,
                $pagoEm,
            );

            AuditLogger::log(
                action: 'cobranca.paga',
                subjectType: 'Invoice',
                subjectId: $invoice->id,
                actorType: 'system',
                usuarioId: $invoice->usuario_id,
                after: ['valor_centavos' => $invoice->valor_centavos, 'creditos' => $invoice->quantidade_creditos, 'provedor' => $invoice->provedor],
            );

            EventosCobranca::contratacaoConcluida($invoice, null, renovacao: false);

            return ['processado', $invoice->id];
        });
    }

    /**
     * Cobrança recusada ou vencida. Se é de uma assinatura que já existe, é uma renovação que falhou
     * (régua de cobrança); senão é o checkout inicial, que apenas não se concretizou.
     *
     * @return array{0: 'processado'|'ignorado', 1: string|null}
     */
    private function aplicarFalha(ProviderEvent $evento): array
    {
        $assinatura = $evento->assinatura_id_externo === null ? null : Assinatura::query()
            ->where('provedor', $evento->provedor)
            ->where('id_externo', $evento->assinatura_id_externo)
            ->first();

        if ($assinatura !== null && $evento->cobranca_id_externo !== null) {
            return $this->falhas->registrar($assinatura, $evento->cobranca_id_externo);
        }

        if (TipoEvento::from($evento->tipo) === TipoEvento::CobrancaFalhou) {
            return $this->encerrarCheckout($evento, EstadoCobranca::Falhou, 'recusado');
        }

        return ['ignorado', null];
    }

    /** @return array{0: 'processado'|'ignorado', 1: string|null} */
    private function aplicarEstorno(ProviderEvent $evento): array
    {
        if ($evento->cobranca_id_externo === null) {
            return $this->semInvoice($evento, 'estorno sem id de cobrança');
        }

        $cobranca = $this->provedor->getCharge(new ReferenciaProvedor($evento->provedor, $evento->cobranca_id_externo));
        $invoice = $this->localizar($evento, $cobranca);

        if ($invoice === null) {
            return $this->semInvoice($evento, 'estorno sem invoice correspondente');
        }

        if ($cobranca->estado !== EstadoCobranca::Estornada) {
            return ['ignorado', $invoice->id];
        }

        return [$this->reembolsos->concluir($invoice->id) ? 'processado' : 'ignorado', $invoice->id];
    }

    /**
     * Contestação (chargeback): a cobrança passa a "Contestada" e a equipe é alertada. O acesso não é
     * retirado sozinho: a contestação pode ser revertida, e quem decide é o admin (a tela destaca).
     *
     * @return array{0: 'processado'|'ignorado', 1: string|null}
     */
    private function aplicarContestacao(ProviderEvent $evento): array
    {
        if ($evento->cobranca_id_externo === null) {
            return $this->semInvoice($evento, 'contestação sem id de cobrança');
        }

        $cobranca = $this->provedor->getCharge(new ReferenciaProvedor($evento->provedor, $evento->cobranca_id_externo));
        $invoice = $this->localizar($evento, $cobranca);

        if ($invoice === null) {
            return $this->semInvoice($evento, 'contestação sem invoice correspondente');
        }

        if ($cobranca->estado !== EstadoCobranca::Contestada) {
            return ['ignorado', $invoice->id];
        }

        return DB::transaction(function () use ($invoice): array {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);

            if (! $invoice->status->podeIrPara(EstadoCobranca::Contestada)) {
                return ['ignorado', $invoice->id];
            }

            $invoice->forceFill(['status' => EstadoCobranca::Contestada])->save();

            Log::error('cobranca.contestada', [
                'module' => 'cobranca',
                'action' => 'processar_evento',
                'invoice_id' => $invoice->id,
            ]);

            AuditLogger::log(
                action: 'cobranca.contestada',
                subjectType: 'Invoice',
                subjectId: $invoice->id,
                actorType: 'system',
                usuarioId: $invoice->usuario_id,
                after: ['valor_centavos' => $invoice->valor_centavos, 'provedor' => $invoice->provedor],
            );

            return ['processado', $invoice->id];
        });
    }

    /** @return array{0: 'processado'|'ignorado', 1: string|null} */
    private function concluirTroca(ProviderEvent $evento): array
    {
        $troca = $this->localizarTroca($evento, null);

        if ($troca === null) {
            return ['ignorado', null];
        }

        if ($evento->assinatura_id_externo === null) {
            // Sem o id da assinatura nova não dá para trocar o ponteiro: a troca fica pendente, para a equipe olhar.
            Log::error('cobranca.troca_de_cartao_sem_assinatura', [
                'module' => 'cobranca',
                'action' => 'processar_evento',
                'provider_event_id' => $evento->id,
                'troca_id' => $troca->id,
            ]);

            return ['ignorado', null];
        }

        $this->trocas->confirmar($troca, $evento->assinatura_id_externo, null, null);

        return ['processado', null];
    }

    /** @return array{0: 'processado'|'ignorado', 1: string|null} */
    private function encerrarCheckout(ProviderEvent $evento, EstadoCobranca $novo, string $motivo): array
    {
        $troca = $this->localizarTroca($evento, null);

        if ($troca !== null) {
            $this->trocas->encerrar($troca, match ($novo) {
                EstadoCobranca::Vencida => 'expirada',
                EstadoCobranca::Cancelada => 'cancelada',
                default => 'falhou',
            });

            return ['processado', null];
        }

        $invoice = $this->localizar($evento, null);

        if ($invoice === null) {
            return $this->semInvoice($evento, 'evento de checkout sem invoice correspondente');
        }

        return DB::transaction(function () use ($invoice, $novo, $motivo): array {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);

            if (! $invoice->status->podeIrPara($novo)) {
                return ['ignorado', $invoice->id];
            }

            $invoice->forceFill(['status' => $novo, 'motivo_encerramento' => $motivo])->save();
            EventosCobranca::contratacaoAbandonada($invoice, $motivo);

            return ['processado', $invoice->id];
        });
    }

    private function localizar(ProviderEvent $evento, ?CobrancaNoProvedor $cobranca): ?Invoice
    {
        $referencia = $cobranca->referenciaInterna ?? $evento->referencia_externa;
        $checkout = $cobranca->checkoutId ?? $evento->checkout_id_externo;

        if ($referencia !== null && Str::isUuid($referencia)) {
            $porId = Invoice::query()->where('provedor', $evento->provedor)->find($referencia);

            if ($porId !== null) {
                return $porId;
            }
        }

        if ($checkout !== null) {
            $porCheckout = Invoice::query()->where('provedor', $evento->provedor)->where('checkout_id_externo', $checkout)->first();

            if ($porCheckout !== null) {
                return $porCheckout;
            }
        }

        return $evento->cobranca_id_externo === null ? null : Invoice::query()
            ->where('provedor', $evento->provedor)
            ->where('id_externo', $evento->cobranca_id_externo)
            ->first();
    }

    /** A troca de cartão a que o evento (ou a cobrança) se refere, pela referência, pelo checkout ou pela assinatura nova. */
    private function localizarTroca(ProviderEvent $evento, ?CobrancaNoProvedor $cobranca): ?TrocaDeCartao
    {
        $referencia = $cobranca->referenciaInterna ?? $evento->referencia_externa;
        $checkout = $cobranca->checkoutId ?? $evento->checkout_id_externo;
        $assinatura = $cobranca->assinaturaId ?? $evento->assinatura_id_externo;

        if ($referencia !== null && Str::isUuid($referencia)) {
            $porId = TrocaDeCartao::query()->where('provedor', $evento->provedor)->find($referencia);

            if ($porId !== null) {
                return $porId;
            }
        }

        if ($checkout !== null) {
            $porCheckout = TrocaDeCartao::query()->where('provedor', $evento->provedor)->where('checkout_id_externo', $checkout)->first();

            if ($porCheckout !== null) {
                return $porCheckout;
            }
        }

        return $assinatura === null ? null : TrocaDeCartao::query()
            ->where('provedor', $evento->provedor)
            ->where('nova_id_externo', $assinatura)
            ->latest('concluida_em')
            ->first();
    }

    private function ajustarValor(string $provedor, string $assinaturaIdExterno): void
    {
        $id = Assinatura::query()->where('provedor', $provedor)->where('id_externo', $assinaturaIdExterno)->where('valor_pendente_no_provedor', true)->value('id');

        if (is_string($id)) {
            $this->ajustes->ajustar($id);
        }
    }

    /** @return array{0: 'ignorado', 1: null} */
    private function semInvoice(ProviderEvent $evento, string $motivo): array
    {
        // Dinheiro que não casa com nenhuma compra: precisa de olhar humano.
        Log::error('cobranca.evento_sem_invoice', [
            'module' => 'cobranca',
            'action' => 'processar_evento',
            'provider_event_id' => $evento->id,
            'tipo' => $evento->tipo,
            'motivo' => $motivo,
        ]);

        return ['ignorado', null];
    }
}

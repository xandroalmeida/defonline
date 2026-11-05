<?php

declare(strict_types=1);

namespace App\Services\Cobranca;

use App\Domain\Cobranca\AssinaturaVigenteJaExiste;
use App\Domain\Cobranca\Carteira;
use App\Domain\Cobranca\CicloCobranca;
use App\Domain\Cobranca\CobrancaNoProvedor;
use App\Domain\Cobranca\CotaMensal;
use App\Domain\Cobranca\EstadoCobranca;
use App\Domain\Cobranca\MeioPagamento;
use App\Domain\Cobranca\RenovacaoPix;
use App\Domain\Cobranca\StatusAssinatura;
use App\Models\Assinatura;
use App\Models\Invoice;
use App\Models\Plano;
use App\Observabilidade\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use UnexpectedValueException;

/**
 * STORY-077 CA-2, CA-6, CA-8 — o que um pagamento confirmado faz numa assinatura. Só é chamado por
 * {@see ProcessarEvento}, depois de conferir o estado ATUAL da cobrança no provedor.
 *
 * - **Ativação:** a Invoice do checkout é paga e nasce a assinatura, com a cota cheia, o preço e
 *   a cota do momento da escolha (CA-5) e o cartão (só bandeira e 4 dígitos, se vierem).
 * - **Renovação por Pix (STORY-080):** a Invoice da renovação, paga, estende o ano (ver {@see renovarPorPix()}).
 * - **Renovação:** uma cobrança paga que não é a da Invoice, mas é de uma assinatura nossa, estende
 *   o período e devolve a cota cheia. Se a assinatura estava "Em atraso" (STORY-079 CA-4), o pagamento
 *   a restabelece e encerra a régua de retentativas. Cada cobrança vira uma Invoice (`UNIQUE (provedor, id_externo)`),
 *   então o mesmo pagamento avisado duas vezes renova uma vez só (CA-8).
 *
 * Retorno: `[status do evento, id da Invoice]` no formato de ProcessarEvento.
 */
final class ConfirmarAssinatura
{
    /** O que se zera quando o pagamento chega durante o atraso: a régua para. */
    private const FIM_DO_ATRASO = [
        'status' => StatusAssinatura::Ativa,
        'atraso_desde' => null,
        'atraso_cobranca_id' => null,
        'tentativas_cobranca' => 0,
        'proxima_tentativa_em' => null,
        'atraso_aviso_em' => null,
    ];

    public function __construct(private readonly Carteira $carteira, private readonly CupomDoCheckout $cupons) {}

    /** @return array{0: 'processado'|'ignorado', 1: string|null} */
    public function aplicar(?Invoice $invoice, CobrancaNoProvedor $cobranca, string $provedor): array
    {
        return DB::transaction(function () use ($invoice, $cobranca, $provedor): array {
            if ($invoice !== null) {
                $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);

                if (in_array($invoice->status, [EstadoCobranca::Pendente, EstadoCobranca::Vencida, EstadoCobranca::Falhou], true)) {
                    return $invoice->renovacao ? $this->renovarPorPix($invoice, $cobranca) : $this->ativar($invoice, $cobranca);
                }

                // A Invoice já está paga: o mesmo pagamento de novo, ou a cobrança do mês seguinte
                // herdando a nossa referência.
                if ($invoice->status !== EstadoCobranca::Paga || $invoice->id_externo === $cobranca->referencia->id) {
                    return ['ignorado', $invoice->id];
                }
            }

            return $this->renovar($cobranca, $provedor, $invoice);
        });
    }

    /**
     * STORY-083 CA-6 — contratação sem cobrança (desconto total ou valor abaixo do mínimo do provedor): a Invoice
     * de valor zero já foi gravada; ativa a assinatura e grava o uso do cupom, sem falar com o provedor.
     * Chamar dentro de uma transação, junto com a criação da Invoice: nunca ativa sem Invoice e sem uso.
     *
     * @return array{0: 'processado'|'ignorado', 1: string|null}
     */
    public function ativarSemCobranca(Invoice $invoice): array
    {
        return $this->ativar($invoice, null);
    }

    /** @return array{0: 'processado'|'ignorado', 1: string|null} */
    private function ativar(Invoice $invoice, ?CobrancaNoProvedor $cobranca): array
    {
        if ($cobranca !== null) {
            $this->conferirValor($invoice, $cobranca);
        }

        $ciclo = CicloCobranca::from((string) $invoice->ciclo);
        $pagoEm = $cobranca?->pagoEm !== null ? CarbonImmutable::instance($cobranca->pagoEm) : CarbonImmutable::now();

        $invoice->forceFill([
            'status' => EstadoCobranca::Paga,
            'id_externo' => $cobranca?->referencia->id,
            'checkout_id_externo' => $invoice->checkout_id_externo ?? $cobranca?->checkoutId,
            'pago_em' => $pagoEm,
            'cartao_bandeira' => $cobranca?->cartaoBandeira,
            'cartao_ultimos4' => $cobranca?->cartaoUltimos4,
        ])->save();

        $vencimento = $ciclo->vencimento($pagoEm);

        try {
            $assinatura = $this->carteira->concederCota(
                $invoice->usuario_id,
                (int) $invoice->cota_snapshot,
                $pagoEm,
                $vencimento,
                $invoice->plano_id,
                $ciclo->value,
            );
        } catch (AssinaturaVigenteJaExiste $e) {
            if ($cobranca === null) {
                // Sem dinheiro envolvido, não há o que estornar: a contratação simplesmente não acontece.
                throw $e;
            }

            // O dinheiro entrou, mas o Usuário já tinha assinatura (duas abas, dois checkouts). Não dá
            // para desfazer sozinho: fica registrado como pago e vira alerta para estorno manual.
            Log::error('cobranca.assinatura_duplicada', [
                'module' => 'cobranca',
                'action' => 'processar_evento',
                'invoice_id' => $invoice->id,
                'usuario_id' => $invoice->usuario_id,
            ]);

            return ['processado', $invoice->id];
        }

        $assinatura->forceFill([
            'provedor' => $cobranca === null ? null : $invoice->provedor,
            'id_externo' => $cobranca?->assinaturaId,
            'preco_snapshot_centavos' => $invoice->valor_centavos,
            'meio_pagamento' => $invoice->meio,
            'periodos_pagos' => 1,
            'proxima_cota_em' => $ciclo === CicloCobranca::Anual ? CotaMensal::proxima($pagoEm, $pagoEm, $vencimento) : null,
            'cartao_bandeira' => $cobranca?->cartaoBandeira,
            'cartao_ultimos4' => $cobranca?->cartaoUltimos4,
        ]);
        DescontoNaAssinatura::gravar($assinatura, $invoice, 1);
        $assinatura->save();

        $invoice->forceFill(['assinatura_id' => $assinatura->id])->save();
        $this->cupons->registrarUso($invoice);

        AuditLogger::log(
            action: 'cobranca.assinatura_ativada',
            subjectType: 'Assinatura',
            subjectId: $assinatura->id,
            actorType: 'system',
            usuarioId: $invoice->usuario_id,
            after: ['plano_id' => $invoice->plano_id, 'ciclo' => $ciclo->value, 'valor_centavos' => $invoice->valor_centavos, 'desconto_centavos' => $invoice->desconto_centavos, 'cota' => $invoice->cota_snapshot, 'provedor' => $invoice->provedor],
        );

        return ['processado', $invoice->id];
    }

    /**
     * STORY-080 — renovação do plano anual pago com Pix: a Invoice pendente da renovação foi paga. O ciclo
     * avança um ano a partir do fim do atual (nada se perde), a cota do mês em curso não é zerada (o
     * aniversário do fim do ciclo a devolve cheia) e o preço fica o da Invoice. Se o plano já acabou
     * (Pix pago depois do fim), o pagamento vira uma assinatura nova, como qualquer contratação.
     *
     * @return array{0: 'processado'|'ignorado', 1: string|null}
     */
    private function renovarPorPix(Invoice $invoice, CobrancaNoProvedor $cobranca): array
    {
        $assinatura = Assinatura::query()->lockForUpdate()->find($invoice->assinatura_id);

        if ($assinatura === null || $assinatura->status !== StatusAssinatura::Ativa) {
            return $this->ativar($invoice, $cobranca);
        }

        $this->conferirValor($invoice, $cobranca);

        $pagoEm = $cobranca->pagoEm !== null ? CarbonImmutable::instance($cobranca->pagoEm) : CarbonImmutable::now();

        $invoice->forceFill([
            'status' => EstadoCobranca::Paga,
            'id_externo' => $cobranca->referencia->id,
            'checkout_id_externo' => $invoice->checkout_id_externo ?? $cobranca->checkoutId,
            'pago_em' => $pagoEm,
        ])->save();

        return $this->estenderAnual($invoice, $assinatura, $pagoEm);
    }

    /**
     * STORY-083 — renovação do plano anual Pix sem cobrança (cupom de desconto total na renovação, ou ciclo
     * gratuito de um desconto que continua). A Invoice de valor zero já existe; estende o ano e grava o uso.
     *
     * @return array{0: 'processado'|'ignorado', 1: string|null}
     */
    public function renovarAnualSemCobranca(Invoice $invoice): array
    {
        $assinatura = Assinatura::query()->lockForUpdate()->findOrFail($invoice->assinatura_id);
        $invoice->forceFill(['status' => EstadoCobranca::Paga, 'pago_em' => CarbonImmutable::now()])->save();

        return $this->estenderAnual($invoice, $assinatura, CarbonImmutable::now());
    }

    /** @return array{0: 'processado'|'ignorado', 1: string|null} */
    private function estenderAnual(Invoice $invoice, Assinatura $assinatura, CarbonImmutable $pagoEm): array
    {
        $periodos = $assinatura->periodos_pagos + 1;
        $vencimento = RenovacaoPix::novoVencimento($assinatura->data_inicio, $assinatura->periodos_pagos);

        $assinatura->forceFill([
            'proximo_vencimento' => $vencimento,
            'periodos_pagos' => $periodos,
            'cota_snapshot' => (int) $invoice->cota_snapshot,
            'preco_snapshot_centavos' => $invoice->valor_centavos,
            'proxima_cota_em' => CotaMensal::proxima($assinatura->data_inicio, $pagoEm, $vencimento),
        ]);
        DescontoNaAssinatura::gravar($assinatura, $invoice, $periodos);
        $assinatura->save();
        $this->cupons->registrarUso($invoice);

        AuditLogger::log(
            action: 'cobranca.assinatura_renovada',
            subjectType: 'Assinatura',
            subjectId: $assinatura->id,
            actorType: 'system',
            usuarioId: $assinatura->usuario_id,
            after: ['periodos_pagos' => $periodos, 'proximo_vencimento' => $vencimento->toIso8601String(), 'cota' => $invoice->cota_snapshot, 'valor_centavos' => $invoice->valor_centavos, 'desconto_centavos' => $invoice->desconto_centavos, 'meio' => $invoice->meio],
        );

        return ['processado', $invoice->id];
    }

    private function conferirValor(Invoice $invoice, CobrancaNoProvedor $cobranca): void
    {
        if ($cobranca->valorCentavos === $invoice->valor_centavos) {
            return;
        }

        Log::error('cobranca.valor_divergente', [
            'module' => 'cobranca',
            'action' => 'processar_evento',
            'invoice_id' => $invoice->id,
            'esperado_centavos' => $invoice->valor_centavos,
            'recebido_centavos' => $cobranca->valorCentavos,
        ]);

        throw new UnexpectedValueException('Valor pago diferente do valor da invoice.');
    }

    /**
     * Em atraso, QUALQUER cobrança paga da assinatura a restabelece e para a régua (STORY-079 CA-4): a
     * retentativa da régua e a 1ª cobrança do cartão novo (STORY-081 CA-3) chegam aqui pelo mesmo caminho,
     * e cada cobrança vira uma só Invoice (`UNIQUE (provedor, id_externo)`), então não há renovação em dobro.
     *
     * @return array{0: 'processado'|'ignorado', 1: string|null}
     */
    private function renovar(CobrancaNoProvedor $cobranca, string $provedor, ?Invoice $referencia): array
    {
        $assinatura = $cobranca->assinaturaId === null ? null : Assinatura::query()
            ->where('provedor', $provedor)
            ->where('id_externo', $cobranca->assinaturaId)
            ->lockForUpdate()
            ->first();

        if ($assinatura === null) {
            // Pagamento da cobrança da assinatura ANTIGA de uma troca de cartão já concluída (a retentativa
            // da régua estava em voo quando o cartão novo foi confirmado): o ciclo vai ser cobrado de novo
            // pelo cartão novo, então pede devolução manual (STORY-087).
            if ($cobranca->assinaturaId !== null && DB::table('trocas_de_cartao')->where('antiga_id_externo', $cobranca->assinaturaId)->where('status', 'concluida')->exists()) {
                Log::error('cobranca.pagamento_da_assinatura_antiga', [
                    'module' => 'cobranca',
                    'action' => 'processar_evento',
                    'cobranca' => $cobranca->referencia->id,
                    'assinatura_antiga' => $cobranca->assinaturaId,
                ]);

                return ['ignorado', $referencia?->id];
            }

            Log::error('cobranca.evento_sem_invoice', [
                'module' => 'cobranca',
                'action' => 'processar_evento',
                'motivo' => 'pagamento de assinatura desconhecida',
            ]);

            return ['ignorado', $referencia?->id];
        }

        if (Invoice::query()->where('provedor', $provedor)->where('id_externo', $cobranca->referencia->id)->exists()) {
            return ['ignorado', $referencia?->id];
        }

        $emAtraso = $assinatura->status === StatusAssinatura::EmAtraso;

        // Cancelada ou encerrada não renova sozinha: o dinheiro entrou, mas não há ciclo a estender.
        // Fica o alerta para a equipe (devolução é manual, STORY-087).
        if ($assinatura->status !== StatusAssinatura::Ativa && ! $emAtraso) {
            Log::error('cobranca.renovacao_de_assinatura_inativa', [
                'module' => 'cobranca',
                'action' => 'processar_evento',
                'assinatura_id' => $assinatura->id,
                'status' => $assinatura->status->value,
            ]);

            return ['ignorado', $referencia?->id];
        }

        $plano = Plano::query()->findOrFail($assinatura->plano_id);
        $ciclo = CicloCobranca::from((string) $assinatura->ciclo);
        $pagoEm = $cobranca->pagoEm !== null ? CarbonImmutable::instance($cobranca->pagoEm) : CarbonImmutable::now();
        $periodos = $assinatura->periodos_pagos + 1;
        $vencimento = $ciclo->vencimento($assinatura->data_inicio, $periodos);
        // Na renovação vale o catálogo de hoje para a cota (CA-5: o snapshot protege até aqui).
        $cota = $plano->cota_mensal;

        // STORY-083 CA-7: com desconto de cupom em curso o esperado é o valor do ciclo (já com desconto).
        $desconto = $assinatura->desconto();
        $comDesconto = $desconto?->vale($periodos) === true;

        if ($cobranca->valorCentavos !== $assinatura->preco_snapshot_centavos) {
            Log::warning('cobranca.valor_da_renovacao_mudou', [
                'module' => 'cobranca',
                'action' => 'processar_evento',
                'assinatura_id' => $assinatura->id,
                'anterior_centavos' => $assinatura->preco_snapshot_centavos,
                'cobrado_centavos' => $cobranca->valorCentavos,
            ]);
        }

        $invoice = Invoice::query()->create([
            'usuario_id' => $assinatura->usuario_id,
            'tipo' => 'assinatura',
            'plano_id' => $plano->id,
            'ciclo' => $ciclo->value,
            'cota_snapshot' => $cota,
            'descricao' => "Plano {$plano->nome} · renovação {$ciclo->value}",
            'valor_centavos' => max(1, $cobranca->valorCentavos),
            'status' => EstadoCobranca::Paga,
            'meio' => MeioPagamento::Cartao->value,
            'provedor' => $provedor,
            'id_externo' => $cobranca->referencia->id,
            'pago_em' => $pagoEm,
            'assinatura_id' => $assinatura->id,
            'renovacao' => true,
            'cartao_bandeira' => $cobranca->cartaoBandeira,
            'cartao_ultimos4' => $cobranca->cartaoUltimos4,
            ...($comDesconto ? [
                'cupom_id' => $assinatura->cupom_id,
                'preco_cheio_centavos' => $desconto->cheio,
                'desconto_centavos' => $desconto->desconto,
            ] : []),
        ]);

        $this->carteira->renovarCota($assinatura->id, $cota, $vencimento);

        $assinatura->refresh()->forceFill([
            'periodos_pagos' => $periodos,
            // O que vale para a PRÓXIMA cobrança: com desconto em curso, o do próximo ciclo; senão o que o provedor cobrou.
            'preco_snapshot_centavos' => $desconto !== null && $desconto->existe() ? $desconto->valorDoPeriodo($periodos + 1) : $cobranca->valorCentavos,
            // O último ciclo com desconto foi pago: o valor no provedor volta ao cheio (feito depois, fora desta transação).
            'valor_pendente_no_provedor' => $desconto?->acabaApos($periodos) === true && $assinatura->id_externo !== null,
            'proxima_cota_em' => $ciclo === CicloCobranca::Anual ? CotaMensal::proxima($assinatura->data_inicio, $pagoEm, $vencimento) : null,
            'cartao_bandeira' => $cobranca->cartaoBandeira ?? $assinatura->cartao_bandeira,
            'cartao_ultimos4' => $cobranca->cartaoUltimos4 ?? $assinatura->cartao_ultimos4,
            ...($emAtraso ? self::FIM_DO_ATRASO : []),
        ])->save();

        if ($emAtraso) {
            AuditLogger::log(
                action: 'cobranca.assinatura_restabelecida',
                subjectType: 'Assinatura',
                subjectId: $assinatura->id,
                actorType: 'system',
                usuarioId: $assinatura->usuario_id,
                after: ['cobranca' => $cobranca->referencia->id, 'periodos_pagos' => $periodos],
            );
        }

        AuditLogger::log(
            action: 'cobranca.assinatura_renovada',
            subjectType: 'Assinatura',
            subjectId: $assinatura->id,
            actorType: 'system',
            usuarioId: $assinatura->usuario_id,
            after: ['periodos_pagos' => $periodos, 'proximo_vencimento' => $vencimento->toIso8601String(), 'cota' => $cota, 'valor_centavos' => $cobranca->valorCentavos],
        );

        return ['processado', $invoice->id];
    }
}

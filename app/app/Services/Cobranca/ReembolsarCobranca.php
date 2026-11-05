<?php

declare(strict_types=1);

namespace App\Services\Cobranca;

use App\Domain\Cobranca\EstadoCobranca;
use App\Domain\Cobranca\PaymentProvider;
use App\Domain\Cobranca\ProvedorIndisponivel;
use App\Domain\Cobranca\ReferenciaProvedor;
use App\Domain\Cobranca\ResultadoReembolso;
use App\Domain\Cobranca\StatusAssinatura;
use App\Domain\Cobranca\StatusLote;
use App\Models\Assinatura;
use App\Models\Invoice;
use App\Models\LoteCredito;
use App\Models\Plano;
use App\Models\Usuario;
use App\Observabilidade\AuditLogger;
use App\Observabilidade\EventosCobranca;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * STORY-085 CA-4, CA-5, CA-7 e ajustes do ADR-010 — reembolso total de uma cobrança paga, pedido pelo admin.
 *
 * - **Não duas vezes (CA-5):** a trava é `invoices.reembolso_estado`, gravada sob o lock da linha ANTES de
 *   chamar o provedor (a chamada de rede fica fora da transação). Além disso, antes de pedir o estorno o
 *   provedor é consultado: cobrança que ele já mostra estornada é só finalizada aqui.
 * - **Só vira "Estornada" com o provedor confirmando:** cartão responde `Concluido` na hora; Pix fica
 *   `aguardando_autorizacao` (o admin autoriza no painel do Asaas) e o webhook `cobranca.estornada` conclui,
 *   pelo mesmo {@see concluir()}, que também atende estorno feito direto no painel.
 * - **O que se retira:** o saldo restante do lote de créditos, ou a assinatura (encerrada, cota zerada,
 *   recorrência cancelada pelo job da régua). Nunca saldo negativo; consumo passado não volta. Pagamento
 *   que não concedeu nada (`devolucao_motivo`) só devolve o dinheiro.
 */
final class ReembolsarCobranca
{
    public function __construct(private readonly PaymentProvider $provedor) {}

    /** O que o reembolso retiraria agora (a tela mostra antes de confirmar). */
    public function previa(Invoice $invoice): PreviaDoReembolso
    {
        return $this->montarPrevia($invoice, false);
    }

    /**
     * @throws ReembolsoNaoRealizado reembolso já pedido, cobrança que não está paga ou recusa do provedor
     * @throws ProvedorIndisponivel o provedor não respondeu: o resultado é desconhecido, confira antes de repetir
     */
    public function solicitar(Invoice $invoice, Usuario $admin, string $motivo): ResultadoReembolso
    {
        $motivo = trim($motivo);

        $invoice = $this->reservar($invoice->id, $admin, $motivo);
        $referencia = new ReferenciaProvedor($invoice->provedor, (string) $invoice->id_externo);

        try {
            $atual = $this->provedor->getCharge($referencia);

            if ($atual->estado === EstadoCobranca::Estornada) {
                // Já estornada lá (painel, ou uma tentativa anterior cuja resposta se perdeu): só finaliza.
                $this->concluir($invoice->id, $admin);

                return ResultadoReembolso::Concluido;
            }

            if ($atual->estado !== EstadoCobranca::Paga) {
                $this->liberar($invoice->id, $admin, 'cobranca_nao_paga', "O provedor mostra a cobrança como {$atual->estado->value}.");

                throw new ReembolsoNaoRealizado("O provedor mostra esta cobrança como {$atual->estado->value}: só cobrança paga pode ser reembolsada.");
            }

            $resposta = $this->provedor->refund($referencia, null, $motivo);
        } catch (ProvedorIndisponivel $e) {
            $this->liberar($invoice->id, $admin, 'provedor_sem_resposta', $e->getMessage());

            throw $e;
        }

        return match ($resposta->resultado) {
            ResultadoReembolso::Concluido => $this->finalizarConcluido($invoice->id, $admin),
            ResultadoReembolso::Recusado => $this->recusar($invoice->id, $admin, $resposta->detalhe),
            default => $this->aguardar($invoice->id, $admin, $resposta->resultado),
        };
    }

    /**
     * O provedor confirmou o estorno (resposta imediata, webhook ou painel): a cobrança vira Estornada e o
     * que ela concedeu é retirado. Idempotente.
     *
     * @return bool `true` se concluiu agora; `false` se já estava concluída ou a cobrança não está mais "paga"
     */
    public function concluir(string $invoiceId, ?Usuario $admin = null): bool
    {
        return DB::transaction(function () use ($invoiceId, $admin): bool {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoiceId);

            if (! $invoice->status->podeIrPara(EstadoCobranca::Estornada)) {
                return false;
            }

            $previa = $this->montarPrevia($invoice, true);

            $invoice->forceFill([
                'status' => EstadoCobranca::Estornada,
                'reembolso_estado' => 'concluido',
                'reembolsado_em' => CarbonImmutable::now(),
            ])->save();

            $this->registrar('cobranca.reembolso_concluido', $invoice, $admin, [
                'retirado' => [
                    'creditos' => $previa->creditosARetirar,
                    'cota' => $previa->cotaARetirar,
                    'assinatura_encerrada' => $previa->encerraAssinatura,
                ],
                'creditos_ja_usados' => $previa->creditosUsados,
            ]);

            return true;
        });
    }

    private function reservar(string $invoiceId, Usuario $admin, string $motivo): Invoice
    {
        return DB::transaction(function () use ($invoiceId, $admin, $motivo): Invoice {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoiceId);

            if ($invoice->reembolso_estado !== null) {
                throw new ReembolsoNaoRealizado(match ($invoice->reembolso_estado) {
                    'concluido' => 'Esta cobrança já foi reembolsada.',
                    'aguardando_autorizacao' => 'O reembolso desta cobrança já foi pedido e aguarda autorização no painel do provedor.',
                    default => 'O reembolso desta cobrança já foi pedido e está em andamento.',
                });
            }

            if ($invoice->status !== EstadoCobranca::Paga) {
                throw new ReembolsoNaoRealizado('Só cobrança paga pode ser reembolsada.');
            }

            // Contratação sem cobrança (cupom de desconto total): não houve dinheiro, não há o que devolver.
            if ($invoice->valor_centavos === 0) {
                throw new ReembolsoNaoRealizado('Esta contratação foi feita sem cobrança (valor zero): não há o que reembolsar.');
            }

            if ($invoice->id_externo === null || $invoice->provedor !== $this->provedor->nome()) {
                throw new ReembolsoNaoRealizado('Esta cobrança não tem registro no provedor de pagamento ativo; o estorno deve ser feito direto no provedor.');
            }

            $invoice->forceFill([
                'reembolso_estado' => 'em_andamento',
                'reembolso_motivo' => $motivo,
                'reembolso_por_id' => $admin->id,
                'reembolso_solicitado_em' => CarbonImmutable::now(),
            ])->save();

            $this->registrar('cobranca.reembolso_solicitado', $invoice, $admin, ['motivo' => $motivo, 'valor_centavos' => $invoice->valor_centavos]);

            return $invoice;
        });
    }

    /** Devolve a cobrança a "sem reembolso" quando o provedor comprovadamente não estornou (ou não respondeu). */
    private function liberar(string $invoiceId, Usuario $admin, string $porque, string $detalhe): void
    {
        DB::transaction(function () use ($invoiceId, $admin, $porque, $detalhe): void {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoiceId);

            // Se um webhook concluiu no meio do caminho, não se desfaz.
            if ($invoice->reembolso_estado === 'concluido') {
                return;
            }

            $invoice->forceFill([
                'reembolso_estado' => null,
                'reembolso_motivo' => null,
                'reembolso_por_id' => null,
                'reembolso_solicitado_em' => null,
            ])->save();

            $this->registrar('cobranca.reembolso_nao_realizado', $invoice, $admin, ['porque' => $porque, 'detalhe' => mb_substr($detalhe, 0, 300)]);
        });
    }

    private function recusar(string $invoiceId, Usuario $admin, ?string $detalhe): never
    {
        $texto = $detalhe ?? 'O provedor recusou o estorno.';
        $this->liberar($invoiceId, $admin, 'recusado_pelo_provedor', $texto);

        throw new ReembolsoNaoRealizado($texto);
    }

    private function finalizarConcluido(string $invoiceId, Usuario $admin): ResultadoReembolso
    {
        $this->concluir($invoiceId, $admin);

        return ResultadoReembolso::Concluido;
    }

    private function aguardar(string $invoiceId, Usuario $admin, ResultadoReembolso $resultado): ResultadoReembolso
    {
        DB::transaction(function () use ($invoiceId, $admin, $resultado): void {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoiceId);

            // O webhook pode ter chegado antes desta resposta: concluído não volta a "aguardando".
            if ($invoice->reembolso_estado === 'em_andamento') {
                $invoice->forceFill(['reembolso_estado' => $resultado->value])->save();
                $this->registrar('cobranca.reembolso_aceito_pelo_provedor', $invoice, $admin, ['estado' => $resultado->value]);
            }
        });

        return $resultado;
    }

    /** `$aplicar` retira de verdade (dentro do lock da cobrança); sem ele só calcula. */
    private function montarPrevia(Invoice $invoice, bool $aplicar): PreviaDoReembolso
    {
        if ($invoice->devolucao_motivo !== null) {
            return PreviaDoReembolso::nada('Este pagamento não concedeu acesso nem créditos (a assinatura estava cancelada, trocada ou já existia): só o dinheiro é devolvido.');
        }

        return match ($invoice->tipo) {
            'credito' => $this->previaDeCreditos($invoice, $aplicar),
            'troca_de_plano' => $this->previaDeTroca($invoice, $aplicar),
            default => $this->previaDeAssinatura($invoice, $aplicar),
        };
    }

    private function previaDeCreditos(Invoice $invoice, bool $aplicar): PreviaDoReembolso
    {
        $query = LoteCredito::query()->where('invoice_id', $invoice->id);
        $lote = ($aplicar ? $query->lockForUpdate() : $query)->first();

        if ($lote === null) {
            return PreviaDoReembolso::nada('Esta compra não gerou lote de créditos.');
        }

        $usados = $lote->quantidade_comprada - $lote->quantidade_restante;
        $retirar = $lote->status === StatusLote::Ativo ? $lote->quantidade_restante : 0;

        if ($aplicar) {
            $lote->forceFill(['quantidade_restante' => 0, 'status' => StatusLote::Estornado])->save();
        }

        return new PreviaDoReembolso('creditos', $lote->quantidade_comprada, $usados, $retirar);
    }

    /**
     * STORY-082 × 085 — estornar a diferença de um upgrade NÃO encerra a assinatura (ela segue paga pelo ciclo):
     * volta ao plano anterior se o ciclo ainda é o da troca; se o ciclo já seguiu (renovou, ou outra troca),
     * o plano atual fica e só o dinheiro da diferença é devolvido, e a tela avisa antes de confirmar.
     */
    private function previaDeTroca(Invoice $invoice, bool $aplicar): PreviaDoReembolso
    {
        $query = Assinatura::query()->whereKey($invoice->assinatura_id);
        $assinatura = $invoice->assinatura_id === null ? null : ($aplicar ? $query->lockForUpdate() : $query)->first();
        $antes = $invoice->troca_anterior;
        $depois = is_array($antes) ? ($antes['depois'] ?? null) : null;

        if ($assinatura === null || ! is_array($antes) || ! is_array($depois)) {
            return PreviaDoReembolso::nada('Não dá para voltar ao plano anterior (a troca não deixou o registro do plano de antes): o plano atual é mantido e só o dinheiro da diferença é devolvido.', alerta: true);
        }

        $mesmoCiclo = $assinatura->status === StatusAssinatura::Ativa
            && $assinatura->plano_id === $invoice->plano_id
            && $assinatura->ciclo === $invoice->ciclo
            && $assinatura->periodos_pagos === (int) $depois['periodos_pagos']
            && CarbonImmutable::parse((string) $depois['data_inicio'])->equalTo($assinatura->data_inicio);

        if (! $mesmoCiclo) {
            return PreviaDoReembolso::nada('Não dá para voltar ao plano anterior: o ciclo ou o plano já mudou depois desta troca (renovou, mudou de novo ou a assinatura não está ativa). O plano atual é mantido e só o dinheiro da diferença é devolvido.', alerta: true);
        }

        $nome = Plano::query()->find($antes['plano_id'])->nome ?? 'anterior';

        if ($aplicar) {
            // O que se usou desde a troca continua usado.
            $uso = max(0, (int) $depois['cota_restante_ciclo'] - $assinatura->cota_restante_ciclo);
            $assinatura->forceFill([
                'plano_id' => $antes['plano_id'],
                'ciclo' => $antes['ciclo'],
                'preco_snapshot_centavos' => $antes['preco_snapshot_centavos'],
                'cota_snapshot' => $antes['cota_snapshot'],
                'cota_restante_ciclo' => max(0, (int) $antes['cota_restante_ciclo'] - $uso),
                'data_inicio' => $antes['data_inicio'],
                'periodos_pagos' => $antes['periodos_pagos'],
                'proximo_vencimento' => $antes['proximo_vencimento'],
                'proxima_cota_em' => $antes['proxima_cota_em'],
                'aviso_renovacao_periodo' => $antes['aviso_renovacao_periodo'],
                'preco_cheio_centavos' => $antes['preco_cheio_centavos'],
                'desconto_centavos' => (int) $antes['desconto_centavos'],
                'desconto_periodos' => $antes['desconto_periodos'],
                'troca_plano_id' => null,
                'troca_ciclo' => null,
                'troca_preco_centavos' => null,
                'troca_preco_cheio_centavos' => null,
                'troca_desconto_centavos' => null,
                'troca_agendada_em' => null,
                // O valor no provedor volta ao do plano anterior pela rotina horária (sem chamada de rede aqui).
                'ajuste_provedor_pendente' => $assinatura->id_externo !== null,
            ])->save();
        }

        return PreviaDoReembolso::voltaAoPlano($nome);
    }

    private function previaDeAssinatura(Invoice $invoice, bool $aplicar): PreviaDoReembolso
    {
        $query = Assinatura::query()->whereKey($invoice->assinatura_id);
        $assinatura = $invoice->assinatura_id === null ? null : ($aplicar ? $query->lockForUpdate() : $query)->first();

        if ($assinatura === null) {
            return PreviaDoReembolso::nada('Esta cobrança não está ligada a uma assinatura.');
        }

        if ($assinatura->status === StatusAssinatura::Expirada) {
            return PreviaDoReembolso::nada('A assinatura já estava encerrada; não há acesso a retirar.');
        }

        $maisNova = Invoice::query()
            ->where('assinatura_id', $assinatura->id)
            ->where('status', EstadoCobranca::Paga->value)
            ->whereKeyNot($invoice->id)
            ->where('pago_em', '>', $invoice->pago_em)
            ->exists();

        if ($maisNova) {
            return PreviaDoReembolso::nada('Há uma cobrança paga mais recente nesta assinatura: o acesso atual pertence a ela e é mantido.');
        }

        $cota = $assinatura->cota_restante_ciclo;

        if ($aplicar) {
            $assinatura->forceFill([
                'status' => StatusAssinatura::Expirada,
                'cota_restante_ciclo' => 0,
                'encerrada_em' => CarbonImmutable::now(),
                'proxima_tentativa_em' => null,
                // O job da régua cancela a recorrência no provedor e repete se ele estiver fora do ar.
                'encerramento_pendente_no_provedor' => $assinatura->id_externo !== null,
            ])->save();

            EventosCobranca::cancelamento($assinatura, EventosCobranca::ORIGEM_REEMBOLSO);
        }

        return new PreviaDoReembolso('assinatura', cotaARetirar: $cota, encerraAssinatura: true);
    }

    /** @param array<string, mixed> $depois */
    private function registrar(string $acao, Invoice $invoice, ?Usuario $admin, array $depois): void
    {
        AuditLogger::log(
            action: $acao,
            subjectType: 'Invoice',
            subjectId: $invoice->id,
            actorType: $admin === null ? 'system' : 'user',
            actorId: $admin?->id,
            usuarioId: $invoice->usuario_id,
            after: $depois,
        );
    }
}

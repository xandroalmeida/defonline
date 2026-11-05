<?php

declare(strict_types=1);

namespace App\Observabilidade;

use App\Domain\Cobranca\MotivoBloqueio;
use App\Domain\Cobranca\TipoEvento;
use App\Models\Assinatura;
use App\Models\Invoice;
use App\Models\ProviderEvent;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Catálogo dos eventos de produto da cobrança (STORY-086, ADR-004 §Decisão 2, PDR-020, NRF §6.3).
 *
 * Tudo sai por {@see EventLogger} (canal único, CA-2). Cada método declara o esquema exato do seu
 * evento ({@see ESQUEMA}) e a chave de idempotência do FATO que registra: o mesmo fato avisado duas
 * vezes (webhook repetido, reprocessamento, clique duplo) grava uma vez só.
 *
 * **Sem dado pessoal nem de cartão (CA-2).** Só identificadores opacos (de assinatura, cobrança, plano,
 * cupom), enumerações, contagens e valores em centavos DO PRODUTO (o preço cobrado, não renda do
 * cliente). Nada de nome, e-mail, documento, IP, bandeira ou final de cartão. O `EventLogger` recusa
 * em execução as chaves proibidas, e o teste de esquema garante que nenhum evento sai com chave fora
 * desta lista.
 *
 * **Funil e métricas (CA-3).** As contas estão em {@see MetricasDeCobranca}; o desenho:
 *  - conversão do trial = `trial_ativado` seguido de `contratacao_concluida` (`tipo=assinatura`,
 *    `renovacao=false`) do mesmo Usuário em até 30 dias;
 *  - conversão do checkout = `checkout_iniciado` × `contratacao_concluida`, ligados por `invoice_id`,
 *    com `meio` e `ciclo` para separar cartão, Pix e anual;
 *  - churn mensal = assinantes da base de abertura que têm `cancelamento` (e não `cancelamento_revertido`
 *    depois) no mês ÷ base de abertura (`contratacao_concluida` de assinatura, líquida de cancelamentos);
 *  - taxa de falha de pagamento = `pagamento_recusado` ÷ (`pagamento_recusado` + `pagamento_confirmado`).
 */
final class EventosCobranca
{
    public const TRIAL_ATIVADO = 'trial_ativado';

    public const TRIAL_ENCERRADO = 'trial_encerrado';

    public const CHECKOUT_INICIADO = 'checkout_iniciado';

    public const CONTRATACAO_CONCLUIDA = 'contratacao_concluida';

    public const CONTRATACAO_ABANDONADA = 'contratacao_abandonada';

    public const CUPOM_APLICADO = 'cupom_aplicado';

    public const CANCELAMENTO = 'cancelamento';

    public const CANCELAMENTO_REVERTIDO = 'cancelamento_revertido';

    public const BLOQUEIO_SEM_SALDO = 'bloqueio_sem_saldo';

    public const PAGAMENTO_CONFIRMADO = 'pagamento_confirmado';

    public const PAGAMENTO_RECUSADO = 'pagamento_recusado';

    /** Quem encerrou o trial: o prazo acabou ou o Usuário contratou um plano. */
    public const FIM_POR_PRAZO = 'prazo';

    public const FIM_POR_CONTRATACAO = 'contratou';

    /** Por que a assinatura paga deixou de renovar. */
    public const ORIGEM_CLIENTE = 'cliente';

    public const ORIGEM_CLIENTE_EM_ATRASO = 'cliente_em_atraso';

    public const ORIGEM_INADIMPLENCIA = 'inadimplencia';

    public const ORIGEM_PIX_NAO_RENOVADO = 'pix_nao_renovado';

    public const ORIGEM_REEMBOLSO = 'reembolso';

    /** De onde veio o bloqueio por falta de saldo. */
    public const BLOQUEIO_ANALISE = 'analise';

    public const BLOQUEIO_DRM = 'drm';

    /**
     * Esquema exato de cada evento (as chaves de `propriedades`). O teste de esquema compara o que sai
     * com esta lista: uma chave nova exige decidir aqui, em revisão, que ela não é dado pessoal.
     *
     * @var array<string, list<string>>
     */
    public const ESQUEMA = [
        self::TRIAL_ATIVADO => ['assinatura_id', 'dias', 'analises'],
        self::TRIAL_ENCERRADO => ['assinatura_id', 'motivo', 'dias_de_teste', 'analises_usadas'],
        self::CHECKOUT_INICIADO => ['invoice_id', 'tipo', 'plano_id', 'pacote_credito_id', 'ciclo', 'meio', 'valor_centavos', 'renovacao'],
        self::CONTRATACAO_CONCLUIDA => ['invoice_id', 'tipo', 'plano_id', 'pacote_credito_id', 'ciclo', 'meio', 'valor_centavos', 'renovacao', 'assinatura_id', 'veio_do_trial'],
        self::CONTRATACAO_ABANDONADA => ['invoice_id', 'tipo', 'plano_id', 'pacote_credito_id', 'ciclo', 'meio', 'valor_centavos', 'renovacao', 'motivo'],
        self::CUPOM_APLICADO => ['cupom_id', 'tipo', 'invoice_id', 'desconto_centavos'],
        self::CANCELAMENTO => ['assinatura_id', 'origem', 'plano_id', 'ciclo', 'meio', 'periodos_pagos', 'dias_de_assinatura'],
        self::CANCELAMENTO_REVERTIDO => ['assinatura_id'],
        self::BLOQUEIO_SEM_SALDO => ['motivo', 'origem'],
        self::PAGAMENTO_CONFIRMADO => ['tipo', 'meio', 'invoice_id'],
        self::PAGAMENTO_RECUSADO => ['tipo', 'meio', 'invoice_id'],
    ];

    // ------------------------------------------------------------------ trial

    /** O teste grátis começou (dentro da transação da ativação). */
    public static function trialAtivado(Assinatura $trial): void
    {
        EventLogger::emitOnce(
            "trial:{$trial->id}:ativado",
            self::TRIAL_ATIVADO,
            [
                'assinatura_id' => $trial->id,
                'dias' => (int) $trial->data_inicio->diffInDays($trial->proximo_vencimento, true),
                'analises' => $trial->cota_snapshot,
            ],
            $trial->usuario_id,
        );
    }

    /** O teste acabou: pelo prazo (varredura horária) ou porque o Usuário contratou um plano. Uma vez por trial. */
    public static function trialEncerrado(Assinatura $trial, string $motivo): void
    {
        EventLogger::emitOnce(
            "trial:{$trial->id}:encerrado",
            self::TRIAL_ENCERRADO,
            [
                'assinatura_id' => $trial->id,
                'motivo' => $motivo,
                'dias_de_teste' => (int) $trial->data_inicio->diffInDays($motivo === self::FIM_POR_PRAZO ? $trial->proximo_vencimento : now(), true),
                'analises_usadas' => max(0, $trial->cota_snapshot - $trial->cota_restante_ciclo),
            ],
            $trial->usuario_id,
        );
    }

    // ------------------------------------------------------------------ funil de contratação

    /** O checkout foi aberto no provedor (assinatura, créditos ou renovação por Pix). Reabrir o mesmo não conta de novo. */
    public static function checkoutIniciado(Invoice $invoice): void
    {
        EventLogger::emitOnce("invoice:{$invoice->id}:checkout", self::CHECKOUT_INICIADO, self::daInvoice($invoice), $invoice->usuario_id);
    }

    /**
     * O pagamento que fecha a contratação foi confirmado: assinatura ativada, créditos concedidos ou ano
     * renovado por Pix. Chamado dentro da transação que concede o acesso (ADR-004 §2.3).
     *
     * `$renovacao` é verdadeiro só quando o pagamento ESTENDEU uma assinatura que já existia (Pix anual):
     * a assinatura nova, mesmo paga por uma Invoice de renovação depois do fim do plano, é contratação.
     */
    public static function contratacaoConcluida(Invoice $invoice, ?Assinatura $assinatura, bool $renovacao): void
    {
        EventLogger::emitOnce(
            "invoice:{$invoice->id}:concluida",
            self::CONTRATACAO_CONCLUIDA,
            ['renovacao' => $renovacao] + self::daInvoice($invoice) + [
                'assinatura_id' => $assinatura?->id,
                'veio_do_trial' => Assinatura::query()->where('usuario_id', $invoice->usuario_id)->where('trial', true)->exists(),
            ],
            $invoice->usuario_id,
        );
    }

    /**
     * O checkout acabou sem pagamento: expirou, foi cancelado ou o meio foi recusado. O Pix vencido ainda
     * pode ser confirmado depois; nesse caso a mesma Invoice tem também `contratacao_concluida`, e a
     * conversão (que conta por Invoice) fica certa.
     */
    public static function contratacaoAbandonada(Invoice $invoice, string $motivo): void
    {
        EventLogger::emitOnce("invoice:{$invoice->id}:abandonada", self::CONTRATACAO_ABANDONADA, self::daInvoice($invoice) + ['motivo' => $motivo], $invoice->usuario_id);
    }

    /**
     * Um cupom foi aplicado a uma contratação (STORY-083). O evento está definido e pronto: o ponto de
     * emissão é ligado por quem entrega o cupom, depois da junção das pistas.
     *
     * @param  'assinatura'|'credito'  $tipo
     */
    public static function cupomAplicado(string $usuarioId, string $cupomId, string $tipo, ?string $invoiceId = null, ?int $descontoCentavos = null): void
    {
        EventLogger::emitOnce(
            'cupom:'.$cupomId.':'.($invoiceId ?? $usuarioId),
            self::CUPOM_APLICADO,
            ['cupom_id' => $cupomId, 'tipo' => $tipo, 'invoice_id' => $invoiceId, 'desconto_centavos' => $descontoCentavos],
            $usuarioId,
        );
    }

    // ------------------------------------------------------------------ cancelamento

    /**
     * A assinatura paga deixou de renovar. `$origem` separa a decisão do cliente da inadimplência, do Pix
     * não renovado e do reembolso. Uma vez por assinatura e origem em cada instante (cancelar, reativar e
     * cancelar de novo grava duas vezes, porque a chave leva o instante).
     */
    public static function cancelamento(Assinatura $assinatura, string $origem): void
    {
        EventLogger::emitOnce(
            "assinatura:{$assinatura->id}:cancelamento:{$origem}:".now()->timestamp,
            self::CANCELAMENTO,
            [
                'assinatura_id' => $assinatura->id,
                'origem' => $origem,
                'plano_id' => $assinatura->plano_id,
                'ciclo' => $assinatura->ciclo,
                'meio' => $assinatura->meio_pagamento,
                'periodos_pagos' => $assinatura->periodos_pagos,
                'dias_de_assinatura' => (int) $assinatura->data_inicio->diffInDays(now(), true),
            ],
            $assinatura->usuario_id,
        );
    }

    /** O Usuário reativou a renovação que tinha cancelado: o cancelamento deixa de contar no churn. */
    public static function cancelamentoRevertido(Assinatura $assinatura): void
    {
        EventLogger::emitOnce(
            "assinatura:{$assinatura->id}:reativacao:".now()->timestamp,
            self::CANCELAMENTO_REVERTIDO,
            ['assinatura_id' => $assinatura->id],
            $assinatura->usuario_id,
        );
    }

    // ------------------------------------------------------------------ bloqueio

    /**
     * O Usuário bateu na parede: sem cota nem crédito válido (ou sem plano, para o DRM). Uma vez por
     * Usuário, causa e origem em cada dia (Brasília): a tela é reaberta várias vezes e não é isso que se mede.
     *
     * Roda ao MONTAR a tela que explica o bloqueio: perder um ponto de métrica nunca pode impedir o
     * Usuário de ver por que está bloqueado, então a falha só vira `Log::warning`.
     */
    public static function bloqueioSemSaldo(string $usuarioId, MotivoBloqueio $motivo, string $origem): void
    {
        $dia = now()->setTimezone('America/Sao_Paulo')->format('Y-m-d');

        try {
            EventLogger::emitOnce(
                "bloqueio:{$usuarioId}:{$origem}:{$motivo->value}:{$dia}",
                self::BLOQUEIO_SEM_SALDO,
                ['motivo' => $motivo->value, 'origem' => $origem],
                $usuarioId,
            );
        } catch (Throwable $e) {
            Log::warning('cobranca.evento_de_produto_nao_gravado', ['evento' => self::BLOQUEIO_SEM_SALDO, 'erro' => $e::class]);
        }
    }

    // ------------------------------------------------------------------ pagamento (taxa de falha)

    /**
     * Traduz um evento do provedor já processado em fato de pagamento: cobrança paga confirma; recusada
     * (ou renovação vencida) recusa. Pix que apenas venceu NÃO é falha de pagamento, é abandono.
     *
     * Roda FORA da transação do pagamento e nunca derruba o processamento: o dinheiro já foi tratado, e
     * perder um ponto de métrica é pior que parar, mas parar o webhook seria pior ainda. A falha vira
     * `Log::warning` (sem `module=cobranca`, para não gerar alerta de pagamento por causa de métrica).
     */
    public static function pagamentoDoEvento(ProviderEvent $evento, string $status, ?string $invoiceId): void
    {
        if ($status !== 'processado') {
            return;
        }

        $tipo = TipoEvento::tryFrom($evento->tipo);
        $confirmado = $tipo === TipoEvento::CobrancaPaga;
        $recusado = $tipo === TipoEvento::CobrancaFalhou || ($tipo === TipoEvento::CobrancaVencida && $evento->assinatura_id_externo !== null);

        if (! $confirmado && ! $recusado) {
            return;
        }

        try {
            $invoice = $invoiceId === null ? null : Invoice::query()->find($invoiceId);
            $assinatura = $evento->assinatura_id_externo === null ? null : Assinatura::query()
                ->where('provedor', $evento->provedor)
                ->where('id_externo', $evento->assinatura_id_externo)
                ->first();
            $usuarioId = $invoice->usuario_id ?? $assinatura->usuario_id ?? null;

            if ($usuarioId === null) {
                return;
            }

            $nome = $confirmado ? self::PAGAMENTO_CONFIRMADO : self::PAGAMENTO_RECUSADO;
            $cobranca = $evento->cobranca_id_externo ?? $evento->id;

            EventLogger::emitOnce(
                "cobranca:{$evento->provedor}:{$cobranca}:".($confirmado ? 'paga' : 'recusada'),
                $nome,
                [
                    'tipo' => self::tipoDoPagamento($invoice),
                    'meio' => $invoice->meio ?? $assinatura->meio_pagamento ?? null,
                    'invoice_id' => $invoice?->id,
                ],
                $usuarioId,
            );
        } catch (Throwable $e) {
            Log::warning('cobranca.evento_de_produto_nao_gravado', ['evento' => $confirmado ? self::PAGAMENTO_CONFIRMADO : self::PAGAMENTO_RECUSADO, 'provider_event_id' => $evento->id, 'erro' => $e::class]);
        }
    }

    // ------------------------------------------------------------------ derivados

    /** @return array<string, mixed> */
    private static function daInvoice(Invoice $invoice): array
    {
        return [
            'invoice_id' => $invoice->id,
            'tipo' => $invoice->tipo,
            'plano_id' => $invoice->plano_id,
            'pacote_credito_id' => $invoice->pacote_credito_id,
            'ciclo' => $invoice->ciclo,
            'meio' => $invoice->meio,
            'valor_centavos' => $invoice->valor_centavos,
            'renovacao' => (bool) $invoice->renovacao,
        ];
    }

    /** `assinatura`, `credito` ou `renovacao` (cobrança recorrente de um plano que já existe). */
    private static function tipoDoPagamento(?Invoice $invoice): string
    {
        if ($invoice === null || $invoice->renovacao) {
            return 'renovacao';
        }

        return $invoice->tipo;
    }
}

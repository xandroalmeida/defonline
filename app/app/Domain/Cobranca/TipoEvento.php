<?php

declare(strict_types=1);

namespace App\Domain\Cobranca;

/** ADR-010 §1 — eventos canônicos. `Outro` guarda o que o provedor mandou e ainda não tratamos. */
enum TipoEvento: string
{
    case CheckoutConcluido = 'checkout.concluido';
    case CheckoutExpirado = 'checkout.expirado';
    case CheckoutCancelado = 'checkout.cancelado';
    case CobrancaCriada = 'cobranca.criada';
    case CobrancaPaga = 'cobranca.paga';
    case CobrancaVencida = 'cobranca.vencida';
    case CobrancaFalhou = 'cobranca.falhou';
    case CobrancaEstornada = 'cobranca.estornada';
    case CobrancaContestada = 'cobranca.contestada';
    case CobrancaCancelada = 'cobranca.cancelada';
    /** STORY-088: a contestação (chargeback) foi revertida a favor do DEFOnline. */
    case ContestacaoRevertida = 'cobranca.contestacao_revertida';
    /** STORY-088: cobrança removida no provedor e restaurada. */
    case CobrancaRestaurada = 'cobranca.restaurada';
    /** STORY-088: estorno parcial; a cobrança segue paga. */
    case EstornoParcial = 'cobranca.estorno_parcial';
    /** STORY-088: o estorno pedido foi negado (Pix recusado no painel). */
    case EstornoNegado = 'cobranca.estorno_negado';
    case Outro = 'outro';
}

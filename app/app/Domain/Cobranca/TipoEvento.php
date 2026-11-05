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
    case Outro = 'outro';
}

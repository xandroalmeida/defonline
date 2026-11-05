<?php

declare(strict_types=1);

namespace App\Domain\Cobranca;

/** STORY-073 — situação de um lote de créditos (arquitetura §4.1, CreditLot). */
enum StatusLote: string
{
    case Ativo = 'ativo';
    case Esgotado = 'esgotado';
    case Expirado = 'expirado';
    /** STORY-085 — a compra foi devolvida: o saldo restante foi retirado. */
    case Estornado = 'estornado';
}

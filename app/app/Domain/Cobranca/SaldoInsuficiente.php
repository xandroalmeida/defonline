<?php

declare(strict_types=1);

namespace App\Domain\Cobranca;

use RuntimeException;

/** STORY-073 CA-3 — não há cota nem crédito válido. Carrega o motivo para a tela explicar. */
final class SaldoInsuficiente extends RuntimeException
{
    public function __construct(public readonly Bloqueio $bloqueio)
    {
        parent::__construct('Sem saldo para a análise: '.$bloqueio->motivo->value);
    }
}

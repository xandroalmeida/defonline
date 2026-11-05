<?php

declare(strict_types=1);

namespace App\Domain\Cobranca;

use RuntimeException;

/** STORY-074 — a ativação foi recusada; o motivo diz a tela o que explicar. */
final class TrialIndisponivel extends RuntimeException
{
    public function __construct(public readonly MotivoInelegivelTrial $motivo)
    {
        parent::__construct('Trial indisponível: '.$motivo->value);
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Cobranca;

use RuntimeException;

/** STORY-073 CA-6 — outra conclusão do mesmo quiz chegou primeiro e já foi cobrada. */
final class AnaliseJaConcluida extends RuntimeException
{
    public function __construct(public readonly ?string $diagnosticoId)
    {
        parent::__construct('A análise deste quiz já foi concluída.');
    }
}

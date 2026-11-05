<?php

declare(strict_types=1);

namespace App\Domain\Cobranca;

use RuntimeException;

/** STORY-073 — o Usuário já tem uma assinatura vigente; quem conceder outra deve encerrar ou trocar a atual antes. */
final class AssinaturaVigenteJaExiste extends RuntimeException
{
    public function __construct(public readonly string $usuarioId)
    {
        parent::__construct('O Usuário já tem uma assinatura vigente.');
    }
}

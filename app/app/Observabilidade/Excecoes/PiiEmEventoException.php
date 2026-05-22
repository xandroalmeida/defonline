<?php

declare(strict_types=1);

namespace App\Observabilidade\Excecoes;

use RuntimeException;

/**
 * Lançada quando `EventLogger::emit()` recebe propriedade proibida (ADR-004 §2.4).
 *
 * Defesa em camadas: o lint estático (Larastan custom — IDR pós-007) detecta o uso
 * em compile-time; esta exceção é a defesa em write-time. Trigger Postgres é a
 * terceira camada opcional.
 */
final class PiiEmEventoException extends RuntimeException
{
    public static function paraChave(string $chave): self
    {
        return new self(
            "Propriedade '{$chave}' não pode aparecer em evento de produto. ".
            'evento_produto é append-only e proíbe PII na origem (ADR-004 §2.4).'
        );
    }
}

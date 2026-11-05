<?php

declare(strict_types=1);

namespace App\Services\Cobranca;

use RuntimeException;

/** A compra não pode começar. `motivo` é estável para a tela escolher a mensagem. */
final class CompraNaoPermitida extends RuntimeException
{
    public const EMAIL_NAO_CONFIRMADO = 'email_nao_confirmado';

    public const ADMIN_PERSONIFICANDO = 'admin_personificando';

    public const PACOTE_INDISPONIVEL = 'pacote_indisponivel';

    private function __construct(public readonly string $motivo)
    {
        parent::__construct("Compra não permitida: {$motivo}.");
    }

    public static function emailNaoConfirmado(): self
    {
        return new self(self::EMAIL_NAO_CONFIRMADO);
    }

    public static function adminPersonificando(): self
    {
        return new self(self::ADMIN_PERSONIFICANDO);
    }

    public static function pacoteIndisponivel(): self
    {
        return new self(self::PACOTE_INDISPONIVEL);
    }
}

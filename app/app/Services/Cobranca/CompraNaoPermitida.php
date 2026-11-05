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

    public const PLANO_INDISPONIVEL = 'plano_indisponivel';

    public const JA_ASSINANTE = 'ja_assinante';

    public const PIX_SO_NO_ANUAL = 'pix_so_no_anual';

    public const SEM_ASSINATURA_PIX = 'sem_assinatura_pix';

    public const FORA_DA_JANELA_DE_RENOVACAO = 'fora_da_janela_de_renovacao';

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

    public static function planoIndisponivel(): self
    {
        return new self(self::PLANO_INDISPONIVEL);
    }

    public static function jaAssinante(): self
    {
        return new self(self::JA_ASSINANTE);
    }

    public static function pixSoNoAnual(): self
    {
        return new self(self::PIX_SO_NO_ANUAL);
    }

    public static function semAssinaturaPix(): self
    {
        return new self(self::SEM_ASSINATURA_PIX);
    }

    public static function foraDaJanelaDeRenovacao(): self
    {
        return new self(self::FORA_DA_JANELA_DE_RENOVACAO);
    }
}

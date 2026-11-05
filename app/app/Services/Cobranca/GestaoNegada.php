<?php

declare(strict_types=1);

namespace App\Services\Cobranca;

use RuntimeException;

/** STORY-081 — a ação em Meu plano não pode ser feita. `motivo` é estável para a tela escolher a mensagem. */
final class GestaoNegada extends RuntimeException
{
    public const ADMIN_PERSONIFICANDO = 'admin_personificando';

    public const SEM_ASSINATURA = 'sem_assinatura';

    /** Pix anual e afins: não há recorrência no provedor para cancelar nem cartão para trocar. */
    public const NAO_RENOVA_SOZINHA = 'nao_renova_sozinha';

    public const JA_ENCERRADA = 'ja_encerrada';

    private function __construct(public readonly string $motivo)
    {
        parent::__construct("Ação negada: {$motivo}.");
    }

    public static function adminPersonificando(): self
    {
        return new self(self::ADMIN_PERSONIFICANDO);
    }

    public static function semAssinatura(): self
    {
        return new self(self::SEM_ASSINATURA);
    }

    public static function naoRenovaSozinha(): self
    {
        return new self(self::NAO_RENOVA_SOZINHA);
    }

    public static function jaEncerrada(): self
    {
        return new self(self::JA_ENCERRADA);
    }
}

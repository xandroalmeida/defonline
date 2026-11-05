<?php

declare(strict_types=1);

namespace App\Domain\Cobranca;

/**
 * STORY-073 — situação da assinatura (inclui o trial, que é uma assinatura marcada com `trial`).
 * `Cancelada` ainda dá acesso até o fim do ciclo pago (espec §2.4).
 */
enum StatusAssinatura: string
{
    case Ativa = 'ativa';
    case EmAtraso = 'em_atraso';
    case Cancelada = 'cancelada';
    case Expirada = 'expirada';

    public function rotulo(): string
    {
        return match ($this) {
            self::Ativa => 'Ativa',
            self::EmAtraso => 'Em atraso',
            self::Cancelada => 'Cancelada',
            self::Expirada => 'Encerrada',
        };
    }

    /** Estados em que a assinatura ainda ocupa o lugar único do Usuário (índice parcial da tabela). */
    public function vigente(): bool
    {
        return $this === self::Ativa || $this === self::EmAtraso;
    }
}

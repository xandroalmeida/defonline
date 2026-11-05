<?php

declare(strict_types=1);

namespace App\Domain\Cobranca;

/**
 * ADR-010 §1 — estados canônicos de uma cobrança. `vencida` cobre o Pix cujo prazo acabou: o banco
 * ainda pode confirmá-lo depois (vale a confirmação do banco), por isso não é estado final.
 */
enum EstadoCobranca: string
{
    case Pendente = 'pendente';
    case Paga = 'paga';
    case Vencida = 'vencida';
    case Falhou = 'falhou';
    case Estornada = 'estornada';
    case EstornadaParcial = 'estornada_parcial';
    case Contestada = 'contestada';
    case Cancelada = 'cancelada';

    /** Estados dos quais não se volta: nenhum evento tardio os reabre nem os transforma em `paga`. */
    public function final(): bool
    {
        return match ($this) {
            self::Estornada, self::Contestada, self::Cancelada => true,
            default => false,
        };
    }

    public function podeIrPara(self $novo): bool
    {
        if ($this === $novo || $this->final()) {
            return false;
        }

        return match ($this) {
            self::Paga => in_array($novo, [self::Estornada, self::EstornadaParcial, self::Contestada], true),
            self::EstornadaParcial => in_array($novo, [self::Estornada, self::Contestada], true),
            default => true,
        };
    }
}

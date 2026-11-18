<?php

declare(strict_types=1);

namespace App\Domain\Captacao;

/**
 * STORY-103 — etapas do quadro de demandas de captação, na ordem das colunas. A equipe pode mover
 * para qualquer etapa (inclusive voltar); o prazo de retorno só cobra das etapas em aberto.
 */
enum StatusDemanda: string
{
    case Nova = 'nova';
    case EmAnalise = 'em_analise';
    case Respondida = 'respondida';
    case Encerrada = 'encerrada';

    public function rotulo(): string
    {
        return match ($this) {
            self::Nova => 'Nova',
            self::EmAnalise => 'Em análise',
            self::Respondida => 'Respondida',
            self::Encerrada => 'Encerrada',
        };
    }

    /** Ainda deve retorno ao cliente: é onde o prazo de 5 dias úteis vale. */
    public function emAberto(): bool
    {
        return $this === self::Nova || $this === self::EmAnalise;
    }
}

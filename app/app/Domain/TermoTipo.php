<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Tipos canônicos de termo aceitos no cadastro (STORY-012 §3.3).
 *
 * `termo_adesao` e `lgpd` são obrigatórios (bloqueiam cadastro); `marketing`
 * é opt-in (default desmarcado) — mas mesmo recusado é registrado na tabela
 * `term_acceptances` (aceito=false) para evidenciar a oferta explícita.
 */
enum TermoTipo: string
{
    case TermoAdesao = 'termo_adesao';
    case Lgpd = 'lgpd';
    case Marketing = 'marketing';

    public function obrigatorio(): bool
    {
        return match ($this) {
            self::TermoAdesao, self::Lgpd => true,
            self::Marketing => false,
        };
    }
}

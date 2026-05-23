<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Como os dados da Empresa Analisada foram obtidos (espec V2 §3.3 / NRF §3.1).
 *
 * `manual` para preenchimento digitado pelo Usuário (STORY-014). `rfb` para
 * enriquecimento via API da Receita Federal (STORY-015) — quando essa estória
 * entregar, vai gravar `enriquecido_at` junto.
 */
enum FonteEnriquecimento: string
{
    case Manual = 'manual';
    case Rfb = 'rfb';

    public function rotulo(): string
    {
        return match ($this) {
            self::Manual => 'Preenchimento manual',
            self::Rfb => 'Receita Federal',
        };
    }
}

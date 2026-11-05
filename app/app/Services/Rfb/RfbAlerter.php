<?php

declare(strict_types=1);

namespace App\Services\Rfb;

use App\Observabilidade\AlertaDaEquipe;

/**
 * Disparador de alerta operacional do monitor RFB (STORY-015 CA-5; ADR-004).
 *
 * Desde a STORY-086 o canal é o {@see AlertaDaEquipe} (um só para todo o produto); esta classe
 * só mantém o nome e o módulo `rfb` nos logs, para o monitor e os dashboards existentes não mudarem.
 */
class RfbAlerter extends AlertaDaEquipe
{
    protected function prefixo(): string
    {
        return 'rfb';
    }
}

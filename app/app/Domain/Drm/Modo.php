<?php

declare(strict_types=1);

namespace App\Domain\Drm;

/** Modo de um grupo: só o total, ou item a item (Anexo K §K.5). */
enum Modo: string
{
    case Agregado = 'agregado';
    case Detalhado = 'detalhado';
}

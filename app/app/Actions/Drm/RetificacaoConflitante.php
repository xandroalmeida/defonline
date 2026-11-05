<?php

declare(strict_types=1);

namespace App\Actions\Drm;

use RuntimeException;

/**
 * Concorrência otimista (ADR-007): o rascunho foi aberto sobre a versão N, mas a vigente já é outra.
 * Não há "forçar": o usuário reabre a versão atual para não sobrescrever a correção alheia.
 */
final class RetificacaoConflitante extends RuntimeException {}

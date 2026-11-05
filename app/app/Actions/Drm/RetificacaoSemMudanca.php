<?php

declare(strict_types=1);

namespace App\Actions\Drm;

use RuntimeException;

/**
 * O `payload_hash` novo é igual ao da vigente (ADR-007, ratificado pelo PO): retificar sem mudar valor
 * não é correção, e contá-la mentiria no "corrigido N×" que a tela exibe.
 */
final class RetificacaoSemMudanca extends RuntimeException {}

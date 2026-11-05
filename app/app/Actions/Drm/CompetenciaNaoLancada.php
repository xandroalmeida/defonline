<?php

declare(strict_types=1);

namespace App\Actions\Drm;

use RuntimeException;

/** Não há lançamento vigente para retificar (nunca foi lançada, ou a empresa/competência não é do usuário). */
final class CompetenciaNaoLancada extends RuntimeException {}

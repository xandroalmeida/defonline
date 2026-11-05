<?php

declare(strict_types=1);

namespace App\Actions\Drm;

use RuntimeException;

/** Regra B1: já existe lançamento vigente para a empresa e a competência. */
final class CompetenciaJaLancada extends RuntimeException {}

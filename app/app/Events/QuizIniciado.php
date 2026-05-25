<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\EmpresaAnalisada;
use App\Models\Usuario;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Disparado na primeira transição de bloco do quiz (bloco 1 → 2) — STORY-027 CA-13.
 *
 * Carrega `motor_version` e `matrix_version` para correlação analítica (epic.md;
 * IDR-010 §Consequências). O listener detalhado é responsabilidade da STORY-035,
 * que consome este evento para popular `evento_produto.quiz_iniciado`.
 *
 * Estrutura mínima sustentada pelo STORY-027: empresa + usuário + versões do
 * motor/matriz vigentes no momento do disparo (lidas de `config('motor.*')`).
 */
final class QuizIniciado
{
    use Dispatchable;

    public function __construct(
        public readonly EmpresaAnalisada $empresa,
        public readonly Usuario $usuario,
        public readonly string $motorVersion,
        public readonly string $matrixVersion,
    ) {}
}

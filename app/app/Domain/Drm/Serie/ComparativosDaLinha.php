<?php

declare(strict_types=1);

namespace App\Domain\Drm\Serie;

/** Os quatro comparativos de `estrutura-drm` §5 para uma linha da demonstração. */
final readonly class ComparativosDaLinha
{
    public function __construct(
        public Comparativo $vsAnterior,
        public Comparativo $vsMedia,
        public Comparativo $acumuladoAno,
        public Comparativo $participacaoMedia,
    ) {}

    public static function todosIndisponiveis(string $motivo): self
    {
        return new self(
            Comparativo::indisponivel($motivo),
            Comparativo::indisponivel($motivo),
            Comparativo::indisponivel($motivo),
            Comparativo::indisponivel($motivo),
        );
    }
}

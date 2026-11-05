<?php

declare(strict_types=1);

namespace App\Support\Drm;

/**
 * Uma linha da demonstração já pronta para a tela: textos formatados, sem decisão pendente.
 *
 * `nominal`/`percentual` são `null` quando indisponíveis; nesse caso `nominalAusente` /
 * `percentualAusente` trazem o texto acessível que acompanha o traço (DDR-005 item 1).
 */
final readonly class LinhaVisao
{
    public function __construct(
        public string $codigo,
        public int $nivel,
        public string $tipo,
        public string $rotulo,
        public string $prefixo,
        public ?string $nominal,
        public ?string $nominalSemMoeda,
        public ?string $percentual,
        public ?string $nominalAusente,
        public ?string $percentualAusente,
        public bool $negativo,
        public bool $final,
        public bool $prejuizo,
    ) {}

    public function ehTotal(): bool
    {
        return $this->tipo === 'total';
    }
}

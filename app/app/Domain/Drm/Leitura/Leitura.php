<?php

declare(strict_types=1);

namespace App\Domain\Drm\Leitura;

/**
 * A interpretação de um mês: faróis por linha e a leitura em prosa (STORY-054).
 *
 * **Derivada, nunca gravada (ADR-007, CA-8).** Nasce a cada exibição, do snapshot do mês, dos
 * comparativos e do pacote de conteúdo vigente; nada daqui vai para `drm_lancamentos`.
 *
 * @phpstan-type Farois array<string, string>
 */
final readonly class Leitura
{
    /**
     * @param  array<string, string>  $farois  código da linha => `verde` | `amarelo` | `vermelho`; só linhas com faixa calibrada
     * @param  list<string>  $frases  a leitura, uma frase por item, na ordem em que se lê
     */
    public function __construct(
        public array $farois,
        public array $frases,
    ) {}
}

<?php

declare(strict_types=1);

namespace App\Domain\Motor;

/**
 * STORY-068 / PDR-016 — de onde o motor lê os textos da matriz de recomendações.
 * O domínio segue sem banco: quem implementa com a tabela está fora de `app/Domain`.
 */
interface FonteRecomendacoes
{
    /** Texto do trio, ou null se ele não existir. */
    public function texto(string $setor, string $indicador, string $farol): ?string;

    /** Versão vigente da matriz do segmento, no formato `mes-aaaa`. */
    public function versao(string $setor): string;
}

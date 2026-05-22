<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Snapshot do termo vigente (STORY-012 CA-3/CA-4).
 *
 * `conteudo_hash` é SHA-256 do HTML renderizado da view placeholder. Quando o
 * jurídico voltar com a redação final, basta trocar a view + a versão; o hash
 * muda automaticamente, e os registros antigos preservam o hash do que foi
 * efetivamente aceito por cada Usuário.
 */
final class TermoVigente
{
    public function __construct(
        public readonly TermoTipo $tipo,
        public readonly string $versao,
        public readonly string $conteudoHash,
        public readonly string $rota,
    ) {}
}

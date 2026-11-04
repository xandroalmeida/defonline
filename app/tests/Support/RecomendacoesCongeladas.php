<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\Motor\FonteRecomendacoes;

/**
 * Fonte dos testes de domínio, que não usam banco: a matriz dez-2025 de Indústria
 * congelada em `tests/Fixtures`. O `RecomendacoesTest` garante que ela é igual ao
 * que a migration grava, para os golden hashes continuarem valendo para produção.
 */
final class RecomendacoesCongeladas implements FonteRecomendacoes
{
    /** @return array<string, mixed> */
    public static function matriz(): array
    {
        return require __DIR__.'/../Fixtures/matriz-dez-2025-industria.php';
    }

    public function texto(string $setor, string $indicador, string $farol): ?string
    {
        $texto = $setor === 'industria' ? (self::matriz()[$indicador][$farol] ?? null) : null;

        return is_string($texto) ? $texto : null;
    }

    public function versao(string $setor): string
    {
        return (string) config('motor.matrix_version');
    }
}

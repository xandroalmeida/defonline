<?php

declare(strict_types=1);

namespace App\Support\Relatorio;

use RuntimeException;

/**
 * Lê `resources/css/tokens.css` e resolve `var(--x)` em CSS que não roda num
 * navegador — o PDF do diagnóstico (dompdf não implementa custom properties).
 *
 * Mantém o `tokens.css` como fonte única da paleta (IDR-008 / DesignTokensTest):
 * o `pdf.css` escreve `var(--color-primary)` como qualquer outro CSS do app, e
 * a troca pelo valor acontece aqui, em tempo de renderização. Sem terceira
 * cópia de hex — foi esse o motivo de a ADR-008 desqualificar o dompdf.
 *
 * Também resolve `color-mix(in srgb, var(--a) N%, var(--b))`, o tingimento do
 * pill de farol (DDR-006), porque o dompdf não o entende.
 */
final class TokensDeDesign
{
    /** @param array<string, string> $tokens nome sem `--` => valor */
    private function __construct(private readonly array $tokens) {}

    public static function doArquivo(?string $caminho = null): self
    {
        $caminho ??= resource_path('css/tokens.css');
        $css = @file_get_contents($caminho);
        if ($css === false) {
            throw new RuntimeException("tokens.css não encontrado em {$caminho}.");
        }

        // Comentários fora — eles citam hex e nomes de token em prosa.
        $css = (string) preg_replace('#/\*.*?\*/#s', '', $css);

        preg_match_all('/--([\w-]+)\s*:\s*([^;]+);/', $css, $m, PREG_SET_ORDER);
        $tokens = [];
        foreach ($m as [, $nome, $valor]) {
            $tokens[$nome] = trim((string) preg_replace('/\s+/', ' ', $valor));
        }

        return new self($tokens);
    }

    /** Valor de um token, já resolvido (ex.: `cor('color-primary')`). */
    public function valor(string $nome): string
    {
        if (! isset($this->tokens[$nome])) {
            throw new RuntimeException("Token --{$nome} não existe em tokens.css.");
        }

        return $this->resolver($this->tokens[$nome]);
    }

    public function resolver(string $css): string
    {
        // Até 5 passes: um token pode apontar para outro.
        for ($i = 0; $i < 5 && str_contains($css, 'var(--'); $i++) {
            $css = (string) preg_replace_callback(
                '/var\(--([\w-]+)\)/',
                fn (array $m): string => $this->tokens[$m[1]]
                    ?? throw new RuntimeException("Token --{$m[1]} não existe em tokens.css."),
                $css,
            );
        }

        return (string) preg_replace_callback(
            '/color-mix\(\s*in srgb\s*,\s*(#[0-9a-fA-F]{6})\s+(\d+(?:\.\d+)?)%\s*,\s*(#[0-9a-fA-F]{6})\s*\)/',
            fn (array $m): string => self::misturar($m[1], (float) $m[2] / 100, $m[3]),
            $css,
        );
    }

    /** `color-mix(in srgb, a p, b)` — interpolação linear canal a canal. */
    private static function misturar(string $a, float $p, string $b): string
    {
        $ca = sscanf(ltrim($a, '#'), '%2x%2x%2x');
        $cb = sscanf(ltrim($b, '#'), '%2x%2x%2x');
        $out = '#';
        for ($i = 0; $i < 3; $i++) {
            $out .= sprintf('%02x', (int) round($ca[$i] * $p + $cb[$i] * (1 - $p)));
        }

        return $out;
    }
}

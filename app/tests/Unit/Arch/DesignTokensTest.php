<?php

declare(strict_types=1);

/**
 * STORY-019 CA-6 + IDR-008 — gate arquitetural do design system.
 *
 * Nenhum hex literal de cor (paleta Stripe-like do design-system v1 ou drift
 * conhecido da paleta antiga da STORY-016) pode aparecer em arquivos de view
 * (Blade) ou em CSS fora de `tokens.css`. Mudar uma cor do design system deve
 * propagar em UM arquivo, não em busca-e-substitui.
 *
 * Exclusões documentadas:
 *   - `resources/css/tokens.css` é a única fonte autorizada de hex.
 *   - `resources/views/layouts/legal.blade.php` é shell separado (LEGAL) com seu
 *     próprio escopo de design — não é alvo da STORY-019.
 *
 * O regex cobre:
 *   - Hex literais da paleta do design-system v1 (`#0A2540`, `#635BFF`, ...).
 *   - Hex literais do drift identificado nas STORY-011/STORY-016
 *     (`#2563eb`, `#1f2937`, `#f9fafb`, ...) — CA-8 cobra explicitamente.
 */
$tokensProibidos = [
    // Design-system v1 (devem viver SÓ em tokens.css).
    '#0A2540', '#425466', '#635BFF', '#F6F9FC', '#FFFFFF', '#E3E8EE',
    '#E11D48', '#0E9F6E', '#D97706', '#DC2626', '#94A3B8',
    '#4F46E5', '#4338CA',
    // Drift conhecido (paleta antiga do scaffold + STORY-016).
    '#2563eb', '#1d4ed8', '#1f2937', '#111827', '#f9fafb', '#f3f4f6',
    '#374151', '#6b7280', '#9ca3af', '#4b5563',
    '#dbeafe', '#eff6ff', '#eef2ff', '#3730a3',
    '#d1fae5', '#065f46', '#dcfce7', '#166534',
    '#fee2e2', '#991b1b', '#fef3c7', '#92400e', '#f59e0b',
    '#b91c1c', '#16a34a', '#fcd34d', '#6ee7b7',
    '#e5e7eb', '#d1d5db',
];

// Normaliza para comparação case-insensitive sem o `#`.
$alvos = array_map(static fn (string $h) => strtolower(ltrim($h, '#')), $tokensProibidos);

$exclusoes = [
    'resources/css/tokens.css',
    'resources/views/layouts/legal.blade.php',
    // Emails transacionais — inline styles obrigatórios (clientes de email
    // ignoram CSS vars). Os hex viram cópia manual da paleta v1; mudar tokens
    // implica atualizar aqui à mão. Aceito por isolamento e baixa rotatividade.
    'resources/views/mail/email-confirmacao.blade.php',
];

function _designTokensProjectRoot(): string
{
    // tests/Unit/Arch/DesignTokensTest.php → tests/Unit/Arch → tests → project root.
    return dirname(__DIR__, 3);
}

function _scanDir(string $dir, string $extPattern, array $exclusoes, array $alvos): array
{
    $base = _designTokensProjectRoot();
    $offending = [];

    if (! is_dir($dir)) {
        return $offending;
    }

    /** @var SplFileInfo $file */
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $file) {
        if (! $file->isFile() || ! preg_match($extPattern, $file->getFilename())) {
            continue;
        }

        $relative = str_replace($base.'/', '', $file->getPathname());
        if (in_array($relative, $exclusoes, true)) {
            continue;
        }

        $conteudo = (string) file_get_contents($file->getPathname());
        if (! preg_match_all('/#([0-9a-fA-F]{6})\b/', $conteudo, $matches)) {
            continue;
        }

        foreach ($matches[1] as $hex) {
            if (in_array(strtolower($hex), $alvos, true)) {
                $offending[] = "{$relative}: #{$hex}";
                break; // Uma ocorrência por arquivo já basta no relatório.
            }
        }
    }

    return $offending;
}

it('nenhum hex literal de cor do design system aparece em views Blade fora de tokens.css (CA-6)', function () use ($exclusoes, $alvos) {
    $offending = _scanDir(
        _designTokensProjectRoot().'/resources/views',
        '/\.blade\.php$/',
        $exclusoes,
        $alvos,
    );

    expect($offending)->toBe(
        [],
        "Hex literal de cor do design system encontrado nas views (mover para tokens.css):\n - ".implode("\n - ", $offending),
    );
});

it('nenhum hex literal de cor do design system aparece em CSS fora de tokens.css (CA-6)', function () use ($exclusoes, $alvos) {
    $offending = _scanDir(
        _designTokensProjectRoot().'/resources/css',
        '/\.css$/',
        $exclusoes,
        $alvos,
    );

    expect($offending)->toBe(
        [],
        "Hex literal de cor do design system encontrado em CSS (mover para tokens.css):\n - ".implode("\n - ", $offending),
    );
});

it('tokens.css contém todos os tokens primários do design-system v1', function () {
    $tokens = (string) file_get_contents(_designTokensProjectRoot().'/resources/css/tokens.css');

    foreach (['--color-primary', '--color-secondary', '--color-tertiary',
        '--color-neutral', '--color-surface', '--color-border',
        '--spacing-md', '--radius-md', '--shadow-sm'] as $token) {
        expect(str_contains($tokens, $token))
            ->toBeTrue("Token {$token} ausente em tokens.css");
    }
});

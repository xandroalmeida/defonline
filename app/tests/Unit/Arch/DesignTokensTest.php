<?php

declare(strict_types=1);

/**
 * STORY-019 CA-6 + IDR-008 — gate arquitetural do design system.
 *
 * STORY-061 — nenhuma cor literal, em QUALQUER notação, pode aparecer em views
 * (Blade) ou em CSS fora de `tokens.css`: hex de 3, 4, 6 e 8 dígitos, `rgb()`,
 * `rgba()`, `hsl()`, `hsla()` (e `hwb/lab/lch/oklab/oklch`). Antes só `#RRGGBB`
 * de uma lista fechada era pego, e um `rgba(99, 91, 255, …)` inline passou. Mudar
 * uma cor do design system deve propagar em UM arquivo, não em busca-e-substitui.
 *
 * Não são cor literal (CA-2): `color-mix(in srgb, var(--token) 10%, …)` (DDR-006),
 * `currentColor`, `transparent`, `inherit`, `var(--token)`. Comentários CSS
 * (blocos de comentário), Blade (`{{-- --}}`) e HTML (`<!-- -->`) são removidos antes da busca:
 * documentar "era #E7F5F0" não é usar a cor.
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
$exclusoes = [
    'resources/css/tokens.css',
    'resources/views/layouts/legal.blade.php',
    // Emails transacionais — inline styles obrigatórios (clientes de email
    // ignoram CSS vars). Os hex viram cópia manual da paleta v1; mudar tokens
    // implica atualizar aqui à mão. Aceito por isolamento e baixa rotatividade.
    'resources/views/mail/email-confirmacao.blade.php',
    'resources/views/mail/link-de-pagamento.blade.php',
    'resources/views/mail/fim-do-trial.blade.php',
    'resources/views/mail/renovacao-proxima.blade.php',
    'resources/views/mail/falha-de-cobranca.blade.php',
    'resources/views/mail/assinatura-encerrada.blade.php',
    'resources/views/mail/renovacao-pix.blade.php',
    'resources/views/mail/fim-do-desconto.blade.php',
    'resources/views/mail/renovacao-diagnostico.blade.php',
    'resources/views/mail/redefinicao-senha.blade.php',
];

function _designTokensProjectRoot(): string
{
    // tests/Unit/Arch/DesignTokensTest.php → tests/Unit/Arch → tests → project root.
    return dirname(__DIR__, 3);
}

/**
 * Cores literais em qualquer notação. O hex exige que o `#` não venha colado em
 * palavra, `&` (entidade `&#39;`), `/` ou `-`, e que não continue em palavra —
 * assim `href="#faq"`, `&#123;` e `#id-x` não são tomados por cor.
 */
const DESIGN_TOKENS_REGEX_COR = '/(?<![\w&\/#-])#(?:[0-9a-fA-F]{8}|[0-9a-fA-F]{6}|[0-9a-fA-F]{3,4})(?![\w-])'
    .'|\b(?:rgba?|hsla?|hwb|lab|lch|oklab|oklch)\s*\([^)]*\)/i';

function _designTokensSemComentarios(string $conteudo): string
{
    // Também remove âncoras (`href="#add"`, `url(#grad)`): parecem hex mas são id.
    return (string) preg_replace(
        ['~/\*.*?\*/~s', '~\{\{--.*?--\}\}~s', '~<!--.*?-->~s', '~href=(["\'])#[\w-]*\1~i', '~url\(\s*#[\w-]*\s*\)~i'],
        '',
        $conteudo,
    );
}

/** Reduz uma cor literal a `rrggbb` (alpha ignorado) quando possível; senão null. */
function _designTokensNormaliza(string $cor): ?string
{
    if (preg_match('/^#([0-9a-f]+)$/i', $cor, $m)) {
        $h = strtolower($m[1]);
        if (strlen($h) <= 4) {
            $h = $h[0].$h[0].$h[1].$h[1].$h[2].$h[2];
        }

        return substr($h, 0, 6);
    }
    if (preg_match('/^rgba?\(\s*(\d+)[\s,]+(\d+)[\s,]+(\d+)/i', $cor, $m)) {
        return sprintf('%02x%02x%02x', $m[1], $m[2], $m[3]);
    }

    return null;
}

/** @return array<string,string> rrggbb => '--token, --outro' declarados em tokens.css */
function _designTokensMapa(): array
{
    $css = _designTokensSemComentarios((string) file_get_contents(_designTokensProjectRoot().'/resources/css/tokens.css'));
    $mapa = [];
    if (preg_match_all('/(--[\w-]+)\s*:\s*(#[0-9a-fA-F]{3,8})\s*;/', $css, $m, PREG_SET_ORDER)) {
        foreach ($m as [, $token, $hex]) {
            $chave = _designTokensNormaliza($hex);
            $mapa[$chave] = isset($mapa[$chave]) ? $mapa[$chave].', '.$token : $token;
        }
    }

    return $mapa;
}

/** @return list<string> mensagens "arquivo: cor — use token" para cada cor literal do conteúdo. */
function _designTokensAchaCores(string $relative, string $conteudo): array
{
    $mapa = _designTokensMapa();
    $achados = [];
    if (preg_match_all(DESIGN_TOKENS_REGEX_COR, _designTokensSemComentarios($conteudo), $m)) {
        foreach (array_unique($m[0]) as $cor) {
            $token = ($n = _designTokensNormaliza($cor)) !== null ? ($mapa[$n] ?? null) : null;
            $achados[] = "{$relative}: {$cor} — ".($token !== null
                ? "use var({$token})"
                : 'sem token equivalente em tokens.css; crie o token (IDR-008) em vez de usar o literal');
        }
    }

    return $achados;
}

function _scanDir(string $dir, string $extPattern, array $exclusoes): array
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

        array_push($offending, ..._designTokensAchaCores($relative, (string) file_get_contents($file->getPathname())));
    }

    return $offending;
}

it('nenhuma cor literal aparece em views Blade fora de tokens.css (CA-6, STORY-061)', function () use ($exclusoes) {
    $offending = _scanDir(_designTokensProjectRoot().'/resources/views', '/\.blade\.php$/', $exclusoes);

    expect($offending)->toBe(
        [],
        "Cor literal encontrada nas views (usar var(--token) de tokens.css):\n - ".implode("\n - ", $offending),
    );
});

it('nenhuma cor literal aparece em CSS fora de tokens.css (CA-6, STORY-061)', function () use ($exclusoes) {
    $offending = _scanDir(_designTokensProjectRoot().'/resources/css', '/\.css$/', $exclusoes);

    expect($offending)->toBe(
        [],
        "Cor literal encontrada em CSS (usar var(--token) de tokens.css):\n - ".implode("\n - ", $offending),
    );
});

it('reprova o markup que passava antes: badge com rgba() inline (STORY-061 CA-4)', function () {
    $badge = '<span class="pill" dusk="diagnostico-mvp-badge"
        style="background-color: rgba(99, 91, 255, 0.10); color: var(--color-tertiary); border: 1px solid rgba(99, 91, 255, 0.3); padding: 4px 10px;">Versão MVP</span>';

    $achados = _designTokensAchaCores('fixture.blade.php', $badge);

    expect($achados)->toHaveCount(2)
        ->and($achados[0])->toContain('fixture.blade.php')
        ->and($achados[0])->toContain('rgba(99, 91, 255, 0.10)')
        ->and($achados[0])->toContain('var(--color-tertiary)'); // CA-6: token equivalente.
});

it('reprova cada notação de cor literal (STORY-061 CA-1)', function (string $trecho) {
    expect(_designTokensAchaCores('f.css', "a { color: {$trecho}; }"))->toHaveCount(1);
})->with([
    'rgb' => 'rgb(10 20 30)',
    'rgba' => 'rgba(10, 20, 30, .5)',
    'hsl' => 'hsl(243, 100%, 67%)',
    'hsla' => 'HSLA(243, 100%, 67%, 0.4)',
    'hex3' => '#abc',
    'hex4' => '#abcd',
    'hex6' => '#a1b2c3',
    'hex8' => '#a1b2c3d4',
    'oklch' => 'oklch(60% 0.2 250)',
]);

it('não acusa composição de token nem palavras-chave nem âncoras (STORY-061 CA-2)', function (string $trecho) {
    expect(_designTokensAchaCores('f.blade.php', $trecho))->toBe([]);
})->with([
    'color-mix' => 'background: color-mix(in srgb, var(--color-farol-verde) 10%, var(--color-surface));',
    'currentColor' => 'border-color: currentColor;',
    'transparent' => 'background: transparent;',
    'inherit' => 'color: inherit;',
    'var' => 'color: var(--color-primary);',
    'âncora' => '<a href="#faq">FAQ</a> <a href="#add">x</a> <a href="#secao-1">y</a>',
    'entidade' => 'it&#39;s &#123;',
    'comentário' => '/* era #E7F5F0 */ {{-- rgba(1,2,3,.4) --}} <!-- #fff -->',
]);

it('tokens.css contém todos os tokens primários do design-system v1', function () {
    $tokens = (string) file_get_contents(_designTokensProjectRoot().'/resources/css/tokens.css');

    foreach (['--color-primary', '--color-secondary', '--color-tertiary',
        '--color-neutral', '--color-surface', '--color-border',
        '--spacing-md', '--radius-md', '--shadow-sm'] as $token) {
        expect(str_contains($tokens, $token))
            ->toBeTrue("Token {$token} ausente em tokens.css");
    }
});

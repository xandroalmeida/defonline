<?php

declare(strict_types=1);

use App\Support\Relatorio\TokensDeDesign;

/**
 * Resolvedor de tokens do PDF — lê `tokens.css` (fonte única da paleta) e troca
 * `var(--x)`/`color-mix(...)` por valores literais para o dompdf.
 */
function tokensDeTeste(): TokensDeDesign
{
    $arquivo = tempnam(sys_get_temp_dir(), 'tokens');
    file_put_contents($arquivo, <<<'CSS'
        /* comentário com --color-fantasma: #000000; não vira token */
        @theme {
            --color-primary: #0A2540;
            --color-surface: #FFFFFF;
            --color-farol-verde: #0E9F6E;
            --color-apelido: var(--color-primary);
            --font-sans: 'Inter', ui-sans-serif,
                system-ui;
        }
        CSS);

    return TokensDeDesign::doArquivo($arquivo);
}

it('troca var(--token) pelo valor declarado', function () {
    expect(tokensDeTeste()->resolver('a { color: var(--color-primary); font-family: var(--font-sans); }'))
        ->toBe("a { color: #0A2540; font-family: 'Inter', ui-sans-serif, system-ui; }");
});

it('resolve token que aponta para outro token', function () {
    expect(tokensDeTeste()->valor('color-apelido'))->toBe('#0A2540');
});

it('calcula color-mix in srgb como o navegador', function () {
    // 10% de #0E9F6E sobre branco: 255 - (255 - c) * 0.10 por canal.
    expect(tokensDeTeste()->resolver('color-mix(in srgb, var(--color-farol-verde) 10%, var(--color-surface))'))
        ->toBe('#e7f5f1');
});

it('ignora o que está em comentário', function () {
    expect(fn () => tokensDeTeste()->valor('color-fantasma'))->toThrow(RuntimeException::class);
});

it('falha alto em token inexistente em vez de gerar CSS inválido', function () {
    expect(fn () => tokensDeTeste()->resolver('color: var(--nao-existe);'))->toThrow(RuntimeException::class);
});

it('lê o tokens.css real do projeto', function () {
    expect(TokensDeDesign::doArquivo(dirname(__DIR__, 3).'/resources/css/tokens.css')->valor('color-tertiary'))->toMatch('/^#[0-9A-Fa-f]{6}$/');
});

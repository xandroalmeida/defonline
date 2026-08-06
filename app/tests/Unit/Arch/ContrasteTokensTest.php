<?php

declare(strict_types=1);

/*
|-------------------------------------------------------------------------
| STORY-059 CA-3, CA-8, CA-9 e CA-10 — os pisos de contraste viram teste.
|
| Um teste que compara strings ("o token mudou para #7C8798") só prova que
| alguém digitou o hex que este arquivo esperava. Aqui a razão de contraste é
| **calculada** a partir de `tokens.css` pela fórmula da WCAG 2.1, de modo que
| qualquer valor futuro — de qualquer um dos tokens envolvidos, inclusive os de
| fundo — é reprovado se derrubar o piso.
|
| Os pisos e as combinações vêm dos DDR-006 e DDR-007 e da medição registrada
| em `design/system/tokens.md` §Dívidas de acessibilidade.
|-------------------------------------------------------------------------
*/

/** @return array{0: float, 1: float, 2: float} Componentes 0–255. */
function _rgb(string $hex): array
{
    $hex = ltrim($hex, '#');

    return [
        (float) hexdec(substr($hex, 0, 2)),
        (float) hexdec(substr($hex, 2, 2)),
        (float) hexdec(substr($hex, 4, 2)),
    ];
}

/** Luminância relativa — WCAG 2.1 §Relative luminance. */
function _luminancia(string $hex): float
{
    $canal = static function (float $c): float {
        $c /= 255;

        return $c <= 0.04045 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
    };

    [$r, $g, $b] = _rgb($hex);

    return 0.2126 * $canal($r) + 0.7152 * $canal($g) + 0.0722 * $canal($b);
}

function _contraste(string $a, string $b): float
{
    $la = _luminancia($a);
    $lb = _luminancia($b);

    return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
}

/** Composição alpha de `$fg` sobre `$bg` — reproduz o `/10` do Tailwind. */
function _sobrepor(string $fg, float $alpha, string $bg): string
{
    $f = _rgb($fg);
    $b = _rgb($bg);
    $out = '#';
    for ($i = 0; $i < 3; $i++) {
        $out .= str_pad(dechex((int) round($f[$i] * $alpha + $b[$i] * (1 - $alpha))), 2, '0', STR_PAD_LEFT);
    }

    return strtoupper($out);
}

function _token(string $nome): string
{
    $css = (string) file_get_contents(dirname(__DIR__, 3).'/resources/css/tokens.css');

    expect(preg_match('/'.preg_quote($nome, '/').':\s*(#[0-9A-Fa-f]{6})\s*;/', $css, $m))
        ->toBe(1, "Token {$nome} não encontrado (ou não é hex de 6 dígitos) em tokens.css");

    return strtoupper($m[1]);
}

dataset('faroes', [
    // [farol, opacidade do fundo tal como está em farol.blade.php]
    'verde' => ['verde', 0.10],
    'amarelo' => ['amarelo', 0.12],
    'vermelho' => ['vermelho', 0.10],
    'cinza' => ['cinza', 0.15],
]);

it('CA-3 — a tinta do farol atinge 4,5:1 sobre o fundo opaco do pill', function (string $farol, float $alpha) {
    $viva = _token("--color-farol-{$farol}");
    $tinta = _token("--color-farol-{$farol}-texto");

    // O fundo do pill é `color-mix(cor viva N%, --color-surface)` — opaco por
    // decisão do DDR-006. Uma única composição a verificar, e ela vale em
    // qualquer superfície, que é justamente o ponto: enquanto o fundo era
    // translúcido, o mesmo pill media 4,76:1 na tabela e 4,2:1 dentro do card
    // do Resumo Executivo, cujo cabeçalho já é tingido da mesma cor.
    $fundo = _sobrepor($viva, $alpha, _token('--color-surface'));
    $razao = _contraste($tinta, $fundo);

    expect($razao)->toBeGreaterThanOrEqual(
        4.5,
        "Farol {$farol}: tinta {$tinta} sobre {$fundo} mede ".
        number_format($razao, 2).':1 — o piso AA para texto normal é 4,5:1.',
    );
})->with('faroes');

it('CA-3 — o fundo do pill continua opaco no CSS (senão a conta acima não vale)', function () {
    $css = (string) file_get_contents(dirname(__DIR__, 3).'/resources/css/app.css');

    foreach (['verde', 'amarelo', 'vermelho', 'nenhum'] as $cor) {
        expect($css)->toMatch(
            '/\.farol--'.$cor.'\s*\{[^}]*background:\s*color-mix\(in srgb,[^;]*var\(--color-surface\)\);/s',
            "O fundo do farol {$cor} deixou de ser opaco — o contraste volta a depender do que estiver atrás.",
        );
    }
});

it('CA-3 (ícone) — a mesma tinta cobre o piso de 3:1 da WCAG 1.4.11 com folga', function (string $farol, float $alpha) {
    // O ícone herda currentColor, então passa junto com o texto. O teste existe
    // para que uma futura separação de ícone e rótulo não derrube o ícone
    // abaixo do piso sem ninguém perceber.
    $fundo = _sobrepor(_token("--color-farol-{$farol}"), $alpha, _token('--color-surface'));

    expect(_contraste(_token("--color-farol-{$farol}-texto"), $fundo))->toBeGreaterThanOrEqual(3.0);
})->with('faroes');

it('CA-3 — a cor viva do farol continua reprovando como texto, e é por isso que a tinta existe', function () {
    // Se um dia a cor viva passar em 4,5:1, o par de tokens perdeu a razão de
    // ser e o DDR-006 deve ser revisitado — não silenciosamente mantido.
    $fundo = _sobrepor(_token('--color-farol-amarelo'), 0.12, _token('--color-surface'));

    expect(_contraste(_token('--color-farol-amarelo'), $fundo))->toBeLessThan(4.5);
});

it('CA-10 — a borda de campo atinge os 3:1 que a WCAG 1.4.11 pede (DDR-007)', function () {
    foreach (['--color-surface', '--color-neutral'] as $base) {
        $razao = _contraste(_token('--color-input'), _token($base));

        expect($razao)->toBeGreaterThanOrEqual(
            3.0,
            'Borda de campo sobre '.$base.' mede '.number_format($razao, 2).':1. '.
            'A borda é o que identifica um campo como campo — piso de 3:1, WCAG 2.1 §1.4.11.',
        );
    }
});

it('CA-10 — `border` e `input` deixaram de ser o mesmo valor, e é deliberado', function () {
    // Eram idênticos do DS v1 até a STORY-059. O DDR-007 exerceu a distinção:
    // `border` separa, `input` delimita componente interativo.
    expect(_token('--color-input'))->not->toBe(_token('--color-border'));
});

it('CA-3 — o badge de versão usa um token que passa em 4,5:1 sobre o próprio fundo tingido', function () {
    $fundo = _sobrepor(_token('--color-tertiary'), 0.10, _token('--color-surface'));

    expect(_contraste(_token('--color-tertiary-hover'), $fundo))->toBeGreaterThanOrEqual(4.5)
        // E o motivo de não ter dado certo com o tertiary puro, que era o que estava lá.
        ->and(_contraste(_token('--color-tertiary'), $fundo))->toBeLessThan(4.5);
});

it('CA-9 — o alvo de toque do <x-help> tem 44×44px sem inflar o círculo visível', function () {
    $css = (string) file_get_contents(dirname(__DIR__, 3).'/resources/css/app.css');

    // O círculo continua com 18px…
    expect($css)->toMatch('/\.help__trigger\s*\{[^}]*width:\s*18px;/s');

    // …e a área ampliada vem de um pseudo-elemento sem conteúdo, fora do fluxo.
    expect($css)->toMatch('/\.help__trigger::after\s*\{[^}]*width:\s*44px;[^}]*height:\s*44px;/s')
        ->and($css)->toMatch('/\.help__trigger::after\s*\{[^}]*position:\s*absolute;/s');
});

<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

/*
 * RNF-UI-01 — dinheiro e percentual digitados usam <x-input-moeda> e <x-input-percentual>.
 * Campo novo escrito à mão reprova aqui, antes de chegar à tela sem máscara.
 */
function _viewsDoApp(): array
{
    return collect(File::allFiles(resource_path('views')))
        ->reject(fn (SplFileInfo $arquivo) => in_array($arquivo->getFilename(), ['input-moeda.blade.php', 'input-percentual.blade.php'], true))
        ->mapWithKeys(fn (SplFileInfo $arquivo) => [$arquivo->getPathname() => File::get($arquivo->getPathname())])
        ->all();
}

it('nenhuma tela monta campo decimal à mão', function () {
    $fora = array_keys(array_filter(_viewsDoApp(), fn (string $conteudo) => str_contains($conteudo, 'inputmode="decimal"')));

    expect($fora)->toBe([]);
});

it('nenhuma tela define máscara própria de dinheiro ou percentual', function () {
    $fora = array_keys(array_filter(_viewsDoApp(), fn (string $conteudo) => preg_match('/maskBrl|maskPct|maskReais|maskPercentual|padStart\(3/', $conteudo) === 1));

    expect($fora)->toBe([]);
});

it('nenhum rótulo repete a unidade que o campo já mostra', function () {
    $fora = array_keys(array_filter(_viewsDoApp(), fn (string $conteudo) => preg_match('/<x-label[^>]*>[^<]*\((R\$|%)\)/', $conteudo) === 1));

    expect($fora)->toBe([]);
});

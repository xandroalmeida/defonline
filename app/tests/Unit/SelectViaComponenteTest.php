<?php

declare(strict_types=1);

/*
 * Todo <select> passa por <x-select>: é o que garante a mesma seta e as mesmas margens
 * no sistema inteiro.
 */
it('nenhuma tela usa <select> direto', function () {
    $raiz = dirname(__DIR__, 2).'/resources/views';
    $arquivos = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($raiz, FilesystemIterator::SKIP_DOTS));
    $infratores = [];

    foreach ($arquivos as $arquivo) {
        $caminho = (string) $arquivo;

        if (str_ends_with($caminho, '/components/select.blade.php') || ! str_ends_with($caminho, '.blade.php')) {
            continue;
        }

        if (preg_match('/<select[\s>]/', (string) file_get_contents($caminho)) === 1) {
            $infratores[] = substr($caminho, strlen($raiz) + 1);
        }
    }

    expect($infratores)->toBe([]);
});

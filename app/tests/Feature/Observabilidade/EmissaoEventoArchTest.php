<?php

declare(strict_types=1);

/**
 * STORY-035 CA-4 (ADR-004 §Decisão 2.3) — `EventLogger::emit()` é a ÚNICA porta
 * de entrada para `evento_produto`. Nenhum código de aplicação chama
 * `EventoProduto::create()` (ou variantes de escrita) diretamente.
 *
 * Varredura textual de `app/` — determinística e barata, casa com a redação
 * literal do CA ("busca ... retorna zero ocorrências fora do EventLogger").
 */
it('só o EventLogger escreve em evento_produto (EventoProduto::create fora dele = 0)', function () {
    $base = dirname(__DIR__, 3).'/app'; // raiz Laravel + namespace App\
    $permitido = $base.'/Observabilidade/EventLogger.php';

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS),
    );

    $infratores = [];
    foreach ($iterator as $arquivo) {
        if ($arquivo->getExtension() !== 'php' || $arquivo->getPathname() === $permitido) {
            continue;
        }

        $conteudo = file_get_contents($arquivo->getPathname());
        if (preg_match('/EventoProduto::(create|insert|firstOrCreate|updateOrCreate|forceCreate)\b/', $conteudo) === 1) {
            $infratores[] = $arquivo->getPathname();
        }
    }

    expect($infratores)->toBe([]);
});

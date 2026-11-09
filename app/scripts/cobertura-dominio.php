<?php

declare(strict_types=1);

/*
 * Cobertura de app/Domain lida do relatório Clover da suíte, para não rodar os testes duas vezes.
 * Uso: php scripts/cobertura-dominio.php <clover.xml> <mínimo>
 */
[$script, $arquivo, $minimo] = $argv + [null, null, null];

$relatorio = $arquivo !== null && is_file($arquivo) ? simplexml_load_file($arquivo) : false;

if ($relatorio === false || $minimo === null) {
    fwrite(STDERR, "Relatório de cobertura ausente ou uso inválido.\n");
    exit(2);
}

$linhas = 0;
$cobertas = 0;

foreach ($relatorio->xpath('//file[contains(@name, "/app/Domain/")]') ?: [] as $fonte) {
    $linhas += (int) $fonte->metrics['statements'];
    $cobertas += (int) $fonte->metrics['coveredstatements'];
}

if ($linhas === 0) {
    fwrite(STDERR, "Nenhum arquivo de app/Domain no relatório.\n");
    exit(2);
}

$percentual = $cobertas / $linhas * 100;
printf("app/Domain: %.1f%% (%d de %d linhas; mínimo %s%%)\n", $percentual, $cobertas, $linhas, $minimo);

exit($percentual >= (float) $minimo ? 0 : 1);

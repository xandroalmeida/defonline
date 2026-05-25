<?php

declare(strict_types=1);

use App\Domain\Motor\Motor;

/*
| ----------------------------------------------------------------------
| Baseline de latência — STORY-028 CA-7.
|
| Mede 50 execuções do motor com fixtures variados e exige p95 < 500ms.
| Esta é a baseline antes do relatório (STORY-029) — o budget total
| p95 ≤ 3s da espec inclui render Blade que entra em STORY-029.
|
| **Nota.** O teste roda em ambiente local Docker. Em CI/homol o número
| absoluto pode variar; o critério `< 500ms` é margem larga vs medição
| real (~5ms por chamada).
| ----------------------------------------------------------------------
*/

it('p95 das 50 execuções do motor com fixtures variadas é menor que 500ms', function () {
    $fixtures = [
        json_decode(file_get_contents(__DIR__.'/Fixtures/quiz_industria_saudavel.json'), true),
        json_decode(file_get_contents(__DIR__.'/Fixtures/quiz_industria_atencao.json'), true),
        json_decode(file_get_contents(__DIR__.'/Fixtures/quiz_industria_alerta.json'), true),
        json_decode(file_get_contents(__DIR__.'/Fixtures/quiz_industria_ncg_negativo.json'), true),
        json_decode(file_get_contents(__DIR__.'/Fixtures/quiz_industria_70pct_indisponivel.json'), true),
    ];

    $motor = new Motor;
    $latenciasMs = [];

    // 50 execuções alternando fixtures, escolha determinística (sem random — IDR-010 §sub-decisão 3).
    for ($i = 0; $i < 50; $i++) {
        $payload = $fixtures[$i % 5];
        $t0 = hrtime(true);
        $motor->calcular($payload, 'industria');
        $t1 = hrtime(true);
        $latenciasMs[] = ($t1 - $t0) / 1_000_000.0;   // ns → ms
    }

    sort($latenciasMs);
    // p95: posição que cobre 95% das amostras (índice 47 de 50 ordenado, base zero).
    $p95 = $latenciasMs[(int) ceil(0.95 * 50) - 1];

    expect($p95)->toBeLessThan(500.0, sprintf(
        'p95 = %.2f ms; esperado < 500 ms. Distribuição (min/p50/p95/max): %.2f / %.2f / %.2f / %.2f',
        $p95,
        $latenciasMs[0],
        $latenciasMs[24],
        $p95,
        $latenciasMs[49],
    ));
});

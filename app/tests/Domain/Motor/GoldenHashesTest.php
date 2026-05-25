<?php

declare(strict_types=1);

use App\Domain\Motor\Motor;

/*
| ----------------------------------------------------------------------
| Golden hashes — idempotencia.md §3 + IDR-010 §sub-decisão 3.
|
| Para cada fixture canônico em `Fixtures/`, o motor produz uma saída
| idêntica bit-a-bit ao longo do tempo. O hash SHA-256 dessa saída,
| serializada com flags determinísticas, é congelado abaixo.
|
| **Mudança de hash em CI = bump obrigatório de `motor_version`** (ou
| correção de bug, justificada no PR). Re-emitir a tabela é parte do PR
| que muda o comportamento. NÃO atualizar o hash silenciosamente.
|
| Os 5 cenários cobrem:
|   - saudavel: todos os indicadores em verde + NCG abs Faixa 1 (folga).
|   - atencao: todos amarelo + NCG abs Faixa 2 (moderado).
|   - alerta: todos vermelho + Div Líq/EBITDA INDISPONÍVEL (ebitda_negativo) + NCG abs Faixa 3 (alto).
|   - ncg_negativo: mistura verde/amarelo, NCG abs claramente negativo.
|   - 70pct_indisponivel: 100% dos indicadores indisponíveis (caminho extremo).
| ----------------------------------------------------------------------
*/

dataset('golden_fixtures', [
    // Hashes re-emitidos na STORY-030 (motor 1.0.0 → 1.1.0):
    // saída agora contém 15 indicadores (14 do Anexo D + Ciclo Operacional informativo)
    // em vez dos 8 anteriores (7 + NCG abs).
    'saudavel' => [
        'quiz_industria_saudavel.json',
        '873225b9c0c62d98d4d8eeec50e628274dba8819145c5ad0fa2e9e868bfa41c4',
    ],
    'atencao' => [
        'quiz_industria_atencao.json',
        'e5b5f0286ea67f77d0c683eaa81f5367ffe6c56de756b0a7eff7f2c6cfc8f3d9',
    ],
    'alerta' => [
        'quiz_industria_alerta.json',
        '44925c20108c7d2601e6376b920792983cc3c29677a3402992f66d32103bc372',
    ],
    'ncg_negativo' => [
        'quiz_industria_ncg_negativo.json',
        '78b37bfb723e64400536e17d04b13abfb5ba7ed602afc3e1760c9505ffe1f5bd',
    ],
    '70pct_indisponivel' => [
        'quiz_industria_70pct_indisponivel.json',
        'a4cea0d1c228b4e5be5b736ba8cfa4a69367532f3b73d6865e030256880ad245',
    ],
]);

it('motor produz saída bit-exata para cada fixture canônico', function (string $arquivo, string $hashEsperado) {
    $caminho = __DIR__."/Fixtures/{$arquivo}";
    $payload = json_decode(file_get_contents($caminho), associative: true);
    expect($payload)->toBeArray();

    $saida = (new Motor)->calcular($payload, 'industria');

    $json = json_encode(
        $saida,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION,
    );
    $hashObservado = hash('sha256', $json);

    expect($hashObservado)->toBe(
        $hashEsperado,
        // Mensagem de erro pedagogicamente exibe os dois hashes para diagnosticar drift.
        "Hash da saída mudou para o fixture {$arquivo}. ".
        "Esperado {$hashEsperado}; observado {$hashObservado}. ".
        'Se a mudança é intencional (bug fix ou novo indicador), bump motor_version '.
        'e atualize o hash neste arquivo no PR. Se não é intencional, investigue o drift.',
    );
})->with('golden_fixtures');

it('cada fixture é JSON puro (sem BOM, sem comentário)', function (string $arquivo) {
    $caminho = __DIR__."/Fixtures/{$arquivo}";
    $conteudo = file_get_contents($caminho);
    expect($conteudo)->toBeString();
    expect(substr($conteudo, 0, 1))->toBe('{', "Fixture {$arquivo} não inicia com {. JSON puro é esperado.");
})->with([
    ['quiz_industria_saudavel.json'],
    ['quiz_industria_atencao.json'],
    ['quiz_industria_alerta.json'],
    ['quiz_industria_ncg_negativo.json'],
    ['quiz_industria_70pct_indisponivel.json'],
]);

it('garante que os 5 fixtures cobrem caminhos distintos do motor (sanidade)', function () {
    $hashesUnicos = [];
    $arquivos = [
        'quiz_industria_saudavel.json',
        'quiz_industria_atencao.json',
        'quiz_industria_alerta.json',
        'quiz_industria_ncg_negativo.json',
        'quiz_industria_70pct_indisponivel.json',
    ];

    foreach ($arquivos as $arq) {
        $payload = json_decode(file_get_contents(__DIR__."/Fixtures/{$arq}"), associative: true);
        $saida = (new Motor)->calcular($payload, 'industria');
        $hashesUnicos[$arq] = hash('sha256', json_encode($saida));
    }

    // 5 fixtures distintos → 5 hashes distintos.
    expect(count(array_unique($hashesUnicos)))->toBe(5);
});

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
    // Hashes re-emitidos na STORY-031 (motor 1.1.0 → 1.2.0):
    // resumo_executivo deixa de ser placeholder e passa a carregar veredito +
    // destaques produzidos pelo algoritmo §4.7.1.
    'saudavel' => [
        'quiz_industria_saudavel.json',
        '3301503e9ce0d9bc1efe8c8b122d6d5bba1465592649259d100a2135e56ecebf',
    ],
    'atencao' => [
        'quiz_industria_atencao.json',
        '67ff7c1f0f9f31daffe8f8291689efaa34dd9723d0c88df992046c35274762ff',
    ],
    'alerta' => [
        'quiz_industria_alerta.json',
        '1d4ccb1c0b8c12a671db7190c934bb49c1a424c03cb47e8c197a3f109ff4526d',
    ],
    'ncg_negativo' => [
        'quiz_industria_ncg_negativo.json',
        'bb03b7c6df395aa27dbf2246ed5b21f9dceacf15680550d7ebf07284f8bad15e',
    ],
    '70pct_indisponivel' => [
        'quiz_industria_70pct_indisponivel.json',
        '315466d44f78b7042967bbc33bed74c307306a472bfb4f7d4c12d9b98567c321',
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

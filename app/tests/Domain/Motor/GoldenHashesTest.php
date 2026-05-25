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
    // Hashes re-emitidos na STORY-032 (motor 1.2.0 → 1.3.0):
    // mensagem placeholder "Faixa verde/amarela/vermelha." substituída pelo
    // texto literal da matriz dez-2025 (Anexo F, coluna Indústria).
    // Destaques do resumo_executivo herdam os textos novos automaticamente.
    'saudavel' => [
        'quiz_industria_saudavel.json',
        '96cdd0e777eeea8aef00a614946c356014e60091cb7137f3353c263fb2fff018',
    ],
    'atencao' => [
        'quiz_industria_atencao.json',
        '33f6baf8aa3d02bbd7b373b3452e1774b565d05156deb3c46f1192456ba118cc',
    ],
    'alerta' => [
        'quiz_industria_alerta.json',
        'b77ec453af3ce4fa54a7e88da6dbe60f831e0eb29ee7523d58cfcc5321525038',
    ],
    'ncg_negativo' => [
        'quiz_industria_ncg_negativo.json',
        '25e11b1279b3599865642716c6ee03850594e57d07ed59e87930f35ac1ea0848',
    ],
    '70pct_indisponivel' => [
        'quiz_industria_70pct_indisponivel.json',
        '0011bb5d1d2d82bae24e210ba69ce515b45690a93ca6f1fc9ee08c570ae9e318',
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

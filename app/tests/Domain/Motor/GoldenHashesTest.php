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
    // Hashes re-emitidos na STORY-060 (motor 1.3.1 → 1.3.2): as três mensagens de
    // faixa do NCG absoluto passaram a carregar a intenção editorial da EBC
    // (Anexo F §F.13). **Os 5 hashes mudam; a mudança de conteúdo atinge só o
    // NCG** — verificado assim, e o método fica registrado porque a conferência é
    // exigência do CA-3:
    //
    //   1. Com `motor.version` fixado de volta em 1.3.1, recalculamos os 5
    //      fixtures e trocamos, no JSON de saída, cada mensagem nova de NCG pela
    //      mensagem exata do 1.3.1.
    //   2. Os 5 hashes voltaram, bit a bit, aos congelados anteriores.
    //
    // Se qualquer outro campo tivesse mudado junto, a reconstrução não fecharia.
    // Os 4 primeiros fixtures trocam 1 mensagem de NCG cada; o `70pct_indisponivel`
    // não tem NCG calculável e só muda por causa do `motor_version` no payload.
    //
    // Hashes anteriores (STORY-041, motor 1.3.1):
    //   saudavel           9eaa789e676f8668ac1aa9ffb2c589c1a1da58c39fb87d4877bc032a482816a9
    //   atencao            71ce9a88f2ef0c8d735ada976fd3b78af7b278fe1830db5b16d37fdc79743829
    //   alerta             9d89a89ce3f764637d7522862bf28bcdd76380553ca02ab02b8ec8631fdab64c
    //   ncg_negativo       5a3a855d6ace5c8fb11cfee9994171bce8e73ff2a426ca74989fa823d825b10b
    //   70pct_indisponivel a858410d56aa98d6a677e14f25b4bfdf53692b11a4fca263fcf0b5a775dbafa8
    //
    // Contexto do re-emit anterior — STORY-041 (motor 1.3.0 → 1.3.1):
    // veredito `em_alerta` passou de "demandam ação" para "demandam ações"
    // (texto canônico da spec §4.7.1). **Os 5 hashes mudam, mas por dois
    // motivos distintos** — verificado isolando o bump:
    //   - `alerta` é o único cujo texto de veredito mudou de fato;
    //   - os outros 4 mudam só porque `motor_version` faz parte do payload
    //     hasheado (Motor::calcular devolve `motor_version` na saída).
    // Com a versão fixada de volta em 1.3.0, os 4 voltam ao hash anterior —
    // ou seja, não há regressão de cálculo escondida atrás deste re-emit.
    //
    // Hashes anteriores (STORY-032, motor 1.3.0): mensagem placeholder
    // "Faixa verde/amarela/vermelha." substituída pelo texto literal da
    // matriz dez-2025 (Anexo F, coluna Indústria).
    'saudavel' => [
        'quiz_industria_saudavel.json',
        '840deb658bfe850d7f6a90300a7041eb64e0141ee4e557d22745325c4af8648c',
    ],
    'atencao' => [
        'quiz_industria_atencao.json',
        '9ecd69df6bc5a909a5aef9948611e42df430fcb0c230aec7584a9396f3ec6579',
    ],
    'alerta' => [
        'quiz_industria_alerta.json',
        '1d6a4ff27ce77d12d5b271a8d3712476d68ac640c0811e9350f6a27eff2bfcb2',
    ],
    'ncg_negativo' => [
        'quiz_industria_ncg_negativo.json',
        '67d4ef83cb588eaef3a95cd97f0d00953a325b6754219d9cead5b30647c6c05f',
    ],
    '70pct_indisponivel' => [
        'quiz_industria_70pct_indisponivel.json',
        '79b7c237195918c49f97b8de916e704afdc5e25178ff96d7c5b74715a311d2f3',
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

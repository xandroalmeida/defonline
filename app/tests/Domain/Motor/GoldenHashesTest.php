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
|   - saudavel: todos os indicadores em verde + NCG abs negativo (NCG ≤ 0).
|   - atencao: todos amarelo + NCG abs positivo.
|   - alerta: vermelhos, exceto NCG/Vendas amarelo + Div Líq/EBITDA INDISPONÍVEL (ebitda_negativo) + NCG abs positivo.
|   - ncg_negativo: mistura verde/amarelo, NCG abs claramente negativo.
|   - 70pct_indisponivel: 100% dos indicadores indisponíveis (caminho extremo).
| ----------------------------------------------------------------------
*/

dataset('golden_fixtures', [
    // Hashes re-emitidos no motor 2.0.2 (PDR-018): o NCG absoluto passa a ler a matriz,
    // com positivo ou negativo. Conferência por reconstrução: com a versão em 2.0.1 e a
    // mensagem de NCG da época no JSON, os 5 hashes voltam bit a bit aos anteriores. Quatro
    // fixtures trocam a mensagem; `70pct_indisponivel` muda só pelo `motor_version`.
    //
    // Hashes anteriores (motor 2.0.1):
    //   saudavel           50d34125986853846a7175dc37303974ac8979d69da8fa1aff88f93c2a1ae6a7
    //   atencao            e255184fc20afc095787f42a2b5a3ea3cf09e519878fabb37366ad35091eb99d
    //   alerta             d684588026c4a63a5249712a256c53c4857baf8f06f8eaf04a387c7917975c36
    //   ncg_negativo       abda35da5436cb00d0ce9fd61f7c71f50bfff7328c22f1b3416ff9ab6eb9c500
    //   70pct_indisponivel e60a0fe5459bcf92e8cd3fb78c10750e27395db49c0f5b012bd96d4c25ee6788
    //
    // Hashes re-emitidos no motor 2.0.1 (PDR-014): o limite do NCG/Vendas volta de
    // 0,10 para 10,00. Em `atencao` e `alerta`, NCG/Vendas volta a amarelo e o NCG
    // absoluto, a moderado. Os 5 hashes mudam também pelo `motor_version` no payload.
    //
    // Hashes re-emitidos no motor 2.0.0 (PDR-014): NCG/Vendas passa à base mensal,
    // com limite 10,00, e Ciclo Operacional sai da saída. Comparando os 5 fixtures
    // antes e depois, o Resumo Executivo não muda. No fixture `alerta`, NCG/Vendas
    // vai de vermelho (0,2333) a amarelo (2,80) e o NCG absoluto, de alto a moderado.
    //
    // Hashes anteriores (motor 1.3.3):
    //   saudavel           dd804d9f8be7e6c0d00378bc02008e9f69d6499b18d1f4511bbc04b321589cb8
    //   atencao            0ef6bbc5152207a4c51914d0be7c821b1ed465abbee031b0604498255fc8bd18
    //   alerta             fe61411e23655a220eb3a925f3e3bbd33d8e19d8308be3319441d488649ce437
    //   ncg_negativo       761e5bae6b1634c254ce370bfcf391e61c3ef1d4fb22775ed65dab6e98049e0d
    //   70pct_indisponivel fe2c664e8e8b9585d82c0b1c350d72c4c9103ed681c896568346bf1254caf333
    //
    // Hashes re-emitidos na revisão de redação do PO (motor 1.3.2 → 1.3.3),
    // 06/08/2026: a faixa 1 do NCG absoluto deixou de dizer "folga operacional".
    // Mesma conferência por reconstrução — com a versão fixada de volta em 1.3.2
    // e a mensagem nova trocada pela de 1.3.2 no JSON, os 5 hashes voltaram bit a
    // bit aos anteriores. **Só 2 fixtures trocam texto** (`saudavel` e
    // `ncg_negativo`, os únicos com NCG ≤ 0); os outros 3 mudam apenas porque
    // `motor_version` entra no payload hasheado.
    //
    // Hashes anteriores (motor 1.3.2):
    //   saudavel           840deb658bfe850d7f6a90300a7041eb64e0141ee4e557d22745325c4af8648c
    //   atencao            9ecd69df6bc5a909a5aef9948611e42df430fcb0c230aec7584a9396f3ec6579
    //   alerta             1d6a4ff27ce77d12d5b271a8d3712476d68ac640c0811e9350f6a27eff2bfcb2
    //   ncg_negativo       67d4ef83cb588eaef3a95cd97f0d00953a325b6754219d9cead5b30647c6c05f
    //   70pct_indisponivel 79b7c237195918c49f97b8de916e704afdc5e25178ff96d7c5b74715a311d2f3
    //
    // Contexto do re-emit anterior — STORY-060 (motor 1.3.1 → 1.3.2): as três mensagens de
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
        'fbb53dbc56077cb39f03715110cba66d533aa0c63aaf0095cf50f2a1c6dd562b',
    ],
    'atencao' => [
        'quiz_industria_atencao.json',
        '00624ce4e5cc97131c38509256bae00a172c95b20c49d9eededb7a8e8d6f4b5f',
    ],
    'alerta' => [
        'quiz_industria_alerta.json',
        '652a12a99fbbafa3f99ad2035a7c640e18e76d5b249d584b0bf39dae202126e9',
    ],
    'ncg_negativo' => [
        'quiz_industria_ncg_negativo.json',
        '84539cb99ed9e6d85d96af966e6f396ba5e786eec50a055ca3056310e8d470c6',
    ],
    '70pct_indisponivel' => [
        'quiz_industria_70pct_indisponivel.json',
        '91dad772e9e040437272e372d503bfcd4215ea40bcdb51a9ea39a665b3b428c4',
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

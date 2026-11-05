<?php

declare(strict_types=1);

/**
 * Apresentação da Demonstração do Mês (STORY-049, spec `STORY-046-demonstracao-mes`, DDR-004).
 *
 * Aqui mora o que é da TELA e não do snapshot: o nome de cada trecho da conta no mobile,
 * qual rótulo de destaque cada linha ganha e o prefixo contábil. O snapshot decide quais
 * linhas existem, a ordem, o nível e o tipo (ADR-007); este arquivo nunca cria nem esconde
 * linha. Código que não consta aqui cai num valor padrão — nunca derruba a tela (CA-8b).
 *
 * Microcopy provisória do Designer; o pacote editorial dos rótulos de linha é `rotulos.php`.
 */
return [
    'version' => '1.0.0', // 2026-11-05 — STORY-049.

    /*
     * Nome do card do mobile, indexado pelo código da linha totalizadora que o FECHA
     * (DDR-004 item 4: um card termina em cada `tipo = total`). O subgrupo `<posicao>`
     * vale só naquele modo do pró-labore e vence o nome sem posição, que vale nos dois.
     */
    'trechos' => [
        'L3' => 'Receita',
        'L5' => 'Custo e lucro bruto',
        'L7' => 'Despesas operacionais',
        'L9' => 'Financeiras',
        'L11' => 'Resultado',
        'dentro' => ['L11' => 'Financeiras e resultado'],
    ],

    /* Card das linhas que sobram depois do último totalizador (estrutura que a tela não conhece). */
    'trecho_restante' => 'Demais linhas',

    /*
     * Prefixo contábil do rótulo. Por tipo; `codigos` sobrepõe (L7b é "±", não "–").
     * O prefixo é o sinal da linha — a hierarquia não depende de cor (spec §6).
     */
    'prefixos' => [
        'tipo' => ['total' => '= ', 'deducao' => '(–) '],
        'codigos' => ['L7b' => '(±) '],
    ],

    /*
     * Os três números antes da conta. Quais LINHAS são destaque vem do snapshot (`destaque`);
     * aqui só o texto que cada uma ganha. A última linha de nível 1 (resultado final) tem
     * um texto para lucro e outro para prejuízo: a troca do verbo carrega o sinal sem cor (DDR-004 item 6).
     */
    'destaques' => [
        'L1' => 'Quanto vendeu',
        'L9' => 'Sobrou antes da sua retirada',
        'L7' => 'Resultado operacional (EBITDA)',
    ],
    'resultado_final' => [
        'lucro' => 'Sobrou depois da sua retirada',
        'prejuizo' => 'Faltou depois da sua retirada',
    ],
    'pill_prejuizo' => 'Prejuízo no mês',

    /* Frase do modo vigente e o botão que oferece o outro (PDR-004 decisão B, CA-3b). */
    'modo' => [
        'fora' => [
            'frase' => 'Vendo com a sua retirada fora do resultado operacional.',
            'trocar' => 'Ver com a retirada dentro',
            'outro' => 'dentro',
        ],
        'dentro' => [
            'frase' => 'Vendo com a sua retirada dentro das despesas operacionais.',
            'trocar' => 'Ver com a retirada fora',
            'outro' => 'fora',
        ],
    ],
    'modo_ajuda' => 'Fora: a sua retirada é descontada depois do resultado operacional, e você vê quanto sobrou antes e depois dela. Dentro: a retirada entra nas despesas operacionais, como na contabilidade padrão. O lucro líquido do mês é o mesmo nos dois jeitos.',

    /* Indisponibilidade (DDR-005): o motivo é dito uma vez; a célula leva só o traço e o texto acessível. */
    'sem_faturamento_complemento' => 'Os valores em reais continuam abaixo.',
    'percentual_indisponivel' => 'Percentual indisponível: :motivo',
    'valor_indisponivel' => 'Valor indisponível: :motivo',
    'motivos_curtos' => [
        'indisponivel:sem_faturamento' => 'sem faturamento neste mês',
        'indisponivel:grupo_agregado' => 'grupo lançado de forma agregada',
    ],

    /*
     * Ressalvas que o usuário aceitou no lançamento (CA-7). `:valor` é o valor que a regra
     * registrou em `alertas_aceitos`; `:competencia` é o rótulo do mês. Regra desconhecida
     * cai em `ressalva_generica`.
     */
    'ressalvas' => [
        'A1' => 'Você não informou nenhuma venda neste mês.',
        'A2' => 'O custo do que foi vendido ficou maior que a venda do mês.',
        'A3' => 'Este mês fechou com prejuízo de :valor.',
        'A4' => 'Uma despesa deste mês ficou bem acima da média dos meses anteriores.',
        'A5' => 'Nenhuma retirada (pró-labore) foi lançada neste mês.',
        'A6' => 'Há meses sem lançamento antes deste.',
    ],
    'ressalva_generica' => 'Uma ressalva foi registrada no lançamento deste mês.',
];

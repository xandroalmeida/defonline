<?php

declare(strict_types=1);

/**
 * Textos da Série histórica e dos comparativos (STORY-051, spec `STORY-046-serie-historica` §6).
 *
 * Microcopy provisória do Designer; o pacote editorial final é da STORY-052. Aqui mora só o
 * que é da TELA: o cálculo vive em `App\Domain\Drm\Serie`. Todo motivo `indisponivel:*` tem uma
 * frase em palavras — nunca `—` sozinho, `0%` ou `N/A` (DDR-005).
 */
return [
    'version' => '1.0.0', // 2026-11-05 — STORY-051.

    'titulo' => 'Todos os meses',
    'rotulo_valor' => 'Lucro do mês',
    'rotulo_vendeu' => 'Quanto vendeu',
    'cta' => 'Lançar :mes',
    'estado' => ['lancado' => 'lançado', 'nao_lancado' => 'não lançado', 'rascunho' => 'rascunho'],
    'sem_lancamento' => 'Sem lançamento para este mês.',
    'rascunho_parou' => 'Você parou em :bloco.',
    'rascunho_sem_bloco' => 'Você começou a lançar este mês.',
    'corrigido_vezes' => 'corrigido :n×',
    'pills' => ['retificado' => 'Retificado', 'rascunho' => 'Rascunho', 'atipico' => 'Mês atípico', 'prejuizo' => 'Prejuízo no mês'],
    'acoes' => ['lancar' => 'Lançar', 'continuar' => 'Continuar', 'corrigir' => 'Corrigir'],
    'acoes_acessiveis' => [
        'lancar' => 'Lançar :mes',
        'continuar' => 'Continuar o rascunho de :mes',
        'corrigir' => 'Corrigir :mes',
    ],
    'cabecalhos' => ['mes' => 'Mês', 'vendeu' => 'Quanto vendeu', 'lucro' => 'Lucro do mês', 'anterior' => 'vs. mês anterior', 'media' => 'vs. média', 'acao' => 'Ação'],

    /* Série vazia (spec §4.3). */
    'vazio_titulo' => 'Você ainda não lançou nenhum mês para esta empresa.',
    'vazio_instrucao' => 'O primeiro lançamento leva alguns minutos. Do segundo em diante, a maior parte já vem preenchida.',

    /* Aviso de lacunas (spec §4.9): a partir de 2 meses sem lançamento entre o primeiro e o último lançado. */
    'lacunas' => 'Você tem :n meses sem lançamento entre :de e :ate. A comparação com a média fica mais útil quando a série está completa.',

    /* Comparativos do mês (spec §4.5). `:mes` é o mês de base, por extenso. */
    'vs_anterior' => ':variacao em relação a :mes',
    'vs_media' => ':variacao em relação à média dos meses anteriores',
    'indisponivel' => [
        'indisponivel:primeiro_lancamento' => 'primeiro mês lançado',
        'indisponivel:sem_lancamento' => ':mes não foi lançado, então não dá para comparar',
        'indisponivel:historico_insuficiente' => 'média disponível a partir do 4º mês lançado',
        'indisponivel:base_zero' => 'o mês de comparação ficou em zero, então a variação não se aplica',
        'indisponivel:grupo_agregado' => 'esta linha não foi detalhada em algum dos meses comparados',
        'indisponivel:sem_faturamento' => 'sem faturamento nos meses anteriores',
    ],

    /* Mês atípico (alerta A4, spec §4.6): explicação no fluxo de leitura, não advertência. */
    'atipico_explicacao' => ':mes teve :rubricas — por isso o resultado do mês :direcao.',
    'atipico_direcao' => ['despesa' => 'caiu', 'receita' => 'subiu'],
    'atipico_receitas' => ['D01', 'D41'],
    'atipico_ajuda' => 'Esta rubrica foi paga quase toda neste mês (regime de caixa): férias e 13º salário, por exemplo, saem de uma vez e fazem o resultado de um mês parecer pior do que o negócio realmente está. O mês continua entrando em todas as contas.',
    'atipico_outras' => 'e outras',

    /* Comparativos por linha, na Demonstração do Mês (spec `demonstracao-mes`, divisão de trabalho). */
    'linha' => [
        'titulo' => 'Comparado aos outros meses',
        'introducao' => 'Cada linha contra o mês anterior, a média dos meses anteriores e o ano até aqui.',
        'resumo' => 'Ver comparações',
        'vs_anterior' => 'Mês anterior',
        'vs_media' => 'Média dos meses anteriores',
        'acumulado' => 'Acumulado no ano',
        'participacao' => 'Participação média sobre a receita',
        'em_relacao_a' => ':variacao em relação a :mes',
        'media_de' => ':variacao (média de :meses)',
        'acumulado_de' => ':valor (:meses em :ano)',
        'participacao_de' => ':percentual (média de :meses)',
    ],
];

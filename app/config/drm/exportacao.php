<?php

declare(strict_types=1);

/**
 * Exportação da Demonstração em planilha (STORY-055, ADR-008) e camada de impressão.
 *
 * Texto que o arquivo e a tela de impressão acrescentam ao snapshot. O snapshot continua
 * mandando em quais linhas existem, a ordem, o nível e o tipo; nada aqui cria linha.
 */
return [
    'version' => '1.0.0', // 2026-11-05 — STORY-055.

    /* Botões da Demonstração. A impressão é interina (ADR-008): não promete PDF gerado por nós (CA-13). */
    'botao_planilha' => 'Exportar planilha (XLSX)',
    'botao_imprimir' => 'Imprimir / Salvar em PDF',

    'titulo' => 'Demonstração de Resultado Mensal',
    'aba_mes' => 'Mês',
    'aba_serie' => 'Série histórica',

    /*
     * Linhas de nível 1 do tipo `deducao` que SOMAM na conta em vez de subtrair: "(±) Outras
     * receitas e despesas" (L7 + L7b − L8). O sinal real do valor já vem no snapshot.
     */
    'linhas_aditivas' => ['L7b'],

    'cabecalho' => [
        'empresa' => 'Empresa',
        'competencia' => 'Competência',
        'setor' => 'Setor',
        'leitura' => 'Leitura da retirada (pró-labore)',
        'situacao' => 'Situação',
    ],
    'colunas' => [
        'linha' => 'Linha',
        'valor' => 'Valor (R$)',
        'percentual' => '% da receita',
        'observacao' => 'Observação',
    ],
    'situacao' => [
        'original' => 'Lançamento original',
        'retificado' => 'Retificado — versão :versao (corrigido :vezes× — última correção em :data)',
        'lancado' => 'Lançado',
        'retificado_curto' => 'Retificado (v:versao)',
        'nao_lancado' => 'Não lançado',
    ],
    'serie' => [
        'nota' => 'Meses sem lançamento ficam em branco: ausência de lançamento não é zero.',
        'bloco_valores' => 'Valores (R$)',
        'bloco_percentuais' => '% da receita',
    ],
    'rodape' => 'motor v:motor · gerado em :data',
    'impressao_rodape' => 'motor v:motor · conteúdo v:conteudo · gerado em :data',
];

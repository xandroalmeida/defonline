<?php

declare(strict_types=1);

/**
 * Mensagens de bloqueio e alerta do DRM — **PROVISÓRIAS**, redigidas pela
 * equipe de produto (`PDR-009` §1, CA-7 da `STORY-052`).
 *
 * # Por que estas existem desde o dia um, e as outras não
 *
 * Tooltip, leitura e farol são conteúdo **explicativo**: podem faltar, e a
 * ausência é honesta. Bloqueio é **função**. O Anexo K §K.6 define regras que
 * impedem o usuário de fechar o mês; uma tela que recusa o avanço sem dizer
 * por quê não é lacuna editorial, é defeito. Por isso este é o único item do
 * pacote cuja ausência não é opção — e por isso ele é a exceção declarada no
 * `PDR-009` §1.
 *
 * # Bloqueio × alerta
 *
 *   - **Bloqueio** (`B1`–`B4`) impede o fechamento. O texto diz o que está
 *     errado **e o que fazer**: barrar alguém sem apontar a saída é hostil.
 *   - **Alerta** (`A1`–`A6`) sinaliza e deixa passar mediante aceite explícito
 *     (mesmo padrão `alertas_aceitos` do Diagnóstico). O texto **não acusa**:
 *     os seis casos são todos legítimos. Mês sem faturamento acontece; prejuízo
 *     acontece; férias concentradas num mês acontecem. O alerta existe para
 *     preparar a leitura, não para questionar o dado.
 *
 * `A3` (prejuízo) é o mais delicado dos seis: o usuário acabou de digitar
 * trinta e oito valores e o produto vai dizer que o mês fechou no vermelho.
 * O texto não suaviza o fato e não culpa ninguém — informa e segue.
 *
 * `**negrito**` é suportado; sem outro markdown. Promoção: ver `README.md`.
 */
return [
    'version' => '1.0.0', // 2026-08-06 — STORY-052: 4 bloqueios + 6 alertas do Anexo K §K.6.

    /*
     * Bloqueantes — impedem o fechamento do lançamento.
     *
     * `:placeholder` é substituído pela implementação (STORY-047/048).
     */
    'bloqueios' => [
        'B1' => [
            'titulo' => 'Este mês já foi lançado',
            'texto' => 'Já existe um lançamento de :competencia para :empresa. Cada mês é lançado uma única vez.',
            'acao_primaria' => 'Abrir o lançamento existente',
            'acao_secundaria' => 'Retificar o mês',
        ],
        'B2' => [
            'titulo' => 'Este mês ainda não terminou',
            'texto' => 'Só dá para lançar um mês depois que ele fecha — antes disso, os números ainda vão mudar. Escolha :ultimo_mes_fechado ou um mês anterior.',
            'acao_primaria' => 'Escolher outro mês',
            'acao_secundaria' => null,
        ],
        'B3' => [
            'titulo' => 'Valor negativo em :campo',
            'texto' => 'Nenhum campo aceita valor negativo. Se houve devolução, subtraia do próprio campo: devolução de venda reduz "Quanto vendeu no mês"; devolução de compra reduz o campo de custo correspondente.',
            'acao_primaria' => 'Corrigir o valor',
            'acao_secundaria' => null,
        ],
        'B4' => [
            'titulo' => 'Os impostos ficaram maiores que a venda',
            'texto' => 'Você informou :valor_impostos de impostos sobre uma venda de :valor_receita. Isso não acontece em nenhum regime tributário brasileiro — é quase sempre um dígito a mais ou uma vírgula fora de lugar. Confira os dois valores.',
            'acao_primaria' => 'Revisar os valores',
            'acao_secundaria' => null,
        ],
    ],

    /*
     * Alertas — sinalizam, permitem prosseguir com aceite explícito.
     *
     * `consequencia` é exibida junto do alerta quando ele muda o que o usuário
     * vai ver depois. Sem isso, o efeito aparece na tela seguinte sem
     * explicação e parece defeito.
     */
    'alertas' => [
        'A1' => [
            'titulo' => 'Mês sem faturamento',
            'texto' => 'Você lançou despesas mas nenhuma venda em :competencia. Isso acontece — obra parada, sazonalidade, mês de reforma. Confirme que é isso mesmo.',
            'consequencia' => 'Os percentuais sobre a receita não serão calculados neste mês, porque não há sobre o que calcular. Os valores em reais aparecem normalmente — e este é o mês em que eles mais importam: mostram quanto custou manter o negócio de pé parado.',
            'acao_primaria' => 'Confirmar e continuar',
            'acao_secundaria' => 'Voltar e revisar',
        ],
        'A2' => [
            'titulo' => 'O custo ficou maior que a venda',
            'texto' => 'O custo do que foi vendido (:valor_custo) passou a venda do mês (:valor_receita). Acontece em mês de reposição de estoque, quando você compra para vender depois. Vale conferir antes de fechar.',
            'consequencia' => null,
            'acao_primaria' => 'Confirmar e continuar',
            'acao_secundaria' => 'Voltar e revisar',
        ],
        'A3' => [
            'titulo' => 'O mês fechou no vermelho',
            'texto' => 'Depois de todas as despesas e da sua retirada, faltaram :valor_prejuizo em :competencia. Não é erro de lançamento — é o resultado do mês.',
            'consequencia' => 'O relatório vai mostrar onde o dinheiro foi, linha por linha. É para isso que ele existe.',
            'acao_primaria' => 'Ver o relatório',
            'acao_secundaria' => 'Voltar e revisar',
        ],
        'A4' => [
            'titulo' => ':campo está bem acima da média',
            'texto' => 'Você lançou :valor em :campo, mais de três vezes a média dos meses anteriores (:media). Se foi férias, 13º ou uma compra grande, está certo — é assim mesmo.',
            'consequencia' => 'O mês fica marcado como atípico na série histórica, para que a comparação com os outros meses seja lida com esse contexto. Ele continua entrando em todas as contas.',
            'acao_primaria' => 'Confirmar e continuar',
            'acao_secundaria' => 'Voltar e revisar',
        ],
        'A5' => [
            'titulo' => 'Você não lançou retirada neste mês',
            'texto' => 'O pró-labore ficou em zero. É legítimo — tem mês em que o dono não retira. Mas se você retirou e lançou junto com os salários, o relatório vai mostrar um lucro que não existe e um custo de folha que não é o seu.',
            'consequencia' => 'Sem retirada lançada, a linha "quanto sobrou depois de eu me pagar" fica igual à linha anterior.',
            'acao_primaria' => 'Confirmar que não houve retirada',
            'acao_secundaria' => 'Voltar e informar a retirada',
        ],
        'A6' => [
            'titulo' => 'Faltam meses entre um lançamento e outro',
            'texto' => 'Seu último lançamento foi :ultima_competencia e você está lançando :competencia. Ficaram :meses_faltantes sem lançar.',
            'consequencia' => 'Mês não lançado não é mês zerado: ele aparece como ausente na série histórica, e não entra nas médias. Sem isso, o relatório mostraria quedas que nunca aconteceram.',
            'acao_primaria' => 'Continuar assim mesmo',
            'acao_secundaria' => 'Lançar o mês que falta',
        ],
    ],
];

<?php

declare(strict_types=1);

/**
 * Frases-modelo da leitura em linguagem simples (`STORY-054`) que descrevem **fatos** do mês.
 *
 * **Não é o texto de interpretação de farol.** Aquele é `leitura.php` (`por_linha_e_farol`), validado
 * pela EBC e, por decisão do PO (`PDR-009` §1), vazio e sem contrapartida provisória: onde o farol
 * cala, a leitura cala. Aqui mora o esqueleto determinístico da prosa — o que o mês vendeu, o que
 * sobrou, o que mudou em relação aos meses anteriores — com os números entrando em `:marcadores`.
 * Nenhuma frase daqui diz que um número é bom ou ruim: isso é o farol, e o farol é calibrado.
 *
 * Microcopy de produto, no mesmo regime de `tela.php` e `serie.php`. Texto novo muda por edição deste
 * arquivo (suba `version` e `conteudo_version`); nunca por código. O gerador nunca escreve texto livre:
 * toda palavra que o usuário lê vem daqui ou de `leitura.php`.
 *
 * Marcadores: `:mes` (mês lido, "outubro de 2026"), `:base` (mês de comparação, "setembro"), `:vendas`,
 * `:resultado`, `:falta`, `:linha`, `:valor`, `:percentual`, `:variacao`, `:de`, `:para`, `:cor`.
 */
return [
    'version' => '1.0.0', // 2026-11-05 — STORY-054.

    'titulo' => 'O que este mês está dizendo',

    /* Primeira frase: o mês em uma linha. Uma das quatro, escolhida pelo resultado. */
    'abertura' => [
        'lucro' => 'Em :mes, o negócio vendeu :vendas e sobraram :resultado depois de pagar todas as contas, inclusive a sua retirada.',
        'zero' => 'Em :mes, o negócio vendeu :vendas e fechou no zero a zero: o que entrou cobriu exatamente o que saiu.',
        'prejuizo' => 'Em :mes, o negócio vendeu :vendas, mas o que saiu passou do que entrou em :falta.',
        'sem_faturamento' => 'Em :mes não houve faturamento registrado. Sem vendas não existe margem para medir, então os percentuais sobre a receita não se aplicam.',
    ],

    /* Mês parado (sem faturamento): o custo de manter o negócio funcionando. */
    'parado' => 'Mesmo sem vender, manter o negócio funcionando custou :valor neste mês (despesas de funcionamento, despesas financeiras e a retirada do dono).',

    /* A linha que mais pesou (prejuízo e mês sem faturamento). A versão com percentual vale quando há faturamento. */
    'mais_pesou' => 'A linha que mais pesou foi “:linha”, com :valor.',
    'mais_pesou_percentual' => 'A linha que mais pesou foi “:linha”, com :valor (:percentual do que o negócio vendeu).',

    /* Comparativos do resultado do mês (STORY-051). */
    'anterior' => [
        'acima' => 'O resultado do mês ficou :variacao acima do de :base.',
        'abaixo' => 'O resultado do mês ficou :variacao abaixo do de :base.',
        'igual' => 'O resultado do mês ficou igual ao de :base.',
    ],
    'por_que' => 'O que mais explica a diferença é a linha “:linha”, que foi de :de em :base para :para neste mês.',
    'media' => [
        'acima' => 'Frente à média dos meses anteriores, o resultado está :variacao acima.',
        'abaixo' => 'Frente à média dos meses anteriores, o resultado está :variacao abaixo.',
        'igual' => 'O resultado está igual à média dos meses anteriores.',
    ],

    /* Sem comparação possível: a leitura diz isso em vez de inventar tendência. */
    'sem_comparacao' => [
        'indisponivel:primeiro_lancamento' => 'Este é o primeiro mês lançado, então ainda não há com o que comparar. A partir do próximo, esta leitura passa a dizer o que mudou.',
        'indisponivel:sem_lancamento' => 'O mês anterior (:base) não foi lançado, então não dá para dizer o que mudou em relação a ele.',
        'indisponivel:base_zero' => 'O resultado de :base foi zero, então a variação em porcentagem não se aplica.',
        'padrao' => 'Não foi possível comparar este mês com o anterior.',
    ],

    /* Reserva editorial (CA-7): combinação de linha e farol sem texto em `leitura.php`. Neutra, sem juízo. */
    'reserva' => '“:linha”: farol :cor.',
    'cores' => ['verde' => 'verde', 'amarelo' => 'amarelo', 'vermelho' => 'vermelho'],

    /* Rodapé do card (CA-8): o que é medição e o que é leitura. `:versao` é o `conteudo_version` em vigor. */
    'rodape' => 'Faróis e leitura são interpretação e podem mudar quando as faixas forem recalibradas (pacote de conteúdo v:versao). Os valores em reais e os percentuais do mês não mudam.',
];

<?php

declare(strict_types=1);
use App\Domain\Quiz\ValidacoesCruzadas;

/**
 * Regras de validação cruzada DRE × Balanço — setor Indústria (STORY-034, espec §6.6).
 *
 * Detectam inconsistências ENTRE blocos do quiz (não dentro de um único campo —
 * isso é validação de faixa, já feita na STORY-027). São avisos **não-bloqueantes**:
 * Roberto vê o alerta e decide corrigir ou continuar.
 *
 * Formato declarativo (sem closures — compatível com `config:cache`). Cada regra é
 * avaliada por {@see ValidacoesCruzadas} como:
 *
 *     soma_ponderada(esquerda)  <operador>  fator × soma_ponderada(direita)
 *
 * As 3 fórmulas canônicas da §6.6 reduzem a esse formato:
 *   - R1: Q16×12 > 2 × Q06            → esquerda {Q16:12}, fator 2, direita {Q06:1}
 *   - R2: (Q14+Q15)×12 > Q09×12       → esquerda {Q14:12,Q15:12}, fator 1, direita {Q09:12}
 *   - R3: (Q06+Q07) > (Q02+…+Q05)     → esquerda {Q06,Q07}, fator 1, direita {Q02..Q05}
 *
 * Mensagem: template com placeholders preenchidos pela classe —
 *   :esquerda   = soma ponderada da esquerda (R$ BR)
 *   :direita    = soma ponderada da direita (R$ BR)
 *   :percentual = round(esquerda ÷ direita × 100) — razão crua, sem o `fator`
 *   :Qxx        = valor bruto do campo (R$ BR)
 *
 * **Ampliar (CA-6):** adicionar uma regra = nova entrada em `regras` + um teste.
 * Nenhum refactor da classe. Atualize `version` ao mexer no conjunto.
 *
 * Textos default aprovados na estória (PO 2026-05-25, §"Textos default").
 */
return [
    'version' => '1.0.0', // 2026-05-26 — conjunto canônico §6.6 (R1/R2/R3).

    'regras' => [
        'R1' => [
            'severidade' => 'warning',
            'campos_envolvidos' => ['Q16', 'Q06'],
            'campo_foco' => 'Q16',
            'botao_label' => 'Ir para Despesas Financeiras (Q16)',
            'condicao' => [
                'esquerda' => ['Q16' => 12],
                'operador' => '>',
                'fator' => 2,
                'direita' => ['Q06' => 1],
            ],
            'mensagem' => 'Suas despesas financeiras mensais (R$ :Q16) anualizadas representam :percentual% da dívida declarada (R$ :Q06). Isso é incomum — verifique o valor de despesas financeiras.',
        ],

        'R2' => [
            'severidade' => 'warning',
            'campos_envolvidos' => ['Q14', 'Q15', 'Q09'],
            'campo_foco' => 'Q14',
            'botao_label' => 'Ir para Custos (Q14/Q15)',
            'condicao' => [
                'esquerda' => ['Q14' => 12, 'Q15' => 12],
                'operador' => '>',
                'fator' => 1,
                'direita' => ['Q09' => 12],
            ],
            'mensagem' => 'Os custos totais anuais (R$ :esquerda) ultrapassam a receita anual (R$ :direita). Provavelmente houve erro em custos ou em receita.',
        ],

        'R3' => [
            'severidade' => 'warning',
            'campos_envolvidos' => ['Q02', 'Q03', 'Q04', 'Q05', 'Q06', 'Q07'],
            'campo_foco' => 'Q02',
            'botao_label' => 'Revisar Balanço',
            'condicao' => [
                'esquerda' => ['Q06' => 1, 'Q07' => 1],            // passivo total
                'operador' => '>',
                'fator' => 1,
                'direita' => ['Q02' => 1, 'Q03' => 1, 'Q04' => 1, 'Q05' => 1], // ativo total
            ],
            'mensagem' => 'O passivo total (R$ :esquerda) é maior que o ativo total (R$ :direita), o que indica PL negativo. Se for o caso real, prossiga — o diagnóstico vai sinalizar.',
        ],
    ],
];

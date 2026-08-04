<?php

declare(strict_types=1);
use App\Domain\Quiz\ValidacoesCruzadas;

/**
 * Regras de validação cruzada DRE × Balanço — setor Indústria (STORY-034, espec §6.6).
 *
 * Detectam inconsistências ENTRE blocos do quiz (não dentro de um único campo —
 * isso é validação de faixa, já feita na STORY-027).
 *
 * **Severidade** (`severidade`) decide o que acontece quando a regra dispara:
 *   - `warning`  — não-bloqueante (R1/R2/R3): Roberto vê o banner no gate
 *     Bloco 3 → 4 e decide corrigir ou continuar mesmo assim.
 *   - `blocking` — bloqueante (R4, STORY-043): segura o botão `Calcular
 *     diagnóstico` até o valor ser corrigido. Sem escape, sem registro em
 *     `alertas_aceitos` (não há o que aceitar).
 * O vocabulário vive em {@see ValidacoesCruzadas::SEVERIDADE_AVISO} /
 * `SEVERIDADE_BLOQUEIO` e é travado por teste arquitetural.
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
 *   - R4: Q20 >= Q09                  → esquerda {Q20:1}, fator 1, direita {Q09:1}
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
    'version' => '1.1.0', // 2026-08-04 — R4 bloqueante (STORY-043). 1.0.0: canônico §6.6 (R1/R2/R3).

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

        /*
         * R4 (STORY-043) — recebíveis × venda mensal. **Bloqueante.**
         *
         * Origem: devolutiva EBC de 2026-05-27, §Bloco 4 — o texto auxiliar do
         * campo ("deve ser inferior à venda mensal") saiu da UI na STORY-042 e
         * virou regra de checagem aqui. A regra em si não é nova: o Anexo A
         * §validações já exigia "Q20 ≥ Q09: bloqueio rígido" desde as correções
         * da EBC de 22/04/2026. O que mudou é o mecanismo — a validação estava
         * numa closure do componente (`Quiz::regraQ20MenorQueQ09()`, removida) e
         * passou a ser declarativa, para haver uma fonte de verdade só.
         *
         * Sentido: recebíveis (venda por cartão/duplicatas) são um subconjunto da
         * venda total, logo devem ser INFERIORES a ela. Por isso o operador é
         * `>=` — a igualdade também bloqueia. Ver `[ESCALONAMENTO-PO]` nas Notas
         * do agente da STORY-043: a estória descrevia o sentido invertido, e o PO
         * confirmou em 2026-08-04 que vale o do Anexo A.
         *
         * Q20 só chega aqui preenchido quando há captação (Q17="sim"); sem
         * captação o componente entrega `null` e a engine pula a regra.
         */
        'R4' => [
            'severidade' => 'blocking',
            'campos_envolvidos' => ['Q20', 'Q09'],
            'campo_foco' => 'Q20',
            'botao_label' => 'Ir para Recebíveis (Q20)',
            'condicao' => [
                'esquerda' => ['Q20' => 1],
                'operador' => '>=',
                'fator' => 1,
                'direita' => ['Q09' => 1],
            ],
            'mensagem' => 'Recebíveis (R$ :Q20) é maior ou igual à venda mensal média (R$ :Q09). O valor de recebíveis deve ser inferior à venda mensal.',
        ],
    ],
];

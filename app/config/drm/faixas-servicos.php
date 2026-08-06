<?php

declare(strict_types=1);

/**
 * Faixas de farol do DRM — setor **Serviços**.
 *
 * # A regra que governa este arquivo
 *
 * Idêntico em estrutura a `faixas-industria.php`; a documentação completa da
 * regra, da semântica dos limites e do procedimento de calibração está lá e
 * vale igual aqui.
 *
 * **Faixa `nao_calibrada` não produz farol.** A linha aparece sem semáforo, e
 * isso é o comportamento **correto** — não é lacuna, não é erro, não é "falta
 * implementar". Dizer a um microempresário que a margem dele está vermelha com
 * base num palpite é pior do que não dizer nada, e é exatamente o risco que o
 * `NRF §9.3` existe para evitar. O módulo pode ir a produção com todas as
 * faixas assim: a demonstração, os comparativos e os valores nominais entregam
 * valor sem nenhum farol aceso.
 *
 * A regra é **do motor**, não da tela (`PDR-009` §2 item 3): se ela morasse na
 * view, a próxima superfície — exportação, PDF, e-mail de resumo — poderia
 * contorná-la sem querer. Ver `App\Domain\Drm\Faixas`.
 *
 * # Por que a estrutura vem antes dos valores
 *
 * Margem bruta saudável em comércio de alimentos não é a mesma de indústria
 * moveleira — e serviços, sem estoque e com custo majoritariamente de mão de
 * obra, tem distribuição própria. Cada setor tem arquivo e valores próprios,
 * ainda que a estrutura de linhas seja idêntica nos três. No Diagnóstico, as faixas passaram por validação externa da EBC
 * (`NRF §9.3`); o mesmo rigor vale aqui. A `STORY-052` entrega a **estrutura**
 * para que a implementação não fique parada esperando texto, e a EBC popula os
 * valores depois — **por edição deste arquivo, sem tocar em código**.
 *
 * # Como calibrar uma faixa
 *
 *  1. Preencher `verde`, `amarelo` e `vermelho` com os limites.
 *  2. Trocar `estado` para `calibrada`.
 *  3. Preencher `origem` (quem validou e em que documento) e `validado_em`.
 *  4. Subir `version` deste arquivo e `conteudo_version` em `config/drm.php`.
 *
 * Nada mais. Nenhum deploy de código, nenhuma migração. Lançamentos já
 * fechados **não** são recalculados (`IDR-010`, `ADR-007`) — o farol passa a
 * valer para leitura nova.
 *
 * # Semântica dos limites
 *
 * Cada faixa é um intervalo sobre o **percentual da linha em relação à Receita
 * Bruta** (`estrutura-drm` §2), em pontos percentuais, com `min` inclusivo e
 * `max` exclusivo. `null` significa "sem limite deste lado".
 *
 * `sentido` diz para que lado é bom, e existe porque não dá para inferir:
 *   - `maior_melhor` — margem, sobra. Quanto mais, melhor.
 *   - `menor_melhor` — custo, despesa. Quanto menos, melhor.
 *
 * Sem `sentido`, um motor futuro teria que adivinhar se 40% de despesa
 * administrativa é bom — e adivinhar aqui é o mesmo erro de publicar faixa
 * não validada.
 */
return [
    'version' => '1.0.0', // 2026-08-06 — STORY-052: estrutura completa, zero faixas calibradas.
    'setor' => 'servicos',

    'linhas' => [
        'L4' => [
            'rotulo_referencia' => 'Custo dos Serviços Prestados (CSP)',
            'sentido' => 'menor_melhor',
            'estado' => 'nao_calibrada',
            'origem' => null,
            'validado_em' => null,
            'verde' => ['min' => null, 'max' => null],
            'amarelo' => ['min' => null, 'max' => null],
            'vermelho' => ['min' => null, 'max' => null],
        ],
        'L5' => [
            'rotulo_referencia' => 'Lucro Bruto (Margem Bruta)',
            'sentido' => 'maior_melhor',
            'estado' => 'nao_calibrada',
            'origem' => null,
            'validado_em' => null,
            'verde' => ['min' => null, 'max' => null],
            'amarelo' => ['min' => null, 'max' => null],
            'vermelho' => ['min' => null, 'max' => null],
        ],
        'L6' => [
            'rotulo_referencia' => 'Despesas Operacionais',
            'sentido' => 'menor_melhor',
            'estado' => 'nao_calibrada',
            'origem' => null,
            'validado_em' => null,
            'verde' => ['min' => null, 'max' => null],
            'amarelo' => ['min' => null, 'max' => null],
            'vermelho' => ['min' => null, 'max' => null],
        ],
        'L6a' => [
            'rotulo_referencia' => 'Despesas com Vendas',
            'sentido' => 'menor_melhor',
            'estado' => 'nao_calibrada',
            'origem' => null,
            'validado_em' => null,
            'verde' => ['min' => null, 'max' => null],
            'amarelo' => ['min' => null, 'max' => null],
            'vermelho' => ['min' => null, 'max' => null],
        ],
        'L6b' => [
            'rotulo_referencia' => 'Despesas Administrativas e Gerais',
            'sentido' => 'menor_melhor',
            'estado' => 'nao_calibrada',
            'origem' => null,
            'validado_em' => null,
            'verde' => ['min' => null, 'max' => null],
            'amarelo' => ['min' => null, 'max' => null],
            'vermelho' => ['min' => null, 'max' => null],
        ],
        'L7' => [
            'rotulo_referencia' => 'Resultado Operacional',
            'sentido' => 'maior_melhor',
            'estado' => 'nao_calibrada',
            'origem' => null,
            'validado_em' => null,
            'verde' => ['min' => null, 'max' => null],
            'amarelo' => ['min' => null, 'max' => null],
            'vermelho' => ['min' => null, 'max' => null],
        ],
        'L8' => [
            'rotulo_referencia' => 'Despesas Financeiras',
            'sentido' => 'menor_melhor',
            'estado' => 'nao_calibrada',
            'origem' => null,
            'validado_em' => null,
            'verde' => ['min' => null, 'max' => null],
            'amarelo' => ['min' => null, 'max' => null],
            'vermelho' => ['min' => null, 'max' => null],
        ],
        'L10' => [
            'rotulo_referencia' => 'Pró-labore',
            'sentido' => 'menor_melhor',
            'estado' => 'nao_calibrada',
            'origem' => null,
            'validado_em' => null,
            'verde' => ['min' => null, 'max' => null],
            'amarelo' => ['min' => null, 'max' => null],
            'vermelho' => ['min' => null, 'max' => null],
        ],
        'L11' => [
            'rotulo_referencia' => 'Lucro Líquido do Mês (Margem Líquida)',
            'sentido' => 'maior_melhor',
            'estado' => 'nao_calibrada',
            'origem' => null,
            'validado_em' => null,
            'verde' => ['min' => null, 'max' => null],
            'amarelo' => ['min' => null, 'max' => null],
            'vermelho' => ['min' => null, 'max' => null],
        ],
    ],
];

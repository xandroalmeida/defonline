<?php

declare(strict_types=1);

/**
 * Versionamento do motor de cálculo (IDR-010 §sub-decisão 1).
 *
 *   - version: semver inteiro `MAJOR.MINOR.PATCH`. Sobe quando muda comportamento
 *     do motor (fórmula, novo indicador, bug fix que altera output).
 *   - matrix_version: versão de origem da matriz (Anexo F). Depois da primeira
 *     edição de um segmento no backoffice, a versão dele é o mês da última edição
 *     (`mes-aaaa` — PDR-016, `App\Support\RecomendacoesNoBanco`).
 *
 * Estes valores são gravados em cada `diagnosticos.motor_version|matrix_version`
 * no momento da emissão, como snapshot — diagnósticos antigos não são
 * recalculados quando estas versões mudam.
 */
return [
    // 1.0.0 — STORY-028 (V1: 7 indicadores essenciais + NCG abs).
    // 1.1.0 — STORY-030 (V2: completa 14 do Anexo D + Ciclo Operacional informativo).
    // 1.2.0 — STORY-031 (resumo_executivo populado pelo algoritmo §4.7.1 — substitui placeholder).
    // 1.3.0 — STORY-032 (indicadores com farol falam matriz dez-2025 do Anexo F — substitui placeholder "Faixa verde/amarela/vermelha.").
    // 1.3.1 — STORY-041 (PATCH: veredito `em_alerta` corrige "demandam ação" → "demandam ações",
    //         alinhando ao texto canônico da spec §4.7.1. Altera output do motor porque
    //         `resumo_executivo.veredito_texto` entra no snapshot imutável — ver aditivo da IDR-010).
    // 1.3.2 — STORY-060 (PATCH: as três mensagens de faixa do NCG absoluto passam a
    //         carregar a intenção editorial da EBC do Anexo F §F.13 — elevação da NCG
    //         acompanhada, na mesma proporção, do aumento das fontes de financiamento
    //         de longo prazo, preferencialmente fornecedores. Texto, não fórmula: nenhum
    //         valor calculado muda. É PATCH pelo mesmo critério da 1.3.1 — altera output
    //         porque a mensagem entra no snapshot imutável (IDR-010).
    // 1.3.3 — STORY-060, revisão do PO em 06/08/2026 (PATCH: a faixa 1 do NCG absoluto
    //         deixa de dizer "folga operacional". A palavra era lida como "sobra", e o
    //         número diz o contrário — há dívida com fornecedor maior do que o que o
    //         ciclo devolve. O texto passou a nomear o financiamento do fornecedor e a
    //         dependência que ele cria. Só a mensagem da faixa 1 muda; nenhum valor,
    //         farol ou veredito é afetado.
    // 2.0.0 — PDR-014 (MAJOR: NCG/Vendas passa a dividir pela venda média mensal, e não pela
    //         anualizada, com limite amarelo/vermelho em 10,00; a faixa do NCG absoluto
    //         acompanha. Ciclo Operacional sai do motor, por pedido da EB Parcerias.)
    // 2.0.1 — PDR-014, correção do PO (PATCH: o limite amarelo/vermelho do NCG/Vendas volta
    //         de 0,10 para 10,00, que estava em produção por engano sob a 2.0.0. Muda farol e
    //         mensagem, não a fórmula. O NCG absoluto acompanha.)
    'version' => '2.0.1',
    'matrix_version' => 'dez-2025',

    // Path do arquivo de lacunas auto-append por MatrizRecomendacoes (CA-5/STORY-032).
    // Default: doc path do épico (visível ao PO). Pode ser sobrescrito via
    // env MOTOR_MATRIZ_LACUNAS_PATH para tmp em testes. String vazia = desliga I/O.
    'matriz_lacunas_path' => env(
        'MOTOR_MATRIZ_LACUNAS_PATH',
        base_path('../defonline-docs/project-state/epics/EPIC-002-diagnostico-industria/design/matriz-lacunas.md'),
    ),
];

<?php

declare(strict_types=1);

/**
 * Versionamento do motor de cálculo (IDR-010 §sub-decisão 1).
 *
 *   - version: semver inteiro `MAJOR.MINOR.PATCH`. Sobe quando muda comportamento
 *     do motor (fórmula, novo indicador, bug fix que altera output).
 *   - matrix_version: formato datado `mes-aaaa`. Sobe quando a EBC valida nova
 *     matriz de recomendações (Anexo F/G).
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
    'version' => '1.3.2',
    'matrix_version' => 'dez-2025',

    // Path do arquivo de lacunas auto-append por MatrizRecomendacoes (CA-5/STORY-032).
    // Default: doc path do épico (visível ao PO). Pode ser sobrescrito via
    // env MOTOR_MATRIZ_LACUNAS_PATH para tmp em testes. String vazia = desliga I/O.
    'matriz_lacunas_path' => env(
        'MOTOR_MATRIZ_LACUNAS_PATH',
        base_path('../defonline-docs/project-state/epics/EPIC-002-diagnostico-industria/design/matriz-lacunas.md'),
    ),
];

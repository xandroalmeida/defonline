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
    'version' => '1.3.0',
    'matrix_version' => 'dez-2025',

    // Path do arquivo de lacunas auto-append por MatrizRecomendacoes (CA-5/STORY-032).
    // Default: doc path do épico (visível ao PO). Pode ser sobrescrito via
    // env MOTOR_MATRIZ_LACUNAS_PATH para tmp em testes. String vazia = desliga I/O.
    'matriz_lacunas_path' => env(
        'MOTOR_MATRIZ_LACUNAS_PATH',
        base_path('../defonline-docs/project-state/epics/EPIC-002-diagnostico-industria/design/matriz-lacunas.md'),
    ),
];

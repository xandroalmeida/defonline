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
    'version' => '1.0.0',
    'matrix_version' => 'dez-2025',
];

<?php

declare(strict_types=1);

namespace App\Domain\Motor;

/**
 * Cores possíveis de farol de um indicador (espec V2.5 §4.5 + Anexo E).
 *
 *   - VERDE/AMARELO/VERMELHO: cores de classificação dos 13 indicadores com farol.
 *   - NENHUM: indicador informativo (NCG absoluto) **e** indicador "Indisponível"
 *     (denominador zero, dado faltante etc. — IDR-010 §sub-decisão 5).
 *
 * Não é um Enum para manter as chaves persistidas em `diagnosticos.indicadores_calculados`
 * estáveis ao longo do tempo (strings literais minúsculas, alinhadas ao texto da spec).
 *
 * @phpstan-type Cor 'verde' | 'amarelo' | 'vermelho' | 'nenhum'
 */
final class Farol
{
    public const VERDE = 'verde';

    public const AMARELO = 'amarelo';

    public const VERMELHO = 'vermelho';

    public const NENHUM = 'nenhum';
}

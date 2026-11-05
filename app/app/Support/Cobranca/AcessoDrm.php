<?php

declare(strict_types=1);

namespace App\Support\Cobranca;

use App\Domain\Cobranca\Bloqueio;
use App\Domain\Cobranca\Carteira;
use App\Models\CobrancaConfig;

/**
 * STORY-084 — o ÚNICO ponto da regra "novos lançamentos do Raio X exigem plano ou trial".
 * Telas de lançar, retificar e (STORY-051/056) qualquer ação nova que grave dados do DRM chamam
 * {@see self::bloqueioPara()}; a leitura do histórico nunca passa por aqui. Com o interruptor de
 * cobrança desligado (STORY-073) o acesso é sempre liberado.
 */
final class AcessoDrm
{
    /** `null` = liberado (interruptor desligado, plano ativo/cancelado dentro do ciclo, ou trial vigente). */
    public static function bloqueioPara(string $usuarioId): ?Bloqueio
    {
        if (! CobrancaConfig::ligada()) {
            return null;
        }

        return app(Carteira::class)->bloqueioDoDrm($usuarioId);
    }

    public static function permitido(string $usuarioId): bool
    {
        return self::bloqueioPara($usuarioId) === null;
    }
}

<?php

declare(strict_types=1);

namespace App\Console\Concerns;

use App\Infrastructure\Cobranca\Nenhum\ProvedorNaoConfigurado;
use App\Models\CobrancaConfig;
use Illuminate\Console\Command;

/**
 * STORY-093 CA-1 / D-10 — rotina de cobrança que precisa do provedor de pagamento. Sem provedor configurado e com a
 * cobrança desligada não há o que fazer: sai com sucesso, sem agir (em produção o beta roda assim até a cobrança
 * ser ligada). Com a cobrança ligada e sem provedor NÃO se silencia: a rotina segue e falha alto, com o motivo.
 *
 * @mixin Command
 */
trait SemProvedorNaoAge
{
    private function semProvedorECobrancaDesligada(): bool
    {
        if (! ProvedorNaoConfigurado::ativo() || CobrancaConfig::ligada()) {
            return false;
        }

        $this->info('Sem provedor de pagamento configurado e cobrança desligada: nada a fazer. ('.ProvedorNaoConfigurado::motivo().')');

        return true;
    }
}

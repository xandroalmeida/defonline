<?php

declare(strict_types=1);

namespace App\Features;

/**
 * Habilita o botão "Disparar e-mail de teste" do hello world.
 *
 * Exemplo de feature flag do MVP — guarda funcionalidade nova (esta página viva).
 * Cada flag carrega owner e data-limite obrigatórios (ADR-006 §Decisão 7).
 *
 * @owner programador
 *
 * @cleanup_due 2026-08-26
 */
final class HelloWorldEmailHabilitado
{
    /**
     * Resolução padrão da flag — sempre ligada no MVP.
     * Em homologação/produção a flag pode ser desabilitada via
     * `php artisan pennant:deactivate App\\Features\\HelloWorldEmailHabilitado`.
     */
    public function resolve(): bool
    {
        return true;
    }
}

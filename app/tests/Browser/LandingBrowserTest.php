<?php

declare(strict_types=1);

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use PHPUnit\Framework\Attributes\Group;
use Tests\DuskTestCase;

/**
 * STORY-024 CA-10 — smoke da landing pública contra URL real.
 *
 * Substitui o smoke `HelloWorldBrowserTest::test_visitor_sees_hello_page_with_version_and_ok_status`
 * — mantém o invariante "a rota `/` em homologação responde com algo público
 * reconhecível como produto" sem depender mais da página de debug.
 *
 * READ-ONLY por design (ADR-006): só visita `/` e checa elementos visíveis.
 * Nenhum submit, nenhum write no banco de homologação.
 */
final class LandingBrowserTest extends DuskTestCase
{
    #[Group('smoke')]
    public function test_visitor_sees_landing_with_logo_and_ctas(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/')
                ->assertSee('Diagnóstico estratégico')
                ->assertPresent('@landing-cta-cadastro')
                ->assertPresent('@landing-cta-login')
                ->assertPresent('@landing-header-logo')
                ->assertPresent('@footer-version');
        });
    }
}

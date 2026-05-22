<?php

declare(strict_types=1);

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use PHPUnit\Framework\Attributes\Group;
use Tests\DuskTestCase;

/**
 * Smoke pós-deploy do EPIC-001 — READ-ONLY por design (ADR-006 + lição
 * aprendida da rc.1: smoke não pode contaminar dados de homologação).
 *
 * Só visita as rotas públicas adicionadas pela STORY-011 e confere os
 * elementos visíveis críticos. Nenhum submit, nenhum write.
 */
final class CadastroLoginSmokeBrowserTest extends DuskTestCase
{
    #[Group('smoke')]
    public function test_cadastro_renderiza_form_visivel(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/cadastro')
                ->assertSee('Criar conta')
                ->assertPresent('@cadastro-cpf')
                ->assertPresent('@cadastro-email')
                ->assertPresent('@cadastro-senha')
                ->assertPresent('@cadastro-telefone')
                ->assertPresent('@cadastro-submit');
        });
    }

    #[Group('smoke')]
    public function test_login_renderiza_form_visivel(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/login')
                ->assertSee('Entrar')
                ->assertPresent('@login-email')
                ->assertPresent('@login-senha')
                ->assertPresent('@login-submit');
        });
    }
}

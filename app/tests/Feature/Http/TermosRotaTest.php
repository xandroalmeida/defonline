<?php

declare(strict_types=1);

/**
 * STORY-012 CA-4 / CA-5 — rotas públicas dos termos (sem login).
 */
it('serve /termos/termo-adesao sem autenticação, com versão e banner placeholder', function () {
    $this->get('/termos/termo-adesao')
        ->assertOk()
        ->assertSee('Termo de Adesão')
        ->assertSee('TEXTO PLACEHOLDER')
        ->assertSee('v1-placeholder')
        ->assertSee('dpo@ebparcerias.com');
});

it('serve /termos/politica-privacidade sem autenticação, com versão e banner placeholder', function () {
    $this->get('/termos/politica-privacidade')
        ->assertOk()
        ->assertSee('Política de Privacidade')
        ->assertSee('TEXTO PLACEHOLDER')
        ->assertSee('v1-placeholder')
        ->assertSee('LGPD');
});

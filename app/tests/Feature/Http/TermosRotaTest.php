<?php

declare(strict_types=1);

/**
 * STORY-012 CA-4 / CA-5 — rotas públicas dos termos (sem login).
 */
it('serve /termos/termo-adesao sem autenticação, na versão vigente e com as regras comerciais', function () {
    $this->get('/termos/termo-adesao')
        ->assertOk()
        ->assertSee('Termo de Adesão')
        ->assertDontSee('TEXTO PLACEHOLDER')
        ->assertSee('v2')
        ->assertSee('Trial gratuito')
        ->assertSee('Cupons de desconto')
        ->assertSee('Direito de arrependimento')
        ->assertSee('Raio X do Negócio')
        ->assertSee('dpo@ebparcerias.com');
});

it('serve /termos/politica-privacidade sem autenticação, na versão vigente e citando o provedor de pagamento', function () {
    $this->get('/termos/politica-privacidade')
        ->assertOk()
        ->assertSee('Política de Privacidade')
        ->assertDontSee('TEXTO PLACEHOLDER')
        ->assertSee('v2')
        ->assertSee('Asaas')
        ->assertSee('LGPD');
});

it('identifica a EB Parcerias nos Termos e no rodapé das telas (Decreto 7.962/2013)', function (string $rota) {
    $this->get($rota)
        ->assertOk()
        ->assertSee('EB Parcerias Ltda')
        ->assertSee('28.261.669/0001-56')
        ->assertSee('Fortaleza/CE');
})->with(['/termos/termo-adesao', '/termos/politica-privacidade', '/login']);

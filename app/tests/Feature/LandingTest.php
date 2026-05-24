<?php

declare(strict_types=1);

/**
 * STORY-024 CA-2..CA-11 — Landing pública `/`.
 *
 * Garante que a rota raiz mostra a landing pública (copy normativa, dois CTAs,
 * footer com versão) e não vaza nada da página de debug anterior (welcome +
 * hello-world da STORY-007).
 */
it('CA-2 — GET / responde 200 e renderiza a view landing', function () {
    $response = $this->get('/');

    $response->assertOk()
        ->assertViewIs('landing')
        ->assertSee('data-testid="landing-header"', escape: false)
        ->assertSee('data-testid="landing-main"', escape: false)
        ->assertSee('data-testid="landing-footer"', escape: false);
});

it('CA-2 — a rota raiz está nomeada como `landing`', function () {
    expect(route('landing'))->toBe(url('/'));
});

it('CA-4 — landing contém o <title> normativo', function () {
    $response = $this->get('/');

    $response->assertSee('<title>DEFOnline — diagnóstico estratégico para sua empresa</title>', escape: false);
});

it('CA-4 — landing contém o H1 normativo com ponto final', function () {
    $response = $this->get('/');

    $response->assertSee('Diagnóstico estratégico para sua empresa.');
});

it('CA-4 — landing contém o subtítulo normativo', function () {
    $response = $this->get('/');

    $response->assertSee('Respostas claras sobre como sua indústria está em 14 indicadores essenciais — em minutos, sem consultor caro, sem planilha.');
});

it('CA-4 — landing contém os dois CTAs com a copy exata e os hrefs corretos', function () {
    $response = $this->get('/');

    $response->assertSee('Criar conta grátis')
        ->assertSee('Já tenho conta')
        ->assertSee('href="'.route('cadastro').'"', escape: false)
        ->assertSee('href="'.route('login').'"', escape: false);
});

it('CA-4/CA-5 — landing tem o copyright literal no footer', function () {
    $response = $this->get('/');

    $response->assertSee('© DEFOnline '.now()->year);
});

it('CA-5 — header da landing tem logo + dois CTAs (Entrar + Criar conta)', function () {
    $response = $this->get('/');

    $response->assertSee('dusk="landing-header-logo"', escape: false)
        ->assertSee('dusk="landing-header-entrar"', escape: false)
        ->assertSee('dusk="landing-header-cadastro"', escape: false)
        ->assertSee('>Entrar<', escape: false)
        ->assertSee('>Criar conta<', escape: false);
});

it('CA-7 — landing NÃO contém vestígios da página de debug (hello, Healthcheck, Mailpit, request_id)', function () {
    $response = $this->get('/');

    $response->assertDontSee('hello DEFOnline')
        ->assertDontSee('Healthcheck')
        ->assertDontSee('Mailpit')
        ->assertDontSee('request_id')
        ->assertDontSee('STORY-')
        ->assertDontSee('Phase 1');
});

it('CA-8 — landing renderiza <x-footer-version> com a versão do APP_VERSION', function () {
    config()->set('app.version', 'v0.4.2-test');

    $response = $this->get('/');

    $response->assertSee('data-testid="footer-version"', escape: false)
        ->assertSee('v0.4.2-test');
});

it('CA-9 — landing tem main#conteudo, lang=pt-BR e h1 único', function () {
    $response = $this->get('/');

    $html = (string) $response->getContent();

    $response->assertSee('<html lang="pt-BR">', escape: false)
        ->assertSee('id="conteudo"', escape: false);

    expect(substr_count($html, '<h1'))->toBe(1, 'A landing deve ter exatamente um <h1>');
});

it('CA-10 — landing tem os atributos dusk dos dois CTAs do corpo', function () {
    $response = $this->get('/');

    $response->assertSee('dusk="landing-cta-cadastro"', escape: false)
        ->assertSee('dusk="landing-cta-login"', escape: false);
});

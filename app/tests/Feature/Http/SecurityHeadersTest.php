<?php

declare(strict_types=1);

/**
 * ADR-009 — no ZalkVPS o Caddy do host não injeta cabeçalhos de segurança;
 * o middleware SecurityHeaders assume o que o Caddyfile próprio fazia (ADR-005).
 */
it('envia os cabeçalhos de segurança em toda resposta', function () {
    $this->get('/health')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
});

it('envia HSTS quando a request chega via HTTPS pelo proxy', function () {
    $this->withServerVariables([
        'HTTP_X_FORWARDED_PROTO' => 'https',
        'HTTP_X_FORWARDED_HOST' => 'defonline.zalk.com.br',
        'HTTPS' => 'off',
    ])->get('http://defonline.zalk.com.br/health')
        ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
});

it('não envia HSTS em HTTP puro — dev local intocado', function () {
    $this->get('/health')->assertHeaderMissing('Strict-Transport-Security');
});

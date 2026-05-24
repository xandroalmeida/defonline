<?php

declare(strict_types=1);

use Illuminate\Support\Facades\URL;

/**
 * Regressão do Mixed Content em homologação (2026-05-24, descoberto no smoke
 * manual da STORY-018).
 *
 * Cenário do bug: Caddy faz TLS termination e envia `X-Forwarded-Proto: https`
 * para o container `web`, que internamente recebe a conexão como HTTP. Sem
 * `trustProxies` registrado em `bootstrap/app.php`, o Laravel ignora o header,
 * `$request->isSecure()` retorna `false`, e `asset()`/`url()` geram URLs
 * `http://...`. Livewire injeta `<script src="http://.../livewire.min.js">`
 * dentro de uma página servida via HTTPS → browser bloqueia (Mixed Content).
 *
 * Estes testes garantem que o trust funciona end-to-end: enviar a request com
 * cabeçalho proxy → request reportada como `isSecure()` → asset URLs https.
 */
it('confia em X-Forwarded-Proto=https ao construir URLs (CA hist. — Mixed Content fix)', function () {
    $response = $this->withServerVariables([
        'HTTP_X_FORWARDED_PROTO' => 'https',
        'HTTP_X_FORWARDED_HOST' => 'defonline.xandrix.com.br',
        'HTTPS' => 'off',
        'REMOTE_ADDR' => '172.20.0.5', // IP arbitrário simulando proxy upstream
    ])->get('http://defonline.xandrix.com.br/health');

    $response->assertOk();

    // Reabre a request gerada para inspecionar — em runtime real isto é o que
    // Livewire/asset() chamam para descobrir o scheme.
    expect(request()->isSecure())->toBeTrue();
    expect(request()->getScheme())->toBe('https');
    expect(URL::asset('livewire/livewire.min.js'))->toStartWith('https://');
});

it('respeita X-Forwarded-Host para o host da URL', function () {
    $this->withServerVariables([
        'HTTP_X_FORWARDED_PROTO' => 'https',
        'HTTP_X_FORWARDED_HOST' => 'defonline.xandrix.com.br',
        'HTTPS' => 'off',
    ])->get('http://defonline.xandrix.com.br/health');

    expect(request()->getHost())->toBe('defonline.xandrix.com.br');
    expect(URL::asset('app.css'))->toBe('https://defonline.xandrix.com.br/app.css');
});

it('sem header X-Forwarded-Proto, comportamento default (http) permanece — local dev intocado', function () {
    // Sem proxy: Laravel deve continuar reportando o scheme da conexão direta.
    // Isso garante que o desenvolvedor rodando `php artisan serve` localmente
    // não tem URLs HTTPS quebradas.
    $this->get('http://localhost/health');

    expect(request()->isSecure())->toBeFalse();
    expect(request()->getScheme())->toBe('http');
});

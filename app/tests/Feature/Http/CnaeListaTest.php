<?php

declare(strict_types=1);

use App\Models\Usuario;

it('exige login para baixar a lista de CNAEs', function () {
    $this->get('/cnaes')->assertRedirect('/login');
});

it('entrega a lista de CNAEs cacheável com ETag e responde 304 quando não mudou', function () {
    $this->actingAs(Usuario::factory()->create());

    $r = $this->get('/cnaes')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/json; charset=utf-8')
        ->assertJsonFragment(['codigo' => '1622699']);

    expect(count($r->json()))->toBeGreaterThan(1000);
    expect($r->headers->get('Cache-Control'))->toContain('max-age=86400');

    $this->get('/cnaes', ['If-None-Match' => $r->headers->get('ETag')])->assertStatus(304);
});

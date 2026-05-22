<?php

declare(strict_types=1);

it('returns 200 from /health without touching the database', function () {
    $response = $this->get('/health');

    $response->assertOk();
    $response->assertJson([
        'status' => 'ok',
    ]);
    expect($response->json('service'))->not->toBeEmpty();
    expect($response->json('version'))->not->toBeEmpty();
});

it('returns 200 from /ready when dependencies are healthy', function () {
    $response = $this->get('/ready');

    $response->assertOk();
    $response->assertJson([
        'status' => 'ok',
    ]);
    expect($response->json('checks'))->toBeArray()->not->toBeEmpty();
});

it('propagates X-Request-Id from the request header into the response', function () {
    $requestId = '0190b1aa-1111-7222-8333-444444444444';

    $response = $this->withHeaders(['X-Request-Id' => $requestId])->get('/health');

    $response->assertHeader('X-Request-Id', $requestId);
});

it('generates a fresh X-Request-Id when none is provided', function () {
    $response = $this->get('/health');

    $header = $response->headers->get('X-Request-Id');
    expect($header)->not->toBeNull();
    expect($header)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/');
});

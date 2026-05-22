<?php

declare(strict_types=1);

use App\Jobs\HelloWorldEmail;
use App\Support\RequestId;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;

it('propagates request_id from dispatcher into job meta', function () {
    $requestId = '0190b1cc-cccc-7ccc-8ccc-cccccccccccc';
    RequestId::set($requestId);

    Bus::fake();
    HelloWorldEmail::dispatch('test@defonline.local');

    Bus::assertDispatched(
        HelloWorldEmail::class,
        fn (HelloWorldEmail $job) => ($job->meta['request_id'] ?? null) === $requestId,
    );
});

it('records the request_id in request_metrics for non-health routes', function () {
    DB::table('request_metrics')->truncate();

    $this->withHeaders(['X-Request-Id' => '0190b1dd-dddd-7ddd-8ddd-dddddddddddd'])
        ->get('/ready')
        ->assertOk();

    $row = DB::table('request_metrics')->orderByDesc('id')->first();
    expect($row)->not->toBeNull();
    expect($row->request_id)->toBe('0190b1dd-dddd-7ddd-8ddd-dddddddddddd');
    expect($row->path)->toBe('/ready');
});

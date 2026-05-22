<?php

declare(strict_types=1);

use App\Support\RequestId;

afterEach(function () {
    RequestId::reset();
});

it('returns the current RequestId via the global request_id() helper', function () {
    $custom = '0190b1aa-0000-7000-8000-000000000000';
    RequestId::set($custom);

    expect(request_id())->toBe($custom);
});

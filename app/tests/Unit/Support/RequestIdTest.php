<?php

declare(strict_types=1);

use App\Support\RequestId;

afterEach(function () {
    RequestId::reset();
});

it('generates a valid UUID v7', function () {
    $id = RequestId::generate();
    expect(RequestId::isValid($id))->toBeTrue();
});

it('returns the same value across multiple get calls in the same request', function () {
    RequestId::reset();
    $first = RequestId::get();
    $second = RequestId::get();
    expect($first)->toBe($second);
});

it('accepts a manually set request_id', function () {
    $custom = '0190b1aa-0000-7000-8000-000000000000';
    RequestId::set($custom);
    expect(RequestId::get())->toBe($custom);
});

it('validates the scheduler prefix variant', function () {
    expect(RequestId::isValid('sched:0190b1aa-0000-7000-8000-000000000000'))->toBeTrue();
});

it('rejects malformed candidates', function () {
    expect(RequestId::isValid('not-a-uuid'))->toBeFalse();
    expect(RequestId::isValid('0190b1aa-0000-4000-8000-000000000000'))->toBeFalse(); // v4
});

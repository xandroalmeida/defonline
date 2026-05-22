<?php

declare(strict_types=1);

use App\Features\HelloWorldEmailHabilitado;

it('resolves to true by default in the MVP', function () {
    expect((new HelloWorldEmailHabilitado)->resolve())->toBeTrue();
});

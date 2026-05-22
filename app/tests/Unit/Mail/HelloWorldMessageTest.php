<?php

declare(strict_types=1);

use App\Mail\HelloWorldMessage;

it('builds an envelope with the request_id in the subject', function () {
    $msg = new HelloWorldMessage(requestId: '0190b1aa-0000-7000-8000-000000000000');

    $envelope = $msg->envelope();

    expect($envelope->subject)
        ->toContain('Hello world')
        ->toContain('0190b1aa-0000-7000-8000-000000000000');
});

it('renders an HTML body mentioning the worker and the request_id', function () {
    $msg = new HelloWorldMessage(requestId: 'sched:0190b1aa-0000-7000-8000-000000000000');

    $content = $msg->content();

    expect($content->htmlString)
        ->toContain('Hello DEFOnline')
        ->toContain('worker')
        ->toContain('sched:0190b1aa-0000-7000-8000-000000000000');
});

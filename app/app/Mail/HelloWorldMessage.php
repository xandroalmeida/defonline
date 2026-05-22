<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class HelloWorldMessage extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly string $requestId)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[DEFOnline] Hello world — request '.$this->requestId,
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: '<p>Hello DEFOnline.</p>'.
                '<p>Este e-mail foi enviado por um <strong>job assíncrono</strong> no worker.</p>'.
                "<p><code>request_id</code>: {$this->requestId}</p>",
        );
    }
}

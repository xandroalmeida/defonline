<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** STORY-079 / espec §3.7 e §4.12 — lembrete de renovação, 7 dias antes do fim do ciclo. */
final class RenovacaoProximaMessage extends Mailable
{
    use SerializesModels;

    public function __construct(
        public readonly string $nome,
        public readonly string $plano,
        public readonly string $ciclo,
        public readonly string $valor,
        public readonly string $data,
        public readonly string $link,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Seu plano renova em {$this->data} — DEFOnline");
    }

    public function content(): Content
    {
        return new Content(view: 'mail.renovacao-proxima', text: 'mail.renovacao-proxima-texto');
    }
}

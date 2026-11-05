<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** STORY-079 CA-5 — o ciclo foi encerrado depois da última tentativa: histórico preservado, convite a reassinar. */
final class AssinaturaEncerradaMessage extends Mailable
{
    use SerializesModels;

    public function __construct(
        public readonly string $nome,
        public readonly string $plano,
        public readonly string $link,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Seu plano foi encerrado — DEFOnline');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.assinatura-encerrada', text: 'mail.assinatura-encerrada-texto');
    }
}

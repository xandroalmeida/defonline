<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** STORY-100 / espec §4.12 — link para redefinir a senha, válido por 30 minutos. */
final class RedefinicaoSenhaMessage extends Mailable implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $nome,
        public readonly string $link,
        public readonly int $minutos,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Redefina sua senha — DEFOnline');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.redefinicao-senha', text: 'mail.redefinicao-senha-texto');
    }
}

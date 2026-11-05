<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** STORY-076 CA-2 — link do checkout de um pacote de créditos. */
final class LinkDePagamentoMessage extends Mailable
{
    use SerializesModels;

    public function __construct(
        public readonly string $nome,
        public readonly string $descricao,
        public readonly string $valor,
        public readonly string $link,
        public readonly string $expiraEm,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Seu Pix para pagar — DEFOnline');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.link-de-pagamento');
    }
}

<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** STORY-076 CA-2 / STORY-078 — link do checkout de um pacote de créditos (Pix ou cartão). */
final class LinkDePagamentoMessage extends Mailable
{
    use SerializesModels;

    public function __construct(
        public readonly string $nome,
        public readonly string $descricao,
        public readonly string $valor,
        public readonly string $link,
        public readonly string $expiraEm,
        public readonly bool $cartao = false,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->cartao ? 'Seu pagamento com cartão — DEFOnline' : 'Seu Pix para pagar — DEFOnline');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.link-de-pagamento');
    }
}

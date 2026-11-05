<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** STORY-079 CA-3 / espec §4.12 — a renovação foi recusada: avisa e leva a atualizar o cartão. */
final class FalhaDeCobrancaMessage extends Mailable
{
    use SerializesModels;

    public function __construct(
        public readonly string $nome,
        public readonly string $plano,
        public readonly string $valor,
        public readonly string $proximaTentativa,
        public readonly string $ultimaTentativa,
        public readonly string $link,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Não conseguimos cobrar a renovação do seu plano — DEFOnline');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.falha-de-cobranca', text: 'mail.falha-de-cobranca-texto');
    }
}

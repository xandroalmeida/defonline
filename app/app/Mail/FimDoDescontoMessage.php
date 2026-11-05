<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** STORY-083 CA-8 — aviso 7 dias antes do fim do desconto do cupom (spec STORY-070-cupom-no-checkout §4). */
final class FimDoDescontoMessage extends Mailable
{
    use SerializesModels;

    public function __construct(
        public readonly string $nome,
        public readonly string $dataFim,
        public readonly string $precoDepois,
        public readonly bool $semCartao,
        public readonly string $link,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Seu desconto termina em {$this->dataFim} — DEFOnline");
    }

    public function content(): Content
    {
        return new Content(view: 'mail.fim-do-desconto', text: 'mail.fim-do-desconto-texto');
    }
}

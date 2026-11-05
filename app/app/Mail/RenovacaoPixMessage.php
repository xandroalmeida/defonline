<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * STORY-080 CA-4 / spec STORY-070-checkout-pix-anual — aviso de que o plano anual pago com Pix termina
 * em breve, não renova sozinho e como pagar a renovação. Diz a data, não só "em breve".
 */
final class RenovacaoPixMessage extends Mailable
{
    use SerializesModels;

    public function __construct(
        public readonly string $nome,
        public readonly string $plano,
        public readonly string $dataFim,
        public readonly string $novoFim,
        public readonly string $valor,
        public readonly string $link,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Seu plano anual termina em {$this->dataFim} — DEFOnline");
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.renovacao-pix',
            text: 'mail.renovacao-pix-texto',
        );
    }
}

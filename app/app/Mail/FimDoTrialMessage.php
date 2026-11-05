<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * STORY-074 CA-6 / espec §4.12 — convite para assinar, em D-2 e em D-0 do fim do trial. Mesmo texto e
 * mesmo CTA da Home (spec STORY-070-trial §3). Sem pressão: nada de contagem nem "oferta limitada".
 */
final class FimDoTrialMessage extends Mailable
{
    use SerializesModels;

    public function __construct(
        public readonly string $nome,
        public readonly string $dataFim,
        /** 2 (faltam dois dias) ou 0 (termina hoje). */
        public readonly int $dias,
        public readonly string $link,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->dias === 0 ? 'Seu teste termina hoje — DEFOnline' : "Seu teste termina em {$this->dias} dias — DEFOnline",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.fim-do-trial',
            text: 'mail.fim-do-trial-texto',
        );
    }
}

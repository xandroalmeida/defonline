<?php

declare(strict_types=1);

namespace App\Mail;

use App\Domain\Captacao\PrazoRetorno;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** STORY-102 / PDR-021 — confirma ao cliente que a demanda de captação chegou e quando terá retorno. */
final class DemandaCaptacaoRecebidaMessage extends Mailable
{
    use SerializesModels;

    public readonly int $diasUteis;

    public function __construct(
        public readonly string $nome,
        public readonly string $empresa,
    ) {
        $this->diasUteis = PrazoRetorno::DIAS_UTEIS;
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Recebemos sua demanda de captação — DEFOnline');
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.demanda-captacao-recebida',
            text: 'mail.demanda-captacao-recebida-texto',
        );
    }
}

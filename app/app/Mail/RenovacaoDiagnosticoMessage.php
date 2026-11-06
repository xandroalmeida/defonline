<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;

/** STORY-099 / espec §4.12.1 — aviso para renovar o diagnóstico de uma empresa, com descadastro. */
final class RenovacaoDiagnosticoMessage extends Mailable
{
    use Queueable;

    public function __construct(
        public readonly string $nome,
        public readonly string $empresa,
        public readonly string $ultimoEm,
        public readonly string $link,
        public readonly string $linkDescadastro,
        public readonly bool $lembrete,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: ($this->lembrete ? 'Lembrete: renove' : 'Hora de renovar')." o diagnóstico de {$this->empresa} — DEFOnline");
    }

    public function headers(): Headers
    {
        return new Headers(text: ['List-Unsubscribe' => "<{$this->linkDescadastro}>"]);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.renovacao-diagnostico', text: 'mail.renovacao-diagnostico-texto');
    }
}

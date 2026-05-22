<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * STORY-013 CA-2 — email transacional de confirmação de cadastro.
 *
 * Conteúdo simples e direto (sem branding pesado nesta primeira versão):
 * saudação personalizada, link clicável de uso único (signedRoute), aviso de
 * expiração em 60 min. O `$link` já vem pronto (assinado, com TTL aplicado).
 */
final class EmailConfirmacaoMessage extends Mailable
{
    use SerializesModels;

    public function __construct(
        public readonly string $nome,
        public readonly string $link,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Confirme seu email — DEFOnline',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.email-confirmacao',
        );
    }
}

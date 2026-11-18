<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** STORY-102 / PDR-021 — avisa a equipe de uma nova demanda de captação, com o contato do cliente. */
final class DemandaCaptacaoEquipeMessage extends Mailable
{
    use SerializesModels;

    /**
     * @param  list<string>  $socios
     */
    public function __construct(
        public readonly string $empresa,
        public readonly string $documento,
        public readonly string $usuarioNome,
        public readonly string $usuarioEmail,
        public readonly ?string $usuarioTelefone,
        public readonly ?string $empresaEmail,
        public readonly ?string $empresaTelefone,
        public readonly ?string $valorCaptar,
        public readonly ?string $endividamento,
        public readonly ?string $vendaCartao,
        public readonly array $socios,
        public readonly string $prazo,
        public readonly string $link,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Nova demanda de captação: {$this->empresa}");
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.demanda-captacao-equipe',
            text: 'mail.demanda-captacao-equipe-texto',
        );
    }
}

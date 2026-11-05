<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Cobranca\EstadoCobranca;
use App\Mail\LinkDePagamentoMessage;
use App\Models\Invoice;
use App\Models\Usuario;
use Illuminate\Support\Facades\Mail;

/** STORY-076 CA-2 — manda o link do checkout por e-mail, para quem fechou a tela. */
final class EnviarEmailPagamento extends BaseJob
{
    public int $tries = 3;

    public function __construct(public readonly string $invoiceId)
    {
        parent::__construct();
    }

    protected function handleJob(): void
    {
        $invoice = Invoice::query()->find($this->invoiceId);
        $usuario = $invoice === null ? null : Usuario::query()->find($invoice->usuario_id);

        // Pagou, expirou ou sumiu entre o dispatch e a execução: o link não serve mais.
        if ($invoice === null || $usuario === null || $invoice->status !== EstadoCobranca::Pendente || $invoice->checkout_url === null || $invoice->expirada()) {
            return;
        }

        Mail::to($usuario->email)->send(new LinkDePagamentoMessage(
            nome: $usuario->primeiroNome(),
            descricao: $invoice->descricao,
            valor: 'R$ '.number_format($invoice->valor_centavos / 100, 2, ',', '.'),
            link: $invoice->checkout_url,
            expiraEm: $invoice->checkout_expira_em?->setTimezone('America/Sao_Paulo')->format('H:i') ?? '',
            cartao: $invoice->meio === 'cartao',
        ));
    }
}

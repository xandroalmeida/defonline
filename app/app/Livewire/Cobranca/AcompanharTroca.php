<?php

declare(strict_types=1);

namespace App\Livewire\Cobranca;

use App\Models\Assinatura;
use App\Models\CobrancaConfig;
use App\Models\Invoice;
use App\Models\Plano;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * STORY-082 CA-2 — volta do checkout da diferença do upgrade. Só LÊ: quem aplica a troca é o webhook
 * (`cobranca.paga`); o retorno do navegador nunca libera nada (ADR-010). Recusado, expirado ou cancelado,
 * o plano atual segue.
 */
#[Layout('components.layouts.app')]
final class AcompanharTroca extends Component
{
    public string $invoiceId;

    public function mount(string $invoice): void
    {
        abort_unless(CobrancaConfig::ligada(), 404);

        $this->invoiceId = $this->invoice($invoice)->id;
    }

    public function render(): View
    {
        $invoice = $this->invoice($this->invoiceId);
        $assinatura = $invoice->assinatura_id === null ? null : Assinatura::query()->find($invoice->assinatura_id);

        return view('livewire.cobranca.acompanhar-troca', [
            'invoice' => $invoice,
            'assinatura' => $assinatura,
            'destino' => $invoice->plano_id === null ? null : Plano::query()->find($invoice->plano_id),
            'atual' => $assinatura?->plano_id === null ? null : Plano::query()->find($assinatura->plano_id),
            // A troca valeu se a assinatura já está no plano e no ciclo da Invoice.
            'aplicada' => $assinatura !== null && $assinatura->plano_id === $invoice->plano_id && $assinatura->ciclo === $invoice->ciclo && $invoice->status->value === 'paga',
        ])->layoutData(['title' => 'Troca de plano']);
    }

    private function invoice(string $id): Invoice
    {
        return Invoice::query()
            ->where('usuario_id', Auth::id())
            ->where('tipo', 'troca_de_plano')
            ->findOrFail($id);
    }
}

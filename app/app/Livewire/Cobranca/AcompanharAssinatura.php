<?php

declare(strict_types=1);

namespace App\Livewire\Cobranca;

use App\Domain\Cobranca\MeioPagamento;
use App\Models\Assinatura;
use App\Models\CobrancaConfig;
use App\Models\Invoice;
use App\Models\Plano;
use App\Services\Cobranca\ConferirPagamentoAtrasado;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * STORY-077 CA-2, CA-3 / STORY-080 — volta do checkout de assinatura (cartão, Pix anual ou renovação por Pix). Só LÊ: quem ativa a assinatura é o webhook
 * (`cobranca.paga`); o retorno do navegador nunca libera nada (ADR-010). Atualiza por polling até
 * a confirmação, a recusa ou o fim do prazo.
 */
#[Layout('components.layouts.app')]
final class AcompanharAssinatura extends Component
{
    public string $invoiceId;

    public function mount(string $invoice): void
    {
        abort_unless(CobrancaConfig::ligada(), 404);

        // Compra de outro usuário (ou inexistente): 404, como o resto do produto (IDR-009).
        $this->invoiceId = $this->invoice($invoice)->id;
    }

    /** STORY-101 — a cada ciclo do polling: se o aviso do provedor atrasou, confere direto com ele. */
    public function conferir(ConferirPagamentoAtrasado $conferir): void
    {
        $conferir->executar($this->invoice($this->invoiceId));
    }

    public function render(): View
    {
        $invoice = $this->invoice($this->invoiceId);
        $assinatura = $invoice->assinatura_id === null ? null : Assinatura::query()->find($invoice->assinatura_id);

        return view('livewire.cobranca.acompanhar-assinatura', [
            'invoice' => $invoice,
            'assinatura' => $assinatura,
            'pix' => $invoice->meio === MeioPagamento::Pix->value,
            'urlDeNovo' => $this->urlDeNovo($invoice),
            'plano' => $invoice->plano_id === null ? null : Plano::query()->find($invoice->plano_id),
            'temMeuPlano' => Route::has('meu-plano'),
        ])->layoutData(['title' => 'Assinatura']);
    }

    /** Onde "Tentar de novo" / "Gerar novo QR Code" leva: nova contratação ou, na renovação por Pix, a tela de renovação. */
    private function urlDeNovo(Invoice $invoice): string
    {
        if ($invoice->renovacao) {
            return route('assinatura.renovar');
        }

        return route('checkout.assinatura', ['plano' => $invoice->plano_id, 'ciclo' => $invoice->ciclo] + ($invoice->meio === MeioPagamento::Pix->value ? ['meio' => 'pix'] : []));
    }

    private function invoice(string $id): Invoice
    {
        return Invoice::query()
            ->where('usuario_id', Auth::id())
            ->where('tipo', 'assinatura')
            // Renovação por cartão nasce já paga, sem tela de retorno; a renovação por Pix tem (STORY-080).
            ->where(fn ($q) => $q->where('renovacao', false)->orWhere('meio', MeioPagamento::Pix->value))
            ->findOrFail($id);
    }
}

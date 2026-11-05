<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Cobranca\PaymentProvider;
use App\Infrastructure\Cobranca\Fake\FakePaymentProvider;
use App\Models\Invoice;
use App\Services\Cobranca\ReceberWebhook;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * STORY-076 / ADR-010 §6 — "página do provedor" do adaptador fake, para dev local e E2E:
 * simula o banco confirmando o Pix ou o prazo vencendo. Só existe com o provedor `fake` ativo e
 * nunca em produção; fora disso responde 404 como se não existisse.
 */
final class PagamentoFakeController extends Controller
{
    public function mostrar(Request $request, Invoice $invoice, PaymentProvider $provedor): View
    {
        $this->exigirFake($request, $invoice, $provedor);

        return view('pagamentos.fake', ['invoice' => $invoice]);
    }

    public function simular(Request $request, Invoice $invoice, PaymentProvider $provedor, ReceberWebhook $receber): RedirectResponse
    {
        $fake = $this->exigirFake($request, $invoice, $provedor);
        $checkout = (string) $invoice->checkout_id_externo;

        $webhook = match ($request->input('acao')) {
            'pagar' => $fake->simularPagamento($checkout),
            'expirar' => $fake->simularExpiracao($checkout),
            'recusar' => $invoice->tipo === 'assinatura' ? $fake->simularRecusa($checkout) : abort(422),
            default => abort(422),
        };

        $receber->tratar($webhook);

        return redirect()->route($invoice->tipo === 'assinatura' ? 'assinatura.retorno' : 'creditos.retorno', $invoice);
    }

    private function exigirFake(Request $request, Invoice $invoice, PaymentProvider $provedor): FakePaymentProvider
    {
        abort_unless(
            $provedor instanceof FakePaymentProvider
                && ! app()->environment('production')
                && $invoice->provedor === FakePaymentProvider::NOME
                && $invoice->usuario_id === $request->user()?->id
                && $invoice->checkout_id_externo !== null,
            404,
        );

        return $provedor;
    }
}

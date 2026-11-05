<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Cobranca\PaymentProvider;
use App\Domain\Cobranca\TipoEvento;
use App\Infrastructure\Cobranca\Fake\FakePaymentProvider;
use App\Models\Invoice;
use App\Models\TrocaDeCartao;
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

    /** STORY-081 — a página do cartão novo (troca de cartão): mesma ideia, outro registro. */
    public function mostrarTroca(Request $request, TrocaDeCartao $troca, PaymentProvider $provedor): View
    {
        $this->exigirFakeDaTroca($request, $troca, $provedor);

        return view('pagamentos.fake-troca', ['troca' => $troca]);
    }

    public function simularTroca(Request $request, TrocaDeCartao $troca, PaymentProvider $provedor, ReceberWebhook $receber): RedirectResponse
    {
        $fake = $this->exigirFakeDaTroca($request, $troca, $provedor);
        $checkout = (string) $troca->checkout_id_externo;

        match ($request->input('acao')) {
            'pagar' => $this->cartaoAceito($fake, $checkout, $receber),
            'recusar' => $receber->tratar($fake->simularRecusa($checkout)),
            'expirar' => $receber->tratar($fake->simularExpiracao($checkout)),
            default => abort(422),
        };

        return redirect()->route('meu-plano', ['cartao' => 'retorno']);
    }

    /** O provedor conclui o checkout e, se a 1ª cobrança é hoje (assinatura em atraso), o banco a aprova. */
    private function cartaoAceito(FakePaymentProvider $fake, string $checkout, ReceberWebhook $receber): void
    {
        $receber->tratar($fake->webhook(TipoEvento::CheckoutConcluido, $checkout));

        if ($fake->cobraNaHora($checkout)) {
            $receber->tratar($fake->simularPagamento($checkout));
        }
    }

    private function exigirFakeDaTroca(Request $request, TrocaDeCartao $troca, PaymentProvider $provedor): FakePaymentProvider
    {
        abort_unless(
            $provedor instanceof FakePaymentProvider
                && ! app()->environment('production')
                && $troca->provedor === FakePaymentProvider::NOME
                && $troca->usuario_id === $request->user()?->id
                && $troca->checkout_id_externo !== null,
            404,
        );

        return $provedor;
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

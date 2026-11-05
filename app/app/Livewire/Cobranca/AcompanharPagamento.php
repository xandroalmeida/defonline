<?php

declare(strict_types=1);

namespace App\Livewire\Cobranca;

use App\Domain\Cobranca\EstadoCobranca;
use App\Domain\Cobranca\PaymentProvider;
use App\Domain\Cobranca\ProvedorIndisponivel;
use App\Domain\Cobranca\ReferenciaProvedor;
use App\Models\CobrancaConfig;
use App\Models\Invoice;
use App\Models\LoteCredito;
use App\Models\QuizRascunho;
use App\Models\Usuario;
use App\Support\Personificacao;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * STORY-076 CA-3, CA-5 — tela de retorno do checkout. Só LÊ a Invoice: quem a marca como paga é o
 * webhook (ADR-010: o retorno do navegador nunca libera crédito). Atualiza sozinha por polling.
 */
#[Layout('components.layouts.app')]
final class AcompanharPagamento extends Component
{
    public string $invoiceId;

    public bool $cancelamentoFalhou = false;

    public function mount(string $invoice): void
    {
        abort_unless(CobrancaConfig::ligada(), 404);

        // Compra de outro usuário (ou inexistente): 404, como o resto do produto (IDR-009).
        $this->invoiceId = $this->invoice($invoice)->id;
    }

    public function cancelar(PaymentProvider $provedor): mixed
    {
        abort_unless(CobrancaConfig::ligada(), 404);

        if (Personificacao::ativa() !== null) {
            return null;
        }

        $invoice = $this->invoice($this->invoiceId);

        if ($invoice->status === EstadoCobranca::Pendente && $invoice->checkout_id_externo !== null) {
            try {
                $provedor->cancelCheckout(new ReferenciaProvedor($invoice->provedor, $invoice->checkout_id_externo));
            } catch (ProvedorIndisponivel $e) {
                // Não deu para cancelar lá: a compra continua pendente e o prazo a encerra sozinho.
                Log::warning('cobranca.cancelamento_falhou', ['module' => 'cobranca', 'invoice_id' => $invoice->id, 'motivo' => $e->getMessage()]);
                $this->cancelamentoFalhou = true;

                return null;
            }

            DB::transaction(function () use ($invoice): void {
                $atual = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);

                if ($atual->status === EstadoCobranca::Pendente) {
                    $atual->forceFill(['status' => EstadoCobranca::Cancelada, 'motivo_encerramento' => 'cancelado'])->save();
                }
            });
        }

        return $this->redirectRoute('creditos.comprar');
    }

    /** "Gerar novo QR Code": volta à revisão do mesmo pacote com o aceite já marcado (spec §4.4). */
    public function novoCodigo(): mixed
    {
        $invoice = $this->invoice($this->invoiceId);
        session()->flash('aceite_previo', true);

        return $this->redirectRoute('creditos.comprar', ['pacote' => $invoice->pacote_credito_id]);
    }

    public function render(): View
    {
        $invoice = $this->invoice($this->invoiceId);
        $lote = $invoice->status === EstadoCobranca::Paga ? LoteCredito::query()->where('invoice_id', $invoice->id)->first() : null;
        $rascunho = $lote === null ? null : QuizRascunho::query()->where('expires_at', '>', now())->latest('updated_at')->first();

        return view('livewire.cobranca.acompanhar-pagamento', [
            'invoice' => $invoice,
            'lote' => $lote,
            'rascunho' => $rascunho,
            'personificando' => Personificacao::ativa() !== null,
            'email' => Auth::user() instanceof Usuario ? Auth::user()->email : '',
            'minutosRestantes' => $invoice->checkout_expira_em === null ? 0 : max(0, (int) ceil(now()->diffInMinutes($invoice->checkout_expira_em, false))),
            'temMeuPlano' => Route::has('meu-plano'),
        ])->layoutData(['title' => 'Pagamento']);
    }

    private function invoice(string $id): Invoice
    {
        return Invoice::query()
            ->where('usuario_id', Auth::id())
            ->where('tipo', 'credito')
            ->findOrFail($id);
    }
}

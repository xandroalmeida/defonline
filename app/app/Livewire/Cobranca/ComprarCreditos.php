<?php

declare(strict_types=1);

namespace App\Livewire\Cobranca;

use App\Domain\Cobranca\EstadoCobranca;
use App\Domain\Cobranca\MeioPagamento;
use App\Domain\Cobranca\ProvedorIndisponivel;
use App\Jobs\EnviarEmailConfirmacao;
use App\Models\CobrancaConfig;
use App\Models\Invoice;
use App\Models\PacoteCredito;
use App\Models\Usuario;
use App\Services\Cobranca\CompraNaoPermitida;
use App\Services\Cobranca\CupomRecusado;
use App\Services\Cobranca\IniciarCompraDeCreditos;
use App\Services\Cobranca\ItemDeCompra;
use App\Support\Personificacao;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * STORY-076 CA-1, CA-2, CA-6, CA-7, CA-10 — revisão do pedido de um pacote de créditos e entrada no
 * checkout (spec STORY-070-checkout-pix-creditos §3.1). Sem `{pacote}` lista os pacotes ativos,
 * até a página de planos (STORY-075) ser o ponto de entrada. Com a cobrança desligada, a rota se
 * comporta como inexistente.
 */
#[Layout('components.layouts.app')]
final class ComprarCreditos extends Component
{
    use ComCupom;

    public ?string $pacoteId = null;

    public bool $aceito = false;

    /** `pix` ou `cartao` (STORY-078; spec: Pix vem selecionado). Aceita `?meio=` para voltar já no cartão. */
    #[Url(as: 'meio')]
    public string $meio = 'pix';

    /** O provedor não atendeu: a tela mostra o aviso com "Tentar de novo". */
    public bool $falhaDoProvedor = false;

    public bool $confirmacaoReenviada = false;

    /** Marcou o aceite: o erro de quando faltava some na hora. */
    public function updatedAceito(): void
    {
        if ($this->aceito) {
            $this->resetErrorBag('aceito');
        }
    }

    public function mount(?string $pacote = null): void
    {
        abort_unless(CobrancaConfig::ligada(), 404);

        $this->pacoteId = $pacote;
        // Vindo de "Gerar novo QR Code": o aceite já dado continua marcado (spec §4.4).
        $this->aceito = (bool) session('aceite_previo', false);
        $this->updatedMeio();
    }

    /** Trocar o meio não zera aceite nem cupom: só muda o bloco de pagamento e o botão. */
    public function updatedMeio(): void
    {
        if (MeioPagamento::tryFrom($this->meio) === null) {
            $this->meio = MeioPagamento::Pix->value;
        }

        $this->falhaDoProvedor = false;
    }

    public function gerar(IniciarCompraDeCreditos $iniciar): mixed
    {
        abort_unless(CobrancaConfig::ligada(), 404);
        $this->falhaDoProvedor = false;

        if (! $this->aceito) {
            $this->addError('aceito', 'Marque o aceite do Termo para continuar.');

            return null;
        }

        $usuario = Auth::user();
        $pacote = $this->pacote();

        if (! $usuario instanceof Usuario || $pacote === null) {
            return null;
        }

        try {
            $invoice = $iniciar->executar($usuario, $pacote, request()->ip(), substr((string) request()->userAgent(), 0, 255), MeioPagamento::from($this->meio), $this->codigoCupom);
        } catch (CupomRecusado $e) {
            $this->cupomRecusadoNoClique($e);

            return null;
        } catch (CompraNaoPermitida) {
            // A própria tela já mostra o motivo (e-mail, admin, pacote): só reavalia o estado.
            return null;
        } catch (ProvedorIndisponivel) {
            $this->falhaDoProvedor = true;

            return null;
        }

        // Sem cobrança (STORY-083 CA-6) não há página do provedor: vai direto à confirmação.
        return $this->redirect($invoice->checkout_url ?? route('creditos.retorno', $invoice));
    }

    public function reenviarConfirmacao(): void
    {
        $usuario = Auth::user();

        if (! $usuario instanceof Usuario || $usuario->emailConfirmado()) {
            return;
        }

        $chave = 'checkout-confirmacao:'.$usuario->id;

        if (! RateLimiter::tooManyAttempts($chave, 3)) {
            RateLimiter::hit($chave, 3600);
            EnviarEmailConfirmacao::dispatch($usuario->id);
        }

        $this->confirmacaoReenviada = true;
    }

    public function render(): View
    {
        $usuario = Auth::user();
        $pacote = $this->pacote();
        $aplicado = $pacote === null ? null : $this->cupomDaTela();

        return view('livewire.cobranca.comprar-creditos', [
            'pacote' => $pacote,
            'aplicado' => $aplicado,
            'cupomConcedido' => $aplicado?->concedido === true,
            'aPagar' => $aplicado?->preco->final ?? ($pacote === null ? 0 : $pacote->preco_centavos),
            'semCobranca' => $aplicado?->preco->semCobranca() === true,
            'pacotes' => $this->pacoteId === null ? $this->pacotesAtivos() : collect(),
            'emailConfirmado' => $usuario instanceof Usuario && $usuario->emailConfirmado(),
            'email' => $usuario instanceof Usuario ? $usuario->email : '',
            'personificando' => Personificacao::ativa() !== null,
            'meioAtual' => MeioPagamento::from($this->meio),
            'pixEmAberto' => $pacote === null || ! $usuario instanceof Usuario ? null : $this->pixEmAberto($usuario, $pacote),
            'minutosPix' => (int) config('services.payments.pix_expiracao_minutos', 30),
            'validade' => $pacote === null ? null : now()->addMonthsNoOverflow($pacote->validade_meses),
        ])->layoutData(['title' => 'Comprar créditos']);
    }

    /** QR Code Pix ainda válido deste pacote: a tela avisa para evitar pagar duas vezes (spec §4). */
    private function pixEmAberto(Usuario $usuario, PacoteCredito $pacote): ?Invoice
    {
        $aberto = Invoice::query()
            ->where('usuario_id', $usuario->id)
            ->where('pacote_credito_id', $pacote->id)
            ->where('meio', MeioPagamento::Pix->value)
            ->where('status', EstadoCobranca::Pendente->value)
            ->whereNotNull('checkout_url')
            ->latest('created_at')
            ->first();

        return $aberto === null || $aberto->expirada() ? null : $aberto;
    }

    protected function itemDeCompra(): ?ItemDeCompra
    {
        $pacote = $this->pacote();

        return $pacote === null ? null : new ItemDeCompra($pacote->preco_centavos, pacoteId: $pacote->id);
    }

    private function pacote(): ?PacoteCredito
    {
        if ($this->pacoteId === null) {
            return null;
        }

        return PacoteCredito::query()->where('ativo', true)->find($this->pacoteId);
    }

    /** @return Collection<int, PacoteCredito> */
    private function pacotesAtivos(): Collection
    {
        return PacoteCredito::query()->where('ativo', true)->orderBy('ordem')->get();
    }
}

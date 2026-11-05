<?php

declare(strict_types=1);

namespace App\Livewire\Cobranca;

use App\Domain\Cobranca\ProvedorIndisponivel;
use App\Jobs\EnviarEmailConfirmacao;
use App\Models\CobrancaConfig;
use App\Models\PacoteCredito;
use App\Models\Usuario;
use App\Services\Cobranca\CompraNaoPermitida;
use App\Services\Cobranca\IniciarCompraDeCreditos;
use App\Support\Personificacao;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
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
    public ?string $pacoteId = null;

    public bool $aceito = false;

    /** O provedor não atendeu: a tela mostra o aviso com "Tentar de novo". */
    public bool $falhaDoProvedor = false;

    public bool $confirmacaoReenviada = false;

    public function mount(?string $pacote = null): void
    {
        abort_unless(CobrancaConfig::ligada(), 404);

        $this->pacoteId = $pacote;
        // Vindo de "Gerar novo QR Code": o aceite já dado continua marcado (spec §4.4).
        $this->aceito = (bool) session('aceite_previo', false);
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
            $invoice = $iniciar->executar($usuario, $pacote, request()->ip(), substr((string) request()->userAgent(), 0, 255));
        } catch (CompraNaoPermitida) {
            // A própria tela já mostra o motivo (e-mail, admin, pacote): só reavalia o estado.
            return null;
        } catch (ProvedorIndisponivel) {
            $this->falhaDoProvedor = true;

            return null;
        }

        return $this->redirect((string) $invoice->checkout_url);
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

        return view('livewire.cobranca.comprar-creditos', [
            'pacote' => $pacote,
            'pacotes' => $this->pacoteId === null ? $this->pacotesAtivos() : collect(),
            'emailConfirmado' => $usuario instanceof Usuario && $usuario->emailConfirmado(),
            'email' => $usuario instanceof Usuario ? $usuario->email : '',
            'personificando' => Personificacao::ativa() !== null,
            'minutosPix' => (int) config('services.payments.pix_expiracao_minutos', 30),
            'validade' => $pacote === null ? null : now()->addMonthsNoOverflow($pacote->validade_meses),
        ])->layoutData(['title' => 'Comprar créditos']);
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

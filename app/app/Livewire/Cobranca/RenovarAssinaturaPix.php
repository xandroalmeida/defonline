<?php

declare(strict_types=1);

namespace App\Livewire\Cobranca;

use App\Domain\Cobranca\ProvedorIndisponivel;
use App\Domain\Cobranca\RenovacaoPix;
use App\Domain\Cobranca\StatusAssinatura;
use App\Jobs\EnviarEmailConfirmacao;
use App\Models\CobrancaConfig;
use App\Models\Plano;
use App\Models\Usuario;
use App\Services\Cobranca\CompraNaoPermitida;
use App\Services\Cobranca\CupomRecusado;
use App\Services\Cobranca\IniciarRenovacaoPix;
use App\Services\Cobranca\ItemDeCompra;
use App\Support\Personificacao;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * STORY-080 CA-4, CA-5, CA-7 — "Pagar renovação" do plano anual pago com Pix (spec
 * STORY-070-checkout-pix-anual §3): revisão, aceite e novo Checkout Pix. Fora da janela de 7 dias a tela
 * diz quando abre; depois do fim, mostra "Encerrado" com o caminho para contratar de novo. Com a
 * cobrança desligada a rota é inexistente (404).
 */
#[Layout('components.layouts.app')]
final class RenovarAssinaturaPix extends Component
{
    use ComCupom;

    public bool $aceito = false;

    public bool $falhaDoProvedor = false;

    public bool $confirmacaoReenviada = false;

    /** Marcou o aceite: o erro de quando faltava some na hora. */
    public function updatedAceito(): void
    {
        if ($this->aceito) {
            $this->resetErrorBag('aceito');
        }
    }

    public function mount(): void
    {
        abort_unless(CobrancaConfig::ligada(), 404);
    }

    public function renovar(IniciarRenovacaoPix $iniciar): mixed
    {
        abort_unless(CobrancaConfig::ligada(), 404);
        $this->falhaDoProvedor = false;

        if (! $this->aceito) {
            $this->addError('aceito', 'Marque o aceite do Termo para continuar.');

            return null;
        }

        $usuario = Auth::user();

        if (! $usuario instanceof Usuario) {
            return null;
        }

        try {
            $invoice = $iniciar->executar($usuario, request()->ip(), substr((string) request()->userAgent(), 0, 255), $this->codigoCupom);
        } catch (CupomRecusado $e) {
            $this->cupomRecusadoNoClique($e);

            return null;
        } catch (CompraNaoPermitida) {
            // A própria tela já mostra o motivo (e-mail, admin, fora da janela): só reavalia o estado.
            return null;
        } catch (ProvedorIndisponivel) {
            $this->falhaDoProvedor = true;

            return null;
        }

        // Renovação sem cobrança (STORY-083): já está paga, vai direto à confirmação.
        return $this->redirect($invoice->checkout_url ?? route('assinatura.retorno', $invoice));
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
        $assinatura = $usuario instanceof Usuario ? IniciarRenovacaoPix::assinaturaDoUsuario($usuario->id) : null;
        $vigente = $assinatura !== null && $assinatura->status === StatusAssinatura::Ativa;
        $plano = $assinatura?->plano_id === null ? null : Plano::query()->find($assinatura->plano_id);
        $emCurso = $assinatura?->desconto();
        $periodo = ($assinatura === null ? 0 : $assinatura->periodos_pagos) + 1;
        // Desconto que continua nesta renovação (CA-7); senão vale o concedido ou o digitado, e nunca os dois (CA-5).
        $continua = $emCurso?->vale($periodo) === true;
        $aplicado = $vigente && ! $continua ? $this->cupomDaTela() : null;
        $cheio = $continua ? $emCurso->cheio : (int) $assinatura?->preco_snapshot_centavos;
        $aPagar = $continua ? $emCurso->valorDoPeriodo($periodo) : ($aplicado?->preco->final ?? $cheio);

        return view('livewire.cobranca.renovar-assinatura-pix', [
            'assinatura' => $assinatura,
            'vigente' => $vigente,
            'plano' => $plano,
            'janelaAberta' => $vigente && RenovacaoPix::janelaAberta($assinatura->proximo_vencimento, now()),
            'abreEm' => $vigente ? RenovacaoPix::abreEm($assinatura->proximo_vencimento) : null,
            'fim' => $assinatura?->proximo_vencimento,
            'novoFim' => $assinatura === null ? null : RenovacaoPix::novoVencimento($assinatura->data_inicio, $assinatura->periodos_pagos),
            'valor' => $aPagar,
            'cheio' => $cheio,
            'descontoValor' => $cheio - $aPagar,
            'continua' => $continua,
            'aplicado' => $aplicado,
            'cupomConcedido' => $aplicado?->concedido === true,
            'semCobranca' => $aPagar === 0,
            'emailConfirmado' => $usuario instanceof Usuario && $usuario->emailConfirmado(),
            'email' => $usuario instanceof Usuario ? $usuario->email : '',
            'personificando' => Personificacao::ativa() !== null,
            'minutosPix' => (int) config('services.payments.pix_expiracao_minutos', 30),
        ])->layoutData(['title' => 'Renovar plano']);
    }

    protected function itemDeCompra(): ?ItemDeCompra
    {
        $usuario = Auth::user();
        $assinatura = $usuario instanceof Usuario ? IniciarRenovacaoPix::assinaturaDoUsuario($usuario->id) : null;

        if ($assinatura === null || $assinatura->plano_id === null || $assinatura->desconto()?->vale($assinatura->periodos_pagos + 1) === true) {
            return null;
        }

        return new ItemDeCompra((int) $assinatura->preco_snapshot_centavos, $assinatura->plano_id, 'anual');
    }
}

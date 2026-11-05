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
use App\Services\Cobranca\IniciarRenovacaoPix;
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
    public bool $aceito = false;

    public bool $falhaDoProvedor = false;

    public bool $confirmacaoReenviada = false;

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
            $invoice = $iniciar->executar($usuario, request()->ip(), substr((string) request()->userAgent(), 0, 255));
        } catch (CompraNaoPermitida) {
            // A própria tela já mostra o motivo (e-mail, admin, fora da janela): só reavalia o estado.
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
        $assinatura = $usuario instanceof Usuario ? IniciarRenovacaoPix::assinaturaDoUsuario($usuario->id) : null;
        $vigente = $assinatura !== null && $assinatura->status === StatusAssinatura::Ativa;
        $plano = $assinatura?->plano_id === null ? null : Plano::query()->find($assinatura->plano_id);
        $fuso = 'America/Sao_Paulo';

        return view('livewire.cobranca.renovar-assinatura-pix', [
            'assinatura' => $assinatura,
            'vigente' => $vigente,
            'plano' => $plano,
            'janelaAberta' => $vigente && RenovacaoPix::janelaAberta($assinatura->proximo_vencimento, now()),
            'abreEm' => $vigente ? RenovacaoPix::abreEm($assinatura->proximo_vencimento)->setTimezone($fuso) : null,
            'fim' => $assinatura?->proximo_vencimento->copy()->setTimezone($fuso),
            'novoFim' => $assinatura === null ? null : RenovacaoPix::novoVencimento($assinatura->data_inicio, $assinatura->periodos_pagos)->setTimezone($fuso),
            'valor' => (int) $assinatura?->preco_snapshot_centavos,
            'emailConfirmado' => $usuario instanceof Usuario && $usuario->emailConfirmado(),
            'email' => $usuario instanceof Usuario ? $usuario->email : '',
            'personificando' => Personificacao::ativa() !== null,
            'minutosPix' => (int) config('services.payments.pix_expiracao_minutos', 30),
        ])->layoutData(['title' => 'Renovar plano']);
    }
}

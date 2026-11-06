<?php

declare(strict_types=1);

namespace App\Livewire\Cobranca;

use App\Domain\Cobranca\MotivoRecusaCupom;
use App\Models\Usuario;
use App\Services\Cobranca\CupomAplicado;
use App\Services\Cobranca\CupomDoCheckout;
use App\Services\Cobranca\CupomRecusado;
use App\Services\Cobranca\ItemDeCompra;
use App\Support\Cobranca\TextoCupom;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;

/**
 * STORY-083 CA-1 a CA-3 — o campo de cupom dos checkouts (spec STORY-070-cupom-no-checkout): colapsado, com
 * erro específico sob o campo, e o concedido à conta já aplicado e sem campo (cupons não acumulam).
 * Quem usa devolve o item da compra em {@see ItemDeCompra()}.
 */
trait ComCupom
{
    /** Tentativas erradas de digitar cupom na janela (STORY-093 CA-4). */
    private const TENTATIVAS_POR_CONTA = 10;

    private const TENTATIVAS_POR_IP = 30;

    private const JANELA_TENTATIVAS_SEGUNDOS = 3600;

    public string $campoCupom = '';

    /** O código que o usuário aplicou; o concedido não passa por aqui. */
    public ?string $codigoCupom = null;

    public bool $cupomAberto = false;

    public ?string $erroCupom = null;

    /** O cupom esgotou entre a tela e o clique: o valor voltou ao cheio e o usuário confere antes de pagar. */
    public bool $cupomEsgotou = false;

    abstract protected function itemDeCompra(): ?ItemDeCompra;

    public function abrirCupom(): void
    {
        $this->cupomAberto = true;
    }

    public function aplicarCupom(): void
    {
        $this->erroCupom = null;
        $this->cupomEsgotou = false;
        $usuario = Auth::user();
        $item = $this->itemDeCompra();

        if (! $usuario instanceof Usuario || $item === null) {
            return;
        }

        if (trim($this->campoCupom) === '') {
            $this->erroCupom = 'Digite o código do cupom.';

            return;
        }

        // STORY-093 CA-4 (D-17): chutar código de cupom tem limite, por conta e por IP. Só a tentativa que erra conta.
        [$porConta, $porIp] = [self::TENTATIVAS_POR_CONTA, self::TENTATIVAS_POR_IP];
        $chaves = ['cupom-conta:'.$usuario->id => $porConta, 'cupom-ip:'.request()->ip() => $porIp];

        foreach ($chaves as $chave => $limite) {
            if (RateLimiter::tooManyAttempts($chave, $limite)) {
                $this->erroCupom = 'Muitas tentativas com código inválido. Tente de novo em '.max(1, (int) ceil(RateLimiter::availableIn($chave) / 60)).' minutos.';

                return;
            }
        }

        try {
            $aplicado = app(CupomDoCheckout::class)->digitado($usuario, $this->campoCupom, $item);
        } catch (CupomRecusado $e) {
            foreach (array_keys($chaves) as $chave) {
                RateLimiter::hit($chave, self::JANELA_TENTATIVAS_SEGUNDOS);
            }

            $this->erroCupom = TextoCupom::recusa($e, $item);
            $this->codigoCupom = null;

            return;
        }

        $this->codigoCupom = $aplicado->cupom->codigo;
        $this->campoCupom = '';
    }

    public function removerCupom(): void
    {
        $this->codigoCupom = null;
        $this->erroCupom = null;
        $this->cupomEsgotou = false;
    }

    /** O cupom da compra agora (concedido ou digitado) e se veio da conta; reavalia o digitado a cada tela. */
    protected function cupomDaTela(): ?CupomAplicado
    {
        $usuario = Auth::user();
        $item = $this->itemDeCompra();

        if (! $usuario instanceof Usuario || $item === null) {
            return null;
        }

        $cupons = app(CupomDoCheckout::class);
        $concedido = $cupons->concedido($usuario, $item);

        if ($concedido !== null || $this->codigoCupom === null) {
            return $concedido;
        }

        try {
            return $cupons->digitado($usuario, $this->codigoCupom, $item);
        } catch (CupomRecusado $e) {
            // Ficou inválido depois de aplicado (venceu, esgotou): sai do resumo com o motivo.
            $this->erroCupom = TextoCupom::recusa($e, $item);
            $this->codigoCupom = null;
            $this->cupomAberto = true;

            return null;
        }
    }

    /** O clique em "pagar" encontrou o cupom recusado: o resumo volta ao preço cheio e nada prossegue sem novo clique. */
    protected function cupomRecusadoNoClique(CupomRecusado $e): void
    {
        $item = $this->itemDeCompra();
        $this->codigoCupom = null;
        $this->cupomAberto = true;
        $this->cupomEsgotou = $e->motivo === MotivoRecusaCupom::LimiteEsgotado;
        $this->erroCupom = $item === null ? $e->getMessage() : TextoCupom::recusa($e, $item);
    }
}

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

/**
 * STORY-083 CA-1 a CA-3 — o campo de cupom dos checkouts (spec STORY-070-cupom-no-checkout): colapsado, com
 * erro específico sob o campo, e o concedido à conta já aplicado e sem campo (cupons não acumulam).
 * Quem usa devolve o item da compra em {@see ItemDeCompra()}.
 */
trait ComCupom
{
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

        try {
            $aplicado = app(CupomDoCheckout::class)->digitado($usuario, $this->campoCupom, $item);
        } catch (CupomRecusado $e) {
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

<?php

declare(strict_types=1);

namespace App\Services\Cobranca;

use App\Domain\Cobranca\PrecoComCupom;
use App\Models\Cupom;

/** STORY-083 — um cupom que vale para esta compra, com o preço já calculado. `ciclos` nulo = toda a assinatura. */
final readonly class CupomAplicado
{
    public function __construct(
        public Cupom $cupom,
        public bool $concedido,
        public ?int $ciclos,
        public PrecoComCupom $preco,
    ) {}

    /** "50% de desconto em 3 cobranças". */
    public function descricao(): string
    {
        $valor = $this->cupom->tipo->formatar($this->cupom->valor);

        return match (true) {
            $this->ciclos === null => "{$valor} de desconto",
            $this->ciclos === 1 => "{$valor} de desconto em 1 cobrança",
            default => "{$valor} de desconto em {$this->ciclos} cobranças",
        };
    }
}

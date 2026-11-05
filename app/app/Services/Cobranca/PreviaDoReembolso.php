<?php

declare(strict_types=1);

namespace App\Services\Cobranca;

/**
 * STORY-085 CA-4 — o que o reembolso retiraria, mostrado ao admin ANTES de confirmar. Nunca há saldo
 * negativo: créditos já consumidos não voltam, só o que ainda resta é retirado.
 */
final readonly class PreviaDoReembolso
{
    public function __construct(
        /** @var 'creditos'|'assinatura'|'nada' */
        public string $tipo,
        public int $creditosComprados = 0,
        public int $creditosUsados = 0,
        public int $creditosARetirar = 0,
        public int $cotaARetirar = 0,
        public bool $encerraAssinatura = false,
        /** Por que nada é retirado, quando é o caso (para a tela explicar). */
        public ?string $porqueNada = null,
    ) {}

    public static function nada(string $porque): self
    {
        return new self('nada', porqueNada: $porque);
    }

    public function retiraAlgo(): bool
    {
        return $this->creditosARetirar > 0 || $this->cotaARetirar > 0 || $this->encerraAssinatura;
    }

    /** Frase para a tela de confirmação. */
    public function descricao(): string
    {
        if ($this->tipo === 'nada') {
            return $this->porqueNada ?? 'Nada será retirado do acesso do usuário; só o dinheiro é devolvido.';
        }

        if ($this->tipo === 'creditos') {
            $usados = $this->creditosUsados === 0
                ? 'Nenhum dos '.$this->creditosComprados.' créditos foi usado'
                : $this->creditosUsados.' dos '.$this->creditosComprados.' créditos já foram usados e não voltam';

            return "{$usados}. Serão retirados {$this->creditosARetirar} "
                .($this->creditosARetirar === 1 ? 'crédito restante' : 'créditos restantes').'; o saldo não fica negativo.';
        }

        return 'A assinatura será encerrada agora e a cota restante do ciclo ('.$this->cotaARetirar.') será retirada; a recorrência é cancelada no provedor.';
    }
}

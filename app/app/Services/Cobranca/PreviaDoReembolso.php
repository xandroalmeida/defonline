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
        /** @var 'creditos'|'assinatura'|'troca'|'nada' */
        public string $tipo,
        public int $creditosComprados = 0,
        public int $creditosUsados = 0,
        public int $creditosARetirar = 0,
        public int $cotaARetirar = 0,
        public bool $encerraAssinatura = false,
        /** Por que nada é retirado, quando é o caso (para a tela explicar). */
        public ?string $porqueNada = null,
        /** STORY-082 × 085: o plano a que a assinatura volta quando se estorna a diferença de uma troca. */
        public ?string $planoAnterior = null,
        /** A tela destaca este aviso (algo que o admin precisa saber antes de confirmar). */
        public bool $alerta = false,
    ) {}

    public static function nada(string $porque, bool $alerta = false): self
    {
        return new self('nada', porqueNada: $porque, alerta: $alerta);
    }

    public static function voltaAoPlano(string $plano): self
    {
        return new self('troca', planoAnterior: $plano);
    }

    public function retiraAlgo(): bool
    {
        return $this->creditosARetirar > 0 || $this->cotaARetirar > 0 || $this->encerraAssinatura || $this->planoAnterior !== null;
    }

    /** Frase para a tela de confirmação. */
    public function descricao(): string
    {
        if ($this->tipo === 'nada') {
            return $this->porqueNada ?? 'Nada será retirado do acesso do usuário; só o dinheiro é devolvido.';
        }

        if ($this->tipo === 'troca') {
            return "A troca de plano será desfeita: a assinatura volta ao plano {$this->planoAnterior}, no mesmo ciclo, com o que já foi usado descontado da cota. A assinatura continua ativa.";
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

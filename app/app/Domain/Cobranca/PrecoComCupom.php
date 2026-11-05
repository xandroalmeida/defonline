<?php

declare(strict_types=1);

namespace App\Domain\Cobranca;

/**
 * STORY-083 CA-1, CA-5 / ADR-010 4.5 e 4.7 — preço cheio, desconto e valor a pagar de UMA cobrança.
 *
 * O valor final nunca fica negativo (o desconto não passa do preço). Se o valor final ficaria abaixo
 * do mínimo que o provedor aceita (ou em zero), a cobrança deixa de existir: a contratação é ativada
 * sem cobrança e o desconto efetivo passa a ser o preço inteiro. O valor que vai ao provedor é sempre
 * `$final`, calculado por nós: o desconto do provedor nunca é usado.
 */
final readonly class PrecoComCupom
{
    public function __construct(
        public int $cheio,
        /** Desconto efetivo, em centavos. */
        public int $desconto,
        /** O que se cobra. Zero = sem cobrança. */
        public int $final,
        /** O desconto do cupom deixaria um valor positivo mas abaixo do mínimo do provedor. */
        public bool $abaixoDoMinimo = false,
    ) {}

    public static function semCupom(int $preco): self
    {
        $preco = max(0, $preco);

        return new self($preco, 0, $preco);
    }

    public static function calcular(int $preco, TipoDesconto $tipo, int $valorDoCupom, int $minimoCentavos): self
    {
        $preco = max(0, $preco);
        $nominal = $tipo->desconto($valorDoCupom, $preco);
        $final = $preco - $nominal;

        if ($nominal > 0 && $final < $minimoCentavos) {
            return new self($preco, $preco, 0, $final > 0);
        }

        return new self($preco, $nominal, $final);
    }

    public function semCobranca(): bool
    {
        return $this->final === 0;
    }

    public function comDesconto(): bool
    {
        return $this->desconto > 0;
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Cobranca;

use Carbon\CarbonImmutable;

/**
 * STORY-082 — o que a troca de plano vai fazer, calculado antes de o usuário confirmar (CA-1).
 *
 * - `imediata`: vale agora (upgrade); `false` = agendada para o fim do ciclo (downgrade).
 * - `mesmoCiclo`: o período em curso continua (só a diferença é cobrada); senão um período novo começa hoje.
 * - `precoNovoCentavos` é o preço efetivo do plano novo neste ciclo (com o desconto de cupom que continua, STORY-083).
 * - `aPagarCentavos`: nunca negativo (CA-3). `semCobranca`: diferença abaixo do mínimo, aplicada sem cobrar.
 */
final readonly class PreviaDeTroca
{
    public function __construct(
        public bool $imediata,
        public bool $mesmoCiclo,
        public int $precoNovoCentavos,
        public int $precoAtualCentavos,
        /** Parcela do preço NOVO que cobre o que resta do período (igual ao preço cheio quando um período novo começa). */
        public int $parcelaNovaCentavos,
        /** Crédito do plano atual pelo que resta do período, já descontado. */
        public int $creditoCentavos,
        public int $aPagarCentavos,
        public bool $semCobranca,
        public int $diasRestantes,
        public CarbonImmutable $vigenteAte,
        public CarbonImmutable $proximaCobranca,
        public CicloCobranca $cicloNovo,
        public int $cotaNova,
        /** Preço CHEIO do plano novo (`precoNovoCentavos` já traz o desconto de cupom que continua). */
        public int $precoNovoCheioCentavos = 0,
        /** O desconto de cupom que segue no plano novo (STORY-083), ou `null`. */
        public ?DescontoDaAssinatura $descontoNovo = null,
        /** O desconto ainda valeria em ciclos futuros e termina nesta troca (a tela avisa). */
        public bool $descontoTermina = false,
        /** O que a PRÓXIMA cobrança do plano novo vai cobrar (com o desconto que continua). */
        public int $proximoValorCentavos = 0,
        /**
         * STORY-091 CA-3: as análises a mais que o upgrade dá no ciclo em curso (proporcionais ao que falta). Num
         * período novo (troca de ciclo) é a cota cheia, que vale já.
         */
        public int $cotaExtraAgora = 0,
        /** Quando a cota cheia do plano novo passa a valer (upgrade no mesmo período); `null` = já vale (período novo). */
        public ?CarbonImmutable $cotaCheiaEm = null,
    ) {}

    public function comDesconto(int $precoCheioNovo, ?DescontoDaAssinatura $descontoNovo, bool $termina, int $proximoValor): self
    {
        return new self(
            $this->imediata, $this->mesmoCiclo, $this->precoNovoCentavos, $this->precoAtualCentavos, $this->parcelaNovaCentavos,
            $this->creditoCentavos, $this->aPagarCentavos, $this->semCobranca, $this->diasRestantes, $this->vigenteAte,
            $this->proximaCobranca, $this->cicloNovo, $this->cotaNova, $precoCheioNovo, $descontoNovo, $termina, $proximoValor,
            $this->cotaExtraAgora, $this->cotaCheiaEm,
        );
    }
}

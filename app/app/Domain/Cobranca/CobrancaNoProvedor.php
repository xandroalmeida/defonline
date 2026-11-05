<?php

declare(strict_types=1);

namespace App\Domain\Cobranca;

use DateTimeImmutable;

/** Retrato canônico de uma cobrança, lido do provedor no momento da conferência. */
final readonly class CobrancaNoProvedor
{
    public function __construct(
        public ReferenciaProvedor $referencia,
        public EstadoCobranca $estado,
        public int $valorCentavos,
        public ?string $checkoutId = null,
        public ?string $referenciaInterna = null,
        public ?DateTimeImmutable $pagoEm = null,
        public ?string $assinaturaId = null,
        public ?string $cartaoBandeira = null,
        public ?string $cartaoUltimos4 = null,
        /** Vencimento da cobrança (a data em que o provedor tenta o cartão). */
        public ?DateTimeImmutable $vencimento = null,
    ) {}

    /**
     * A cobrança já devia ter sido paga e não foi: vencida, recusada, ou ainda "pendente" um dia
     * depois do vencimento (cartão recusado que o provedor não marcou). Cobrança paga ou em estado
     * final (cancelada, estornada, contestada) nunca conta.
     */
    public function semPagamento(DateTimeImmutable $agora): bool
    {
        if ($this->estado === EstadoCobranca::Vencida || $this->estado === EstadoCobranca::Falhou) {
            return true;
        }

        return $this->estado === EstadoCobranca::Pendente
            && $this->vencimento !== null
            && $this->vencimento->modify('+1 day')->setTime(0, 0) <= $agora;
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Cobranca;

use Carbon\CarbonImmutable;

/**
 * STORY-081 / DDR-009 — as seis situações do plano, com o vocabulário fixo da interface. Mesma régua
 * da {@see RegrasConsumo}: assinatura ativa ou cancelada só dá acesso até o vencimento; em atraso
 * dá acesso durante as retentativas (a decisão fina é da STORY-079).
 */
enum SituacaoDoPlano: string
{
    case SemPlano = 'sem_plano';
    case Teste = 'teste';
    case Ativo = 'ativo';
    case Cancelado = 'cancelado';
    case EmAtraso = 'em_atraso';
    case Encerrado = 'encerrado';

    public static function de(?StatusAssinatura $status, bool $trial, ?CarbonImmutable $vencimento, CarbonImmutable $agora): self
    {
        if ($status === null || $vencimento === null) {
            return self::SemPlano;
        }

        if ($status === StatusAssinatura::EmAtraso) {
            return self::EmAtraso;
        }

        if ($status === StatusAssinatura::Expirada || $vencimento->lessThanOrEqualTo($agora)) {
            return self::Encerrado;
        }

        if ($status === StatusAssinatura::Cancelada) {
            return self::Cancelado;
        }

        return $trial ? self::Teste : self::Ativo;
    }

    /** Texto do `pill`. `SemPlano` não tem pill. */
    public function rotulo(): ?string
    {
        return match ($this) {
            self::SemPlano => null,
            self::Teste => 'Teste',
            self::Ativo => 'Ativo',
            self::Cancelado => 'Cancelado',
            self::EmAtraso => 'Em atraso',
            self::Encerrado => 'Encerrado',
        };
    }

    /** Há plano ou teste a mostrar com ações (ainda que só de leitura)? */
    public function temVinculo(): bool
    {
        return $this !== self::SemPlano;
    }
}

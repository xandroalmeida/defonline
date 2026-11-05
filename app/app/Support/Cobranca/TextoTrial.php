<?php

declare(strict_types=1);

namespace App\Support\Cobranca;

use App\Domain\Cobranca\MotivoInelegivelTrial;

/**
 * STORY-074 — o texto do trial conforme a spec `STORY-070-trial` §4 e §5 (microcopy provisória: a spec
 * manda sobre este arquivo). As mensagens de inelegibilidade explicam o motivo sem acusar e sem
 * revelar de qual conta nem de que dia.
 */
final class TextoTrial
{
    public static function inelegivel(MotivoInelegivelTrial $motivo): string
    {
        return match ($motivo) {
            MotivoInelegivelTrial::Desativado => 'O teste grátis não está disponível no momento.',
            MotivoInelegivelTrial::CpfJaUsou => 'Já existe um teste gratuito ativado com este CPF, então não conseguimos abrir outro.',
            MotivoInelegivelTrial::JaTemAssinatura => 'Você já tem um plano ativo, então não precisa do teste gratuito.',
        };
    }

    public static function analises(int $n): string
    {
        return $n.' '.($n === 1 ? 'análise' : 'análises');
    }
}

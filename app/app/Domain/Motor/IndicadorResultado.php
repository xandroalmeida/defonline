<?php

declare(strict_types=1);

namespace App\Domain\Motor;

/**
 * Resultado bit-exato do cálculo de um indicador (IDR-010 §sub-decisão 3).
 *
 *   - `valor`: número (float/int) quando o indicador foi calculável; `null`
 *     quando o input degenerou (vendas=0, EBITDA=0, dado faltante, etc.).
 *     Casos extremos catalogados em `epics/EPIC-002-.../design/casos-extremos.md`.
 *   - `farol`: uma das constantes de {@see Farol}. `NENHUM` cobre tanto
 *     indicador informativo (NCG abs) quanto indisponibilidade.
 *   - `motivo`: código estável (`indisponivel:<chave>`) ou `null` quando
 *     o indicador foi calculado normalmente.
 *   - `mensagem`: texto curto para exibição. Placeholder genérico
 *     ("Faixa verde/amarela/vermelha") nesta V1 — STORY-032 substituirá pelo
 *     texto da matriz DEZ/2025.
 *
 * **Imutável.** Comparação por valor (não há identidade); equals via toArray().
 */
final class IndicadorResultado
{
    public function __construct(
        public readonly float|int|null $valor,
        public readonly string $farol,
        public readonly ?string $motivo,
        public readonly string $mensagem,
    ) {}

    /**
     * Atalho para construção do caso "Indisponível" — `valor=null`, `farol=nenhum`,
     * com código de motivo + texto padrão do {@see casos-extremos.md}.
     */
    public static function indisponivel(string $motivo, string $mensagem): self
    {
        return new self(
            valor: null,
            farol: Farol::NENHUM,
            motivo: $motivo,
            mensagem: $mensagem,
        );
    }

    /**
     * @return array{valor: float|int|null, farol: string, motivo: ?string, mensagem: string}
     */
    public function toArray(): array
    {
        return [
            'valor' => $this->valor,
            'farol' => $this->farol,
            'motivo' => $this->motivo,
            'mensagem' => $this->mensagem,
        ];
    }
}

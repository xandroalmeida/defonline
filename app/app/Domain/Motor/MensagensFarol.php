<?php

declare(strict_types=1);

namespace App\Domain\Motor;

/**
 * Textos curtos placeholder dos indicadores **com** farol (V1).
 *
 * Esta V1 (STORY-028 — `motor_version = 1.0.0`) entrega o cálculo do farol
 * **sem** os textos da matriz DEZ/2025. A STORY-032 substitui estes
 * placeholders pelos textos efetivos (`matrix_version = dez-2025` continua
 * o mesmo; o snapshot do diagnóstico passa a carregar o texto certo a
 * partir do PR daquela estória).
 *
 * **Estável bit-exato.** Estes textos entram no snapshot
 * `indicadores_calculados[*].mensagem` e portanto no `payload_hash` de saída.
 * Mudança aqui requer atualização dos golden hashes (IDR-010 §sub-decisão 3).
 */
final class MensagensFarol
{
    public const VERDE = 'Faixa verde.';

    public const AMARELO = 'Faixa amarela.';

    public const VERMELHO = 'Faixa vermelha.';

    /**
     * Devolve o texto curto correspondente à cor do farol.
     *
     * @throws \InvalidArgumentException se a cor for `nenhum` — esse caso é
     *                                   tratado individualmente (indisponibilidade ou indicador informativo).
     */
    public static function paraFarol(string $farol): string
    {
        return match ($farol) {
            Farol::VERDE => self::VERDE,
            Farol::AMARELO => self::AMARELO,
            Farol::VERMELHO => self::VERMELHO,
            default => throw new \InvalidArgumentException(
                "MensagensFarol::paraFarol() chamado com farol '{$farol}' — esperado verde/amarelo/vermelho.",
            ),
        };
    }
}

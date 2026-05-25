<?php

declare(strict_types=1);

namespace App\Support\Relatorio;

use App\Domain\Motor\Motor;

/**
 * Formatação dos 7 indicadores essenciais + NCG absoluto para exibição no
 * relatório minimalista (STORY-029).
 *
 * Catálogo de unidades (alinhado com IDR-010 e Anexo D):
 *   - Percentuais já em base 100 (margens, despesas/EBITDA quando vier).
 *   - Razão decimal (NCG/Vendas grava `0.085` = 8,5% — multiplicado por 100 aqui).
 *   - Múltiplos (Dívida Líq./EBITDA: `1.5×`).
 *   - Dias (PMR/PMC/CicloFin: `45 dias`).
 *   - Moeda BR (NCG abs: `R$ 50.000,00`).
 *
 * Quando `valor === null` o chamador exibe a mensagem do snapshot em texto
 * cinza — não formata nada aqui.
 */
final class IndicadorFormatter
{
    public const NOMES = [
        'margem_bruta' => 'Margem Bruta',
        'margem_liquida' => 'Margem Líquida',
        'divida_liquida_ebitda' => 'Dívida Líquida / EBITDA',
        'ncg_vendas' => 'NCG / Vendas',
        'pmr' => 'PMR — Prazo médio de recebimento',
        'pmc' => 'PMC — Prazo médio de pagamento',
        'ciclo_financeiro' => 'Ciclo Financeiro',
        'ncg_absoluto' => 'NCG — Necessidade de Capital de Giro',
    ];

    /**
     * Ordem canônica de exibição dos 7 indicadores (NCG absoluto é tratado à parte).
     * Igual à ordem do {@see Motor::indicadores()}.
     *
     * @var list<string>
     */
    public const ORDEM_ESSENCIAIS = [
        'margem_bruta',
        'margem_liquida',
        'divida_liquida_ebitda',
        'ncg_vendas',
        'pmr',
        'pmc',
        'ciclo_financeiro',
    ];

    public static function nome(string $codigo): string
    {
        return self::NOMES[$codigo] ?? $codigo;
    }

    public static function valor(string $codigo, float|int|null $valor): string
    {
        if ($valor === null) {
            return '—';
        }

        return match ($codigo) {
            'margem_bruta', 'margem_liquida' => self::percentual((float) $valor, 1),
            'divida_liquida_ebitda' => self::multiplo((float) $valor),
            'ncg_vendas' => self::percentual((float) $valor * 100.0, 1),
            'pmr', 'pmc', 'ciclo_financeiro' => self::dias((float) $valor),
            'ncg_absoluto' => self::moedaBr((float) $valor),
            default => self::numeroBr((float) $valor, 2),
        };
    }

    private static function percentual(float $v, int $casas): string
    {
        return self::numeroBr($v, $casas).'%';
    }

    private static function multiplo(float $v): string
    {
        return self::numeroBr($v, 2).'×';
    }

    private static function dias(float $v): string
    {
        return ((int) round($v)).' dias';
    }

    private static function moedaBr(float $v): string
    {
        $sinal = $v < 0 ? '-' : '';

        return $sinal.'R$ '.number_format(abs($v), 2, ',', '.');
    }

    private static function numeroBr(float $v, int $casas): string
    {
        return number_format($v, $casas, ',', '.');
    }
}

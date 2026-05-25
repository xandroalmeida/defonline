<?php

declare(strict_types=1);

namespace App\Support\Relatorio;

use App\Domain\Motor\Motor;

/**
 * Formatação dos indicadores do motor para exibição no relatório
 * minimalista (STORY-029 + STORY-030 V2 = 13 essenciais + 2 informativos).
 *
 * Catálogo de unidades (alinhado com IDR-010 e Anexo D):
 *   - Percentuais já em base 100 (margens, desp.fin/EBITDA, inadimplência).
 *   - Razão decimal (NCG/Vendas grava `0.085` = 8,5% — multiplicado por 100 aqui).
 *   - Múltiplos (Dívida Líq./EBITDA: `1.5×`; Fontes Recursos: `0.50×`; Giro: `2.5×`).
 *   - Dias (PMR/PMC/PME/CicloFin/CicloOperacional: `45 dias`).
 *   - Moeda BR (NCG abs: `R$ 50.000,00`).
 *
 * Quando `valor === null` o chamador exibe a mensagem do snapshot em texto
 * cinza — não formata nada aqui.
 */
final class IndicadorFormatter
{
    public const NOMES = [
        'margem_bruta' => 'Margem Bruta',
        'margem_ebitda' => 'Margem EBITDA',
        'margem_liquida' => 'Margem Líquida',
        'divida_liquida_ebitda' => 'Dívida Líquida / EBITDA',
        'despesas_fin_ebitda' => 'Despesas Financeiras / EBITDA',
        'fontes_recursos' => 'Fontes de Recursos (PC / PL)',
        'giro_ativo' => 'Giro do Ativo',
        'ciclo_financeiro' => 'Ciclo Financeiro',
        'ncg_absoluto' => 'NCG — Necessidade de Capital de Giro',
        'ncg_vendas' => 'NCG / Vendas',
        'pmc' => 'PMC — Prazo médio de pagamento',
        'pme' => 'PME — Prazo médio de estoque',
        'pmr' => 'PMR — Prazo médio de recebimento',
        'inadimplencia' => 'Inadimplência',
        'ciclo_operacional' => 'Ciclo Operacional',
    ];

    /**
     * Ordem canônica de exibição dos 13 indicadores **com farol** (Anexo D §4.5).
     * NCG absoluto e Ciclo Operacional são informativos e tratados à parte
     * (cards ou linhas separadas).
     *
     * Igual à ordem do {@see Motor::indicadores()} filtrada pelos que têm farol.
     *
     * @var list<string>
     */
    public const ORDEM_ESSENCIAIS = [
        'margem_bruta',
        'margem_ebitda',
        'margem_liquida',
        'divida_liquida_ebitda',
        'despesas_fin_ebitda',
        'fontes_recursos',
        'giro_ativo',
        'ciclo_financeiro',
        'ncg_vendas',
        'pmc',
        'pme',
        'pmr',
        'inadimplencia',
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
            'margem_bruta',
            'margem_ebitda',
            'margem_liquida',
            'despesas_fin_ebitda',
            'inadimplencia' => self::percentual((float) $valor, 1),
            'divida_liquida_ebitda',
            'fontes_recursos',
            'giro_ativo' => self::multiplo((float) $valor),
            'ncg_vendas' => self::percentual((float) $valor * 100.0, 1),
            'pmr',
            'pmc',
            'pme',
            'ciclo_financeiro',
            'ciclo_operacional' => self::dias((float) $valor),
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

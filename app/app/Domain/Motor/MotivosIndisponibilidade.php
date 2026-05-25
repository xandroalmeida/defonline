<?php

declare(strict_types=1);

namespace App\Domain\Motor;

/**
 * Códigos de motivo de indisponibilidade + mensagens padrão (catálogo
 * `epics/EPIC-002-.../design/casos-extremos.md`).
 *
 * Cada constante é o **código estável** gravado em
 * `diagnosticos.indicadores_calculados[*].motivo`; o método `mensagem()`
 * devolve a mensagem PT-BR padrão correspondente. STORY-029 (relatório)
 * pode mapear para microcopy diferente sem mudar o código persistido.
 *
 * **Imutável.** Constantes adicionadas/alteradas exigem bump de
 * `motor_version` PATCH (mudança de output bit-exato).
 */
final class MotivosIndisponibilidade
{
    public const VENDAS_ZERO = 'indisponivel:vendas_zero';

    public const VENDAS_FALTANTE = 'indisponivel:vendas_faltante';

    public const DESPESAS_FALTANTE = 'indisponivel:despesas_faltante';

    public const DESPESAS_FINANCEIRAS_FALTANTE = 'indisponivel:despesas_financeiras_faltante';

    public const EBITDA_ZERO = 'indisponivel:ebitda_zero';

    public const EBITDA_NEGATIVO = 'indisponivel:ebitda_negativo';

    public const DIVIDA_COMPONENTE_FALTANTE = 'indisponivel:divida_componente_faltante';

    public const NCG_COMPONENTE_FALTANTE = 'indisponivel:ncg_componente_faltante';

    public const PMC_FALTANTE = 'indisponivel:pmc_faltante';

    public const PME_FALTANTE = 'indisponivel:pme_faltante';

    public const PMR_FALTANTE = 'indisponivel:pmr_faltante';

    public const PRAZO_FALTANTE = 'indisponivel:prazo_faltante';

    // ============================================================
    // Adicionados em STORY-030 (motor V2 = 1.1.0)
    // ============================================================

    public const EBITDA_EXTREMO = 'indisponivel:ebitda_extremo';

    public const PL_NAO_POSITIVO = 'indisponivel:pl_nao_positivo';

    public const ATIVO_ZERO = 'indisponivel:ativo_zero';

    public const ATIVO_COMPONENTE_FALTANTE = 'indisponivel:ativo_componente_faltante';

    public const INADIMPLENCIA_FALTANTE = 'indisponivel:inadimplencia_faltante';

    public const CICLO_OPERACIONAL_PRAZO_FALTANTE = 'indisponivel:ciclo_operacional_prazo_faltante';

    private const MENSAGENS = [
        self::VENDAS_ZERO => 'Indicador indisponível: vendas anuais são zero.',
        self::VENDAS_FALTANTE => 'Indicador indisponível: vendas mensais não informadas.',
        self::DESPESAS_FALTANTE => 'Indicador indisponível: despesas fixas ou variáveis não informadas.',
        self::DESPESAS_FINANCEIRAS_FALTANTE => 'Indicador indisponível: despesas financeiras não informadas.',
        self::EBITDA_ZERO => 'Indicador indisponível: EBITDA é zero.',
        self::EBITDA_NEGATIVO => 'Indicador indisponível: EBITDA negativo. Veja Margem EBITDA.',
        self::EBITDA_EXTREMO => 'Indicador fora de faixa exibível (margem inferior a −100%). Reveja custos e despesas declarados.',
        self::DIVIDA_COMPONENTE_FALTANTE => 'Indicador indisponível: dívidas ou disponibilidades não informadas.',
        self::NCG_COMPONENTE_FALTANTE => 'NCG indisponível: estoques, clientes ou fornecedores não informados.',
        self::PMC_FALTANTE => 'Indicador indisponível: prazo de pagamento de fornecedores não informado.',
        self::PME_FALTANTE => 'Indicador indisponível: prazo médio de estoque não informado.',
        self::PMR_FALTANTE => 'Indicador indisponível: prazo de recebimento de clientes não informado.',
        self::PRAZO_FALTANTE => 'Indicador indisponível: prazos médios (PMC/PME/PMR) não totalmente informados.',
        self::PL_NAO_POSITIVO => 'Indicador indisponível: patrimônio líquido apurado é zero ou negativo (passivo ≥ ativo). Reveja contas a pagar e dívidas declaradas.',
        self::ATIVO_ZERO => 'Indicador indisponível: ativo total declarado é zero.',
        self::ATIVO_COMPONENTE_FALTANTE => 'Indicador indisponível: componentes do ativo não informados.',
        self::INADIMPLENCIA_FALTANTE => 'Indicador indisponível: índice de inadimplência não informado.',
        self::CICLO_OPERACIONAL_PRAZO_FALTANTE => 'Indicador informativo indisponível: prazos médios de estoque (PME) ou recebimento (PMR) não informados.',
    ];

    public static function mensagem(string $motivo): string
    {
        if (! isset(self::MENSAGENS[$motivo])) {
            throw new \InvalidArgumentException("Motivo de indisponibilidade desconhecido: '{$motivo}'.");
        }

        return self::MENSAGENS[$motivo];
    }
}

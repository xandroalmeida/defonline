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

    private const MENSAGENS = [
        self::VENDAS_ZERO => 'Indicador indisponível: vendas anuais são zero.',
        self::VENDAS_FALTANTE => 'Indicador indisponível: vendas mensais não informadas.',
        self::DESPESAS_FALTANTE => 'Indicador indisponível: despesas fixas ou variáveis não informadas.',
        self::DESPESAS_FINANCEIRAS_FALTANTE => 'Indicador indisponível: despesas financeiras não informadas.',
        self::EBITDA_ZERO => 'Indicador indisponível: EBITDA é zero.',
        self::EBITDA_NEGATIVO => 'Indicador indisponível: EBITDA negativo. Veja Margem EBITDA.',
        self::DIVIDA_COMPONENTE_FALTANTE => 'Indicador indisponível: dívidas ou disponibilidades não informadas.',
        self::NCG_COMPONENTE_FALTANTE => 'NCG indisponível: estoques, clientes ou fornecedores não informados.',
        self::PMC_FALTANTE => 'Indicador indisponível: prazo de pagamento de fornecedores não informado.',
        self::PME_FALTANTE => 'Indicador indisponível: prazo médio de estoque não informado.',
        self::PMR_FALTANTE => 'Indicador indisponível: prazo de recebimento de clientes não informado.',
        self::PRAZO_FALTANTE => 'Indicador indisponível: prazos médios (PMC/PME/PMR) não totalmente informados.',
    ];

    public static function mensagem(string $motivo): string
    {
        if (! isset(self::MENSAGENS[$motivo])) {
            throw new \InvalidArgumentException("Motivo de indisponibilidade desconhecido: '{$motivo}'.");
        }

        return self::MENSAGENS[$motivo];
    }
}

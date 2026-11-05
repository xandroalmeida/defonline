<?php

declare(strict_types=1);

namespace App\Domain\Motor\Indicadores;

use App\Domain\Motor\Calculos\DreAdaptada;
use App\Domain\Motor\Farois\FarolIndustria;
use App\Domain\Motor\IndicadorResultado;
use App\Domain\Motor\MatrizRecomendacoes;
use App\Domain\Motor\MotivosIndisponibilidade;

/**
 * Indicador #10 — NCG / Vendas = `(ACC − PCC) / Venda média mensal` (Anexo D; PDR-014).
 *   - ACC = Q03 (Clientes) + Q04 (Estoques).
 *   - PCC = Q07 (Fornecedores).
 *
 * Valor em **razão direta**: 0,76 = a NCG equivale a 0,76 de um mês de vendas. A
 * base é mensal, e não Q09 × 12, porque foi assim que a EB Parcerias definiu o
 * indicador. As faixas ("≤ 0", "0,01–10,00", "> 10,00") estão na mesma escala.
 *
 * Casos extremos (catálogo §10):
 *   - Vendas zero → `indisponivel:vendas_zero`.
 *   - Q03 ∨ Q04 ∨ Q07 ausente → `indisponivel:ncg_componente_faltante`.
 */
final class NcgVendas implements Indicador
{
    public function chave(): string
    {
        return 'ncg_vendas';
    }

    public function calcular(array $payload, DreAdaptada $dre): IndicadorResultado
    {
        $clientes = $dre->clientes();
        $estoques = $dre->estoques();
        $fornecedores = $dre->fornecedores();
        if ($clientes === null || $estoques === null || $fornecedores === null) {
            return IndicadorResultado::indisponivel(
                MotivosIndisponibilidade::NCG_COMPONENTE_FALTANTE,
                MotivosIndisponibilidade::mensagem(MotivosIndisponibilidade::NCG_COMPONENTE_FALTANTE),
            );
        }

        if (($payload['Q09'] ?? null) === null) {
            return IndicadorResultado::indisponivel(
                MotivosIndisponibilidade::VENDAS_FALTANTE,
                MotivosIndisponibilidade::mensagem(MotivosIndisponibilidade::VENDAS_FALTANTE),
            );
        }

        $vendas = $dre->vendasMensais();
        if ($vendas === null) {
            return IndicadorResultado::indisponivel(
                MotivosIndisponibilidade::VENDAS_FALTANTE,
                MotivosIndisponibilidade::mensagem(MotivosIndisponibilidade::VENDAS_FALTANTE),
            );
        }

        if (bccomp($vendas, '0', DreAdaptada::ESCALA) === 0) {
            return IndicadorResultado::indisponivel(
                MotivosIndisponibilidade::VENDAS_ZERO,
                MotivosIndisponibilidade::mensagem(MotivosIndisponibilidade::VENDAS_ZERO),
            );
        }

        $acc = bcadd($clientes, $estoques, DreAdaptada::ESCALA);
        $ncg = bcsub($acc, $fornecedores, DreAdaptada::ESCALA);
        $ratio = bcdiv($ncg, $vendas, 6);
        // Arredonda a 4 casas decimais explicitamente — bcdiv truncou.
        $valor = (float) bcadd($ratio, '0', 4);

        $farol = FarolIndustria::classificar('ncg_vendas', $valor);

        return new IndicadorResultado(
            valor: $valor,
            farol: $farol,
            motivo: null,
            mensagem: (new MatrizRecomendacoes)->texto($this->chave(), $farol),
        );
    }
}

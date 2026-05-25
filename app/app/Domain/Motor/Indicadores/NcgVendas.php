<?php

declare(strict_types=1);

namespace App\Domain\Motor\Indicadores;

use App\Domain\Motor\Calculos\DreAdaptada;
use App\Domain\Motor\Farois\FarolIndustria;
use App\Domain\Motor\IndicadorResultado;
use App\Domain\Motor\MensagensFarol;
use App\Domain\Motor\MotivosIndisponibilidade;

/**
 * Indicador #10 — NCG / Vendas = `(ACC − PCC) / Vendas` (Anexo D).
 *   - ACC = Q03 (Clientes) + Q04 (Estoques).
 *   - PCC = Q07 (Fornecedores).
 *
 * Valor em **fração** (ex.: 0.05 = 5% das vendas). O Anexo E expressa as faixas
 * em "≤ 0", "0,01–10%", "> 10%" — já configuradas como fração (0, 0.10).
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

        $vendas = $dre->vendasAnuais();
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
        $ratio = bcdiv($ncg, $vendas, 6);   // fração
        // Arredonda a 4 casas decimais explicitamente — bcdiv truncou.
        $valor = (float) bcadd($ratio, '0', 4);

        $farol = FarolIndustria::classificar('ncg_vendas', $valor);

        return new IndicadorResultado(
            valor: $valor,
            farol: $farol,
            motivo: null,
            mensagem: MensagensFarol::paraFarol($farol),
        );
    }
}

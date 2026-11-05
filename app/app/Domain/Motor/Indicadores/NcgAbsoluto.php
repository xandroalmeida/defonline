<?php

declare(strict_types=1);

namespace App\Domain\Motor\Indicadores;

use App\Domain\Motor\Calculos\DreAdaptada;
use App\Domain\Motor\Farol;
use App\Domain\Motor\IndicadorResultado;
use App\Domain\Motor\MatrizRecomendacoes;
use App\Domain\Motor\MotivosIndisponibilidade;

/**
 * Indicador #9 — NCG absoluto = `Estoques + Clientes − Fornecedores` (Anexo D; espec §4.5).
 *   - Clientes = Q03; Estoques = Q04; Fornecedores = Q07.
 *
 * **Informativo — sem farol.** Decisão registrada na espec §4.5 (fechada em
 * 17/05/2026 pelo item §6.3): farol = `Farol::NENHUM` sempre que calculado.
 *
 * **Texto (PDR-018).** Vem da matriz editável, como nos indicadores com farol, em duas
 * situações: `positiva` (NCG > 0) e `negativa` (NCG ≤ 0, incluindo o zero exato).
 * Substitui as três faixas de mensagem fixas no código.
 *
 * Casos extremos (catálogo §9):
 *   - Q03 ∨ Q04 ∨ Q07 ausente → `indisponivel:ncg_componente_faltante`.
 *   - Vendas faltante NÃO bloqueia — NCG é calculado de qualquer forma.
 */
final class NcgAbsoluto implements Indicador
{
    public const POSITIVA = 'positiva';

    public const NEGATIVA = 'negativa';

    public function chave(): string
    {
        return 'ncg_absoluto';
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

        $ncg = bcsub(bcadd($clientes, $estoques, DreAdaptada::ESCALA), $fornecedores, DreAdaptada::ESCALA);
        $situacao = bccomp($ncg, '0', DreAdaptada::ESCALA) > 0 ? self::POSITIVA : self::NEGATIVA;

        return new IndicadorResultado(
            valor: (float) $ncg,
            farol: Farol::NENHUM,
            motivo: null,
            mensagem: (new MatrizRecomendacoes)->texto($this->chave(), $situacao),
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Motor\Indicadores;

use App\Domain\Motor\Calculos\DreAdaptada;
use App\Domain\Motor\Farol;
use App\Domain\Motor\IndicadorResultado;
use App\Domain\Motor\MotivosIndisponibilidade;

/**
 * Indicador #9 — NCG absoluto = `Estoques + Clientes − Fornecedores` (Anexo D; espec §4.5).
 *   - Clientes = Q03; Estoques = Q04; Fornecedores = Q07.
 *
 * **Informativo — sem farol.** Decisão registrada na espec §4.5 (fechada em
 * 17/05/2026 pelo item §6.3): farol = `Farol::NENHUM` sempre que calculado.
 * A interpretação semântica entra via 3 faixas de mensagem:
 *
 *   - Faixa 1 (`NCG ≤ 0`): folga operacional / capital de giro positivo.
 *   - Faixa 2 (`0 < NCG ≤ 10% Vendas anualizadas`): patamar moderado, atenção a prazos.
 *   - Faixa 3 (`NCG > 10% Vendas anualizadas`): pressão sobre o caixa.
 *
 * **Quando Vendas anuais é null ou zero** mas o NCG é calculável e positivo,
 * a referência "10% das Vendas" não existe — usamos a Faixa 2 como fallback
 * conservador (mensagem moderada, não alarmista).
 *
 * **Não consome a matriz `dez-2025`.** A STORY-032 (motor 1.3.0) migrou os 13
 * indicadores com farol para `MatrizRecomendacoes::texto()`. Este indicador
 * **não** entrou na migração: o Anexo F só tem 2 cenários (positiva/negativa)
 * e a granularidade FOLGA/MODERADO/ALTO entregue pela STORY-028 é mais útil
 * ao usuário. Decisão registrada no briefing da STORY-032. A entrada do Anexo F
 * para NCG abs fica disponível em `config/motor/matriz-dez-2025-industria.php`
 * (chaves `positiva`/`negativa`) para auditoria editorial.
 *
 * Casos extremos (catálogo §9):
 *   - Q03 ∨ Q04 ∨ Q07 ausente → `indisponivel:ncg_componente_faltante`.
 *   - Vendas faltante NÃO bloqueia — NCG é calculado de qualquer forma.
 */
final class NcgAbsoluto implements Indicador
{
    public const MSG_FOLGA = 'Folga operacional: seu ciclo de pagamento a fornecedores cobre o ciclo de recebimento e estoques. Capital de giro positivo.';

    public const MSG_MODERADO = 'NCG positivo moderado: patamar gerenciável. Acompanhe seus prazos médios (PMC, PME e PMR).';

    public const MSG_ALTO = 'NCG positivo alto: pressão sobre o caixa. Considere rever prazos médios ou estruturar captação.';

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
        $valor = (float) $ncg;

        $mensagem = $this->mensagemPorFaixa($ncg, $dre->vendasAnuais());

        return new IndicadorResultado(
            valor: $valor,
            farol: Farol::NENHUM,
            motivo: null,
            mensagem: $mensagem,
        );
    }

    private function mensagemPorFaixa(string $ncg, ?string $vendasAnuais): string
    {
        // Faixa 1: NCG ≤ 0.
        if (bccomp($ncg, '0', DreAdaptada::ESCALA) <= 0) {
            return self::MSG_FOLGA;
        }

        // Sem referência de vendas → fallback moderado.
        if ($vendasAnuais === null || bccomp($vendasAnuais, '0', DreAdaptada::ESCALA) <= 0) {
            return self::MSG_MODERADO;
        }

        // Faixa 3: NCG > 10% Vendas.
        $dezPctVendas = bcmul($vendasAnuais, '0.10', DreAdaptada::ESCALA);
        if (bccomp($ncg, $dezPctVendas, DreAdaptada::ESCALA) > 0) {
            return self::MSG_ALTO;
        }

        // Faixa 2: 0 < NCG ≤ 10% Vendas.
        return self::MSG_MODERADO;
    }
}

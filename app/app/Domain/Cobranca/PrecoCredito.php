<?php

declare(strict_types=1);

namespace App\Domain\Cobranca;

/**
 * STORY-071 / PDR-020 / spec §2.2 — o crédito avulso deve custar mais que a análise
 * dentro de um plano ativo, para preservar o incentivo à assinatura. A regra só
 * avisa, nunca impede. Tudo em centavos inteiros: a comparação é por produto
 * cruzado, sem divisão nem arredondamento.
 */
final class PrecoCredito
{
    /**
     * Nomes dos planos cujo unitário mensal (preço ÷ cota) supera o unitário do pacote.
     *
     * @param  iterable<array{nome: string, cota_mensal: int, preco_mensal_centavos: int}>  $planosAtivos
     * @return list<string>
     */
    public static function planosMaisBaratos(int $precoPacoteCentavos, int $quantidade, iterable $planosAtivos): array
    {
        if ($quantidade < 1) {
            return [];
        }

        $nomes = [];

        foreach ($planosAtivos as $plano) {
            if ($plano['cota_mensal'] >= 1 && $precoPacoteCentavos * $plano['cota_mensal'] < $plano['preco_mensal_centavos'] * $quantidade) {
                $nomes[] = $plano['nome'];
            }
        }

        return $nomes;
    }
}

<?php

declare(strict_types=1);

namespace App\Support\Drm;

/**
 * Quais linhas da demonstração são DERIVADAS de outras, e como (STORY-055 CA-4).
 *
 * Lê só a estrutura do snapshot (`nivel` e `tipo`, na ordem), nunca um código de linha fixo — a
 * única exceção declarada é a lista de linhas aditivas em `config/drm/exportacao.php`. Cada linha
 * derivada vira uma lista de termos `[sinal, posição]`:
 *
 *  - `total` de nível 1 = a linha-base anterior (item ou total de nível 1) menos as deduções que vêm
 *    depois dela (as aditivas somam);
 *  - `deducao` de nível 1 seguida de linhas de nível 2 = a soma dessas linhas.
 *
 * O plano é uma PROPOSTA: a planilha só escreve a fórmula num mês quando {@see self::confere()} prova
 * que ela reproduz o valor do snapshot. Fórmula que não bate vira o valor do snapshot — planilha que
 * mente é pior do que planilha sem fórmula.
 */
final class PlanoDeFormulas
{
    /**
     * @param  list<LinhaVisao|object{nivel: int, tipo: string, codigo: string}>  $linhas
     * @param  list<string>  $aditivas
     * @return array<int, list<array{0: int, 1: int}>> posição da linha => termos `[sinal, posição]`
     */
    public static function de(array $linhas, array $aditivas): array
    {
        $plano = [];
        $base = null;
        $deducoes = [];

        foreach ($linhas as $i => $linha) {
            if ($linha->nivel !== 1) {
                continue;
            }

            if ($linha->tipo === 'total' && $base !== null) {
                $termos = [[1, $base]];
                foreach ($deducoes as $d) {
                    $termos[] = [in_array($linhas[$d]->codigo, $aditivas, true) ? 1 : -1, $d];
                }
                $plano[$i] = $termos;
            }

            if ($linha->tipo === 'deducao') {
                $filhas = [];
                for ($j = $i + 1; $j < count($linhas) && $linhas[$j]->nivel >= 2; $j++) {
                    if ($linhas[$j]->nivel === 2) {
                        $filhas[] = [1, $j];
                    }
                }
                if ($filhas !== []) {
                    $plano[$i] = $filhas;
                }
                $deducoes[] = $i;
            }

            if ($linha->tipo === 'item' || $linha->tipo === 'total') {
                $base = $i;
                $deducoes = [];
            }
        }

        return $plano;
    }

    /**
     * A fórmula reproduz o valor do snapshot? Todos os operandos precisam existir (nulo nunca vira zero).
     *
     * @param  list<array{0: int, 1: int}>  $termos
     * @param  array<int, int|null>  $centavos  posição da linha => valor do snapshot naquele mês
     */
    public static function confere(array $termos, int $posicao, array $centavos): bool
    {
        if (($centavos[$posicao] ?? null) === null) {
            return false;
        }

        $soma = 0;
        foreach ($termos as [$sinal, $p]) {
            if (($centavos[$p] ?? null) === null) {
                return false;
            }
            $soma += $sinal * $centavos[$p];
        }

        return $soma === $centavos[$posicao];
    }
}

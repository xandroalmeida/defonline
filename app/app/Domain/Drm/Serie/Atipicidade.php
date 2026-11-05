<?php

declare(strict_types=1);

namespace App\Domain\Drm\Serie;

use App\Domain\Drm\Competencia;

/**
 * Alerta A4 (Anexo K §K.6, K.8) — rubrica atípica: o campo que passa de 3× a própria média móvel.
 *
 * Mesma regra no lançamento (alerta) e na série (marca do mês), para as duas telas nunca discordarem.
 * A média é a dos meses LANÇADOS nos 12 meses anteriores em que o campo foi coletado; mês sem
 * lançamento, ou com o campo suprimido (grupo agregado), fica de fora — não entra como zero.
 * A regra só vale com pelo menos 3 meses na janela (a partir do 4º lançamento). Média zero e valor
 * positivo contam como atípico: é a assinatura do 13º, que nos outros onze meses é zero.
 */
final class Atipicidade
{
    public const JANELA = 12;

    public const MINIMO_DE_MESES = 3;

    public const FATOR = 3;

    /**
     * @param  array<string, int|null>  $atuais  campo => centavos do mês analisado
     * @param  array<string, array<string, int|null>>  $historico  `AAAA-MM` => campos de cada mês lançado
     * @return list<array{campo: string, valor: int, media: int}> em ordem de campo
     */
    public static function detectar(Competencia $competencia, array $atuais, array $historico): array
    {
        $janela = [];
        for ($i = 1; $i <= self::JANELA; $i++) {
            $texto = $competencia->data->subMonthsNoOverflow($i)->format('Y-m');
            if (isset($historico[$texto])) {
                $janela[] = $historico[$texto];
            }
        }

        $atipicos = [];
        foreach ($atuais as $campo => $valor) {
            if ($valor === null || $valor <= 0) {
                continue;
            }
            $anteriores = [];
            foreach ($janela as $mes) {
                if (($mes[$campo] ?? null) !== null) {
                    $anteriores[] = $mes[$campo];
                }
            }
            $n = count($anteriores);
            if ($n < self::MINIMO_DE_MESES) {
                continue;
            }
            $soma = array_sum($anteriores);
            if ($valor * $n > self::FATOR * $soma) {
                $atipicos[] = ['campo' => $campo, 'valor' => $valor, 'media' => intdiv($soma, $n)];
            }
        }

        return $atipicos;
    }
}

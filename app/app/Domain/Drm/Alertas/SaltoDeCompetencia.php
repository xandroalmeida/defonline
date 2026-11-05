<?php

declare(strict_types=1);

namespace App\Domain\Drm\Alertas;

use App\Domain\Drm\Competencia;
use App\Domain\Drm\Lancamento;
use App\Domain\Drm\Ocorrencia;

/**
 * A6 — salto de competência: há meses sem lançamento entre o último lançamento anterior e o atual.
 * Mês sem lançamento não é mês zerado; o alerta só informa e oferece lançar o que falta.
 */
final class SaltoDeCompetencia implements RegraDeAlerta
{
    /**
     * @param  list<string>  $lancadas  competências lançadas (`AAAA-MM`)
     */
    public function __construct(private readonly array $lancadas) {}

    public function codigo(): string
    {
        return 'A6';
    }

    public function avaliar(Lancamento $lancamento, Competencia $competencia): ?Ocorrencia
    {
        $anteriores = array_filter($this->lancadas, static fn (string $c): bool => $c < $competencia->texto());
        if ($anteriores === []) {
            return null;
        }

        $ultima = Competencia::deTexto(max($anteriores));
        $faltantes = [];
        for ($c = $ultima->data->addMonthNoOverflow(); $c->format('Y-m') < $competencia->texto(); $c = $c->addMonthNoOverflow()) {
            $faltantes[] = Competencia::deData($c);
        }
        if ($faltantes === []) {
            return null;
        }

        return new Ocorrencia('A6', null, [
            'ultima_competencia' => $ultima->rotulo(),
            'meses_faltantes' => self::descrever($faltantes),
            'primeira_faltante' => $faltantes[0]->texto(),
        ]);
    }

    /**
     * @param  non-empty-list<Competencia>  $faltantes
     */
    private static function descrever(array $faltantes): string
    {
        $n = count($faltantes);
        if ($n === 1) {
            return '1 mês ('.$faltantes[0]->rotulo().')';
        }
        if ($n > 3) {
            return $n.' meses (de '.$faltantes[0]->rotulo().' a '.$faltantes[$n - 1]->rotulo().')';
        }

        $rotulos = array_map(static fn (Competencia $c): string => $c->rotulo(), $faltantes);
        $ultimo = array_pop($rotulos);

        return $n.' meses ('.implode(', ', $rotulos).' e '.$ultimo.')';
    }
}

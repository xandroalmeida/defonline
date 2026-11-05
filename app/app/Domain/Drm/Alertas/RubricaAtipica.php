<?php

declare(strict_types=1);

namespace App\Domain\Drm\Alertas;

use App\Domain\Drm\Competencia;
use App\Domain\Drm\Dinheiro;
use App\Domain\Drm\Lancamento;
use App\Domain\Drm\Ocorrencia;
use App\Domain\Drm\Serie\Atipicidade;

/**
 * A4 — rubrica atípica: campo acima de 3× a própria média móvel (Anexo K §K.6, K.8).
 * Cobre férias e 13º. Informa; o mês continua entrando em todas as contas.
 */
final class RubricaAtipica implements RegraComVariasOcorrencias
{
    /**
     * @param  array<string, array<string, int|null>>  $historico  `AAAA-MM` => campos de cada mês lançado
     */
    public function __construct(private readonly array $historico) {}

    public function codigo(): string
    {
        return 'A4';
    }

    public function ocorrencias(Lancamento $lancamento, Competencia $competencia): array
    {
        $atuais = [];
        foreach (Lancamento::todosOsCampos() as $campo) {
            $atuais[$campo] = $lancamento->centavos[$campo] ?? null;
        }

        return array_map(
            static fn (array $a): Ocorrencia => new Ocorrencia('A4', $a['campo'], [
                'valor' => Dinheiro::brl($a['valor']),
                'media' => Dinheiro::brl($a['media']),
            ], $a['valor']),
            Atipicidade::detectar($competencia, $atuais, $this->historico),
        );
    }
}

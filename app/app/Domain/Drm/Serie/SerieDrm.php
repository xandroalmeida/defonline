<?php

declare(strict_types=1);

namespace App\Domain\Drm\Serie;

use App\Domain\Drm\Competencia;
use App\Domain\Drm\Percentual;

/**
 * A série histórica de uma Empresa Analisada: só os meses LANÇADOS, lidos das versões vigentes.
 *
 * **Ausência não é zero (ADR-007, DDR-005).** Um mês sem lançamento simplesmente não está aqui;
 * nenhum método devolve `0` para ele. A espinha de meses é gerada na leitura ({@see espinha()}) e
 * cada comparativo olha só os meses que existem. Os comparativos nunca são persistidos: saem
 * destes snapshots a cada leitura, então retificar um mês muda o do mês seguinte sem tocá-lo.
 */
final class SerieDrm
{
    /** Janela da média móvel e da participação média, em meses. */
    public const JANELA = 12;

    /** Mínimo de competências anteriores para a média móvel existir. */
    public const MINIMO_PARA_MEDIA = 3;

    /** @var array<string, MesDaSerie> `AAAA-MM` => mês */
    private array $meses = [];

    /**
     * @param  list<MesDaSerie>  $meses
     */
    public function __construct(array $meses)
    {
        foreach ($meses as $mes) {
            $this->meses[$mes->competencia->texto()] = $mes;
        }
        ksort($this->meses);
    }

    public function mes(Competencia $competencia): ?MesDaSerie
    {
        return $this->meses[$competencia->texto()] ?? null;
    }

    /** @return list<MesDaSerie> do mais antigo ao mais recente */
    public function meses(): array
    {
        return array_values($this->meses);
    }

    /**
     * Todos os meses entre o primeiro lançado (ou rascunho) e `$ate`, do mais recente ao mais antigo.
     * Vazia se não há lançamento nem rascunho. Mês sem lançamento está na espinha, e é por isso que
     * aparece como lacuna.
     *
     * @param  list<Competencia>  $rascunhos
     * @return list<Competencia>
     */
    public function espinha(Competencia $ate, array $rascunhos = []): array
    {
        $pontas = array_merge(
            array_map(static fn (MesDaSerie $m): Competencia => $m->competencia, array_values($this->meses)),
            $rascunhos,
        );
        if ($pontas === []) {
            return [];
        }

        $primeira = min(array_map(static fn (Competencia $c): string => $c->texto(), $pontas));
        $ultima = max($ate->texto(), ...array_map(static fn (Competencia $c): string => $c->texto(), $pontas));

        $espinha = [];
        for ($c = Competencia::deTexto($ultima); $c->texto() >= $primeira; $c = self::deslocar($c, -1)) {
            $espinha[] = $c;
        }

        return $espinha;
    }

    /**
     * Os quatro comparativos de uma linha da demonstração para um mês lançado.
     */
    public function comparativos(Competencia $competencia, string $linha): ComparativosDaLinha
    {
        $mes = $this->mes($competencia);
        if ($mes === null) {
            return ComparativosDaLinha::todosIndisponiveis(Comparativo::SEM_LANCAMENTO);
        }

        $atual = $mes->nominal($linha);
        if ($atual === null) {
            // Item que o mês não informou (grupo agregado): nunca se infere do total.
            return ComparativosDaLinha::todosIndisponiveis(Comparativo::GRUPO_AGREGADO);
        }

        return new ComparativosDaLinha(
            $this->variacaoContraAnterior($mes, $linha, $atual),
            $this->variacaoContraMedia($mes, $linha, $atual),
            $this->acumuladoNoAno($mes, $linha),
            $this->participacaoMedia($mes, $linha),
        );
    }

    /**
     * Campos atípicos de um mês lançado (alerta A4), para a marca da série.
     *
     * @return list<array{campo: string, valor: int, media: int}>
     */
    public function atipicos(Competencia $competencia): array
    {
        $mes = $this->mes($competencia);

        return $mes === null ? [] : Atipicidade::detectar($competencia, $mes->campos, $this->camposPorMes());
    }

    /** @return array<string, array<string, int|null>> */
    public function camposPorMes(): array
    {
        return array_map(static fn (MesDaSerie $m): array => $m->campos, $this->meses);
    }

    // ------------------------------------------------------------------ comparativos

    private function variacaoContraAnterior(MesDaSerie $mes, string $linha, int $atual): Comparativo
    {
        $anterior = $this->mes(self::deslocar($mes->competencia, -1));
        if ($anterior === null) {
            return Comparativo::indisponivel($this->existeAnteriorA($mes) ? Comparativo::SEM_LANCAMENTO : Comparativo::PRIMEIRO_LANCAMENTO);
        }

        $base = $anterior->nominal($linha);
        if ($base === null) {
            return Comparativo::indisponivel(Comparativo::GRUPO_AGREGADO);
        }
        if ($base === 0) {
            return Comparativo::indisponivel(Comparativo::BASE_ZERO);
        }

        return Comparativo::percentual((string) Percentual::deCentavos($atual - $base, abs($base)), 1);
    }

    private function variacaoContraMedia(MesDaSerie $mes, string $linha, int $atual): Comparativo
    {
        $janela = $this->anterioresNaJanela($mes, self::JANELA);
        if (count($janela) < self::MINIMO_PARA_MEDIA) {
            return Comparativo::indisponivel(Comparativo::HISTORICO_INSUFICIENTE);
        }

        $valores = [];
        foreach ($janela as $anterior) {
            $valor = $anterior->nominal($linha);
            if ($valor !== null) {
                $valores[] = $valor;
            }
        }
        $n = count($valores);
        if ($n < self::MINIMO_PARA_MEDIA) {
            return Comparativo::indisponivel(Comparativo::GRUPO_AGREGADO);
        }

        $soma = array_sum($valores);
        if ($soma === 0) {
            return Comparativo::indisponivel(Comparativo::BASE_ZERO);
        }

        // (atual − soma/n) / |soma/n|, sem dividir a média antes: nenhuma casa perdida.
        return Comparativo::percentual((string) Percentual::deCentavos($atual * $n - $soma, abs($soma)), $n);
    }

    private function acumuladoNoAno(MesDaSerie $mes, string $linha): Comparativo
    {
        $ano = $mes->competencia->data->year;
        $soma = 0;
        $quantos = 0;
        foreach ($this->meses as $outro) {
            if ($outro->competencia->data->year !== $ano || $outro->competencia->texto() > $mes->competencia->texto()) {
                continue;
            }
            $valor = $outro->nominal($linha);
            if ($valor === null) {
                return Comparativo::indisponivel(Comparativo::GRUPO_AGREGADO);
            }
            $soma += $valor;
            $quantos++;
        }

        return Comparativo::centavos($soma, $quantos);
    }

    private function participacaoMedia(MesDaSerie $mes, string $linha): Comparativo
    {
        $percentuais = [];
        $algumNominal = false;
        foreach ([$mes, ...$this->anterioresNaJanela($mes, self::JANELA - 1)] as $dessaJanela) {
            $algumNominal = $algumNominal || $dessaJanela->nominal($linha) !== null;
            $percentual = $dessaJanela->percentual($linha);
            if ($percentual !== null) {
                $percentuais[] = $percentual;
            }
        }

        if ($percentuais === []) {
            return Comparativo::indisponivel($algumNominal ? Comparativo::SEM_FATURAMENTO : Comparativo::GRUPO_AGREGADO);
        }

        return Comparativo::percentual(Percentual::media($percentuais), count($percentuais));
    }

    // ------------------------------------------------------------------ janela

    /**
     * Os meses LANÇADOS dentre os `$tamanho` meses de calendário anteriores ao dado.
     *
     * @return list<MesDaSerie>
     */
    private function anterioresNaJanela(MesDaSerie $mes, int $tamanho): array
    {
        $janela = [];
        for ($i = 1; $i <= $tamanho; $i++) {
            $anterior = $this->mes(self::deslocar($mes->competencia, -$i));
            if ($anterior !== null) {
                $janela[] = $anterior;
            }
        }

        return $janela;
    }

    private function existeAnteriorA(MesDaSerie $mes): bool
    {
        $primeira = array_key_first($this->meses);

        return $primeira < $mes->competencia->texto();
    }

    private static function deslocar(Competencia $competencia, int $meses): Competencia
    {
        return Competencia::deData($competencia->data->addMonthsNoOverflow($meses));
    }
}

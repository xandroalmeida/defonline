<?php

declare(strict_types=1);

namespace App\Support\Drm;

use App\Domain\Drm\Competencia;
use App\Domain\Drm\Serie\Comparativo;
use App\Domain\Drm\Serie\SerieDrm;

/**
 * Frases dos comparativos (spec `STORY-046-serie-historica` §4.5). Cada comparativo vira UMA frase,
 * disponível ou não: o motivo de uma indisponibilidade é dito em palavras, nunca `—` solto nem `0%`.
 */
final class ComparativosVisao
{
    /**
     * Os quatro comparativos de cada linha de nível 1 (CA-3), já em frases, para a Demonstração do Mês.
     *
     * @param  list<LinhaVisao>  $linhas
     * @return list<array{codigo: string, rotulo: string, itens: list<array{chave: string, titulo: string, texto: string, disponivel: bool}>}>
     */
    public static function dasLinhas(SerieDrm $serie, Competencia $competencia, array $linhas): array
    {
        $saida = [];
        foreach ($linhas as $linha) {
            if ($linha->nivel !== 1) {
                continue;
            }
            $c = $serie->comparativos($competencia, $linha->codigo);
            $saida[] = [
                'codigo' => $linha->codigo,
                'rotulo' => $linha->rotulo,
                'itens' => [
                    ['chave' => 'anterior', 'titulo' => (string) config('drm.serie.linha.vs_anterior'), 'texto' => self::vsAnterior($competencia, $c->vsAnterior), 'disponivel' => $c->vsAnterior->disponivel()],
                    ['chave' => 'media', 'titulo' => (string) config('drm.serie.linha.vs_media'), 'texto' => self::mediaDaLinha($c->vsMedia), 'disponivel' => $c->vsMedia->disponivel()],
                    ['chave' => 'acumulado', 'titulo' => (string) config('drm.serie.linha.acumulado'), 'texto' => self::acumulado($competencia, $c->acumuladoAno), 'disponivel' => $c->acumuladoAno->disponivel()],
                    ['chave' => 'participacao', 'titulo' => (string) config('drm.serie.linha.participacao'), 'texto' => self::participacao($c->participacaoMedia), 'disponivel' => $c->participacaoMedia->disponivel()],
                ],
            ];
        }

        return $saida;
    }

    /** Mês anterior: `+12,4% em relação a julho`, ou o motivo. */
    public static function vsAnterior(Competencia $competencia, Comparativo $c): string
    {
        $anterior = ucfirst(self::anterior($competencia)->mesPorExtenso());
        $anterior = mb_strtolower($anterior);

        if ($c->percentual !== null) {
            return strtr((string) config('drm.serie.vs_anterior'), [':variacao' => Formato::variacao($c->percentual), ':mes' => $anterior]);
        }

        return self::motivo($c, $anterior);
    }

    /** Média móvel: `+5,1% em relação à média dos meses anteriores`, ou o motivo. */
    public static function vsMedia(Comparativo $c): string
    {
        if ($c->percentual !== null) {
            return strtr((string) config('drm.serie.vs_media'), [':variacao' => Formato::variacao($c->percentual)]);
        }

        return self::motivo($c, '');
    }

    /** Detalhe da média na Demonstração do Mês: diz de quantos meses lançados ela veio. */
    public static function mediaDaLinha(Comparativo $c): string
    {
        if ($c->percentual === null) {
            return self::motivo($c, '');
        }

        return strtr((string) config('drm.serie.linha.media_de'), [':variacao' => Formato::variacao($c->percentual), ':n' => (string) $c->meses]);
    }

    public static function acumulado(Competencia $competencia, Comparativo $c): string
    {
        if ($c->centavos === null) {
            return self::motivo($c, '');
        }

        return strtr((string) config('drm.serie.linha.acumulado_de'), [
            ':valor' => Formato::moeda($c->centavos),
            ':n' => (string) $c->meses,
            ':ano' => (string) $competencia->data->year,
        ]);
    }

    public static function participacao(Comparativo $c): string
    {
        if ($c->percentual === null) {
            return self::motivo($c, '');
        }

        return strtr((string) config('drm.serie.linha.participacao_de'), [':percentual' => Formato::percentual($c->percentual), ':n' => (string) $c->meses]);
    }

    private static function motivo(Comparativo $c, string $mesDeBase): string
    {
        $frase = config('drm.serie.indisponivel.'.$c->motivo);

        return is_string($frase) ? strtr($frase, [':mes' => $mesDeBase]) : 'não dá para comparar';
    }

    private static function anterior(Competencia $competencia): Competencia
    {
        return Competencia::deData($competencia->data->subMonthNoOverflow());
    }
}

<?php

declare(strict_types=1);

namespace App\Support\Drm;

use App\Domain\Drm\Competencia;
use App\Domain\Drm\ConteudoDrm;
use App\Domain\Drm\Serie\MesDaSerie;
use App\Domain\Drm\Serie\SerieDrm;
use App\Models\EmpresaAnalisada;
use Carbon\CarbonImmutable;

/**
 * A espinha de meses da série pronta para a tela (STORY-051, spec `STORY-046-serie-historica`).
 *
 * **Lê, não calcula e não inventa.** Os valores vêm do snapshot vigente de cada mês, os comparativos
 * de {@see SerieDrm} (derivados na leitura) e a ausência de lançamento é uma linha de estado
 * `nao_lancado` — sem valor, sem comparativo, nunca zero (DDR-005). Mês lançado, retificado, não
 * lançado e rascunho em aberto são estados distintos na marcação e no texto, não só na cor.
 */
final readonly class SerieVisao
{
    /**
     * @param  list<array{ano: int, linhas: list<LinhaDaSerie>}>  $anos  do mais recente ao mais antigo
     */
    private function __construct(
        public array $anos,
        public ?string $avisoLacunas,
        public ?string $ctaRotulo,
        public ?string $ctaUrl,
        public bool $vazio,
    ) {}

    /**
     * @param  array<string, array{bloco: int|null}>  $rascunhos  `AAAA-MM` => rascunho de lançamento inicial em aberto
     * @param  array<int, string>  $nomesDosBlocos
     */
    public static function de(
        EmpresaAnalisada $empresa,
        SerieDrm $serie,
        array $rascunhos,
        array $nomesDosBlocos,
        CarbonImmutable $hoje,
        ConteudoDrm $conteudo,
    ): self {
        $abertos = array_values(array_filter(
            array_keys($rascunhos),
            static fn (string $texto): bool => $serie->mes(Competencia::deTexto($texto)) === null,
        ));
        $espinha = $serie->espinha(
            Competencia::padrao($hoje),
            array_map(static fn (string $t): Competencia => Competencia::deTexto($t), $abertos),
        );

        $anos = [];
        $cta = null;
        foreach ($espinha as $competencia) {
            $mes = $serie->mes($competencia);
            $linha = $mes === null
                ? self::linhaEmAberto($empresa, $competencia, $rascunhos[$competencia->texto()] ?? null, $nomesDosBlocos, $hoje)
                : self::linhaLancada($empresa, $serie, $mes, $hoje, $conteudo);
            $cta ??= $mes === null ? $competencia : null;
            $anos[$competencia->data->year][] = $linha;
        }

        $vazio = $espinha === [];
        $ctaCompetencia = $cta ?? ($vazio ? Competencia::padrao($hoje) : null);

        return new self(
            anos: array_map(static fn (int $ano, array $linhas): array => ['ano' => $ano, 'linhas' => $linhas], array_keys($anos), array_values($anos)),
            avisoLacunas: self::avisoDeLacunas($serie),
            ctaRotulo: $ctaCompetencia === null ? null : strtr((string) config('drm.serie.cta'), [':mes' => $ctaCompetencia->rotulo()]),
            ctaUrl: $ctaCompetencia === null ? null : route('drm.lancar', [$empresa, 'competencia' => $ctaCompetencia->texto()]),
            vazio: $vazio,
        );
    }

    // ------------------------------------------------------------------ linhas

    /**
     * @param  array{bloco: int|null}|null  $rascunho
     * @param  array<int, string>  $nomesDosBlocos
     */
    private static function linhaEmAberto(EmpresaAnalisada $empresa, Competencia $competencia, ?array $rascunho, array $nomesDosBlocos, CarbonImmutable $hoje): LinhaDaSerie
    {
        $ehRascunho = $rascunho !== null;
        $tipo = $ehRascunho ? 'continuar' : 'lancar';
        $nome = self::nome($competencia);
        $bloco = $ehRascunho ? ($nomesDosBlocos[(int) $rascunho['bloco']] ?? null) : null;

        return new LinhaDaSerie(
            competencia: $competencia->texto(),
            estado: $ehRascunho ? LinhaDaSerie::RASCUNHO : LinhaDaSerie::NAO_LANCADO,
            rotulo: self::rotulo($competencia, $hoje),
            nomeAcessivel: $nome,
            estadoTexto: (string) config('drm.serie.estado.'.($ehRascunho ? 'rascunho' : 'nao_lancado')),
            acaoTipo: $tipo,
            acaoRotulo: (string) config("drm.serie.acoes.{$tipo}"),
            acaoAria: strtr((string) config("drm.serie.acoes_acessiveis.{$tipo}"), [':mes' => $nome]),
            acaoUrl: route('drm.lancar', [$empresa, 'competencia' => $competencia->texto()]),
            rascunhoTexto: $ehRascunho
                ? ($bloco === null ? (string) config('drm.serie.rascunho_sem_bloco') : strtr((string) config('drm.serie.rascunho_parou'), [':bloco' => mb_strtolower($bloco)]))
                : (string) config('drm.serie.sem_lancamento'),
        );
    }

    private static function linhaLancada(EmpresaAnalisada $empresa, SerieDrm $serie, MesDaSerie $mes, CarbonImmutable $hoje, ConteudoDrm $conteudo): LinhaDaSerie
    {
        $competencia = $mes->competencia;
        $nome = self::nome($competencia);
        $final = $mes->codigoFinal();
        $nominal = $final === null ? null : ($mes->nominais[$final] ?? null);
        $percentual = $final === null ? null : ($mes->percentuais[$final] ?? null);
        $ancora = $mes->codigoAncora();
        $vendeu = $ancora === null ? null : ($mes->nominais[$ancora] ?? null);

        $comparativos = $final === null ? null : $serie->comparativos($competencia, $final);
        $atipicos = $serie->atipicos($competencia);

        return new LinhaDaSerie(
            competencia: $competencia->texto(),
            estado: LinhaDaSerie::LANCADO,
            rotulo: self::rotulo($competencia, $hoje),
            nomeAcessivel: $nome,
            estadoTexto: (string) config('drm.serie.estado.lancado'),
            acaoTipo: 'corrigir',
            acaoRotulo: (string) config('drm.serie.acoes.corrigir'),
            acaoAria: strtr((string) config('drm.serie.acoes_acessiveis.corrigir'), [':mes' => $nome]),
            acaoUrl: route('drm.corrigir', [$empresa, $competencia->texto()]),
            mesUrl: route('drm.mes', [$empresa, $competencia->texto()]),
            retificado: $mes->versao > 1,
            corrigidoTexto: $mes->versao > 1 ? strtr((string) config('drm.serie.corrigido_vezes'), [':n' => (string) ($mes->versao - 1)]) : null,
            atipico: $atipicos !== [],
            explicacao: $atipicos === [] ? null : self::explicacao($competencia, array_column($atipicos, 'campo'), $conteudo),
            prejuizo: $nominal !== null && $nominal < 0,
            valor: $nominal === null ? null : Formato::moeda($nominal),
            valorSemMoeda: $nominal === null ? null : Formato::moeda($nominal, false),
            percentual: $percentual === null ? null : Formato::percentual($percentual),
            percentualAusente: $percentual === null ? (string) config('drm.tela.motivos_curtos.indisponivel:sem_faturamento') : null,
            vendeu: $vendeu === null ? null : Formato::moeda($vendeu),
            vsAnterior: $comparativos === null ? null : ComparativosVisao::vsAnterior($competencia, $comparativos->vsAnterior),
            vsAnteriorOk: $comparativos?->vsAnterior->disponivel() ?? false,
            vsMedia: $comparativos === null ? null : ComparativosVisao::vsMedia($comparativos->vsMedia),
            vsMediaOk: $comparativos?->vsMedia->disponivel() ?? false,
        );
    }

    /**
     * `Dezembro teve décimo-terceiro salário — por isso o resultado do mês caiu.`
     *
     * @param  list<string>  $campos
     */
    private static function explicacao(Competencia $competencia, array $campos, ConteudoDrm $conteudo): string
    {
        $receitas = (array) config('drm.serie.atipico_receitas');
        $soReceita = array_diff($campos, $receitas) === [];

        $rotulos = array_map(
            static fn (string $c): string => mb_strtolower((string) $conteudo->rotuloCampo($c)),
            array_slice($campos, 0, 3),
        );
        if (count($campos) > 3) {
            $rotulos[] = (string) config('drm.serie.atipico_outras');
        }
        $ultimo = array_pop($rotulos);
        $rubricas = $rotulos === [] ? (string) $ultimo : implode(', ', $rotulos).' e '.$ultimo;

        return strtr((string) config('drm.serie.atipico_explicacao'), [
            ':mes' => ucfirst($competencia->mesPorExtenso()),
            ':rubricas' => $rubricas,
            ':direcao' => (string) config('drm.serie.atipico_direcao.'.($soReceita ? 'receita' : 'despesa')),
        ]);
    }

    /** Spec §4.9: com 2 ou mais meses sem lançamento entre o primeiro e o último lançado. */
    private static function avisoDeLacunas(SerieDrm $serie): ?string
    {
        $meses = $serie->meses();
        if (count($meses) < 2) {
            return null;
        }
        $primeiro = $meses[0]->competencia;
        $ultimo = $meses[count($meses) - 1]->competencia;
        $entre = ($ultimo->data->year * 12 + $ultimo->data->month) - ($primeiro->data->year * 12 + $primeiro->data->month) - 1;
        $faltam = $entre - (count($meses) - 2);
        if ($faltam < 2) {
            return null;
        }

        return strtr((string) config('drm.serie.lacunas'), [':n' => (string) $faltam, ':de' => $primeiro->rotulo(), ':ate' => $ultimo->rotulo()]);
    }

    // ------------------------------------------------------------------ rótulos

    /** `AGOSTO` no ano corrente (a caixa alta é do CSS); `dezembro/2025` nos anteriores. */
    private static function rotulo(Competencia $competencia, CarbonImmutable $hoje): string
    {
        return $competencia->data->year === $hoje->year ? $competencia->mesPorExtenso() : $competencia->rotulo();
    }

    /** `agosto de 2026`: o nome que o leitor de tela lê. */
    private static function nome(Competencia $competencia): string
    {
        return $competencia->mesPorExtenso().' de '.$competencia->data->year;
    }
}

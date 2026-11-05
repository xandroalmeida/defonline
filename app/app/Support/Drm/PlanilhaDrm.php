<?php

declare(strict_types=1);

namespace App\Support\Drm;

use App\Domain\Drm\Competencia;
use App\Domain\Drm\Setor;
use App\Models\DrmLancamento;
use App\Models\EmpresaAnalisada;
use App\Support\DataExibicao;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * A Demonstração em planilha XLSX (STORY-055, ADR-008): um arquivo, duas abas.
 *
 *  - **Mês**: a competência aberta, na estrutura de `estrutura-drm.md` §1 (mesma ordem, mesma hierarquia,
 *    coluna de % sobre a receita), com empresa, competência e, se houver, a retificação sinalizada;
 *  - **Série histórica**: uma coluna por mês, da primeira à última competência lançada. Mês sem lançamento é
 *    coluna em branco (ausência ≠ zero).
 *
 * **A fonte é o snapshot** (`drm_lancamentos.demonstracao` das versões vigentes), nunca o motor. As linhas
 * derivadas saem como fórmula do Excel ({@see PlanoDeFormulas}) quando a fórmula reproduz o snapshot; se não
 * reproduz, sai o valor do snapshot. Nada é gravado: a planilha é montada na requisição e descartada (ADR-008).
 */
final class PlanilhaDrm
{
    private const FORMATO_VALOR = '#,##0.00;-#,##0.00';

    private const FORMATO_PERCENTUAL = '0.0%;-0.0%';

    /** Linha de cabeçalho do bloco de linhas da aba Mês. */
    private const CABECALHO_MES = 8;

    public static function gerar(EmpresaAnalisada $empresa, DrmLancamento $aberto, string $posicao): Spreadsheet
    {
        $planilha = new Spreadsheet;
        $planilha->getProperties()->setTitle((string) config('drm.exportacao.titulo'))->setCreator('DEFOnline');

        $vigentes = DrmLancamento::query()
            ->vigente()
            ->where('empresa_analisada_id', $empresa->id)
            ->orderBy('competencia')
            ->get();

        $mes = $planilha->getActiveSheet();
        $mes->setTitle((string) config('drm.exportacao.aba_mes'));
        $visao = DemonstracaoVisao::de($aberto->demonstracao->getArrayCopy(), $posicao);
        self::abaMes($mes, $empresa, $aberto, $visao);

        $serie = $planilha->createSheet();
        $serie->setTitle((string) config('drm.exportacao.aba_serie'));
        self::abaSerie($serie, $empresa, $vigentes, $visao->posicao);

        $planilha->setActiveSheetIndex(0);

        return $planilha;
    }

    /** `drm_<empresa>_<AAAA-MM>.xlsx`: previsível e ordenável por empresa e depois por competência (CA-8). */
    public static function nomeArquivo(EmpresaAnalisada $empresa, Competencia $competencia): string
    {
        $nome = Str::slug($empresa->nomeExibicao()) ?: 'empresa';

        return sprintf('drm_%s_%s.xlsx', $nome, $competencia->texto());
    }

    // ------------------------------------------------------------------ aba Mês

    private static function abaMes(Worksheet $aba, EmpresaAnalisada $empresa, DrmLancamento $aberto, DemonstracaoVisao $visao): void
    {
        $competencia = Competencia::deData($aberto->competencia);
        $c = (array) config('drm.exportacao.cabecalho');
        $setor = Setor::tryFrom($aberto->setor);

        $aba->setCellValue('A1', (string) config('drm.exportacao.titulo'));
        $aba->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $cabecalho = [
            [$c['empresa'], $empresa->nomeExibicao()],
            [$c['competencia'], ucfirst($competencia->mesPorExtenso()).' de '.$competencia->data->year],
            [$c['setor'], $setor === null ? $aberto->setor : self::rotuloSetor($setor)],
            [$c['leitura'], (string) config("drm.tela.modo.{$visao->posicao}.frase")],
            [$c['situacao'], self::situacao($aberto)],
        ];
        foreach ($cabecalho as $i => [$rotulo, $valor]) {
            $aba->setCellValue('A'.(2 + $i), $rotulo);
            $aba->setCellValueExplicit('B'.(2 + $i), $valor, DataType::TYPE_STRING);
            $aba->getStyle('A'.(2 + $i))->getFont()->setBold(true);
        }

        $col = (array) config('drm.exportacao.colunas');
        $h = self::CABECALHO_MES;
        foreach (['A' => 'linha', 'B' => 'valor', 'C' => 'percentual', 'D' => 'observacao'] as $letra => $chave) {
            $aba->setCellValue($letra.$h, $col[$chave]);
        }
        $aba->getStyle("A{$h}:D{$h}")->getFont()->setBold(true);
        $aba->getStyle("A{$h}:D{$h}")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN);
        $aba->getStyle("B{$h}:C{$h}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        $linhas = $visao->linhas;
        $plano = PlanoDeFormulas::de($linhas, (array) config('drm.exportacao.linhas_aditivas'));
        $centavos = array_map(static fn (LinhaVisao $l): ?int => $l->centavos, $linhas);
        $primeira = $h + 1;
        $ancora = $primeira; // a primeira linha de nível 1 é a receita bruta, âncora de 100%.

        foreach ($linhas as $i => $l) {
            $r = $primeira + $i;
            self::texto($aba, "A{$r}", $l->prefixo.$l->rotulo);
            $aba->getStyle("A{$r}")->getAlignment()->setIndent(max(0, $l->nivel - 1));

            if ($l->centavos === null) {
                self::texto($aba, "D{$r}", (string) $l->nominalAusente);
            } elseif (isset($plano[$i]) && PlanoDeFormulas::confere($plano[$i], $i, $centavos)) {
                $aba->setCellValue("B{$r}", self::formula($plano[$i], 'B', $primeira));
            } else {
                $aba->setCellValue("B{$r}", $l->centavos / 100);
            }

            if ($l->percentualBruto !== null && $l->centavos !== null && ($centavos[0] ?? 0) !== 0) {
                $aba->setCellValue("C{$r}", self::percentualConfere($l->centavos, (int) $centavos[0], $l->percentualBruto)
                    ? "=B{$r}/B\${$ancora}"
                    : (float) $l->percentualBruto / 100);
            } elseif ($l->percentualBruto === null) {
                self::texto($aba, "D{$r}", trim($aba->getCell("D{$r}")->getValue().' '.$l->percentualAusente));
            }

            if ($l->prejuizo) {
                self::texto($aba, "D{$r}", trim($aba->getCell("D{$r}")->getValue().' '.config('drm.tela.pill_prejuizo')));
            }

            if ($l->ehTotal()) {
                $aba->getStyle("A{$r}:C{$r}")->getFont()->setBold(true);
            }
        }

        $ultima = $primeira + max(count($linhas), 1) - 1;
        $aba->getStyle("B{$primeira}:B{$ultima}")->getNumberFormat()->setFormatCode(self::FORMATO_VALOR);
        $aba->getStyle("C{$primeira}:C{$ultima}")->getNumberFormat()->setFormatCode(self::FORMATO_PERCENTUAL);

        $rodape = $ultima + 2;
        $aba->setCellValue("A{$rodape}", self::rodape($visao));
        $aba->getStyle("A{$rodape}")->getFont()->setItalic(true);

        $aba->getColumnDimension('A')->setWidth(48);
        $aba->getColumnDimension('B')->setWidth(18);
        $aba->getColumnDimension('C')->setWidth(14);
        $aba->getColumnDimension('D')->setWidth(48);
        $aba->freezePane('A'.$primeira);
    }

    // ------------------------------------------------------------------ aba Série histórica

    /**
     * @param  Collection<int, DrmLancamento>  $vigentes
     */
    private static function abaSerie(Worksheet $aba, EmpresaAnalisada $empresa, Collection $vigentes, string $posicao): void
    {
        $c = (array) config('drm.exportacao.cabecalho');
        $aba->setCellValue('A1', (string) config('drm.exportacao.titulo').' — '.config('drm.exportacao.aba_serie'));
        $aba->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $aba->setCellValue('A2', $c['empresa']);
        $aba->setCellValueExplicit('B2', $empresa->nomeExibicao(), DataType::TYPE_STRING);
        $aba->getStyle('A2')->getFont()->setBold(true);
        $aba->setCellValue('A3', (string) config('drm.exportacao.serie.nota'));
        $aba->getStyle('A3')->getFont()->setItalic(true);

        if ($vigentes->isEmpty()) {
            return;
        }

        // A espinha de meses vai do primeiro ao último lançamento, sem buracos; o buraco é coluna em branco.
        $primeiro = Competencia::deData($vigentes->first()->competencia)->data->startOfMonth();
        $ultimo = Competencia::deData($vigentes->last()->competencia)->data->startOfMonth();
        $meses = [];
        for ($m = $primeiro; $m <= $ultimo; $m = $m->addMonth()) {
            $meses[$m->format('Y-m')] = null;
        }
        /** @var array<string, array{lancamento: DrmLancamento, visao: DemonstracaoVisao}> $lancados */
        $lancados = [];
        foreach ($vigentes as $v) {
            $chave = Competencia::deData($v->competencia)->texto();
            $lancados[$chave] = ['lancamento' => $v, 'visao' => DemonstracaoVisao::de($v->demonstracao->getArrayCopy(), $posicao)];
        }

        $espinha = self::espinhaDeLinhas(array_map(static fn (array $x): DemonstracaoVisao => $x['visao'], array_values($lancados)));
        $plano = PlanoDeFormulas::de($espinha, (array) config('drm.exportacao.linhas_aditivas'));

        $cabecalho = 5;
        $situacao = $cabecalho + 1;
        $inicioValores = $situacao + 2; // título do bloco, depois as linhas
        $aba->setCellValue("A{$cabecalho}", $c['competencia']);
        $aba->setCellValue("A{$situacao}", $c['situacao']);
        $aba->setCellValue('A'.($inicioValores - 1), (string) config('drm.exportacao.serie.bloco_valores'));
        $totalLinhas = count($espinha);
        $inicioPercentuais = $inicioValores + $totalLinhas + 2;
        $aba->setCellValue('A'.($inicioPercentuais - 1), (string) config('drm.exportacao.serie.bloco_percentuais'));
        foreach (['A'.($inicioValores - 1), 'A'.($inicioPercentuais - 1), "A{$cabecalho}", "A{$situacao}"] as $negrito) {
            $aba->getStyle($negrito)->getFont()->setBold(true);
        }

        foreach ($espinha as $i => $l) {
            foreach ([$inicioValores, $inicioPercentuais] as $inicio) {
                self::texto($aba, 'A'.($inicio + $i), $l->prefixo.$l->rotulo);
                $aba->getStyle('A'.($inicio + $i))->getAlignment()->setIndent(max(0, $l->nivel - 1));
                if ($l->ehTotal()) {
                    $aba->getStyle('A'.($inicio + $i))->getFont()->setBold(true);
                }
            }
        }

        $coluna = 2;
        foreach (array_keys($meses) as $chave) {
            $letra = Coordinate::stringFromColumnIndex($coluna++);
            $aba->setCellValue("{$letra}{$cabecalho}", Competencia::deTexto($chave)->rotulo());
            $aba->getStyle("{$letra}{$cabecalho}")->getFont()->setBold(true);
            $aba->getStyle("{$letra}{$cabecalho}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $aba->getColumnDimension($letra)->setWidth(16);

            $mes = $lancados[$chave] ?? null;
            if ($mes === null) {
                $aba->setCellValue("{$letra}{$situacao}", (string) config('drm.exportacao.situacao.nao_lancado'));
                $aba->getStyle("{$letra}{$situacao}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                continue; // coluna em branco: ausência não é zero.
            }

            $versao = $mes['lancamento']->versao;
            $aba->setCellValue("{$letra}{$situacao}", $versao > 1
                ? strtr((string) config('drm.exportacao.situacao.retificado_curto'), [':versao' => (string) $versao])
                : (string) config('drm.exportacao.situacao.lancado'));
            $aba->getStyle("{$letra}{$situacao}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

            // Valores deste mês, na ordem da espinha (código ausente neste mês = em branco).
            $porCodigo = [];
            foreach ($mes['visao']->linhas as $l) {
                $porCodigo[$l->codigo] = $l;
            }
            $centavos = [];
            foreach ($espinha as $i => $e) {
                $centavos[$i] = ($porCodigo[$e->codigo] ?? null)?->centavos;
            }
            $ancora = $centavos[0] ?? null;

            foreach ($espinha as $i => $e) {
                $linha = $porCodigo[$e->codigo] ?? null;
                if ($linha === null || $linha->centavos === null) {
                    continue;
                }
                $rv = $inicioValores + $i;
                $aba->setCellValue("{$letra}{$rv}", isset($plano[$i]) && PlanoDeFormulas::confere($plano[$i], $i, $centavos)
                    ? self::formula($plano[$i], $letra, $inicioValores)
                    : $linha->centavos / 100);

                if ($linha->percentualBruto !== null && $ancora !== null && $ancora !== 0) {
                    $rp = $inicioPercentuais + $i;
                    $aba->setCellValue("{$letra}{$rp}", self::percentualConfere($linha->centavos, $ancora, $linha->percentualBruto)
                        ? "={$letra}{$rv}/{$letra}\${$inicioValores}"
                        : (float) $linha->percentualBruto / 100);
                }
            }
        }

        $ultimaColuna = Coordinate::stringFromColumnIndex(count($meses) + 1);
        $aba->getStyle("B{$inicioValores}:{$ultimaColuna}".($inicioValores + $totalLinhas - 1))->getNumberFormat()->setFormatCode(self::FORMATO_VALOR);
        $aba->getStyle("B{$inicioPercentuais}:{$ultimaColuna}".($inicioPercentuais + $totalLinhas - 1))->getNumberFormat()->setFormatCode(self::FORMATO_PERCENTUAL);
        $aba->getColumnDimension('A')->setWidth(48);
        $aba->freezePane('B'.($situacao + 1));
    }

    /**
     * As linhas de todos os meses numa lista só: a ordem do primeiro mês, e cada código que só aparece
     * em outro mês entra logo depois do que o antecede naquele mês.
     *
     * @param  list<DemonstracaoVisao>  $visoes
     * @return list<LinhaVisao>
     */
    private static function espinhaDeLinhas(array $visoes): array
    {
        /** @var list<LinhaVisao> $espinha */
        $espinha = [];
        foreach ($visoes as $visao) {
            $anterior = null;
            foreach ($visao->linhas as $l) {
                $pos = null;
                foreach ($espinha as $i => $e) {
                    if ($e->codigo === $l->codigo) {
                        $pos = $i;
                        break;
                    }
                }
                if ($pos === null) {
                    $ponto = $anterior === null ? 0 : $anterior + 1;
                    array_splice($espinha, $ponto, 0, [$l]);
                    $pos = $ponto;
                }
                $anterior = $pos;
            }
        }

        return $espinha;
    }

    // ------------------------------------------------------------------ utilidades

    /**
     * @param  list<array{0: int, 1: int}>  $termos
     */
    private static function formula(array $termos, string $coluna, int $primeiraLinha): string
    {
        $texto = '';
        foreach ($termos as $k => [$sinal, $posicao]) {
            $texto .= ($sinal < 0 ? '-' : ($k === 0 ? '' : '+')).$coluna.($primeiraLinha + $posicao);
        }

        return '='.$texto;
    }

    /** O percentual do snapshot (4 casas) e o da fórmula coincidem? Diferença de arredondamento não conta. */
    private static function percentualConfere(int $centavos, int $receita, string $percentualBruto): bool
    {
        return abs($centavos / $receita * 100 - (float) $percentualBruto) < 0.0002;
    }

    /** Texto é sempre texto: um rótulo "= Lucro Bruto" não pode virar fórmula. */
    private static function texto(Worksheet $aba, string $celula, string $valor): void
    {
        $aba->setCellValueExplicit($celula, $valor, DataType::TYPE_STRING);
    }

    private static function situacao(DrmLancamento $l): string
    {
        if (! $l->foiRetificado()) {
            return (string) config('drm.exportacao.situacao.original');
        }

        return strtr((string) config('drm.exportacao.situacao.retificado'), [
            ':versao' => (string) $l->versao,
            ':vezes' => (string) $l->vezesRetificado(),
            ':data' => DataExibicao::data($l->gerado_em),
        ]);
    }

    private static function rodape(DemonstracaoVisao $visao): string
    {
        return strtr((string) config('drm.exportacao.rodape'), [
            ':motor' => $visao->motorVersion ?? '—',
            ':data' => DataExibicao::dataHora($visao->geradoEm, '—'),
        ]);
    }

    private static function rotuloSetor(Setor $setor): string
    {
        return match ($setor) {
            Setor::Industria => 'Indústria',
            Setor::Comercio => 'Comércio',
            Setor::Servicos => 'Serviços',
        };
    }
}

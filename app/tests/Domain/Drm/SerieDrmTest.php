<?php

declare(strict_types=1);

use App\Domain\Drm\Competencia;
use App\Domain\Drm\Serie\Comparativo;
use App\Domain\Drm\Serie\ComparativosDaLinha;
use App\Domain\Drm\Serie\MesDaSerie;
use App\Domain\Drm\Serie\SerieDrm;

/*
|-------------------------------------------------------------------------
| STORY-051 CA-2, CA-3, CA-4, CA-7, CA-8, CA-9 — os quatro comparativos, um a um.
|
| A série é montada direto com valores em centavos, sem banco: o cálculo é puro. Cada teste fixa
| a conta à mão no comentário. Toda indisponibilidade é conferida pelo motivo, nunca por "null".
|-------------------------------------------------------------------------
*/

/**
 * Um mês lançado com a linha `L11` (resultado) e, opcionalmente, outras. `$percentual` é o de L11.
 *
 * @param  array<string, int|null>  $nominais
 * @param  array<string, string|null>  $percentuais
 */
function mesLancado(string $competencia, ?int $l11, ?string $pct = null, array $nominais = [], array $percentuais = [], array $campos = []): MesDaSerie
{
    return new MesDaSerie(
        Competencia::deTexto($competencia),
        1,
        ['L1' => 100_000, 'L11' => $l11, ...$nominais],
        ['L1' => '100.0000', 'L11' => $pct, ...$percentuais],
        $campos,
        ['L1', 'L11'],
    );
}

/** Série com um L11 por mês, mês a mês, a partir de `$inicio` (sem lacunas). */
function serieConsecutiva(string $inicio, array $valores): SerieDrm
{
    $meses = [];
    $c = Competencia::deTexto($inicio);
    foreach ($valores as $valor) {
        $meses[] = mesLancado($c->texto(), $valor, '5.0000');
        $c = Competencia::deData($c->data->addMonthNoOverflow());
    }

    return new SerieDrm($meses);
}

function comparativosDe(SerieDrm $serie, string $competencia, string $linha = 'L11'): ComparativosDaLinha
{
    return $serie->comparativos(Competencia::deTexto($competencia), $linha);
}

describe('variação vs. mês anterior', function () {
    it('calcula a variação sobre o mês imediatamente anterior', function () {
        // (4.240,00 − 3.770,00) / 3.770,00 = 12,4668...% → truncado em 4 casas
        $serie = serieConsecutiva('2026-07', [377_000, 424_000]);

        $c = comparativosDe($serie, '2026-08')->vsAnterior;

        expect($c->disponivel())->toBeTrue()
            ->and($c->percentual)->toBe('12.4668')
            ->and($c->meses)->toBe(1);
    });

    it('queda é negativa, e base negativa usa o módulo (de −100 para 50 é +150%)', function () {
        expect(comparativosDe(serieConsecutiva('2026-07', [100_000, 75_000]), '2026-08')->vsAnterior->percentual)->toBe('-25.0000')
            ->and(comparativosDe(serieConsecutiva('2026-07', [-10_000, 5_000]), '2026-08')->vsAnterior->percentual)->toBe('150.0000');
    });

    it('mesmo valor é 0%, disponível — não é indisponível', function () {
        $c = comparativosDe(serieConsecutiva('2026-07', [100, 100]), '2026-08')->vsAnterior;

        expect($c->disponivel())->toBeTrue()->and($c->percentual)->toBe('0.0000');
    });

    it('o primeiro mês da série não tem anterior', function () {
        expect(comparativosDe(serieConsecutiva('2026-07', [100]), '2026-07')->vsAnterior->motivo)->toBe(Comparativo::PRIMEIRO_LANCAMENTO);
    });

    it('mês anterior não lançado é sem_lancamento, não zero (a queda fantasma que o ADR-007 proíbe)', function () {
        // Maio e julho lançados; junho ausente. Julho NÃO é comparado com junho = 0.
        $serie = new SerieDrm([mesLancado('2026-05', 100_000, '5.0000'), mesLancado('2026-07', 90_000, '5.0000')]);

        $c = comparativosDe($serie, '2026-07')->vsAnterior;

        expect($c->disponivel())->toBeFalse()
            ->and($c->motivo)->toBe(Comparativo::SEM_LANCAMENTO)
            ->and($c->percentual)->toBeNull();
    });

    it('mês anterior lançado com resultado zero é base_zero (zero real ≠ ausência)', function () {
        $c = comparativosDe(serieConsecutiva('2026-07', [0, 500]), '2026-08')->vsAnterior;

        expect($c->motivo)->toBe(Comparativo::BASE_ZERO);
    });
});

describe('variação vs. média móvel de até 12 meses', function () {
    it('com menos de 3 competências anteriores, não existe', function () {
        $serie = serieConsecutiva('2026-01', [100, 100, 100]);

        expect(comparativosDe($serie, '2026-02')->vsMedia->motivo)->toBe(Comparativo::HISTORICO_INSUFICIENTE)
            ->and(comparativosDe($serie, '2026-03')->vsMedia->motivo)->toBe(Comparativo::HISTORICO_INSUFICIENTE);
    });

    it('a partir do 4º mês lançado, compara com a média dos anteriores', function () {
        // média de (100, 200, 300) = 200; (400 − 200) / 200 = +100%
        $c = comparativosDe(serieConsecutiva('2026-01', [100, 200, 300, 400]), '2026-04')->vsMedia;

        expect($c->percentual)->toBe('100.0000')->and($c->meses)->toBe(3);
    });

    it('a média é exata: a divisão não perde casa (10/3 não vira 3)', function () {
        // anteriores 1, 3, 6 → média 10/3 = 3,3333...; (4 − 10/3) / (10/3) = +20%
        $c = comparativosDe(serieConsecutiva('2026-01', [1, 3, 6, 4]), '2026-04')->vsMedia;

        expect($c->percentual)->toBe('20.0000');
    });

    it('usa só os meses lançados: lacuna não entra como zero nem derruba a média', function () {
        // Lançados: jan 300, fev 300, abr 300 (março AUSENTE), mai 300. Média dos anteriores a maio = 300 (n=3).
        // Se março virasse zero, a média seria 225 e a variação, +33%.
        $serie = new SerieDrm([
            mesLancado('2026-01', 300), mesLancado('2026-02', 300), mesLancado('2026-04', 300), mesLancado('2026-05', 300),
        ]);

        $c = comparativosDe($serie, '2026-05')->vsMedia;

        expect($c->percentual)->toBe('0.0000')->and($c->meses)->toBe(3);
    });

    it('a janela é de 12 meses de calendário: o 13º mês para trás fica de fora', function () {
        // Dez/2024 = 1.000.000 está a 13 meses de jan/2026: fora. Jan..dez/2025 = 100 cada (12 meses); jan/2026 = 100.
        $meses = [mesLancado('2024-12', 1_000_000)];
        for ($m = 1; $m <= 12; $m++) {
            $meses[] = mesLancado(sprintf('2025-%02d', $m), 100);
        }
        $meses[] = mesLancado('2026-01', 100);

        $c = comparativosDe(new SerieDrm($meses), '2026-01')->vsMedia;

        expect($c->percentual)->toBe('0.0000')->and($c->meses)->toBe(12);
    });

    it('janela cheia de 12 anteriores usa os 12', function () {
        $serie = serieConsecutiva('2026-01', array_merge(array_fill(0, 12, 100), [200]));

        $c = comparativosDe($serie, '2027-01')->vsMedia;

        expect($c->meses)->toBe(12)->and($c->percentual)->toBe('100.0000');
    });

    it('média zero não tem variação (base_zero)', function () {
        expect(comparativosDe(serieConsecutiva('2026-01', [0, 0, 0, 50]), '2026-04')->vsMedia->motivo)->toBe(Comparativo::BASE_ZERO);
    });

    it('mês atípico ENTRA na média (CA-7): omitir dado real seria pior que explicá-lo', function () {
        // dezembro atípico (13º): 1.000 entre meses de 100. A média inclui os 1.000.
        $serie = new SerieDrm([
            mesLancado('2025-10', 100, null, [], [], ['D21' => 0]),
            mesLancado('2025-11', 100, null, [], [], ['D21' => 0]),
            mesLancado('2025-12', 1000, null, [], [], ['D21' => 900_000]),
            mesLancado('2026-01', 100),
        ]);

        // média (100, 100, 1000) = 400; (100 − 400) / 400 = −75%
        expect(comparativosDe($serie, '2026-01')->vsMedia->percentual)->toBe('-75.0000')
            ->and($serie->atipicos(Competencia::deTexto('2025-12')))->toBe([]);   // só 2 anteriores: A4 ainda não vale
    });
});

describe('acumulado no ano', function () {
    it('soma as competências do mesmo ano-calendário até o mês, e diz quantas entraram', function () {
        $serie = serieConsecutiva('2026-01', [100, 200, 300]);

        $c = comparativosDe($serie, '2026-03')->acumuladoAno;

        expect($c->centavos)->toBe(600)->and($c->meses)->toBe(3);
    });

    it('está disponível desde o primeiro lançamento', function () {
        $c = comparativosDe(serieConsecutiva('2026-01', [150]), '2026-01')->acumuladoAno;

        expect($c->disponivel())->toBeTrue()->and($c->centavos)->toBe(150)->and($c->meses)->toBe(1);
    });

    it('não atravessa o ano nem olha para o futuro', function () {
        $serie = serieConsecutiva('2025-11', [1000, 1000, 5, 7, 9]); // nov, dez de 2025; jan, fev, mar de 2026

        expect(comparativosDe($serie, '2026-02')->acumuladoAno->centavos)->toBe(12)         // 5 + 7: sem 2025, sem março
            ->and(comparativosDe($serie, '2026-02')->acumuladoAno->meses)->toBe(2);
    });

    it('com lacuna soma só o lançado e informa a contagem (a tela pode dizer "2 meses lançados")', function () {
        $serie = new SerieDrm([mesLancado('2026-01', 100), mesLancado('2026-04', 300)]);

        $c = comparativosDe($serie, '2026-04')->acumuladoAno;

        expect($c->centavos)->toBe(400)->and($c->meses)->toBe(2);
    });

    it('prejuízo acumulado é negativo, não zero', function () {
        expect(comparativosDe(serieConsecutiva('2026-01', [-500, 200]), '2026-02')->acumuladoAno->centavos)->toBe(-300);
    });
});

describe('participação média sobre a receita', function () {
    it('é a média dos percentuais dos meses da janela, o mês incluído', function () {
        $serie = new SerieDrm([
            mesLancado('2026-01', 1, '10.0000'), mesLancado('2026-02', 1, '20.0000'), mesLancado('2026-03', 1, '33.3333'),
        ]);

        $c = comparativosDe($serie, '2026-03')->participacaoMedia;

        // (10 + 20 + 33,3333) / 3 = 21,1111 (truncado)
        expect($c->percentual)->toBe('21.1111')->and($c->meses)->toBe(3);
    });

    it('mês sem faturamento sai da média, não entra como 0%', function () {
        $serie = new SerieDrm([
            mesLancado('2026-01', 1, '10.0000'), mesLancado('2026-02', 1, null), mesLancado('2026-03', 1, '20.0000'),
        ]);

        $c = comparativosDe($serie, '2026-03')->participacaoMedia;

        expect($c->percentual)->toBe('15.0000')->and($c->meses)->toBe(2);
    });

    it('percentual negativo (prejuízo) entra com o sinal', function () {
        $serie = new SerieDrm([mesLancado('2026-01', 1, '-4.0000'), mesLancado('2026-02', 1, '2.0000')]);

        expect(comparativosDe($serie, '2026-02')->participacaoMedia->percentual)->toBe('-1.0000');
    });

    it('janela inteira sem faturamento é sem_faturamento', function () {
        $serie = new SerieDrm([mesLancado('2026-01', -5, null), mesLancado('2026-02', -5, null)]);

        expect(comparativosDe($serie, '2026-02')->participacaoMedia->motivo)->toBe(Comparativo::SEM_FATURAMENTO);
    });

    it('só olha 12 meses: o 13º para trás não entra', function () {
        // A janela é o mês + os 11 anteriores: fev/2025..jan/2026. Jan/2025 (90%) fica de fora.
        $meses = [mesLancado('2025-01', 1, '90.0000')];
        for ($m = 2; $m <= 12; $m++) {
            $meses[] = mesLancado(sprintf('2025-%02d', $m), 1, '10.0000');
        }
        $meses[] = mesLancado('2026-01', 1, '10.0000');

        $c = comparativosDe(new SerieDrm($meses), '2026-01')->participacaoMedia;

        expect($c->percentual)->toBe('10.0000')->and($c->meses)->toBe(12);
    });
});

describe('granularidades mistas (CA-8)', function () {
    /** Item detalhado (L8a) só existe nos meses detalhados; no agregado vem sem valor. */
    function mesComItem(string $competencia, ?int $item, ?string $pct): MesDaSerie
    {
        return mesLancado($competencia, 1_000, '5.0000', ['L8a' => $item], ['L8a' => $pct]);
    }

    it('item detalhado num mês agregado: os quatro comparativos são grupo_agregado', function () {
        $serie = new SerieDrm([mesComItem('2026-01', 100, '1.0000'), mesComItem('2026-02', null, null)]);

        $c = comparativosDe($serie, '2026-02', 'L8a');

        expect($c->vsAnterior->motivo)->toBe(Comparativo::GRUPO_AGREGADO)
            ->and($c->vsMedia->motivo)->toBe(Comparativo::GRUPO_AGREGADO)
            ->and($c->acumuladoAno->motivo)->toBe(Comparativo::GRUPO_AGREGADO)
            ->and($c->participacaoMedia->motivo)->toBe(Comparativo::GRUPO_AGREGADO);
    });

    it('mês detalhado cuja base é agregada: o item nunca é inferido do total', function () {
        $serie = new SerieDrm([mesComItem('2026-01', null, null), mesComItem('2026-02', 100, '1.0000')]);

        $c = comparativosDe($serie, '2026-02', 'L8a');

        expect($c->vsAnterior->motivo)->toBe(Comparativo::GRUPO_AGREGADO)
            ->and($c->acumuladoAno->motivo)->toBe(Comparativo::GRUPO_AGREGADO)   // jan não tem o item: o acumulado seria parcial
            ->and($c->participacaoMedia->meses)->toBe(1);                         // só fev tem percentual do item
    });

    it('média do item com menos de 3 meses detalhados na janela é grupo_agregado', function () {
        $serie = new SerieDrm([
            mesComItem('2026-01', null, null), mesComItem('2026-02', null, null), mesComItem('2026-03', 100, '1.0000'), mesComItem('2026-04', 100, '1.0000'),
        ]);

        expect(comparativosDe($serie, '2026-04', 'L8a')->vsMedia->motivo)->toBe(Comparativo::GRUPO_AGREGADO);
    });

    it('item com 3 meses detalhados na janela tem média, mesmo com agregados no meio', function () {
        $serie = new SerieDrm([
            mesComItem('2026-01', 100, '1.0000'), mesComItem('2026-02', null, null), mesComItem('2026-03', 100, '1.0000'),
            mesComItem('2026-04', 100, '1.0000'), mesComItem('2026-05', 200, '2.0000'),
        ]);

        expect(comparativosDe($serie, '2026-05', 'L8a')->vsMedia->percentual)->toBe('100.0000');
    });

    it('linha de nível 1 está sempre disponível nos dois modos (mesma estrutura)', function () {
        $serie = new SerieDrm([mesComItem('2026-01', null, null), mesComItem('2026-02', null, null)]);

        expect(comparativosDe($serie, '2026-02', 'L11')->vsAnterior->disponivel())->toBeTrue();
    });

    it('item sem percentual por falta de faturamento (nominal existe) é sem_faturamento, não agregado', function () {
        $serie = new SerieDrm([mesLancado('2026-01', 1, null, ['L8a' => 100], ['L8a' => null])]);

        expect(comparativosDe($serie, '2026-01', 'L8a')->participacaoMedia->motivo)->toBe(Comparativo::SEM_FATURAMENTO);
    });
});

describe('linha omitida por estar zerada x item agregado (STORY-095, D-2)', function () {
    /** Mês em que a L7b foi omitida do snapshot porque D41 = D42 = 0. */
    function mesSemL7b(string $competencia): MesDaSerie
    {
        return mesLancado($competencia, 1_000, '5.0000', campos: ['D41' => 0, 'D42' => 0]);
    }

    /** Mês com L7b exibida (D41 = 500,00). */
    function mesComL7b(string $competencia, int $valor = 50_000): MesDaSerie
    {
        return mesLancado($competencia, 1_000, '5.0000', ['L7b' => $valor], ['L7b' => '50.0000'], ['D41' => $valor, 'D42' => 0]);
    }

    it('L7b omitida vale zero: comparativos corretos no mês em que ela volta, sem "grupo agregado"', function () {
        $serie = new SerieDrm([mesSemL7b('2026-01'), mesSemL7b('2026-02'), mesSemL7b('2026-03'), mesComL7b('2026-04')]);

        $c = comparativosDe($serie, '2026-04', 'L7b');

        // Base zero (mês anterior sem a linha): indisponível pelo motivo certo, não por agregação.
        expect($c->vsAnterior->motivo)->toBe(Comparativo::BASE_ZERO)
            ->and($c->vsMedia->motivo)->toBe(Comparativo::BASE_ZERO)             // média dos 3 anteriores = 0
            ->and($c->acumuladoAno->disponivel())->toBeTrue()
            ->and($c->acumuladoAno->centavos)->toBe(50_000)                      // 0 + 0 + 0 + 500,00
            ->and($c->acumuladoAno->meses)->toBe(4)
            ->and($c->participacaoMedia->disponivel())->toBeTrue()
            ->and($c->participacaoMedia->percentual)->toBe('12.5000');           // (0 + 0 + 0 + 50) / 4
    });

    it('com a linha presente nos meses anteriores, a variação e a média saem da conta e a omissão conta como zero', function () {
        $serie = new SerieDrm([mesComL7b('2026-01', 10_000), mesComL7b('2026-02', 10_000), mesComL7b('2026-03', 10_000), mesSemL7b('2026-04')]);

        $c = comparativosDe($serie, '2026-04', 'L7b');

        expect($c->vsAnterior->percentual)->toBe('-100.0000')                    // (0 − 100) / 100
            ->and($c->vsMedia->percentual)->toBe('-100.0000')
            ->and($c->acumuladoAno->centavos)->toBe(30_000);
    });

    it('o acumulado segue disponível a partir do 1º lançamento', function () {
        $c = comparativosDe(new SerieDrm([mesSemL7b('2026-01')]), '2026-01', 'L7b');

        expect($c->acumuladoAno->disponivel())->toBeTrue()->and($c->acumuladoAno->centavos)->toBe(0)
            ->and($c->vsAnterior->motivo)->toBe(Comparativo::PRIMEIRO_LANCAMENTO);
    });

    it('sem faturamento a participação da linha omitida segue indisponível, e item agregado de outra linha continua grupo_agregado', function () {
        $semReceita = mesLancado('2026-01', 1_000, null, percentuais: ['L1' => null], campos: ['D41' => 0, 'D42' => 0]);
        $serie = new SerieDrm([$semReceita, mesComItem('2026-02', null, null)]);

        expect(comparativosDe($serie, '2026-01', 'L7b')->participacaoMedia->motivo)->toBe(Comparativo::SEM_FATURAMENTO)
            ->and(comparativosDe($serie, '2026-02', 'L8a')->acumuladoAno->motivo)->toBe(Comparativo::GRUPO_AGREGADO);
    });

    it('só a L7b é omitida por zero: outra linha ausente, ou D41/D42 diferentes de zero, segue sem valor', function () {
        $mes = mesLancado('2026-01', 1, campos: ['D41' => 0, 'D42' => 0]);
        $comD41 = mesLancado('2026-01', 1, campos: ['D41' => 0, 'D42' => 100]);

        expect($mes->nominal('L7b'))->toBe(0)->and($mes->percentual('L7b'))->toBe('0.0000')
            ->and($mes->nominal('L8a'))->toBeNull()->and($mes->percentual('L8a'))->toBeNull()
            ->and($comD41->nominal('L7b'))->toBeNull()->and($comD41->percentual('L7b'))->toBeNull();
    });
});

describe('o mês fora da série e a retificação (CA-2, CA-9)', function () {
    it('competência sem lançamento: tudo indisponível por sem_lancamento', function () {
        $c = comparativosDe(serieConsecutiva('2026-01', [100]), '2026-03');

        expect($c->vsAnterior->motivo)->toBe(Comparativo::SEM_LANCAMENTO)
            ->and($c->participacaoMedia->motivo)->toBe(Comparativo::SEM_LANCAMENTO);
    });

    it('linha que o snapshot não tem é grupo_agregado — nunca zero', function () {
        expect(comparativosDe(serieConsecutiva('2026-01', [100, 200]), '2026-02', 'L99')->vsAnterior->motivo)->toBe(Comparativo::GRUPO_AGREGADO);
    });

    it('trocar o mês-base pelo valor retificado muda o comparativo do mês seguinte', function () {
        $antes = serieConsecutiva('2026-07', [100_000, 110_000]);
        $depois = serieConsecutiva('2026-07', [80_000, 110_000]);

        expect(comparativosDe($antes, '2026-08')->vsAnterior->percentual)->toBe('10.0000')
            ->and(comparativosDe($depois, '2026-08')->vsAnterior->percentual)->toBe('37.5000')
            ->and(comparativosDe($antes, '2026-08')->acumuladoAno->centavos)->toBe(210_000)
            ->and(comparativosDe($depois, '2026-08')->acumuladoAno->centavos)->toBe(190_000);
    });
});

describe('espinha de meses', function () {
    it('sem lançamento nem rascunho, é vazia', function () {
        expect((new SerieDrm([]))->espinha(Competencia::deTexto('2026-10')))->toBe([]);
    });

    it('vai do mais recente ao mais antigo, com os meses sem lançamento no meio', function () {
        $serie = new SerieDrm([mesLancado('2026-06', 1), mesLancado('2026-09', 1)]);

        $espinha = array_map(fn (Competencia $c) => $c->texto(), $serie->espinha(Competencia::deTexto('2026-10')));

        expect($espinha)->toBe(['2026-10', '2026-09', '2026-08', '2026-07', '2026-06']);
    });

    it('um rascunho antes do primeiro lançamento estende a espinha', function () {
        $serie = new SerieDrm([mesLancado('2026-06', 1)]);

        $espinha = array_map(fn (Competencia $c) => $c->texto(), $serie->espinha(Competencia::deTexto('2026-06'), [Competencia::deTexto('2026-04')]));

        expect($espinha)->toBe(['2026-06', '2026-05', '2026-04']);
    });

    it('só rascunho já é uma espinha de um mês', function () {
        $espinha = (new SerieDrm([]))->espinha(Competencia::deTexto('2026-10'), [Competencia::deTexto('2026-10')]);

        expect(array_map(fn (Competencia $c) => $c->texto(), $espinha))->toBe(['2026-10']);
    });

    it('lançamento depois de `ate` também entra (a série nunca esconde dado)', function () {
        $serie = new SerieDrm([mesLancado('2026-12', 1)]);

        $espinha = array_map(fn (Competencia $c) => $c->texto(), $serie->espinha(Competencia::deTexto('2026-11')));

        expect($espinha)->toBe(['2026-12']);
    });

    it('atravessa a virada de ano', function () {
        $serie = new SerieDrm([mesLancado('2025-11', 1), mesLancado('2026-02', 1)]);

        expect(array_map(fn (Competencia $c) => $c->texto(), $serie->espinha(Competencia::deTexto('2026-02'))))
            ->toBe(['2026-02', '2026-01', '2025-12', '2025-11']);
    });

    it('expõe os meses lançados em ordem cronológica, qualquer que seja a ordem de entrada', function () {
        $serie = new SerieDrm([mesLancado('2026-03', 1), mesLancado('2026-01', 1)]);

        expect(array_map(fn (MesDaSerie $m) => $m->competencia->texto(), $serie->meses()))->toBe(['2026-01', '2026-03']);
    });
});

describe('MesDaSerie a partir do snapshot', function () {
    function snapshotDeSerie(): array
    {
        return ['linhas' => [
            'fora' => [
                ['codigo' => 'L1', 'nivel' => 1, 'nominal' => '1000.50', 'percentual' => '100.0000'],
                ['codigo' => 'L8a', 'nivel' => 2, 'nominal' => null, 'percentual' => null],
                ['codigo' => 'L11', 'nivel' => 1, 'nominal' => '-25.50', 'percentual' => '-2.5000'],
                'lixo',
                ['nivel' => 1],
            ],
            'dentro' => [
                ['codigo' => 'L1', 'nivel' => 1, 'nominal' => '1000.50', 'percentual' => 'abc'],
            ],
        ]];
    }

    it('lê nominal em centavos (inclusive negativo), percentual e a lista de linhas de nível 1', function () {
        $mes = MesDaSerie::doSnapshot(Competencia::deTexto('2026-09'), 2, snapshotDeSerie(), ['D01' => '1000.50', 'D02' => null, 'D03' => 'x'], 'fora');

        expect($mes->nominais)->toBe(['L1' => 100_050, 'L8a' => null, 'L11' => -2_550])
            ->and($mes->percentuais)->toBe(['L1' => '100.0000', 'L8a' => null, 'L11' => '-2.5000'])
            ->and($mes->nivel1)->toBe(['L1', 'L11'])
            ->and($mes->codigoFinal())->toBe('L11')
            ->and($mes->codigoAncora())->toBe('L1')
            ->and($mes->versao)->toBe(2)
            ->and($mes->campos['D01'])->toBe(100_050)
            ->and($mes->campos['D02'])->toBeNull()
            ->and($mes->campos['D03'])->toBeNull()
            ->and(array_keys($mes->campos))->toHaveCount(42);
    });

    it('percentual fora do formato vira ausente, nunca texto adivinhado', function () {
        $mes = MesDaSerie::doSnapshot(Competencia::deTexto('2026-09'), 1, snapshotDeSerie(), [], 'dentro');

        expect($mes->percentuais['L1'])->toBeNull();
    });

    it('posição inexistente cai na primeira que o snapshot tiver', function () {
        $mes = MesDaSerie::doSnapshot(Competencia::deTexto('2026-09'), 1, snapshotDeSerie(), [], 'outra');

        expect($mes->codigoFinal())->toBe('L11');
    });

    it('snapshot sem linhas gera mês sem linha de nível 1 (não quebra)', function () {
        $mes = MesDaSerie::doSnapshot(Competencia::deTexto('2026-09'), 1, [], [], 'fora');

        expect($mes->nominais)->toBe([])->and($mes->codigoFinal())->toBeNull()->and($mes->codigoAncora())->toBeNull();
    });

    it('linhas fora do formato de lista são ignoradas', function () {
        $mes = MesDaSerie::doSnapshot(Competencia::deTexto('2026-09'), 1, ['linhas' => ['fora' => 'x']], [], 'fora');

        expect($mes->nominais)->toBe([]);
    });
});

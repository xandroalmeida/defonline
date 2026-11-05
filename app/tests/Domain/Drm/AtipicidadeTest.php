<?php

declare(strict_types=1);

use App\Domain\Drm\Alertas\AvaliadorDeAlertas;
use App\Domain\Drm\Alertas\RubricaAtipica;
use App\Domain\Drm\Alertas\SaltoDeCompetencia;
use App\Domain\Drm\Competencia;
use App\Domain\Drm\Grupo;
use App\Domain\Drm\Lancamento;
use App\Domain\Drm\Modo;
use App\Domain\Drm\Serie\Atipicidade;
use App\Domain\Drm\Setor;

/*
|-------------------------------------------------------------------------
| STORY-051 CA-5 e CA-6 — alerta A4 (rubrica atípica) e A6 (salto de competência).
|-------------------------------------------------------------------------
*/

/** @return array<string, array<string, int|null>> mesmos centavos de D21 em cada mês dado */
function historicoDe(string $campo, array $porMes): array
{
    $h = [];
    foreach ($porMes as $mes => $valor) {
        $h[$mes] = [$campo => $valor];
    }

    return $h;
}

describe('A4 — Atipicidade::detectar', function () {
    it('não vale antes do 4º lançamento (menos de 3 meses anteriores)', function () {
        $h = historicoDe('D21', ['2026-01' => 100, '2026-02' => 100]);

        expect(Atipicidade::detectar(Competencia::deTexto('2026-03'), ['D21' => 100_000], $h))->toBe([]);
    });

    it('a partir do 4º lançamento, passa de 3× a média: atípico, com a média informada', function () {
        $h = historicoDe('D21', ['2026-01' => 100, '2026-02' => 100, '2026-03' => 100]);

        expect(Atipicidade::detectar(Competencia::deTexto('2026-04'), ['D21' => 301], $h))
            ->toBe([['campo' => 'D21', 'valor' => 301, 'media' => 100]]);
    });

    it('exatamente 3× a média NÃO é atípico (precisa exceder)', function () {
        $h = historicoDe('D21', ['2026-01' => 100, '2026-02' => 100, '2026-03' => 100]);

        expect(Atipicidade::detectar(Competencia::deTexto('2026-04'), ['D21' => 300], $h))->toBe([]);
    });

    it('a comparação é exata, sem arredondar a média (1+1+2 = 4/3; 3× = 4)', function () {
        $h = historicoDe('D21', ['2026-01' => 1, '2026-02' => 1, '2026-03' => 2]);

        expect(Atipicidade::detectar(Competencia::deTexto('2026-04'), ['D21' => 4], $h))->toBe([])
            ->and(Atipicidade::detectar(Competencia::deTexto('2026-04'), ['D21' => 5], $h))
            ->toBe([['campo' => 'D21', 'valor' => 5, 'media' => 1]]);
    });

    it('média zero e valor positivo é atípico: a assinatura do 13º (zero nos outros onze meses)', function () {
        $h = historicoDe('D21', ['2026-01' => 0, '2026-02' => 0, '2026-03' => 0]);

        expect(Atipicidade::detectar(Competencia::deTexto('2026-04'), ['D21' => 1], $h))
            ->toBe([['campo' => 'D21', 'valor' => 1, 'media' => 0]]);
    });

    it('valor zero, campo não coletado e campo ausente nunca disparam', function () {
        $h = historicoDe('D21', ['2026-01' => 0, '2026-02' => 0, '2026-03' => 0]);

        expect(Atipicidade::detectar(Competencia::deTexto('2026-04'), ['D21' => 0, 'D20' => null], $h))->toBe([]);
    });

    it('mês sem lançamento fica de fora da média (não conta como zero)', function () {
        // jan, fev, mar lançados = 100; abril ausente; maio = 301 → média dos 3 lançados é 100 (com abril = 0 seria 75 e 301 também passaria;
        // o que importa: com só 2 lançados na janela, a regra não vale)
        $h = historicoDe('D21', ['2026-02' => 100, '2026-03' => 100]);

        expect(Atipicidade::detectar(Competencia::deTexto('2026-05'), ['D21' => 100_000], $h))->toBe([]);
    });

    it('mês com o campo suprimido (grupo agregado) não conta: precisa de 3 meses com o campo', function () {
        $h = ['2026-01' => ['D21' => 100], '2026-02' => ['D21' => null], '2026-03' => ['D21' => 100], '2026-04' => ['D21' => 100]];

        expect(Atipicidade::detectar(Competencia::deTexto('2026-05'), ['D21' => 1_000], $h))
            ->toBe([['campo' => 'D21', 'valor' => 1_000, 'media' => 100]]);

        $h['2026-04']['D21'] = null;
        expect(Atipicidade::detectar(Competencia::deTexto('2026-05'), ['D21' => 1_000], $h))->toBe([]);
    });

    it('só olha os 12 meses anteriores e ignora meses posteriores', function () {
        $h = historicoDe('D21', ['2025-01' => 1_000_000, '2026-02' => 100, '2026-03' => 100, '2026-04' => 100, '2026-06' => 999_999]);

        // jan/2025 está a 13+ meses de fev/2027? Aqui: competência 2026-05 → janela 2025-05..2026-04: jan/2025 fica fora.
        expect(Atipicidade::detectar(Competencia::deTexto('2026-05'), ['D21' => 301], $h))
            ->toBe([['campo' => 'D21', 'valor' => 301, 'media' => 100]]);
    });

    it('devolve mais de um campo, na ordem do mapa', function () {
        $h = ['2026-01' => ['D20' => 10, 'D21' => 10], '2026-02' => ['D20' => 10, 'D21' => 10], '2026-03' => ['D20' => 10, 'D21' => 10]];

        $r = Atipicidade::detectar(Competencia::deTexto('2026-04'), ['D20' => 500, 'D21' => 500, 'D22' => 9], $h);

        expect(array_column($r, 'campo'))->toBe(['D20', 'D21']);
    });
});

describe('A4 — regra no lançamento', function () {
    function lancamentoComD(array $texto): Lancamento
    {
        $modos = array_fill_keys(array_map(fn (Grupo $g) => $g->value, Grupo::cases()), Modo::Detalhado);

        return Lancamento::deTexto(Setor::Comercio, $modos, ['D01' => '1000', 'D02' => '0', 'D40' => '0', ...$texto]);
    }

    it('gera uma ocorrência por campo, com valor e média formatados e o valor envolvido', function () {
        $h = historicoDe('D21', ['2026-01' => 100, '2026-02' => 100, '2026-03' => 100]);

        $o = (new RubricaAtipica($h))->ocorrencias(lancamentoComD(['D21' => '5,00']), Competencia::deTexto('2026-04'));

        expect($o)->toHaveCount(1)
            ->and($o[0]->codigo)->toBe('A4')
            ->and($o[0]->campo)->toBe('D21')
            ->and($o[0]->contexto)->toBe(['valor' => 'R$ 5,00', 'media' => 'R$ 1,00'])
            ->and($o[0]->valorEnvolvido)->toBe(500);
    });

    it('sem histórico suficiente, nada', function () {
        expect((new RubricaAtipica([]))->ocorrencias(lancamentoComD(['D21' => '5,00']), Competencia::deTexto('2026-04')))->toBe([])
            ->and((new RubricaAtipica([]))->codigo())->toBe('A4');
    });

    it('o avaliador junta as ocorrências da regra múltipla com as demais, em ordem de código', function () {
        $h = historicoDe('D21', ['2026-01' => 100, '2026-02' => 100, '2026-03' => 100]);

        $codigos = array_map(
            fn ($o) => $o->codigo,
            AvaliadorDeAlertas::comHistorico($h, ['2026-01'])->avaliar(lancamentoComD(['D21' => '5,00']), Competencia::deTexto('2026-04')),
        );

        // A5 (pró-labore zerado) e A6 (faltam fev e mar) também disparam; A4 vem entre A3 e A5.
        expect($codigos)->toBe(['A4', 'A5', 'A6']);
    });
});

describe('A6 — SaltoDeCompetencia', function () {
    function saltoA6(array $lancadas, string $competencia)
    {
        return (new SaltoDeCompetencia($lancadas))->avaliar(lancamentoComD([]), Competencia::deTexto($competencia));
    }

    it('mês seguinte ao último lançado: sem salto', function () {
        expect(saltoA6(['2026-05'], '2026-06'))->toBeNull()->and((new SaltoDeCompetencia([]))->codigo())->toBe('A6');
    });

    it('primeiro lançamento: sem salto', function () {
        expect(saltoA6([], '2026-06'))->toBeNull();
    });

    it('lançar um mês ANTES do último lançado não é salto', function () {
        expect(saltoA6(['2026-08'], '2026-06'))->toBeNull()->and(saltoA6(['2026-06'], '2026-06'))->toBeNull();
    });

    it('um mês faltando', function () {
        $o = saltoA6(['2026-05'], '2026-07');

        expect($o->codigo)->toBe('A6')
            ->and($o->contexto)->toBe([
                'ultima_competencia' => 'maio/2026',
                'meses_faltantes' => '1 mês (junho/2026)',
                'primeira_faltante' => '2026-06',
            ]);
    });

    it('dois e três meses faltando listam os meses; o "último lançamento" é o mais recente ANTES do atual', function () {
        expect(saltoA6(['2026-03', '2026-05'], '2026-08')->contexto['meses_faltantes'])->toBe('2 meses (junho/2026 e julho/2026)')
            ->and(saltoA6(['2026-05'], '2026-09')->contexto['meses_faltantes'])->toBe('3 meses (junho/2026, julho/2026 e agosto/2026)')
            ->and(saltoA6(['2026-05', '2026-12'], '2026-08')->contexto['ultima_competencia'])->toBe('maio/2026');
    });

    it('mais de três meses viram um intervalo, atravessando o ano', function () {
        $o = saltoA6(['2025-11'], '2026-04');

        expect($o->contexto['meses_faltantes'])->toBe('4 meses (de dezembro/2025 a março/2026)')
            ->and($o->contexto['primeira_faltante'])->toBe('2025-12');
    });
});

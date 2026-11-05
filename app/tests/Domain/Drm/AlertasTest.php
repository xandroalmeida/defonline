<?php

declare(strict_types=1);

use App\Domain\Drm\Alertas\AvaliadorDeAlertas;
use App\Domain\Drm\Alertas\CustoMaiorQueReceita;
use App\Domain\Drm\Alertas\Prejuizo;
use App\Domain\Drm\Alertas\ProLaboreZerado;
use App\Domain\Drm\Alertas\RegraDeAlerta;
use App\Domain\Drm\Alertas\SemFaturamento;
use App\Domain\Drm\Competencia;
use App\Domain\Drm\Grupo;
use App\Domain\Drm\Lancamento;
use App\Domain\Drm\Modo;
use App\Domain\Drm\Ocorrencia;
use App\Domain\Drm\Setor;

/*
|-------------------------------------------------------------------------
| STORY-047 CA-9 — alertas A1, A2, A3 e A5 (A4 e A6 são da STORY-051).
|-------------------------------------------------------------------------
*/

function lancamentoAgregado(array $texto): Lancamento
{
    $base = ['D01' => '1000', 'D02' => '0', 'D03' => '0', 'D08' => '0', 'D15' => '0', 'D37' => '0', 'D40' => '100'];

    return Lancamento::deTexto(
        Setor::Comercio,
        array_fill_keys(array_map(fn (Grupo $g) => $g->value, Grupo::cases()), Modo::Agregado),
        [...$base, ...$texto],
    );
}

function codigosDosAlertas(Lancamento $l): array
{
    return array_map(
        fn (Ocorrencia $o) => $o->codigo,
        AvaliadorDeAlertas::padrao()->avaliar($l, Competencia::deTexto('2026-10')),
    );
}

it('não alerta num mês saudável', function () {
    expect(codigosDosAlertas(lancamentoAgregado([])))->toBe([]);
});

it('A1 — sem faturamento com despesa lançada', function () {
    $l = lancamentoAgregado(['D01' => '0', 'D15' => '300']);
    $a = AvaliadorDeAlertas::padrao()->avaliar($l, Competencia::deTexto('2026-10'));

    expect(array_map(fn ($o) => $o->codigo, $a))->toBe(['A1', 'A3'])
        ->and($a[0]->contexto)->toBe(['competencia' => 'outubro/2026'])
        ->and($a[0]->valorEnvolvido)->toBe(40000);
});

it('A1 — mês totalmente zerado não alerta (nada foi lançado)', function () {
    expect(codigosDosAlertas(lancamentoAgregado(['D01' => '0', 'D40' => '0'])))->toBe(['A5']);
});

it('A2 — custo maior que a receita, e igual não alerta', function () {
    $maior = lancamentoAgregado(['D03' => '1000,01']);
    $a = AvaliadorDeAlertas::padrao()->avaliar($maior, Competencia::deTexto('2026-10'));

    expect(array_map(fn ($o) => $o->codigo, $a))->toContain('A2')
        ->and($a[0]->contexto)->toBe(['valor_custo' => 'R$ 1.000,01', 'valor_receita' => 'R$ 1.000,00'])
        ->and(codigosDosAlertas(lancamentoAgregado(['D03' => '1000'])))->not->toContain('A2');
});

it('A3 — prejuízo traz o valor positivo; lucro zero não alerta', function () {
    $a = AvaliadorDeAlertas::padrao()->avaliar(lancamentoAgregado(['D15' => '1000']), Competencia::deTexto('2026-10'));

    expect($a)->toHaveCount(1)
        ->and($a[0]->codigo)->toBe('A3')
        ->and($a[0]->contexto)->toBe(['valor_prejuizo' => 'R$ 100,00', 'competencia' => 'outubro/2026'])
        ->and($a[0]->valorEnvolvido)->toBe(10000)
        ->and(codigosDosAlertas(lancamentoAgregado(['D15' => '900'])))->toBe([]);
});

it('A5 — pró-labore zerado', function () {
    expect(codigosDosAlertas(lancamentoAgregado(['D40' => '0'])))->toBe(['A5'])
        ->and(codigosDosAlertas(lancamentoAgregado(['D40' => '0,01'])))->toBe([]);
});

it('aceita uma regra nova sem mudar o avaliador (ponto de extensão para A4 e A6)', function () {
    $a4 = new class implements RegraDeAlerta
    {
        public function codigo(): string
        {
            return 'A4';
        }

        public function avaliar(Lancamento $lancamento, Competencia $competencia): ?Ocorrencia
        {
            return new Ocorrencia('A4');
        }
    };

    $avaliador = new AvaliadorDeAlertas([$a4]);

    expect(array_map(fn ($o) => $o->codigo, $avaliador->avaliar(lancamentoAgregado([]), Competencia::deTexto('2026-10'))))->toBe(['A4']);
});

it('cada regra informa o próprio código', function () {
    $codigos = array_map(
        fn (RegraDeAlerta $r) => $r->codigo(),
        [new SemFaturamento, new CustoMaiorQueReceita, new Prejuizo, new ProLaboreZerado],
    );

    expect($codigos)->toBe(['A1', 'A2', 'A3', 'A5']);
});

<?php

declare(strict_types=1);

use App\Domain\Drm\Competencia;
use App\Domain\Drm\Grupo;
use App\Domain\Drm\Lancamento;
use App\Domain\Drm\Modo;
use App\Domain\Drm\Setor;
use App\Domain\Drm\ValidaLancamento;
use Carbon\CarbonImmutable;

/*
|-------------------------------------------------------------------------
| STORY-047 CA-3, CA-4, CA-7, CA-8 — o que o lançamento coleta e as regras B2–B4.
|-------------------------------------------------------------------------
*/

/** @return array<string, Modo> */
function modos(Modo $todos): array
{
    return array_fill_keys(array_map(fn (Grupo $g) => $g->value, Grupo::cases()), $todos);
}

it('coleta 9 campos com os quatro grupos agregados (Anexo K §K.3)', function () {
    $campos = Lancamento::camposColetados(Setor::Comercio, modos(Modo::Agregado));

    expect($campos)->toBe(['D01', 'D02', 'D03', 'D08', 'D15', 'D40', 'D37', 'D41', 'D42']);
});

it('coleta 38/37/36 campos com tudo detalhado, conforme o setor', function (Setor $setor, int $total, array $ausentes) {
    $campos = Lancamento::camposColetados($setor, modos(Modo::Detalhado));

    expect($campos)->toHaveCount($total)
        ->and(array_intersect($campos, $ausentes))->toBe([])
        ->and(array_intersect($campos, ['D03', 'D08', 'D15', 'D37']))->toBe([]);
})->with([
    'indústria' => [Setor::Industria, 38, []],
    'comércio' => [Setor::Comercio, 37, ['D05']],
    'serviços' => [Setor::Servicos, 36, ['D04', 'D05']],
]);

it('grava nulo no que não foi coletado e zero no opcional em branco', function () {
    $modos = [...modos(Modo::Agregado), 'custo' => Modo::Detalhado];
    $l = Lancamento::deTexto(Setor::Servicos, $modos, [
        'D01' => '1.000,00', 'D02' => '100,00', 'D06' => '50,00', 'D08' => '0', 'D15' => '10,00', 'D37' => '0', 'D40' => '0,00',
    ]);
    $p = $l->payload();

    expect($l->temErros())->toBeFalse()
        ->and($p['D04'])->toBeNull()          // suprimido pelo setor
        ->and($p['D05'])->toBeNull()          // suprimido pelo setor
        ->and($p['D03'])->toBeNull()          // grupo detalhado: totalizador oculto
        ->and($p['D06'])->toBe('50.00')
        ->and($p['D07'])->toBe('0.00')        // coletado, em branco = zero
        ->and($p['D16'])->toBeNull()          // grupo agregado: itens ocultos
        ->and($p['D15'])->toBe('10.00')
        ->and($p['D41'])->toBe('0.00')
        ->and($p['modos'])->toBe(['custo' => 'detalhado', 'vendas' => 'agregado', 'administrativas' => 'agregado', 'financeiras' => 'agregado'])
        ->and(array_keys($p))->toHaveCount(43);
});

it('exige os obrigatórios do Anexo K: D01, D02, D40 e o totalizador do grupo agregado', function () {
    $l = Lancamento::deTexto(Setor::Comercio, modos(Modo::Agregado), []);

    expect($l->erros)->toBe([
        'D01' => 'obrigatorio', 'D02' => 'obrigatorio', 'D03' => 'obrigatorio', 'D08' => 'obrigatorio',
        'D15' => 'obrigatorio', 'D40' => 'obrigatorio', 'D37' => 'obrigatorio',
    ]);
});

it('não exige itens de grupo detalhado', function () {
    $l = Lancamento::deTexto(Setor::Comercio, modos(Modo::Detalhado), ['D01' => '10', 'D02' => '1', 'D40' => '0']);

    expect($l->temErros())->toBeFalse();
});

it('registra negativo e inválido por campo (B3 e formato)', function () {
    $l = Lancamento::deTexto(Setor::Comercio, modos(Modo::Detalhado), [
        'D01' => '100', 'D02' => '-1', 'D40' => '0', 'D04' => 'abc',
    ]);

    expect($l->erros)->toBe(['D02' => 'negativo', 'D04' => 'invalido']);
});

it('soma o total do grupo: digitado no agregado, soma dos itens no detalhado', function () {
    $modos = [...modos(Modo::Agregado), 'vendas' => Modo::Detalhado];
    $l = Lancamento::deTexto(Setor::Comercio, $modos, [
        'D01' => '1000', 'D02' => '0', 'D40' => '0', 'D03' => '300,10', 'D08' => '999', 'D09' => '10,05', 'D10' => '20,10',
        'D15' => '0', 'D37' => '0',
    ]);

    expect($l->total(Grupo::Custo))->toBe(30010)
        ->and($l->total(Grupo::Vendas))->toBe(3015); // D08 digitado antes é ignorado: grupo detalhado
});

it('calcula o lucro líquido como a estrutura-drm: L11 = L1 − L2 − custos − despesas − retirada ± outras', function () {
    $l = Lancamento::deTexto(Setor::Comercio, modos(Modo::Agregado), [
        'D01' => '1000', 'D02' => '100', 'D03' => '200', 'D08' => '50', 'D15' => '100', 'D37' => '10',
        'D40' => '80', 'D41' => '30', 'D42' => '5',
    ]);

    expect($l->lucroLiquido())->toBe(48500)
        ->and($l->despesas())->toBe(44500);
});

describe('ValidaLancamento', function () {
    $hoje = CarbonImmutable::parse('2026-11-05');
    $ok = ['D01' => '1000', 'D02' => '100', 'D40' => '0'];

    it('não acusa nada num lançamento correto de mês fechado', function () use ($hoje, $ok) {
        $l = Lancamento::deTexto(Setor::Comercio, modos(Modo::Detalhado), $ok);

        expect(ValidaLancamento::bloqueios($l, Competencia::deTexto('2026-10'), $hoje))->toBe([]);
    });

    it('B2 — bloqueia o mês corrente e diz qual é o último mês fechado', function () use ($hoje, $ok) {
        $l = Lancamento::deTexto(Setor::Comercio, modos(Modo::Detalhado), $ok);
        $b = ValidaLancamento::bloqueios($l, Competencia::deTexto('2026-11'), $hoje);

        expect($b)->toHaveCount(1)
            ->and($b[0]->codigo)->toBe('B2')
            ->and($b[0]->campo)->toBe('D00')
            ->and($b[0]->contexto)->toBe(['competencia' => 'novembro/2026', 'ultimo_mes_fechado' => 'outubro/2026']);
    });

    it('B3 — um bloqueio por campo negativo', function () use ($hoje) {
        $l = Lancamento::deTexto(Setor::Comercio, modos(Modo::Detalhado), ['D01' => '100', 'D02' => '-1', 'D40' => '-2']);
        $b = ValidaLancamento::bloqueios($l, Competencia::deTexto('2026-10'), $hoje);

        expect(array_map(fn ($o) => [$o->codigo, $o->campo], $b))->toBe([['B3', 'D02'], ['B3', 'D40']]);
    });

    it('B4 — impostos maiores que a receita, com os dois valores', function () use ($hoje) {
        $l = Lancamento::deTexto(Setor::Comercio, modos(Modo::Detalhado), ['D01' => '100', 'D02' => '100,01', 'D40' => '0']);
        $b = ValidaLancamento::bloqueios($l, Competencia::deTexto('2026-10'), $hoje);

        expect($b)->toHaveCount(1)
            ->and($b[0]->codigo)->toBe('B4')
            ->and($b[0]->contexto)->toBe(['valor_impostos' => 'R$ 100,01', 'valor_receita' => 'R$ 100,00']);
    });

    it('B4 — impostos iguais à receita é permitido (a regra é "maior")', function () use ($hoje) {
        $l = Lancamento::deTexto(Setor::Comercio, modos(Modo::Detalhado), ['D01' => '100', 'D02' => '100', 'D40' => '0']);

        expect(ValidaLancamento::bloqueios($l, Competencia::deTexto('2026-10'), $hoje))->toBe([]);
    });

    it('B4 — não compara se um dos dois campos nem é número válido', function () use ($hoje) {
        foreach ([['D01' => 'xx', 'D02' => '100'], ['D01' => '100', 'D02' => 'xx']] as $valores) {
            $l = Lancamento::deTexto(Setor::Comercio, modos(Modo::Detalhado), [...$valores, 'D40' => '0']);

            expect(ValidaLancamento::bloqueios($l, Competencia::deTexto('2026-10'), $hoje))->toBe([]);
        }
    });
});

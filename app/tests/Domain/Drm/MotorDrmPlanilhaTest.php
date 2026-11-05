<?php

declare(strict_types=1);

use App\Domain\Drm\Dinheiro;
use App\Domain\Drm\Grupo;
use App\Domain\Drm\Lancamento;
use App\Domain\Drm\Modo;
use App\Domain\Drm\Setor;

require_once __DIR__.'/MotorDrmHelpers.php';

/*
|-------------------------------------------------------------------------
| STORY-048 CA-11 — conferência com a planilha original, célula a célula.
|
| `tests/Fixtures/Drm/planilha-drm.json` guarda as fórmulas da planilha do Key User
| (`Demonstração de Resultado Mensal - DRM.xlsx`) e o mapeamento do Anexo K §K.11.
| O teste avalia essas fórmulas — como o Excel faria — sobre as mesmas entradas e
| exige que o motor devolva exatamente o mesmo em cada célula: valor em centavos,
| inteiro, e percentual `D<n>/D$2*100`.
|
| A planilha só conhece o modo "fora" (é o desenho do Key User) e não tem D41/D42,
| nem agregadores. O modo "dentro" é conferido contra a mesma planilha com o
| pró-labore somado às despesas, que é o que o modo diz.
|-------------------------------------------------------------------------
*/

/** @return array<string, mixed> */
function motorDrm_planilha(): array
{
    return json_decode((string) file_get_contents(base_path('tests/Fixtures/Drm/planilha-drm.json')), true, flags: JSON_THROW_ON_ERROR);
}

/**
 * Avalia uma célula da planilha em centavos (todas as fórmulas são só soma, subtração e SUM).
 *
 * @param  array<string, string>  $formulas
 * @param  array<string, int>  $entradas  célula => centavos
 */
function motorDrm_celula(string $celula, array $formulas, array $entradas): int
{
    if (! isset($formulas[$celula])) {
        return $entradas[$celula] ?? 0; // célula de entrada vazia vale zero no Excel
    }

    $expressao = preg_replace_callback(
        '/SUM\(D(\d+):D(\d+)\)/',
        fn (array $m) => implode('+', array_map(fn (int $n) => "D{$n}", range((int) $m[1], (int) $m[2]))),
        $formulas[$celula],
    );

    $total = 0;
    preg_match_all('/([+-]?)(D\d+)/', '+'.$expressao, $termos, PREG_SET_ORDER);
    foreach ($termos as [, $sinal, $ref]) {
        $valor = motorDrm_celula($ref, $formulas, $entradas);
        $total += $sinal === '-' ? -$valor : $valor;
    }

    return $total;
}

/** Entradas aleatórias (em centavos) por célula da planilha, respeitando o que o setor não coleta. */
function motorDrm_entradasDaPlanilha(Setor $setor): array
{
    $mapa = motorDrm_planilha()['entradas'];
    $suprimidos = $setor->camposSuprimidos();
    $entradas = [];
    foreach ($mapa as $celula => $campo) {
        $entradas[$celula] = in_array($campo, $suprimidos, true) ? 0 : (mt_rand(1, 10) <= 2 ? 0 : mt_rand(1, 3_000_000));
    }
    $entradas['D3'] = mt_rand(5_000_000, 90_000_000);
    $entradas['D5'] = mt_rand(0, 5_000_000);
    $entradas['D48'] = mt_rand(0, 2_000_000);

    return $entradas;
}

it('CA-11 — o modo "fora" reproduz a planilha em cada célula (valores e percentuais)', function (Setor $setor) {
    mt_srand(11);
    $planilha = motorDrm_planilha();
    $celulasComPercentual = ['D2', 'D4', 'D6', 'D7', 'D12', 'D13', 'D14', 'D21', 'D43', 'D44', 'D45', 'D46', 'D47', 'D48', 'D49'];
    $comparadas = 0;

    for ($caso = 0; $caso < 300; $caso++) {
        $entradas = motorDrm_entradasDaPlanilha($setor);

        $campos = [];
        foreach ($planilha['entradas'] as $celula => $campo) {
            if (! in_array($campo, $setor->camposSuprimidos(), true)) {
                $campos[$campo] = Dinheiro::decimal($entradas[$celula]);
            }
        }
        $demonstracao = motorDrm_calcular(motorDrm_payload($campos), $setor);

        $receita = motorDrm_celula('D2', $planilha['formulas'], $entradas);
        foreach ($planilha['linhas'] as $celula => $codigo) {
            $esperado = $celula === 'D48' || $celula === 'D45' || $celula === 'D46'
                ? $entradas[$celula]
                : motorDrm_celula($celula, $planilha['formulas'], $entradas);
            $linha = motorDrm_linha($demonstracao, 'fora', $codigo);

            // Valor: exato, em centavos inteiros.
            expect(Dinheiro::deDecimal($linha['nominal']))->toBe($esperado, "caso {$caso} {$celula}/{$codigo}");

            // Percentual: a fórmula da planilha, em ponto flutuante, deve cair a menos de 0,0001 ponto do valor
            // truncado do motor (o motor trunca em 4 casas; o Excel guarda o double).
            if (in_array($celula, $celulasComPercentual, true) && $receita !== 0) {
                $excel = $esperado / $receita * 100;
                expect(abs((float) $linha['percentual'] - $excel))->toBeLessThan(0.0001 + 1e-9, "caso {$caso} E{$celula}/{$codigo}");
            }
            $comparadas++;
        }
    }

    // 300 casos × 15 linhas conferidas.
    expect($comparadas)->toBe(4500);
})->with([
    'indústria' => [Setor::Industria],
    'comércio' => [Setor::Comercio],
    'serviços' => [Setor::Servicos],
]);

it('CA-11 — o modo "dentro" é a mesma planilha com o pró-labore somado às despesas', function () {
    mt_srand(111);
    $planilha = motorDrm_planilha();

    for ($caso = 0; $caso < 200; $caso++) {
        $entradas = motorDrm_entradasDaPlanilha(Setor::Industria);
        $campos = [];
        foreach ($planilha['entradas'] as $celula => $campo) {
            $campos[$campo] = Dinheiro::decimal($entradas[$celula]);
        }
        $demonstracao = motorDrm_calcular(motorDrm_payload($campos), Setor::Industria);

        $lucroBruto = motorDrm_celula('D12', $planilha['formulas'], $entradas);
        $despesas = motorDrm_celula('D13', $planilha['formulas'], $entradas) + $entradas['D48'];
        $financeiras = motorDrm_celula('D44', $planilha['formulas'], $entradas);

        // Resultado Operacional (EBITDA) = Lucro Bruto − (Despesas Operacionais + pró-labore).
        expect(Dinheiro::deDecimal(motorDrm_linha($demonstracao, 'dentro', 'L6')['nominal']))->toBe($despesas)
            ->and(Dinheiro::deDecimal(motorDrm_linha($demonstracao, 'dentro', 'L7')['nominal']))->toBe($lucroBruto - $despesas)
            // E o Lucro Líquido da planilha (D49) é o mesmo dos dois modos.
            ->and(Dinheiro::deDecimal(motorDrm_linha($demonstracao, 'dentro', 'L11')['nominal']))->toBe($lucroBruto - $despesas - $financeiras)
            ->and(Dinheiro::deDecimal(motorDrm_linha($demonstracao, 'dentro', 'L11')['nominal']))->toBe(motorDrm_celula('D49', $planilha['formulas'], $entradas));
    }
});

it('CA-11 — o mapeamento do fixture cobre exatamente os campos do Anexo K §K.11', function () {
    $campos = array_values(array_unique(array_values(motorDrm_planilha()['entradas'])));
    sort($campos);

    $esperados = array_merge(
        ['D01', 'D02', 'D40'],
        Grupo::Custo->itens(Setor::Industria),
        Grupo::Vendas->itens(Setor::Industria),
        Grupo::Administrativas->itens(Setor::Industria),
        Grupo::Financeiras->itens(Setor::Industria),
    );
    sort($esperados);

    expect($campos)->toBe($esperados)->and(count($campos))->toBe(36);
});

it('CA-11 — as fórmulas do fixture são as da planilha: Σ(D22:D42) tem 21 itens (D16..D36)', function () {
    $adm = Lancamento::camposDoGrupo(Grupo::Administrativas, Setor::Industria, array_fill_keys(array_map(fn ($g) => $g->value, Grupo::cases()), Modo::Detalhado));

    expect(count($adm))->toBe(21)->and(motorDrm_planilha()['formulas']['D21'])->toBe('SUM(D22:D42)');
});

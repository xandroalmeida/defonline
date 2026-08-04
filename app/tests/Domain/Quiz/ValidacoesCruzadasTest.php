<?php

declare(strict_types=1);

use App\Domain\Quiz\Alerta;
use App\Domain\Quiz\ValidacoesCruzadas;

/*
| ----------------------------------------------------------------------
| Validações cruzadas DRE × Balanço — STORY-034 §6.6.
|
| CA-2: ≥ 3 testes por regra (disparado, não-disparado, limiar exato).
| CA-6: regras vêm de config — teste de extensibilidade sem refactor.
| ----------------------------------------------------------------------
*/

function vcValidar(array $valores): array
{
    return (new ValidacoesCruzadas)->validar($valores);
}

/** @return array<string, string> regra => mensagem */
function vcAlertasPorRegra(array $valores): array
{
    $out = [];
    foreach (vcValidar($valores) as $alerta) {
        $out[$alerta->regra] = $alerta->mensagem;
    }

    return $out;
}

// ============================================================================
// R1 — Despesas financeiras × dívida: Q16 × 12 > Q06 × 2
// ============================================================================

it('R1 dispara quando as despesas financeiras anualizadas passam de 2× a dívida', function () {
    // Q16×12 = 120.000 > 2×Q06 = 80.000.
    $alertas = vcValidar(['Q16' => 10000.0, 'Q06' => 40000.0]);

    expect($alertas)->toHaveCount(1)
        ->and($alertas[0])->toBeInstanceOf(Alerta::class)
        ->and($alertas[0]->regra)->toBe('R1')
        ->and($alertas[0]->severidade)->toBe('warning')
        ->and($alertas[0]->camposEnvolvidos)->toBe(['Q16', 'Q06'])
        ->and($alertas[0]->campoFoco)->toBe('Q16');
});

it('R1 não dispara quando as despesas financeiras estão dentro do esperado', function () {
    // Q16×12 = 24.000 < 2×Q06 = 80.000.
    expect(vcAlertasPorRegra(['Q16' => 2000.0, 'Q06' => 40000.0]))->not->toHaveKey('R1');
});

it('R1 NÃO dispara no limiar exato (Q16×12 == 2×Q06) — comparação estrita', function () {
    // Q16×12 = 12.000 == 2×Q06 = 12.000.
    expect(vcAlertasPorRegra(['Q16' => 1000.0, 'Q06' => 6000.0]))->not->toHaveKey('R1');

    // 1 real acima do limiar já dispara.
    expect(vcAlertasPorRegra(['Q16' => 1001.0, 'Q06' => 6000.0]))->toHaveKey('R1');
});

it('R1 traz mensagem acionável com o percentual e os valores (CA-4)', function () {
    $alertas = vcValidar(['Q16' => 10000.0, 'Q06' => 40000.0]);

    expect($alertas[0]->mensagem)
        ->toContain('300%')                  // 120.000 ÷ 40.000
        ->toContain('R$ 10.000,00')          // :Q16
        ->toContain('R$ 40.000,00')          // :Q06
        ->and($alertas[0]->botaoLabel)->toContain('Despesas Financeiras');
});

// ============================================================================
// R2 — Custos anuais × receita anual: (Q14 + Q15) × 12 > Q09 × 12
// ============================================================================

it('R2 dispara quando os custos anuais ultrapassam a receita anual', function () {
    // (50.000 + 40.000)×12 = 1.080.000 > 80.000×12 = 960.000.
    $alertas = vcValidar(['Q14' => 50000.0, 'Q15' => 40000.0, 'Q09' => 80000.0]);

    expect($alertas)->toHaveCount(1)
        ->and($alertas[0]->regra)->toBe('R2')
        ->and($alertas[0]->camposEnvolvidos)->toBe(['Q14', 'Q15', 'Q09'])
        ->and($alertas[0]->mensagem)
        ->toContain('R$ 1.080.000,00')        // :esquerda
        ->toContain('R$ 960.000,00');         // :direita
});

it('R2 não dispara quando a receita cobre os custos', function () {
    // (20.000 + 8.000)×12 = 336.000 < 100.000×12 = 1.200.000.
    expect(vcAlertasPorRegra(['Q14' => 20000.0, 'Q15' => 8000.0, 'Q09' => 100000.0]))->not->toHaveKey('R2');
});

it('R2 NÃO dispara no limiar exato (custos anuais == receita anual)', function () {
    // (50.000 + 50.000) == 100.000.
    expect(vcAlertasPorRegra(['Q14' => 50000.0, 'Q15' => 50000.0, 'Q09' => 100000.0]))->not->toHaveKey('R2');
});

// ============================================================================
// R3 — Passivo × Ativo: (Q06 + Q07) > (Q02 + Q03 + Q04 + Q05)
// ============================================================================

it('R3 dispara quando o passivo total supera o ativo total (PL negativo)', function () {
    // Passivo = 200.000 > Ativo = 40.000.
    $alertas = vcValidar([
        'Q02' => 10000.0, 'Q03' => 10000.0, 'Q04' => 10000.0, 'Q05' => 10000.0,
        'Q06' => 100000.0, 'Q07' => 100000.0,
    ]);

    expect($alertas)->toHaveCount(1)
        ->and($alertas[0]->regra)->toBe('R3')
        ->and($alertas[0]->camposEnvolvidos)->toBe(['Q02', 'Q03', 'Q04', 'Q05', 'Q06', 'Q07'])
        ->and($alertas[0]->mensagem)
        ->toContain('R$ 200.000,00')          // :esquerda (passivo)
        ->toContain('R$ 40.000,00');          // :direita (ativo)
});

it('R3 não dispara em balanço saudável (ativo > passivo)', function () {
    expect(vcAlertasPorRegra([
        'Q02' => 50000.0, 'Q03' => 20000.0, 'Q04' => 10000.0, 'Q05' => 300000.0,
        'Q06' => 40000.0, 'Q07' => 80000.0,
    ]))->not->toHaveKey('R3');
});

it('R3 NÃO dispara no limiar exato (passivo == ativo)', function () {
    // Passivo 40.000 == Ativo 40.000.
    expect(vcAlertasPorRegra([
        'Q02' => 10000.0, 'Q03' => 10000.0, 'Q04' => 10000.0, 'Q05' => 10000.0,
        'Q06' => 20000.0, 'Q07' => 20000.0,
    ]))->not->toHaveKey('R3');
});

// ============================================================================
// Bordas / contrato
// ============================================================================

it('não dispara nada quando faltam campos para avaliar a regra', function () {
    expect(vcValidar([]))->toBe([]);
    // Só Q16 presente — R1 precisa de Q06 também.
    expect(vcValidar(['Q16' => 99999.0]))->toBe([]);
});

it('ignora regra com campo não-numérico em vez de quebrar', function () {
    expect(vcValidar(['Q16' => 'abc', 'Q06' => 40000.0]))->toBe([]);
});

it('aceita strings numéricas (formato canônico do motor)', function () {
    $alertas = vcValidar(['Q16' => '10000.00', 'Q06' => '40000.00']);
    expect($alertas)->toHaveCount(1)->and($alertas[0]->regra)->toBe('R1');
});

it('pode disparar mais de uma regra ao mesmo tempo', function () {
    // R1: Q16×12 = 24.000 > 2×Q06 = 20.000.  R3: passivo 210.000 > ativo 4.000.
    $regras = array_keys(vcAlertasPorRegra([
        'Q16' => 2000.0,
        'Q02' => 1000.0, 'Q03' => 1000.0, 'Q04' => 1000.0, 'Q05' => 1000.0,
        'Q06' => 10000.0, 'Q07' => 200000.0,
    ]));

    expect($regras)->toContain('R1')->toContain('R3');
});

// ============================================================================
// CA-6 — extensibilidade por config (sem refactor da classe)
// ============================================================================

it('avalia uma regra adicional definida só na config (CA-6)', function () {
    config()->set('quiz.validacoes-cruzadas.regras.R_TESTE', [
        'severidade' => 'warning',
        'campos_envolvidos' => ['Q02', 'Q03'],
        'campo_foco' => 'Q02',
        'botao_label' => 'Revisar',
        'condicao' => [
            'esquerda' => ['Q02' => 1],
            'operador' => '>',
            'fator' => 1,
            'direita' => ['Q03' => 1],
        ],
        'mensagem' => 'Q02 (R$ :Q02) maior que Q03 (R$ :direita).',
    ]);

    $alertas = vcAlertasPorRegra(['Q02' => 100.0, 'Q03' => 50.0]);

    expect($alertas)->toHaveKey('R_TESTE')
        ->and($alertas['R_TESTE'])->toContain('R$ 100,00');
});

it('suporta os operadores >=, <, <= e ignora operador desconhecido', function (string $operador, float $esquerda, float $direita, bool $esperaDisparo) {
    config()->set('quiz.validacoes-cruzadas.regras', [
        'R_OP' => [
            'severidade' => 'warning',
            'campos_envolvidos' => ['Q02', 'Q03'],
            'campo_foco' => 'Q02',
            'botao_label' => 'Revisar',
            'condicao' => [
                'esquerda' => ['Q02' => 1],
                'operador' => $operador,
                'fator' => 1,
                'direita' => ['Q03' => 1],
            ],
            'mensagem' => 'op :esquerda :direita',
        ],
    ]);

    $alertas = vcValidar(['Q02' => $esquerda, 'Q03' => $direita]);

    expect($alertas !== [])->toBe($esperaDisparo);
})->with([
    'maior-ou-igual no limiar dispara' => ['>=', 50.0, 50.0, true],
    'menor dispara' => ['<', 10.0, 50.0, true],
    'menor não dispara acima' => ['<', 90.0, 50.0, false],
    'menor-ou-igual no limiar dispara' => ['<=', 50.0, 50.0, true],
    'operador desconhecido nunca dispara' => ['??', 999.0, 1.0, false],
]);

// ============================================================================
// R4 — Recebíveis × venda mensal: Q20 >= Q09 (STORY-043, severidade `blocking`)
//
// Sentido: recebíveis (venda por cartão/duplicatas) são um subconjunto da venda
// total, logo devem ser INFERIORES a ela — Anexo A §validações ("Q20 ≥ Q09:
// bloqueio rígido"). A regra saiu da closure `Quiz::regraQ20MenorQueQ09()` e
// virou entrada declarativa para não haver duas fontes de verdade.
// ============================================================================

it('R4 dispara quando os recebíveis passam da venda mensal', function () {
    $alertas = vcValidar(['Q20' => 150000.0, 'Q09' => 100000.0]);

    expect($alertas)->toHaveCount(1)
        ->and($alertas[0])->toBeInstanceOf(Alerta::class)
        ->and($alertas[0]->regra)->toBe('R4')
        ->and($alertas[0]->severidade)->toBe(ValidacoesCruzadas::SEVERIDADE_BLOQUEIO)
        ->and($alertas[0]->camposEnvolvidos)->toBe(['Q20', 'Q09'])
        ->and($alertas[0]->campoFoco)->toBe('Q20');
});

it('R4 não dispara quando os recebíveis são inferiores à venda mensal', function () {
    expect(vcAlertasPorRegra(['Q20' => 26000.0, 'Q09' => 40000.0]))->not->toHaveKey('R4');
});

it('R4 DISPARA no limiar exato (Q20 == Q09) — o Anexo A exige "inferior"', function () {
    expect(vcAlertasPorRegra(['Q20' => 100000.0, 'Q09' => 100000.0]))->toHaveKey('R4');

    // 1 centavo abaixo do limiar já passa.
    expect(vcAlertasPorRegra(['Q20' => 99999.99, 'Q09' => 100000.0]))->not->toHaveKey('R4');
});

it('R4 traz a mensagem acionável do CA-3 com os dois valores e o botão de foco', function () {
    $alertas = vcValidar(['Q20' => 150000.0, 'Q09' => 100000.0]);

    expect($alertas[0]->mensagem)
        ->toContain('Recebíveis (R$ 150.000,00)')     // :Q20
        ->toContain('venda mensal média (R$ 100.000,00)')  // :Q09
        ->toContain('deve ser inferior à venda mensal')
        ->and($alertas[0]->botaoLabel)->toBe('Ir para Recebíveis (Q20)');
});

it('R4 é pulada quando os recebíveis não foram informados (sem captação)', function () {
    // Q17="não" → o componente entrega Q20 nulo; a regra não pode bloquear ninguém.
    expect(vcValidar(['Q20' => null, 'Q09' => 100000.0]))->toBe([]);
    expect(vcValidar(['Q09' => 100000.0]))->toBe([]);
});

// ============================================================================
// Filtro por severidade (STORY-043 CA-4) — o gate do Bloco 3 só vê avisos; o
// submit só vê bloqueios. Uma engine só, dois pontos de consumo.
// ============================================================================

it('filtra por severidade sem misturar aviso com bloqueio', function () {
    // Dados que disparam R3 (aviso: passivo > ativo) E R4 (bloqueio: Q20 >= Q09).
    $valores = [
        'Q02' => 1000.0, 'Q03' => 1000.0, 'Q04' => 1000.0, 'Q05' => 1000.0,
        'Q06' => 100000.0, 'Q07' => 100000.0,
        'Q09' => 100000.0, 'Q20' => 150000.0,
    ];

    $engine = new ValidacoesCruzadas;

    $todas = array_map(fn (Alerta $a): string => $a->regra, $engine->validar($valores));
    $avisos = array_map(fn (Alerta $a): string => $a->regra, $engine->validar($valores, ValidacoesCruzadas::SEVERIDADE_AVISO));
    $bloqueios = array_map(fn (Alerta $a): string => $a->regra, $engine->validar($valores, ValidacoesCruzadas::SEVERIDADE_BLOQUEIO));

    expect($todas)->toContain('R3')->toContain('R4')
        ->and($avisos)->toContain('R3')->not->toContain('R4')
        ->and($bloqueios)->toBe(['R4']);
});

it('filtro por severidade desconhecida não devolve nada (fail-closed)', function () {
    expect((new ValidacoesCruzadas)->validar(['Q20' => 150000.0, 'Q09' => 100000.0], 'inexistente'))->toBe([]);
});

// ============================================================================
// Arquiteturais da config (CA-1) — formato declarativo e severidades conhecidas.
// ============================================================================

it('toda regra da config declara uma severidade conhecida pela engine', function () {
    $regras = (array) config('quiz.validacoes-cruzadas.regras');

    expect($regras)->not->toBeEmpty();

    foreach ($regras as $id => $regra) {
        expect($regra['severidade'] ?? null)->toBeIn(
            [ValidacoesCruzadas::SEVERIDADE_AVISO, ValidacoesCruzadas::SEVERIDADE_BLOQUEIO],
            "Regra {$id} declara severidade fora do vocabulário da engine.",
        );
    }
});

it('a config de validações cruzadas é config:cache-safe (nenhuma closure)', function () {
    $temClosure = function (mixed $valor) use (&$temClosure): bool {
        if ($valor instanceof Closure) {
            return true;
        }
        if (! is_array($valor)) {
            return false;
        }

        foreach ($valor as $item) {
            if ($temClosure($item)) {
                return true;
            }
        }

        return false;
    };

    expect($temClosure(config('quiz.validacoes-cruzadas')))->toBeFalse();
});

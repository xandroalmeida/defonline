<?php

declare(strict_types=1);

use App\Domain\Drm\Competencia;
use App\Domain\Drm\Dinheiro;
use App\Domain\Drm\Grupo;
use App\Domain\Drm\Lancamento;
use App\Domain\Drm\Modo;
use App\Domain\Drm\MotorDrm;
use App\Domain\Drm\Setor;

require_once __DIR__.'/MotorDrmHelpers.php';

/*
|-------------------------------------------------------------------------
| STORY-048 CA-1c — o Lucro Líquido do Mês é idêntico nos dois modos.
|
| Divergência aqui é bug grave: a resposta a "meu negócio deu lucro?" não pode
| depender de uma preferência de exibição (estrutura-drm §1.3). A semente é
| fixa: o conjunto é o mesmo em toda execução, e uma falha é reproduzível.
|-------------------------------------------------------------------------
*/

/**
 * Lançamento aleatório e coerente com o que o formulário produz (setor, modos e campos coletados).
 *
 * @return array{0: Setor, 1: Lancamento}
 */
function motorDrm_lancamentoAleatorio(): array
{
    $setor = Setor::cases()[mt_rand(0, 2)];
    $modos = [];
    foreach (Grupo::cases() as $grupo) {
        $modos[$grupo->value] = mt_rand(0, 1) === 1 ? Modo::Agregado : Modo::Detalhado;
    }

    $texto = [];
    foreach (Lancamento::camposColetados($setor, $modos) as $campo) {
        $sorteio = mt_rand(1, 10);
        $centavos = match (true) {
            $sorteio <= 3 => 0,
            $sorteio === 4 => mt_rand(0, Dinheiro::MAXIMO_CENTAVOS),
            default => mt_rand(0, 20_000_000),
        };
        // Receita e deduções precisam de peso para os dois lados do resultado (lucro e prejuízo) aparecerem.
        if ($campo === 'D01' && $sorteio !== 1) {
            $centavos = mt_rand(1, 400_000_000);
        }
        $texto[$campo] = Dinheiro::mascara($centavos);
    }

    return [$setor, Lancamento::deTexto($setor, $modos, $texto)];
}

it('o Lucro Líquido é idêntico nos dois modos em 2000 lançamentos aleatórios', function () {
    mt_srand(48);
    $motor = new MotorDrm;
    $lucros = 0;
    $prejuizos = 0;

    for ($i = 0; $i < 2000; $i++) {
        [$setor, $lancamento] = motorDrm_lancamentoAleatorio();
        $d = $motor->calcular($lancamento->payload(), $setor, Competencia::deTexto('2026-09'), new DateTimeImmutable('2026-10-01T00:00:00+00:00'));

        $fora = array_values(array_filter($d['linhas']['fora'], fn (array $l) => $l['codigo'] === 'L11'))[0];
        $dentro = array_values(array_filter($d['linhas']['dentro'], fn (array $l) => $l['codigo'] === 'L11'))[0];

        expect($dentro['nominal'])->toBe($fora['nominal'], "caso {$i}: nominal")
            ->and($dentro['percentual'])->toBe($fora['percentual'], "caso {$i}: percentual")
            // E concorda com a conta independente da STORY-047, usada no alerta A3.
            ->and(Dinheiro::deDecimal($fora['nominal']))->toBe($lancamento->lucroLiquido(), "caso {$i}: lançamento");

        $lancamento->lucroLiquido() < 0 ? $prejuizos++ : $lucros++;
    }

    // O conjunto precisa exercitar os dois lados; sem isso o teste "passa" sem provar nada.
    expect($lucros)->toBeGreaterThan(200)->and($prejuizos)->toBeGreaterThan(200);
});

it('as linhas intermediárias dos modos se relacionam como o contrato diz', function () {
    mt_srand(480);
    $motor = new MotorDrm;

    for ($i = 0; $i < 500; $i++) {
        [$setor, $lancamento] = motorDrm_lancamentoAleatorio();
        $d = $motor->calcular($lancamento->payload(), $setor, Competencia::deTexto('2026-09'), new DateTimeImmutable('2026-10-01T00:00:00+00:00'));
        $valor = fn (string $modo, string $codigo): int => Dinheiro::deDecimal(array_values(array_filter($d['linhas'][$modo], fn (array $l) => $l['codigo'] === $codigo))[0]['nominal']);

        // As linhas até o Lucro Bruto são idênticas; o pró-labore só muda onde está.
        expect($valor('dentro', 'L5'))->toBe($valor('fora', 'L5'))
            ->and($valor('dentro', 'L6'))->toBe($valor('fora', 'L6') + $valor('fora', 'L10'))
            ->and($valor('dentro', 'L7'))->toBe($valor('fora', 'L7') - $valor('fora', 'L10'))
            ->and($valor('fora', 'L9') - $valor('fora', 'L10'))->toBe($valor('fora', 'L11'));
    }
});

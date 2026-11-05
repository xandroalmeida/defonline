<?php

declare(strict_types=1);

use App\Domain\Drm\Competencia;
use App\Domain\Drm\Dinheiro;
use App\Domain\Drm\MotorDrm;
use App\Domain\Drm\Setor;

/*
|-------------------------------------------------------------------------
| STORY-048 CA-10 — golden tests congelados.
|
| Cada arquivo de `tests/Fixtures/Drm/golden/` guarda a entrada (payload canônico, setor,
| competência e carimbo) e a saída completa do motor, os dois modos incluídos. Alterar
| qualquer valor, rótulo, ordem, `tipo` ou `destaque` quebra o teste — é o objetivo.
| Quem muda a saída de propósito sobe `motor_drm_version` e regenera os arquivos revisando o
| diff linha a linha; não existe regeneração automática.
|
| Os números de cada cenário foram conferidos à mão (ver notas da STORY-048) e as mesmas
| fórmulas são conferidas contra a planilha em MotorDrmPlanilhaTest.
|-------------------------------------------------------------------------
*/

/** @return array<string, array{0: array<string, mixed>}> */
function motorDrm_cenarios(): array
{
    $cenarios = [];
    foreach (glob(__DIR__.'/../../Fixtures/Drm/golden/*.json') ?: [] as $arquivo) {
        $cenarios[basename($arquivo, '.json')] = [json_decode((string) file_get_contents($arquivo), true, flags: JSON_THROW_ON_ERROR)];
    }
    ksort($cenarios);

    return $cenarios;
}

dataset('golden_drm', motorDrm_cenarios());

it('CA-10 — o motor reproduz o golden, bit a bit', function (array $golden) {
    $e = $golden['entrada'];
    $obtido = (new MotorDrm)->calcular($e['payload'], Setor::from($e['setor']), Competencia::deTexto($e['competencia']), new DateTimeImmutable($e['gerado_em']));

    // Comparação estrita (===): mesmas chaves, mesma ordem, mesmos tipos.
    expect($obtido)->toBe($golden['esperado']);
})->with('golden_drm');

it('CA-10 — o golden respeita o invariante: Lucro Líquido idêntico nos dois modos', function (array $golden) {
    $l11 = fn (string $modo): string => array_values(array_filter($golden['esperado']['linhas'][$modo], fn (array $l) => $l['codigo'] === 'L11'))[0]['nominal'];

    expect($l11('dentro'))->toBe($l11('fora'));
})->with('golden_drm');

it('CA-10 — o conjunto congelado cobre o que a estória exige', function () {
    $cenarios = motorDrm_cenarios();
    $golden = array_map(fn (array $c) => $c[0], $cenarios);
    $modosDe = fn (array $g): array => array_unique(array_values($g['esperado']['modos']));
    $nominal = fn (array $g, string $modo, string $codigo): ?string => array_values(array_filter($g['esperado']['linhas'][$modo], fn (array $l) => $l['codigo'] === $codigo))[0]['nominal'] ?? null;

    $setoresSaudaveis = [];
    $achou = ['prejuizo' => false, 'sem_faturamento' => false, 'agregados' => false, 'detalhados' => false, 'misto' => false];
    foreach ($golden as $nome => $g) {
        $lucro = Dinheiro::deDecimal($nominal($g, 'fora', 'L11'));
        if ($lucro > 0) {
            $setoresSaudaveis[$g['entrada']['setor']] = true;
        }
        $achou['prejuizo'] = $achou['prejuizo'] || $lucro < 0;
        $achou['sem_faturamento'] = $achou['sem_faturamento'] || $nominal($g, 'fora', 'L1') === '0.00';
        $modos = $modosDe($g);
        $achou['agregados'] = $achou['agregados'] || $modos === ['agregado'];
        $achou['detalhados'] = $achou['detalhados'] || $modos === ['detalhado'];
        $achou['misto'] = $achou['misto'] || count($modos) === 2;
    }

    expect(count($cenarios))->toBeGreaterThanOrEqual(10)
        ->and(array_keys($setoresSaudaveis))->toEqualCanonicalizing(['industria', 'comercio', 'servicos'])
        ->and($achou)->toBe(['prejuizo' => true, 'sem_faturamento' => true, 'agregados' => true, 'detalhados' => true, 'misto' => true]);
});

it('CA-10 — L7b aparece quando há não operacionais e some quando não há (nos dois modos)', function () {
    $golden = array_map(fn (array $c) => $c[0], motorDrm_cenarios());
    $codigos = fn (string $cenario, string $modo): array => array_column($golden[$cenario]['esperado']['linhas'][$modo], 'codigo');

    expect($codigos('sem_prolabore_sem_nao_operacionais', 'fora'))->not->toContain('L7b')
        ->and($codigos('sem_prolabore_sem_nao_operacionais', 'dentro'))->not->toContain('L7b')
        ->and($codigos('nao_operacionais_que_se_anulam', 'fora'))->toContain('L7b')
        ->and($codigos('nao_operacionais_que_se_anulam', 'dentro'))->toContain('L7b');
});

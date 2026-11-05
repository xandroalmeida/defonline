<?php

declare(strict_types=1);

use App\Domain\Cobranca\VitrinePrecos;

/** STORY-075 — valores derivados da vitrine. */
it('divide o anual em 12 parcelas, com meio centavo para cima', function (int $anual, int $esperado) {
    expect(VitrinePrecos::equivalenteMensal($anual))->toBe($esperado);
})->with([[39900, 3325], [159900, 13325], [10000, 833], [10006, 834], [10005, 834], [0, 0]]);

it('calcula a economia do anual sobre 12 mensais', function (int $mensal, int $anual, int $esperado) {
    expect(VitrinePrecos::economiaPercentual($mensal, $anual))->toBe($esperado);
})->with([
    'básico' => [4990, 39900, 33],
    'pro' => [19900, 159900, 33],
    'sem economia' => [1000, 12000, 0],
    'anual mais caro' => [1000, 13000, 0],
    'mensal zero' => [0, 5000, 0],
    'metade' => [1000, 6000, 50],
    'arredonda meio ponto para cima' => [1000, 7980, 34],
]);

it('calcula o preço unitário do pacote', function (int $preco, int $qtd, int $esperado) {
    expect(VitrinePrecos::unitario($preco, $qtd))->toBe($esperado);
})->with([[34990, 5, 6998], [59990, 10, 5999], [8990, 1, 8990], [1000, 3, 333], [1001, 2, 501], [1000, 0, 0]]);

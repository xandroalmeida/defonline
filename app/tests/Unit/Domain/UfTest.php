<?php

declare(strict_types=1);

use App\Domain\Uf;

it('lista as 27 UFs brasileiras (26 estados + DF)', function () {
    expect(Uf::cases())->toHaveCount(27);
});

it('expõe valores como strings de 2 letras maiúsculas', function () {
    $valores = Uf::valores();
    expect($valores)->toHaveCount(27);
    foreach ($valores as $v) {
        expect($v)->toMatch('/^[A-Z]{2}$/');
    }
    expect($valores)->toContain('SP', 'RJ', 'DF', 'AC', 'TO');
});

it('garante unicidade dos códigos', function () {
    $valores = Uf::valores();
    expect(array_unique($valores))->toHaveCount(count($valores));
});

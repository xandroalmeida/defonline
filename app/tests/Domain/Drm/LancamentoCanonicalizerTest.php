<?php

declare(strict_types=1);

use App\Domain\Drm\LancamentoCanonicalizer;

/*
|-------------------------------------------------------------------------
| STORY-047 CA-10 — as cinco regras do IDR-010 aplicadas ao payload do DRM.
|-------------------------------------------------------------------------
*/

it('ordena as chaves recursivamente (regra 1)', function () {
    $a = LancamentoCanonicalizer::json(['b' => '1.00', 'a' => ['z' => '2.00', 'y' => '3.00']]);
    $b = LancamentoCanonicalizer::json(['a' => ['y' => '3.00', 'z' => '2.00'], 'b' => '1.00']);

    expect($a)->toBe($b)->toBe('{"a":{"y":"3.00","z":"2.00"},"b":"1.00"}');
});

it('serializa número como string de casas fixas (regra 2)', function () {
    expect(LancamentoCanonicalizer::canonicalizar(['a' => 5, 'b' => 1.5, 'c' => '7.00']))
        ->toBe(['a' => '5.00', 'b' => '1.50', 'c' => '7.00']);
});

it('troca string vazia ou só espaços por null (regra 3)', function () {
    expect(LancamentoCanonicalizer::canonicalizar(['a' => '', 'b' => '   ', 'c' => null]))
        ->toBe(['a' => null, 'b' => null, 'c' => null]);
});

it('mantém booleanos literais (regra 4)', function () {
    expect(LancamentoCanonicalizer::json(['sim' => true, 'nao' => false]))->toBe('{"nao":false,"sim":true}');
});

it('normaliza UTF-8 para NFC (regra 5)', function () {
    $nfd = "Cafe\u{0301}";
    $nfc = "Caf\u{00E9}";

    expect(LancamentoCanonicalizer::hash(['x' => $nfd]))->toBe(LancamentoCanonicalizer::hash(['x' => $nfc]));
});

it('preserva a ordem de listas', function () {
    expect(LancamentoCanonicalizer::json(['l' => ['b', 'a']]))->toBe('{"l":["b","a"]}');
});

it('gera SHA-256 hexadecimal estável (golden)', function () {
    $payload = ['D01' => '84300.00', 'D02' => null, 'modos' => ['custo' => 'agregado']];

    expect(LancamentoCanonicalizer::hash($payload))
        ->toMatch('/^[0-9a-f]{64}$/')
        ->toBe(hash('sha256', '{"D01":"84300.00","D02":null,"modos":{"custo":"agregado"}}'));
});

it('o modo de um grupo muda o hash (é conteúdo, ADR-007)', function () {
    expect(LancamentoCanonicalizer::hash(['modos' => ['custo' => 'agregado']]))
        ->not->toBe(LancamentoCanonicalizer::hash(['modos' => ['custo' => 'detalhado']]));
});

it('falha alto se o payload não for serializável', function () {
    expect(fn () => LancamentoCanonicalizer::json(['x' => "\xB1\x31"]))->toThrow(RuntimeException::class);
});

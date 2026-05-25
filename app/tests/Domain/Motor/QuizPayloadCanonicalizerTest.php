<?php

declare(strict_types=1);

use App\Domain\Motor\Motor;
use App\Domain\Motor\QuizPayloadCanonicalizer;

/*
| ----------------------------------------------------------------------
| Canonicalização do quiz_payload — IDR-010 §sub-decisão 3.
|
| Cobre: ordenação de chaves (recursiva), normalização de strings vazias,
| preservação de zero e false, encoding NFC, serialização JSON estável.
| ----------------------------------------------------------------------
*/

it('ordena chaves lexicograficamente no nível raiz', function () {
    $entrada = ['Q09' => '100', 'Q01' => '1', 'Q05' => '300'];
    $canonical = QuizPayloadCanonicalizer::canonicalize($entrada);
    expect(array_keys($canonical))->toBe(['Q01', 'Q05', 'Q09']);
});

it('ordena chaves recursivamente em sub-arrays', function () {
    $entrada = ['Q01' => 1, 'extra' => ['zeta' => 'z', 'alpha' => 'a', 'mu' => 'm']];
    $canonical = QuizPayloadCanonicalizer::canonicalize($entrada);
    expect(array_keys($canonical['extra']))->toBe(['alpha', 'mu', 'zeta']);
});

it('preserva ordem de listas (arrays indexados sequenciais)', function () {
    $entrada = ['Q21_socios' => ['Carlos', 'Ana', 'Beatriz']];
    $canonical = QuizPayloadCanonicalizer::canonicalize($entrada);
    expect($canonical['Q21_socios'])->toBe(['Carlos', 'Ana', 'Beatriz']);
});

it('normaliza string vazia para null', function () {
    $canonical = QuizPayloadCanonicalizer::canonicalize(['Q11' => '']);
    expect($canonical['Q11'])->toBeNull();
});

it('normaliza string só com espaços para null', function () {
    $canonical = QuizPayloadCanonicalizer::canonicalize(['Q11' => '   ']);
    expect($canonical['Q11'])->toBeNull();
});

it('preserva string contendo apenas zero "0"', function () {
    $canonical = QuizPayloadCanonicalizer::canonicalize(['Q10' => '0']);
    expect($canonical['Q10'])->toBe('0');
});

it('preserva int 0 e float 0.0 e false (não confunde com vazio)', function () {
    $canonical = QuizPayloadCanonicalizer::canonicalize(['Q10' => 0, 'Q11' => 0.0, 'Q17_flag' => false]);
    expect($canonical['Q10'])->toBe(0);
    expect($canonical['Q11'])->toBe(0.0);
    expect($canonical['Q17_flag'])->toBeFalse();
});

it('preserva null literal', function () {
    $canonical = QuizPayloadCanonicalizer::canonicalize(['Q11' => null]);
    expect($canonical['Q11'])->toBeNull();
});

it('toJson serializa com flags determinísticas (UNESCAPED_UNICODE + PRESERVE_ZERO_FRACTION)', function () {
    $canonical = QuizPayloadCanonicalizer::canonicalize(['Q09' => 100.0, 'Q01' => 'José']);
    $json = QuizPayloadCanonicalizer::toJson($canonical);
    // UNESCAPED_UNICODE preserva 'é' literal; PRESERVE_ZERO_FRACTION emite 100.0 (não 100).
    expect($json)->toBe('{"Q01":"José","Q09":100.0}');
});

it('também conta com a Action no Provider — service container resolve Motor', function () {
    expect(app(Motor::class))->toBeInstanceOf(Motor::class);
});

it('toJson é estável para duas execuções com mesma entrada (idempotente)', function () {
    $payload = ['Q09' => '100000.00', 'Q01' => 1, 'Q03' => '80000.00'];
    $j1 = QuizPayloadCanonicalizer::toJson(QuizPayloadCanonicalizer::canonicalize($payload));
    $j2 = QuizPayloadCanonicalizer::toJson(QuizPayloadCanonicalizer::canonicalize($payload));
    expect($j1)->toBe($j2);
});

it('toJson produz JSON idêntico independente da ordem de inserção do array original', function () {
    $a = ['Q09' => '100', 'Q01' => 1, 'Q03' => '80'];
    $b = ['Q03' => '80', 'Q09' => '100', 'Q01' => 1];
    $jA = QuizPayloadCanonicalizer::toJson(QuizPayloadCanonicalizer::canonicalize($a));
    $jB = QuizPayloadCanonicalizer::toJson(QuizPayloadCanonicalizer::canonicalize($b));
    expect($jA)->toBe($jB);
});

it('aplica NFC em strings com acentos decompostos', function () {
    // "José" decomposto (NFD): J o s e + combining acute
    $decomposto = "Jose\u{0301}";
    $canonical = QuizPayloadCanonicalizer::canonicalize(['nome' => $decomposto]);
    // Após NFC: "José" canônico — mesma sequência de bytes que UTF-8 padrão.
    expect($canonical['nome'])->toBe('José');
});

it('serializa false sem trocar por 0', function () {
    $json = QuizPayloadCanonicalizer::toJson(QuizPayloadCanonicalizer::canonicalize(['Q17' => false]));
    expect($json)->toBe('{"Q17":false}');
});

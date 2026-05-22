<?php

declare(strict_types=1);

use App\Observabilidade\LogSanitizer;

/**
 * Teste unitário trivial (STORY-007 CA-7) — também exercita o framework de testes.
 */

it('redacts credentials completely', function () {
    $sanitized = LogSanitizer::sanitize([
        'password' => 'super-secret',
        'token' => 'abc.def.ghi',
        'authorization' => 'Bearer xyz',
    ]);

    expect($sanitized)
        ->toHaveKey('password', '[REDACTED]')
        ->toHaveKey('token', '[REDACTED]')
        ->toHaveKey('authorization', '[REDACTED]');
});

it('masks CPF preserving last two digits', function () {
    $sanitized = LogSanitizer::sanitize(['cpf' => '12345678901']);

    expect($sanitized['cpf'])->toBe('***.***.***-01');
});

it('masks email preserving first local char and TLD', function () {
    $sanitized = LogSanitizer::sanitize(['email' => 'jose@gmail.com']);

    expect($sanitized['email'])->toBe('j***@*****.com');
});

it('masks CNPJ allowing only root segment', function () {
    $sanitized = LogSanitizer::sanitize(['cnpj' => '12345678000199']);

    expect($sanitized['cnpj'])->toBe('12345678/****-**');
});

it('redacts financial keys by regex', function () {
    $sanitized = LogSanitizer::sanitize([
        'faturamento_bruto' => 123_456.78,
        'balanco_2025' => 555,
        'receita_q1' => 99,
        'custo_total' => 42,
    ]);

    expect($sanitized)
        ->toHaveKey('faturamento_bruto', '[REDACTED]')
        ->toHaveKey('balanco_2025', '[REDACTED]')
        ->toHaveKey('receita_q1', '[REDACTED]')
        ->toHaveKey('custo_total', '[REDACTED]');
});

it('recursively sanitizes nested arrays', function () {
    $sanitized = LogSanitizer::sanitize([
        'usuario' => [
            'nome' => 'Roberto', // not in proibido list — keeps
            'email' => 'roberto@empresa.com',
            'cpf' => '12345678901',
        ],
    ]);

    expect($sanitized['usuario']['nome'])->toBe('Roberto');
    expect($sanitized['usuario']['email'])->toBe('r******@*******.com');
    expect($sanitized['usuario']['cpf'])->toBe('***.***.***-01');
});

it('leaves non-sensitive keys untouched', function () {
    $sanitized = LogSanitizer::sanitize([
        'request_id' => '0190b1aa-0000-7000-8000-000000000000',
        'module' => 'cadastro',
        'count' => 42,
    ]);

    expect($sanitized)
        ->toHaveKey('request_id', '0190b1aa-0000-7000-8000-000000000000')
        ->toHaveKey('module', 'cadastro')
        ->toHaveKey('count', 42);
});

it('handles empty values gracefully', function () {
    $sanitized = LogSanitizer::sanitize([
        'password' => '',
        'cpf' => '',
    ]);

    expect($sanitized['password'])->toBe('[REDACTED]');
    expect($sanitized['cpf'])->toBe('[REDACTED]');
});

<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

/**
 * STORY-077 CA-4 / ADR-010 §2 — o banco não tem coluna para número, validade, CVV nem token de
 * cartão. Das colunas de cartão, só bandeira e 4 últimos dígitos, curtas demais para ser um número.
 */
it('nenhuma tabela tem coluna de número, validade, CVV ou token de cartão', function () {
    $colunas = DB::table('information_schema.columns')
        ->where('table_schema', 'public')
        ->where(fn ($q) => $q
            ->where('column_name', 'ilike', '%cvv%')->orWhere('column_name', 'ilike', '%cvc%')->orWhere('column_name', 'ilike', '%ccv%')
            ->orWhere('column_name', 'ilike', '%numero_cartao%')->orWhere('column_name', 'ilike', '%card_number%')
            ->orWhere('column_name', 'ilike', '%validade_cartao%')->orWhere('column_name', 'ilike', '%titular%')
            ->orWhere('column_name', 'ilike', '%token%'))
        ->get(['table_name', 'column_name'])
        ->map(fn ($c) => "{$c->table_name}.{$c->column_name}")
        // `personal_access_tokens`/`remember_token`/`token` de reset de senha não são de cartão.
        ->reject(fn (string $c) => preg_match('/remember_token|password_reset|personal_access|\.token$|sessions|cache/', $c) === 1)
        ->values()->all();

    expect($colunas)->toBe([]);
});

it('as colunas de cartão guardam no máximo bandeira (20) e 4 dígitos', function () {
    $tamanhos = DB::table('information_schema.columns')
        ->where('table_schema', 'public')->where('column_name', 'like', 'cartao\_%')
        ->pluck('character_maximum_length', 'column_name')->all();

    expect($tamanhos)->toBe(['cartao_bandeira' => 20, 'cartao_ultimos4' => 4]);
});

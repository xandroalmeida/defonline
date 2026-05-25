<?php

declare(strict_types=1);

use App\Domain\Motor\Farois\FarolIndustria;

/*
| ----------------------------------------------------------------------
| Classificador FarolIndustria — Anexo E literal.
|
| Cobre as duas direções (maior_melhor, menor_melhor) com casos típicos
| + as 3 fronteiras de cada indicador (verde→amarelo, amarelo→vermelho,
| extremos). null sempre retorna NENHUM.
| ----------------------------------------------------------------------
*/

// ---------- maior_melhor: Margem Bruta (Indústria > 25% verde, (20, 25] amarelo, ≤ 20 vermelho) ----------

it('Margem Bruta — verde acima de 25%', function () {
    expect(FarolIndustria::classificar('margem_bruta', 30.0))->toBe('verde');
});

it('Margem Bruta — fronteira verde: 25.01 → verde (estritamente > 25)', function () {
    expect(FarolIndustria::classificar('margem_bruta', 25.01))->toBe('verde');
});

it('Margem Bruta — fronteira amarelo superior: 25.0 exato → amarelo (≤ 25)', function () {
    expect(FarolIndustria::classificar('margem_bruta', 25.0))->toBe('amarelo');
});

it('Margem Bruta — amarelo típico: 22.5', function () {
    expect(FarolIndustria::classificar('margem_bruta', 22.5))->toBe('amarelo');
});

it('Margem Bruta — fronteira amarelo inferior: 20.01 → amarelo (> 20)', function () {
    expect(FarolIndustria::classificar('margem_bruta', 20.01))->toBe('amarelo');
});

it('Margem Bruta — fronteira vermelho superior: 20.0 → vermelho (≤ 20)', function () {
    expect(FarolIndustria::classificar('margem_bruta', 20.0))->toBe('vermelho');
});

it('Margem Bruta — vermelho típico: 10.0', function () {
    expect(FarolIndustria::classificar('margem_bruta', 10.0))->toBe('vermelho');
});

it('Margem Bruta — vermelho extremo negativo', function () {
    expect(FarolIndustria::classificar('margem_bruta', -5.0))->toBe('vermelho');
});

// ---------- menor_melhor: Dívida Líq/EBITDA (≤ 2 verde, (2, 3] amarelo, > 3 vermelho) ----------

it('Dívida Líq/EBITDA — verde abaixo de 2', function () {
    expect(FarolIndustria::classificar('divida_liquida_ebitda', 1.5))->toBe('verde');
});

it('Dívida Líq/EBITDA — fronteira verde: 2.0 exato → verde (≤ 2)', function () {
    expect(FarolIndustria::classificar('divida_liquida_ebitda', 2.0))->toBe('verde');
});

it('Dívida Líq/EBITDA — fronteira amarelo: 2.01 → amarelo (> 2)', function () {
    expect(FarolIndustria::classificar('divida_liquida_ebitda', 2.01))->toBe('amarelo');
});

it('Dívida Líq/EBITDA — fronteira amarelo superior: 3.0 → amarelo (≤ 3)', function () {
    expect(FarolIndustria::classificar('divida_liquida_ebitda', 3.0))->toBe('amarelo');
});

it('Dívida Líq/EBITDA — fronteira vermelho: 3.01 → vermelho (> 3)', function () {
    expect(FarolIndustria::classificar('divida_liquida_ebitda', 3.01))->toBe('vermelho');
});

it('Dívida Líq/EBITDA — valor negativo (caixa líquido) → verde', function () {
    expect(FarolIndustria::classificar('divida_liquida_ebitda', -0.5))->toBe('verde');
});

// ---------- null sempre retorna NENHUM (indisponível) ----------

it('valor null sempre retorna NENHUM', function () {
    expect(FarolIndustria::classificar('margem_bruta', null))->toBe('nenhum');
    expect(FarolIndustria::classificar('divida_liquida_ebitda', null))->toBe('nenhum');
});

// ---------- erros de invariante ----------

it('lança exception se chave não está configurada', function () {
    FarolIndustria::classificar('indicador_inexistente', 10.0);
})->throws(InvalidArgumentException::class, "Faixa de farol não configurada para indicador 'indicador_inexistente'");

it('lança exception se config tem tipo inválido', function () {
    config()->set('motor.faroes-industria.bug_test', [
        'tipo' => 'tipo_invalido',
        'verde' => ['op' => '>', 'valor' => 0.0],
        'amarelo' => ['min' => 0.0, 'max' => 1.0],
        'vermelho' => ['op' => '<=', 'valor' => 0.0],
    ]);
    FarolIndustria::classificar('bug_test', 5.0);
})->throws(InvalidArgumentException::class, 'Tipo de farol inválido');

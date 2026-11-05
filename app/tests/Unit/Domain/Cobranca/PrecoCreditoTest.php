<?php

declare(strict_types=1);

use App\Domain\Cobranca\PrecoCredito;

/**
 * STORY-071 CA-7 — o crédito avulso abaixo do unitário mensal de um plano ativo gera aviso.
 */
$planos = [
    ['nome' => 'Básico', 'cota_mensal' => 1, 'preco_mensal_centavos' => 4990],
    ['nome' => 'Pro', 'cota_mensal' => 10, 'preco_mensal_centavos' => 19900],
];

it('não avisa quando o unitário do pacote supera o de todos os planos', function () use ($planos) {
    expect(PrecoCredito::planosMaisBaratos(8990, 1, $planos))->toBe([])
        ->and(PrecoCredito::planosMaisBaratos(34990, 5, $planos))->toBe([])
        ->and(PrecoCredito::planosMaisBaratos(59990, 10, $planos))->toBe([]);
});

it('avisa o plano cujo unitário é maior que o do pacote', function () use ($planos) {
    // 4,00 por crédito < 49,90 do Básico, mas também < 19,90 do Pro.
    expect(PrecoCredito::planosMaisBaratos(400, 1, $planos))->toBe(['Básico', 'Pro'])
        // 40,00 por crédito: abaixo do Básico (49,90), acima do Pro (19,90).
        ->and(PrecoCredito::planosMaisBaratos(40000, 10, $planos))->toBe(['Básico']);
});

it('unitário igual ao do plano não avisa (borda)', function () use ($planos) {
    expect(PrecoCredito::planosMaisBaratos(4990, 1, $planos))->toBe([])
        ->and(PrecoCredito::planosMaisBaratos(19900, 10, [$planos[1]]))->toBe([]);
});

it('compara sem arredondar (centavos fracionários do unitário)', function () use ($planos) {
    // 19,90 do Pro = 1990/crédito. Pacote 3 por 5969 = 1989,67/crédito: abaixo.
    expect(PrecoCredito::planosMaisBaratos(5969, 3, [$planos[1]]))->toBe(['Pro'])
        ->and(PrecoCredito::planosMaisBaratos(5970, 3, [$planos[1]]))->toBe([]);
});

it('ignora quantidade inválida, plano sem cota e lista vazia', function () use ($planos) {
    expect(PrecoCredito::planosMaisBaratos(100, 0, $planos))->toBe([])
        ->and(PrecoCredito::planosMaisBaratos(100, 1, [['nome' => 'X', 'cota_mensal' => 0, 'preco_mensal_centavos' => 999]]))->toBe([])
        ->and(PrecoCredito::planosMaisBaratos(100, 1, []))->toBe([]);
});

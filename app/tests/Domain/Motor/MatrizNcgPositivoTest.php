<?php

declare(strict_types=1);

use App\Domain\Motor\Motor;
use Tests\Support\RecomendacoesCongeladas;

/*
|-------------------------------------------------------------------------
| STORY-042 CA-7 + PDR-018 — recomendação de NCG na matriz dez-2025.
|
| O autor do Anexo F enxugou o próprio texto de §F.13 / coluna Indústria / linha
| "NCG positiva": a primeira frase sai, a segunda fica. Desde o PDR-018 o
| relatório exibe esse texto.
|-------------------------------------------------------------------------
*/

$textoNovo = 'A elevação deve ser acompanhada, na mesma proporção, pelo aumento das fontes de financiamento de longo prazo, preferencialmente de fornecedores.';

it('CA-7 — `ncg_absoluto.positiva` é exatamente o texto revisado', function () use ($textoNovo) {
    // Igualdade estrita: `toContain` passaria com o texto antigo, do qual o
    // texto novo é literalmente a segunda frase.
    expect(RecomendacoesCongeladas::matriz()['ncg_absoluto']['positiva'])->toBe($textoNovo);
});

it('CA-7 (borda) — o cenário `negativa` não foi tocado', function () {
    expect(RecomendacoesCongeladas::matriz()['ncg_absoluto']['negativa'])->toBe(
        'Teoricamente, a empresa não necessita de recursos para o giro. NCG negativa significa Investimento Operacional em Giro (IOG).',
    );
});

it('CA-7 (borda) — a edição não vazou para o `ncg_vendas`, que tem faixas próprias (§F.14)', function () use ($textoNovo) {
    // A estória apontou o alvo como "`ncg_vendas` × farol_positivo". Esse par não
    // existe (ncg_vendas é verde/amarelo/vermelho) e o texto pedido é de §F.13.
    // Trocar o alvo errado é o modo de falha real desta tarefa.
    /** @var array<string, array<string, string>> $matriz */
    $matriz = RecomendacoesCongeladas::matriz();

    foreach (['verde', 'amarelo', 'vermelho'] as $farol) {
        expect($matriz['ncg_vendas'][$farol])->not->toBe($textoNovo);
    }

    expect($matriz['ncg_vendas']['vermelho'])->toBe(
        'Não é recomendável a elevação das atividades e da NCG, se não acompanhada do aumento do prazo para pagamento a fornecedores.',
    );
});

it('PDR-018 — a mensagem gravada no snapshot é o texto da matriz', function () use ($textoNovo) {
    // NCG = Clientes + Estoques − Fornecedores = 20.000 + 10.000 − 5.000 > 0.
    $payload = [
        'Q01' => 1, 'Q02' => '50000', 'Q03' => '20000', 'Q04' => '10000',
        'Q05' => '300000', 'Q06' => '40000', 'Q07' => '5000', 'Q08' => '50000',
        'Q09' => '100000', 'Q10' => 90, 'Q11' => 15, 'Q12' => 15, 'Q13' => '2',
        'Q14' => '20000', 'Q15' => '8000', 'Q16' => '2000',
    ];

    $ncg = (new Motor)->calcular($payload, 'industria')['indicadores_calculados']['ncg_absoluto'];

    expect($ncg['valor'])->toBeGreaterThan(0)
        ->and($ncg['mensagem'])->toBe($textoNovo);
});

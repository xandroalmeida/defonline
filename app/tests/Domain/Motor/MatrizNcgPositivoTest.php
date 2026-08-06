<?php

declare(strict_types=1);

use App\Domain\Motor\Indicadores\NcgAbsoluto;
use App\Domain\Motor\Motor;

/*
|-------------------------------------------------------------------------
| STORY-042 CA-7 — recomendação de NCG positivo na matriz dez-2025.
|
| Edvan (autor do Anexo F) enxugou o próprio texto de §F.13 / coluna
| Indústria / linha "NCG positiva": a primeira frase sai, a segunda fica.
|
| **O que este arquivo prova — e o que ele não prova.** A entrada
| `ncg_absoluto` da matriz é editorial: `NcgAbsoluto` não a consome (decisão
| da STORY-032, ver docblock do indicador). Logo a troca de texto **não**
| altera nada do que o usuário lê hoje. Os testes abaixo cobrem os dois
| lados disso de propósito: o texto novo está na matriz, e o snapshot do
| motor segue intocado. A metade do CA-7 que fala em "diagnóstico novo
| exibe a recomendação revisada" depende de uma decisão de produto que não
| é minha — está escalada em "Notas do agente" da estória.
|
| **Atualizado pela STORY-060.** A escalação acima foi decidida no PDR-006 §2:
| o CA-7 virou `n/a` e o resíduo real — a intenção editorial não chegar ao
| usuário — foi resolvido levando-a para as constantes de faixa de
| `NcgAbsoluto` (ver `Indicadores/NcgAbsolutoTest.php`). A transcrição do
| Anexo F desceu para a seção `_referencia_editorial` da matriz, e os testes
| deste arquivo passaram a apontar para lá. O que eles provam continua
| valendo, com um item a mais: a entrada editorial segue intacta **e** segue
| fora do runtime.
|-------------------------------------------------------------------------
*/

$textoNovo = 'A elevação deve ser acompanhada, na mesma proporção, pelo aumento das fontes de financiamento de longo prazo, preferencialmente de fornecedores.';

it('CA-7 — `ncg_absoluto.positiva` da referência editorial é exatamente o texto revisado', function () use ($textoNovo) {
    /** @var array<string, array<string, array<string, string>>> $matriz */
    $matriz = config('motor.matriz-dez-2025-industria');

    // Igualdade estrita: `toContain` passaria com o texto antigo, do qual o
    // texto novo é literalmente a segunda frase.
    expect($matriz['_referencia_editorial']['ncg_absoluto']['positiva'])->toBe($textoNovo);
});

it('CA-7 (borda) — o cenário `negativa` não foi tocado', function () {
    /** @var array<string, array<string, array<string, string>>> $matriz */
    $matriz = config('motor.matriz-dez-2025-industria');

    expect($matriz['_referencia_editorial']['ncg_absoluto']['negativa'])->toBe(
        'Teoricamente, a empresa não necessita de recursos para o giro. NCG negativa significa Investimento Operacional em Giro (IOG).',
    );
});

it('CA-7 (borda) — a edição não vazou para o `ncg_vendas`, que tem faixas próprias (§F.14)', function () use ($textoNovo) {
    // A estória apontou o alvo como "`ncg_vendas` × farol_positivo". Esse par não
    // existe (ncg_vendas é verde/amarelo/vermelho) e o texto pedido é de §F.13.
    // Trocar o alvo errado é o modo de falha real desta tarefa.
    /** @var array<string, array<string, string>> $matriz */
    $matriz = config('motor.matriz-dez-2025-industria');

    foreach (['verde', 'amarelo', 'vermelho'] as $farol) {
        expect($matriz['ncg_vendas'][$farol])->not->toBe($textoNovo);
    }

    expect($matriz['ncg_vendas']['vermelho'])->toBe(
        'Não é recomendável a elevação das atividades e da NCG, se não acompanhada do aumento do prazo para pagamento a fornecedores.',
    );
});

it('CA-7 (não-regressão) — a mensagem gravada no snapshot não é cópia literal da matriz', function () use ($textoNovo) {
    // Depois da STORY-060 a mensagem de faixa carrega a **intenção** deste texto,
    // não o texto. A asserção de não-igualdade continua provando o que interessa:
    // o indicador não lê a matriz, então editar a matriz não muda o produto.
    // NCG = Clientes + Estoques − Fornecedores = 20.000 + 10.000 − 5.000 > 0.
    $payload = [
        'Q01' => 1, 'Q02' => '50000', 'Q03' => '20000', 'Q04' => '10000',
        'Q05' => '300000', 'Q06' => '40000', 'Q07' => '5000', 'Q08' => '50000',
        'Q09' => '100000', 'Q10' => 90, 'Q11' => 15, 'Q12' => 15, 'Q13' => '2',
        'Q14' => '20000', 'Q15' => '8000', 'Q16' => '2000',
    ];

    $saida = (new Motor)->calcular($payload, 'industria');
    $ncg = $saida['indicadores_calculados']['ncg_absoluto'];

    expect($ncg['valor'])->toBeGreaterThan(0)
        ->and($ncg['mensagem'])->toBeIn([NcgAbsoluto::MSG_MODERADO, NcgAbsoluto::MSG_ALTO])
        ->and($ncg['mensagem'])->not->toContain($textoNovo);
});

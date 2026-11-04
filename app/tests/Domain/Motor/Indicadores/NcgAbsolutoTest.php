<?php

declare(strict_types=1);

use App\Domain\Motor\Calculos\DreAdaptada;
use App\Domain\Motor\Indicadores\NcgAbsoluto;

function calcNcgAbs(array $payload): array
{
    return (new NcgAbsoluto)->calcular($payload, new DreAdaptada($payload))->toArray();
}

function pBaseNa(array $overrides = []): array
{
    return array_merge([
        'Q03' => '80000',
        'Q04' => '60000',
        'Q07' => '30000',
        'Q09' => '100000',
    ], $overrides);
}

// ---------- valor calculado ----------

it('calcula NCG = Q03 + Q04 − Q07 (padrão 80+60−30 = 110k)', function () {
    $r = calcNcgAbs(pBaseNa());
    expect($r['valor'])->toBe(110000.0);
});

it('NCG sempre tem farol nenhum (informativo)', function () {
    $r = calcNcgAbs(pBaseNa());
    expect($r['farol'])->toBe('nenhum');
});

it('motivo é null quando indicador calculável (não é "indisponivel:*")', function () {
    $r = calcNcgAbs(pBaseNa());
    expect($r['motivo'])->toBeNull();
});

// ---------- Faixa 1: NCG ≤ 0 ----------

it('Faixa 1 — NCG negativo (ciclo financiado pelo fornecedor)', function () {
    $r = calcNcgAbs(pBaseNa(['Q03' => '20000', 'Q04' => '10000', 'Q07' => '50000']));
    expect($r['valor'])->toBe(-20000.0)
        ->and($r['mensagem'])->toBe(NcgAbsoluto::MSG_FOLGA);
});

it('Faixa 1 — NCG = 0 exato (fronteira inclusiva)', function () {
    $r = calcNcgAbs(pBaseNa(['Q03' => '20000', 'Q04' => '10000', 'Q07' => '30000']));
    expect($r['valor'])->toBe(0.0)
        ->and($r['mensagem'])->toBe(NcgAbsoluto::MSG_FOLGA);
});

// ---------- Faixa 2: NCG/Vendas até 0,10 ----------

it('Faixa 2 — fronteira superior: NCG = 10% de um mês de vendas', function () {
    $r = calcNcgAbs(pBaseNa(['Q03' => '10000', 'Q04' => '0', 'Q07' => '0']));
    expect($r['valor'])->toBe(10000.0)
        ->and($r['mensagem'])->toContain('moderado');
});

// ---------- Faixa 3: NCG/Vendas acima de 0,10 ----------

it('Faixa 3 — logo acima de 10% de um mês de vendas', function () {
    $r = calcNcgAbs(pBaseNa(['Q03' => '10001', 'Q04' => '0', 'Q07' => '0']));
    expect($r['valor'])->toBeGreaterThan(10000.0)
        ->and($r['mensagem'])->toContain('alto');
});

it('Faixa 3 — caso da Drogaria (NCG/Vendas 0,76) é alto', function () {
    $r = calcNcgAbs(['Q03' => '1500', 'Q04' => '120000', 'Q07' => '30000', 'Q09' => '120000']);
    expect($r['valor'])->toBe(91500.0)
        ->and($r['mensagem'])->toContain('alto');
});

it('segue o limite do farol do NCG/Vendas, lido da config', function () {
    config(['motor.faroes-industria.ncg_vendas.vermelho.valor' => 2.0]);
    $r = calcNcgAbs(pBaseNa());
    expect($r['mensagem'])->toContain('moderado');
});

// ---------- fallback quando Vendas faltante ----------

it('fallback Faixa 2 quando Vendas é null (e NCG > 0)', function () {
    $r = calcNcgAbs(pBaseNa(['Q09' => null]));
    expect($r['valor'])->toBe(110000.0)
        ->and($r['mensagem'])->toContain('moderado');
});

it('fallback Faixa 2 quando Vendas = 0 (e NCG > 0)', function () {
    $r = calcNcgAbs(pBaseNa(['Q09' => '0']));
    expect($r['valor'])->toBe(110000.0)
        ->and($r['mensagem'])->toContain('moderado');
});

it('NCG ≤ 0 com vendas ausente continua na Faixa 1', function () {
    $r = calcNcgAbs(pBaseNa(['Q03' => '0', 'Q04' => '0', 'Q07' => '10000', 'Q09' => null]));
    expect($r['valor'])->toBe(-10000.0)
        ->and($r['mensagem'])->toBe(NcgAbsoluto::MSG_FOLGA);
});

// ---------- STORY-060 CA-1: intenção editorial da EBC nas três faixas ----------

/*
| O texto revisado da EBC (Anexo F §F.13, cenário "NCG positiva", coluna
| Indústria) diz: a elevação da NCG deve ser acompanhada, **na mesma
| proporção**, pelo aumento das **fontes de financiamento de longo prazo**,
| **preferencialmente de fornecedores**. Esses três elementos são a intenção
| editorial — é isso que precisa chegar ao usuário nas faixas MODERADO e ALTO.
|
| Os testes abaixo checam os elementos, não a frase inteira: a redação pode ser
| ajustada pelo PO sem quebrar a suíte, mas remover qualquer um dos três
| elementos quebra — e é exatamente o que esta estória existe para impedir.
*/

it('CA-1 — a faixa MODERADO carrega os três elementos da orientação da EBC', function () {
    expect(NcgAbsoluto::MSG_MODERADO)
        ->toContain('na mesma proporção')
        ->toContain('longo prazo')
        ->toContain('fornecedores');
});

it('CA-1 — a faixa ALTO carrega os três elementos da orientação da EBC', function () {
    expect(NcgAbsoluto::MSG_ALTO)
        ->toContain('na mesma proporção')
        ->toContain('longo prazo')
        ->toContain('fornecedores');
});

it('CA-1 — a faixa 1 nomeia o financiamento do fornecedor e a dependência que ele cria', function () {
    // §F.13/negativa: "Teoricamente, a empresa não necessita de recursos para o
    // giro." Dito em linguagem de microempresário, sem o jargão IOG.
    //
    // **Revisão do PO em 06/08/2026.** A redação anterior abria com "Folga
    // operacional", e a palavra foi lida como "sobra" — que é o oposto do que o
    // número diz. Não há dinheiro disponível: há dívida com fornecedor maior do
    // que o que o ciclo devolve. O texto passou a nomear o mecanismo e o risco.
    expect(NcgAbsoluto::MSG_FOLGA)
        ->toContain('financiado pelos fornecedores')
        ->toContain('dependência')
        ->not->toContain('IOG');
});

it('CA-1 — a faixa 1 não promete folga nem sobra de dinheiro', function () {
    // O indicador **não distingue prazo negociado de atraso com fornecedor**:
    // os dois produzem o mesmo número, e só o primeiro é vantagem. Afirmar a
    // leitura favorável escolhe uma das duas sem saber qual é.
    //
    // A própria EBC é mais cautelosa no mesmo cenário: o texto do Anexo F para
    // `ncg_vendas` verde — exibido no MESMO relatório — diz "pode indicar que a
    // empresa não necessita de recursos para o giro".
    expect(NcgAbsoluto::MSG_FOLGA)
        ->not->toContain('folga')
        ->not->toContain('Folga')
        ->not->toContain('sobra');
});

it('CA-1 (borda) — a faixa 1 vale também no zero exato, e o texto respeita isso', function () {
    // Em `NCG = 0`, o que se tem a receber e em estoque é **igual** ao que se
    // deve — não menor. "não supera" cobre os dois; "é menor que" seria falso
    // na fronteira, que é caso de teste desde a STORY-028.
    expect(NcgAbsoluto::MSG_FOLGA)
        ->toContain('não supera')
        ->not->toContain('é menor do que o que deve');
});

it('CA-2 — continuam sendo três faixas distintas (a STORY-032 não é revertida)', function () {
    $mensagens = [NcgAbsoluto::MSG_FOLGA, NcgAbsoluto::MSG_MODERADO, NcgAbsoluto::MSG_ALTO];

    expect(array_unique($mensagens))->toHaveCount(3);
    foreach ($mensagens as $mensagem) {
        expect($mensagem)->toBeString()->not->toBe('');
    }
});

it('CA-1 (borda) — a orientação de captação de longo prazo NÃO aparece na faixa 1', function () {
    // Quem tem NCG negativo não precisa financiar giro nenhum. Repetir a
    // orientação ali seria conselho errado para o cenário certo.
    expect(NcgAbsoluto::MSG_FOLGA)->not->toContain('na mesma proporção');
});

it('CA-1 — as três faixas continuam saindo pelo caminho de cálculo, não só pelas constantes', function () {
    $folga = calcNcgAbs(pBaseNa(['Q03' => '20000', 'Q04' => '10000', 'Q07' => '50000']));
    $moderado = calcNcgAbs(pBaseNa(['Q03' => '10000', 'Q04' => '5000', 'Q07' => '8000']));
    $alto = calcNcgAbs(pBaseNa(['Q03' => '120000', 'Q04' => '50000', 'Q07' => '26000']));

    expect($folga['mensagem'])->toBe(NcgAbsoluto::MSG_FOLGA)
        ->and($moderado['mensagem'])->toBe(NcgAbsoluto::MSG_MODERADO)
        ->and($alto['mensagem'])->toBe(NcgAbsoluto::MSG_ALTO);
});

// ---------- casos extremos (catálogo §9) ----------

it('indisponível — Q03 ausente', function () {
    $r = calcNcgAbs(pBaseNa(['Q03' => null]));
    expect($r['valor'])->toBeNull()
        ->and($r['farol'])->toBe('nenhum')
        ->and($r['motivo'])->toBe('indisponivel:ncg_componente_faltante');
});

it('indisponível — Q04 ausente', function () {
    $r = calcNcgAbs(pBaseNa(['Q04' => null]));
    expect($r['valor'])->toBeNull()
        ->and($r['motivo'])->toBe('indisponivel:ncg_componente_faltante');
});

it('indisponível — Q07 ausente', function () {
    $r = calcNcgAbs(pBaseNa(['Q07' => null]));
    expect($r['valor'])->toBeNull()
        ->and($r['motivo'])->toBe('indisponivel:ncg_componente_faltante');
});

it('indisponível — todos os componentes ausentes', function () {
    $r = calcNcgAbs(['Q09' => '100000']);
    expect($r['valor'])->toBeNull()
        ->and($r['motivo'])->toBe('indisponivel:ncg_componente_faltante');
});

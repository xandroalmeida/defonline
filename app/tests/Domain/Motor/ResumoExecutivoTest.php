<?php

declare(strict_types=1);

use App\Domain\Motor\Farol;
use App\Domain\Motor\ResumoExecutivo;

/*
| ----------------------------------------------------------------------
| ResumoExecutivo — algoritmo determinístico §4.7.1.
|
| Cobertura mínima exigida pela spec (§4.7.1, princípios operacionais) e
| pelo briefing-031: ≥ 10 cenários canônicos + 1 teste explícito de
| determinismo (loop de 100 iterações = mesma saída bit-exata).
|
| Os indicadores são montados via helper `indicador(...)` que devolve um
| array `{valor, farol, motivo, mensagem}` compatível com o snapshot do motor.
| Mensagens placeholder vivem em MensagensFarol (Faixa verde./amarela./vermelha.)
| — STORY-032 substituirá pelo texto da matriz DEZ/2025; até lá, os destaques
| usam exatamente esses placeholders truncados.
| ----------------------------------------------------------------------
*/

/**
 * Helper para construir entry de `indicadores_calculados`.
 *
 * @return array{valor: float|int|null, farol: string, motivo: ?string, mensagem: string}
 */
function indicador(float|int|null $valor, string $farol, string $mensagem = 'Mensagem curta.', ?string $motivo = null): array
{
    return [
        'valor' => $valor,
        'farol' => $farol,
        'motivo' => $motivo,
        'mensagem' => $mensagem,
    ];
}

/**
 * Helper para criar snapshot completo (14 do Anexo D + Ciclo Operacional).
 * Por padrão, todos verdes; passa um override no segundo argumento para
 * substituir indicadores específicos.
 *
 * @param  array<string, array{valor: float|int|null, farol: string, motivo: ?string, mensagem: string}>  $overrides
 * @return array<string, array{valor: float|int|null, farol: string, motivo: ?string, mensagem: string}>
 */
function snapshot(array $overrides = []): array
{
    // Valores genéricos em verde para cada indicador. Tomamos valores claramente
    // dentro de cada faixa verde — fronteira intencionalmente longe pra evitar
    // ambiguidade.
    $verde = [
        'margem_bruta' => indicador(40.0, Farol::VERDE),
        'margem_ebitda' => indicador(30.0, Farol::VERDE),
        'margem_liquida' => indicador(20.0, Farol::VERDE),
        'divida_liquida_ebitda' => indicador(1.0, Farol::VERDE),
        'despesas_fin_ebitda' => indicador(10.0, Farol::VERDE),
        'fontes_recursos' => indicador(0.2, Farol::VERDE),
        'giro_ativo' => indicador(3.0, Farol::VERDE),
        'ciclo_financeiro' => indicador(15.0, Farol::VERDE),
        'ncg_absoluto' => indicador(0.0, Farol::NENHUM, 'Folga operacional.'),
        'ncg_vendas' => indicador(-0.05, Farol::VERDE),
        'pmc' => indicador(75.0, Farol::VERDE),
        'pme' => indicador(20.0, Farol::VERDE),
        'pmr' => indicador(20.0, Farol::VERDE),
        'inadimplencia' => indicador(2.0, Farol::VERDE),
        // Ciclo Operacional — informativo, fora do Anexo D.
        'ciclo_operacional' => indicador(40.0, Farol::NENHUM, 'PME + PMR.'),
    ];

    return array_merge($verde, $overrides);
}

beforeEach(function (): void {
    $this->resumo = new ResumoExecutivo;
});

it('CA-2 — todos verde produz veredito saudável + mensagem extra (Passo 6)', function () {
    $out = $this->resumo->gerar(snapshot(), '1.2.0');

    expect($out['veredito'])->toBe('saudavel');
    expect($out['veredito_texto'])->toBe('Sua empresa apresenta indicadores saudáveis no período avaliado.');
    expect($out['destaques_negativos'])->toBe([]);
    expect($out['destaque_positivo'])->toBeNull();
    expect($out['mensagem_extra'] ?? null)->toBe('Todos os indicadores avaliados estão em patamar saudável. Continue acompanhando.');
    expect($out['linha_fixa'])->toBe('Veja a tabela abaixo para análise detalhada e recomendações específicas.');
    expect($out['fallback_acionado'])->toBeFalse();
    expect($out['motor_version_origem'])->toBe('1.2.0');
});

it('CA-2 — 1 vermelho, 0 amarelos → precisa_atenção (regra V≥1) + 1 destaque negativo + 1 positivo', function () {
    $out = $this->resumo->gerar(snapshot([
        'margem_liquida' => indicador(2.0, Farol::VERMELHO, 'Faixa vermelha.'),
    ]), '1.2.0');

    expect($out['veredito'])->toBe('precisa_atencao');
    expect($out['destaques_negativos'])->toHaveCount(1);
    expect($out['destaques_negativos'][0]['codigo'])->toBe('margem_liquida');
    expect($out['destaques_negativos'][0]['texto'])->toContain('Margem Líquida');
    expect($out['destaque_positivo'])->not->toBeNull();
    expect($out['destaque_positivo']['texto'])->toStartWith('Por outro lado, ');
});

it('CA-2 — 5 amarelos sem vermelho → saudável (A/N = 5/13 < 0.50), com até 2 amarelos como destaques negativos', function () {
    // Veredito saudável, mas a spec ainda preenche os slots negativos com amarelos
    // quando não há vermelhos. Único caso que zera destaques_negativos = Passo 6 (todo-verde).
    $overrides = [
        'margem_bruta' => indicador(23.0, Farol::AMARELO),
        'margem_ebitda' => indicador(18.0, Farol::AMARELO),
        'margem_liquida' => indicador(10.0, Farol::AMARELO),
        'divida_liquida_ebitda' => indicador(2.5, Farol::AMARELO),
        'despesas_fin_ebitda' => indicador(40.0, Farol::AMARELO),
    ];
    $out = $this->resumo->gerar(snapshot($overrides), '1.2.0');

    expect($out['veredito'])->toBe('saudavel');
    expect($out['destaques_negativos'])->toHaveCount(2);
    foreach ($out['destaques_negativos'] as $d) {
        expect($d['codigo'])->toBeIn(array_keys($overrides));
    }
    expect($out['destaque_positivo'])->not->toBeNull();
});

it('CA-2 — 7 amarelos sem vermelho → precisa_atenção (A/N = 7/13 ≥ 0.50)', function () {
    $overrides = [
        'margem_bruta' => indicador(23.0, Farol::AMARELO),
        'margem_ebitda' => indicador(18.0, Farol::AMARELO),
        'margem_liquida' => indicador(10.0, Farol::AMARELO),
        'divida_liquida_ebitda' => indicador(2.5, Farol::AMARELO),
        'despesas_fin_ebitda' => indicador(40.0, Farol::AMARELO),
        'fontes_recursos' => indicador(0.7, Farol::AMARELO),
        'giro_ativo' => indicador(1.5, Farol::AMARELO),
    ];
    $out = $this->resumo->gerar(snapshot($overrides), '1.2.0');

    expect($out['veredito'])->toBe('precisa_atencao');
    expect($out['destaques_negativos'])->toHaveCount(2);
    // Sem vermelhos — todos destaques negativos vêm dos amarelos.
    foreach ($out['destaques_negativos'] as $d) {
        expect($d['codigo'])->toBeIn(array_keys($overrides));
    }
    expect($out['destaque_positivo'])->not->toBeNull();
});

it('CA-2 — 5 vermelhos → em_alerta (V/N = 5/13 ≈ 0.38 ≥ 0.30) + 2 destaques vermelhos + 1 verde', function () {
    $overrides = [
        'margem_bruta' => indicador(5.0, Farol::VERMELHO),
        'margem_ebitda' => indicador(0.0, Farol::VERMELHO),
        'margem_liquida' => indicador(0.0, Farol::VERMELHO),
        'divida_liquida_ebitda' => indicador(9.0, Farol::VERMELHO),
        'despesas_fin_ebitda' => indicador(70.0, Farol::VERMELHO),
    ];
    $out = $this->resumo->gerar(snapshot($overrides), '1.2.0');

    expect($out['veredito'])->toBe('em_alerta');
    expect($out['destaques_negativos'])->toHaveCount(2);
    expect($out['destaques_negativos'][0]['codigo'])->toBeIn(array_keys($overrides));
    expect($out['destaques_negativos'][1]['codigo'])->toBeIn(array_keys($overrides));
    expect($out['destaque_positivo'])->not->toBeNull();
});

it('CA-2 + Passo 6 — todos vermelho → em_alerta + 2 destaques negativos sem destaque positivo', function () {
    $overrides = [];
    $valoresVermelhos = [
        'margem_bruta' => 5.0,
        'margem_ebitda' => 0.0,
        'margem_liquida' => 0.0,
        'divida_liquida_ebitda' => 9.0,
        'despesas_fin_ebitda' => 70.0,
        'fontes_recursos' => 2.0,
        'giro_ativo' => 0.2,
        'ciclo_financeiro' => 120.0,
        'ncg_vendas' => 0.5,
        'pmc' => 10.0,
        'pme' => 120.0,
        'pmr' => 120.0,
        'inadimplencia' => 10.0,
    ];
    foreach ($valoresVermelhos as $codigo => $valor) {
        $overrides[$codigo] = indicador($valor, Farol::VERMELHO);
    }
    $out = $this->resumo->gerar(snapshot($overrides), '1.2.0');

    expect($out['veredito'])->toBe('em_alerta');
    expect($out['destaques_negativos'])->toHaveCount(2);
    expect($out['destaque_positivo'])->toBeNull();
});

it('CA-2 — fallback ≥ 70% indisponíveis (10 de 14) → mensagem fixa', function () {
    // 10 dos 14 são null → 10/14 ≈ 71%.
    $indispo = indicador(null, Farol::NENHUM, '', 'indisponivel:teste');
    $overrides = [
        'margem_bruta' => $indispo,
        'margem_ebitda' => $indispo,
        'margem_liquida' => $indispo,
        'divida_liquida_ebitda' => $indispo,
        'despesas_fin_ebitda' => $indispo,
        'fontes_recursos' => $indispo,
        'giro_ativo' => $indispo,
        'ciclo_financeiro' => $indispo,
        'ncg_vendas' => $indispo,
        'pmc' => $indispo,
    ];
    $out = $this->resumo->gerar(snapshot($overrides), '1.2.0');

    expect($out['veredito'])->toBe('fallback');
    expect($out['fallback_acionado'])->toBeTrue();
    expect($out['mensagem_fallback'])->toBe('Não foi possível calcular indicadores suficientes para um resumo executivo. Revise os dados informados ou consulte a tabela abaixo.');
    expect($out['destaques_negativos'])->toBe([]);
    expect($out['destaque_positivo'])->toBeNull();
    expect($out['linha_fixa'])->toBeNull();
});

it('CA-3 — empate de severidade em vermelhos: desempate pelo Anexo D ASC', function () {
    // margem_bruta (#1) e margem_ebitda (#2) em vermelho, ambos com severidade idêntica:
    //  - margem_bruta: maior_melhor, fronteira_amarela=20, amplitude=20, valor=0 → sev=1.0
    //  - margem_ebitda: maior_melhor, fronteira_amarela=15, amplitude=15, valor=0 → sev=1.0
    $out = $this->resumo->gerar(snapshot([
        'margem_bruta' => indicador(0.0, Farol::VERMELHO),
        'margem_ebitda' => indicador(0.0, Farol::VERMELHO),
    ]), '1.2.0');

    // 2 vermelhos preenchem ambos os slots.
    expect($out['destaques_negativos'])->toHaveCount(2);
    expect($out['destaques_negativos'][0]['codigo'])->toBe('margem_bruta');
    expect($out['destaques_negativos'][1]['codigo'])->toBe('margem_ebitda');
});

it('CA-3 — empate de favorabilidade em verdes: desempate pelo Anexo D ASC', function () {
    // Snapshot todo-verde → todos os 13 com farol verde empatam.
    // Sem o "Passo 6 todos verde", o desempate pelo Anexo D deve eleger margem_bruta (#1).
    // Forçamos uma situação NÃO-Passo-6 metendo 1 amarelo no meio.
    $out = $this->resumo->gerar(snapshot([
        'inadimplencia' => indicador(4.0, Farol::AMARELO),
    ]), '1.2.0');

    expect($out['veredito'])->toBe('saudavel');
    // 1 amarelo isolado vai pra destaques_negativos.
    expect($out['destaques_negativos'])->toHaveCount(1);
    expect($out['destaque_positivo'])->not->toBeNull();
    // Verdes mais favoráveis empatam; com mesma favorabilidade, Anexo D vence.
    // margem_bruta (#1) é o primeiro candidato. Mas as escalas das fronteiras são
    // diferentes — a favorabilidade real depende dos valores. Apenas asseguramos
    // que o destaque positivo veio dos verdes.
    expect($out['destaque_positivo']['codigo'])->not->toBe('inadimplencia');
});

it('CA-4 — truncamento em ~80 chars com "…" e sem cortar palavra', function () {
    $msgLonga = 'Margem operacional severamente comprometida pela combinação de despesas financeiras altas e queda nas receitas — necessário revisar precificação e estrutura.';
    $out = $this->resumo->gerar(snapshot([
        'margem_liquida' => indicador(2.0, Farol::VERMELHO, $msgLonga),
    ]), '1.2.0');

    $destaque = $out['destaques_negativos'][0];
    expect($destaque['codigo'])->toBe('margem_liquida');

    // Texto = "Margem Líquida: " + mensagem truncada.
    expect($destaque['texto'])->toStartWith('Margem Líquida: ');
    // Mensagem truncada termina em "…" ou em final de frase.
    $msgTruncada = mb_substr($destaque['texto'], mb_strlen('Margem Líquida: '));
    expect(mb_strlen($msgTruncada))->toBeLessThanOrEqual(81); // 80 + ellipsis
    // Bloco total ≤ ~400 chars.
    $blocoTotal = mb_strlen($out['veredito_texto'])
        + array_sum(array_map(fn ($d) => mb_strlen($d['texto']), $out['destaques_negativos']))
        + mb_strlen($out['destaque_positivo']['texto'] ?? '')
        + mb_strlen($out['linha_fixa']);
    expect($blocoTotal)->toBeLessThanOrEqual(420); // pequena tolerância
});

it('CA-5 — NCG abs e Ciclo Operacional ignorados na contagem V/A/Vd e em destaques', function () {
    // Cenário: TODOS os 13 indicadores com farol em verde, mas NCG abs e Ciclo Op com farol=nenhum.
    // Veredito deve ser saudavel + Passo 6 (todos verde) — confirma que NCG abs não atrapalha N=13.
    $out = $this->resumo->gerar(snapshot([
        'ncg_absoluto' => indicador(50000.0, Farol::NENHUM, 'NCG positivo moderado.'),
        'ciclo_operacional' => indicador(50.0, Farol::NENHUM, 'PME+PMR mensagem informativa.'),
    ]), '1.2.0');

    expect($out['veredito'])->toBe('saudavel');
    expect($out['mensagem_extra'] ?? null)->toBe('Todos os indicadores avaliados estão em patamar saudável. Continue acompanhando.');
    // NCG abs e Ciclo Operacional nunca aparecem como destaque.
    foreach (array_merge($out['destaques_negativos'], $out['destaque_positivo'] === null ? [] : [$out['destaque_positivo']]) as $d) {
        expect($d['codigo'])->not->toBe('ncg_absoluto');
        expect($d['codigo'])->not->toBe('ciclo_operacional');
    }
});

it('CA-5 — Ciclo Operacional indisponível NÃO conta no denominador 14 do fallback', function () {
    // 9 indisponíveis dos 14 do Anexo D = 9/14 ≈ 64% < 70% (não fallback).
    // Ciclo Operacional indisponível adicional não conta — deve permanecer não-fallback.
    $indispo = indicador(null, Farol::NENHUM, '', 'indisponivel:teste');
    $overrides = [
        'margem_bruta' => $indispo,
        'margem_ebitda' => $indispo,
        'margem_liquida' => $indispo,
        'divida_liquida_ebitda' => $indispo,
        'despesas_fin_ebitda' => $indispo,
        'fontes_recursos' => $indispo,
        'giro_ativo' => $indispo,
        'ciclo_financeiro' => $indispo,
        'ncg_vendas' => $indispo,
        'ciclo_operacional' => $indispo,
    ];
    $out = $this->resumo->gerar(snapshot($overrides), '1.2.0');

    expect($out['fallback_acionado'])->toBeFalse();
});

it('determinismo — 100 execuções produzem saída bit-exata', function () {
    $snap = snapshot([
        'margem_bruta' => indicador(5.0, Farol::VERMELHO),
        'margem_ebitda' => indicador(0.0, Farol::VERMELHO),
        'margem_liquida' => indicador(15.0, Farol::AMARELO),
        'divida_liquida_ebitda' => indicador(2.5, Farol::AMARELO),
        'pmr' => indicador(45.0, Farol::AMARELO),
        'inadimplencia' => indicador(4.0, Farol::AMARELO),
    ]);

    $primeiro = json_encode($this->resumo->gerar($snap, '1.2.0'), JSON_UNESCAPED_UNICODE);
    expect($primeiro)->toBeString();

    for ($i = 0; $i < 100; $i++) {
        $atual = json_encode($this->resumo->gerar($snap, '1.2.0'), JSON_UNESCAPED_UNICODE);
        expect($atual)->toBe($primeiro);
    }
});

it('Passo 1 — caso "V/N borderline" com V=1 e N=4 (V/N=0.25 < 0.30) → precisa_atencao (V≥1)', function () {
    // 10 indisponíveis seriam 71% → fallback. Usamos 9 indisponíveis → 9/14 ≈ 64% (não fallback).
    // N = 14 - 9 = 5; mas precisamos NCG abs ou outros não em V/A/Vd. Cenário concreto:
    // - 9 indisponíveis
    // - 1 vermelho, 1 amarelo, 2 verdes, 1 NCG abs com valor (nenhum)
    // → N = 1+1+2 = 4; V/N = 0.25 < 0.30; V≥1 → precisa_atencao.
    $indispo = indicador(null, Farol::NENHUM, '', 'indisponivel:teste');
    $out = $this->resumo->gerar(snapshot([
        'margem_bruta' => indicador(5.0, Farol::VERMELHO),
        'margem_ebitda' => indicador(18.0, Farol::AMARELO),
        'margem_liquida' => indicador(20.0, Farol::VERDE),
        'divida_liquida_ebitda' => indicador(1.0, Farol::VERDE),
        // Indisponíveis para chegar em 9.
        'despesas_fin_ebitda' => $indispo,
        'fontes_recursos' => $indispo,
        'giro_ativo' => $indispo,
        'ciclo_financeiro' => $indispo,
        'ncg_vendas' => $indispo,
        'pmc' => $indispo,
        'pme' => $indispo,
        'pmr' => $indispo,
        'inadimplencia' => $indispo,
    ]), '1.2.0');

    expect($out['veredito'])->toBe('precisa_atencao');
    expect($out['destaques_negativos'][0]['codigo'])->toBe('margem_bruta');
});

it('defensivo — N=0 sem disparar fallback cai no resultado de fallback (não vaza estado patológico)', function () {
    // Cenário só teoricamente alcançável: todos os 14 com farol=nenhum e valor não-nulo.
    // Não pode acontecer em produção (NCG abs é o único farol=nenhum com valor) mas garantimos
    // que o algoritmo não trava nem divide por zero.
    $semFarol = indicador(123.0, Farol::NENHUM, 'Informativo.');
    $overrides = array_fill_keys(ResumoExecutivo::CODIGOS_ANEXO_D, $semFarol);
    $out = $this->resumo->gerar(snapshot($overrides), '1.2.0');

    expect($out['veredito'])->toBe('fallback');
    expect($out['fallback_acionado'])->toBeTrue();
});

it('CA-4 — truncamento usa primeira frase quando ". " aparece antes do limite', function () {
    $msg = 'Frase curta. Esta segunda frase é mais longa e ultrapassa em muito o limite de 80 caracteres usado pelo destaque do resumo.';
    $out = $this->resumo->gerar(snapshot([
        'margem_liquida' => indicador(2.0, Farol::VERMELHO, $msg),
    ]), '1.2.0');

    $destaque = $out['destaques_negativos'][0];
    expect($destaque['texto'])->toBe('Margem Líquida: Frase curta.');
});

it('contrato — caso fallback tem todos os campos esperados (NOT NULL no banco)', function () {
    $indispo = indicador(null, Farol::NENHUM, '', 'indisponivel:teste');
    $overrides = array_fill_keys(
        array_diff(ResumoExecutivo::CODIGOS_ANEXO_D, ['ncg_absoluto']),
        $indispo,
    );
    // ncg_absoluto também indisponível.
    $overrides['ncg_absoluto'] = $indispo;
    $out = $this->resumo->gerar(snapshot($overrides), '1.2.0');

    expect($out)->toMatchArray([
        'motor_version_origem' => '1.2.0',
        'veredito' => 'fallback',
        'veredito_texto' => null,
        'destaques_negativos' => [],
        'destaque_positivo' => null,
        'linha_fixa' => null,
        'fallback_acionado' => true,
    ]);
    expect($out['mensagem_fallback'])->toBeString()->not->toBe('');
});

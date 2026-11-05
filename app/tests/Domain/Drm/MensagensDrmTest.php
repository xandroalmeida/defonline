<?php

declare(strict_types=1);

use App\Domain\Drm\ConteudoDrm;
use App\Domain\Drm\Ocorrencia;
use App\Support\Drm\MensagensDrm;

it('preenche os :placeholders do pacote de conteúdo com o contexto da ocorrência', function () {
    $m = (new MensagensDrm(new ConteudoDrm))->de(
        new Ocorrencia('B4', 'D02', ['valor_impostos' => 'R$ 10,00', 'valor_receita' => 'R$ 5,00']),
    );

    expect($m['titulo'])->toBe('Os impostos ficaram maiores que a venda')
        ->and($m['texto'])->toContain('R$ 10,00')->toContain('R$ 5,00')->not->toContain(':valor');
});

it('troca :campo pelo rótulo e aceita extras da tela', function () {
    $m = (new MensagensDrm(new ConteudoDrm))->de(new Ocorrencia('B3', 'D10'));
    expect($m['titulo'])->toBe('Valor negativo em Taxas de administração de cartões');

    $a = (new MensagensDrm(new ConteudoDrm))->de(new Ocorrencia('A1', 'D01', ['competencia' => 'outubro/2026']), ['empresa' => 'X']);
    expect($a['texto'])->toContain('outubro/2026')->and($a['consequencia'])->not->toBeNull();
});

it('código sem texto no pacote cai no próprio código, sem quebrar', function () {
    $m = (new MensagensDrm(new ConteudoDrm))->de(new Ocorrencia('A9'));

    expect($m)->toBe(['titulo' => 'A9', 'texto' => '', 'consequencia' => null]);
});

it('html promove só negrito e escapa o resto', function () {
    expect((string) MensagensDrm::html('a **b** <i>c</i>'))->toBe('a <strong>b</strong> &lt;i&gt;c&lt;/i&gt;');
});

it('rotuloGrupo devolve o rótulo validado dos grupos sem variação por setor', function () {
    $c = new ConteudoDrm;

    expect($c->rotuloGrupo('vendas'))->toBe('Despesas com Vendas')
        ->and($c->rotuloGrupo('administrativas'))->toBe('Despesas Administrativas e Gerais')
        ->and($c->rotuloGrupo('inexistente'))->toBeNull();
});

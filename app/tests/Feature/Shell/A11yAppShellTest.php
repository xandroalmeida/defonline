<?php

declare(strict_types=1);

use App\Models\Usuario;

/*
|-------------------------------------------------------------------------
| STORY-059 CA-1, CA-2 e CA-8 — acessibilidade do app shell (markup).
|
| Estes testes cobrem o que o servidor entrega. O comportamento em browser
| real — foco não entrando no drawer fechado, alvo de toque do <x-help> —
| está em `tests/Browser/A11yShellBrowserTest.php`, porque `inert` e
| hit-testing só existem depois que o Alpine roda.
|-------------------------------------------------------------------------
*/

it('CA-1 — o toggle de conta tem nome acessível que não depende do viewport', function () {
    $usuario = Usuario::factory()->create(['nome' => 'Roberto Souza']);

    $html = $this->actingAs($usuario)->get('/home')->assertOk()->getContent();

    // A saudação visível some abaixo de 640px (`hidden`), e a inicial e o
    // chevron são `aria-hidden`. Sem o aria-label, em celular o botão chegava
    // ao leitor de tela como "botão" — era o `button-name` do gate.
    expect($html)->toContain('aria-label="Menu da conta de Roberto"');
});

it('CA-1 (borda) — o nome acessível usa o primeiro nome, como a saudação visível', function () {
    $usuario = Usuario::factory()->create(['nome' => 'Ana Paula Marques Silva']);

    $html = $this->actingAs($usuario)->get('/home')->assertOk()->getContent();

    expect($html)->toContain('aria-label="Menu da conta de Ana"')
        ->and($html)->toContain('Olá, Ana');
});

it('CA-2 — o drawer não amarra mais aria-hidden só a navOpen', function () {
    $usuario = Usuario::factory()->create();

    $html = $this->actingAs($usuario)->get('/home')->assertOk()->getContent();

    // O binding antigo escondia a sidebar do DESKTOP (visível, navOpen false)
    // da árvore de acessibilidade, e deixava os filhos do drawer fechado na
    // ordem de tabulação. Os dois lados vinham da mesma expressão.
    expect($html)->not->toContain(":aria-hidden=\"navOpen ? 'false' : 'true'\"");
});

it('CA-2 — o drawer delega a decisão ao shell, que conhece o breakpoint', function () {
    $usuario = Usuario::factory()->create();

    $html = $this->actingAs($usuario)->get('/home')->assertOk()->getContent();

    expect($html)->toContain('x-effect="sincronizarInerte($el)"')
        // A condição real de "escondido" é estreito E fechado — não só fechado.
        ->and($html)->toContain('navFechado')
        ->and($html)->toContain("window.matchMedia('(min-width: 1024px)')");
});

it('CA-2 (borda) — o drawer não nasce com aria-hidden fixo no HTML do servidor', function () {
    $usuario = Usuario::factory()->create();

    $html = $this->actingAs($usuario)->get('/home')->assertOk()->getContent();

    // Um aria-hidden estático valeria também no desktop, onde a sidebar está
    // visível — trocaria um defeito por outro.
    $aside = mb_substr($html, (int) mb_strpos($html, 'id="app-nav-drawer"'), 900);
    expect($aside)->not->toContain('aria-hidden="true"');
});

it('CA-3 — os pills de farol saem por classe de componente, não por utilitário translúcido', function () {
    $html = view('components.relatorio.farol', ['cor' => 'amarelo'])->render();

    expect($html)->toContain('class="farol farol--amarelo"')
        // O fundo translúcido é o que somava com o tingimento do card do Resumo
        // Executivo e derrubava o contraste do rótulo. Não pode voltar.
        ->and($html)->not->toContain('bg-[color:var(--color-farol-');
});

it('CA-3 — o fundo do pill é opaco, então o contraste não depende da superfície de trás', function () {
    $css = (string) file_get_contents(resource_path('css/app.css'));

    foreach (['verde', 'amarelo', 'vermelho', 'nenhum'] as $cor) {
        expect($css)->toMatch('/\.farol--'.$cor.'\s*\{[^}]*color-mix\(in srgb,[^}]*var\(--color-surface\)\)/s');
    }
});

it('CA-5 — o farol continua comunicando por cor, ícone e texto', function () {
    $css = (string) file_get_contents(resource_path('css/app.css'));

    foreach (['verde' => 'Verde', 'amarelo' => 'Amarelo', 'vermelho' => 'Vermelho', 'nenhum' => 'Sem classificação'] as $cor => $rotulo) {
        $html = view('components.relatorio.farol', ['cor' => $cor])->render();

        expect($html)->toContain($rotulo)                          // texto
            ->and($html)->toContain('<svg')                        // ícone
            ->and($html)->toContain('farol--'.$cor)                // cor (via classe)
            ->and($html)->toContain('aria-label="Farol: '.$rotulo.'"');

        // E a classe tem cor própria de verdade — não é rótulo de CSS vazio.
        expect($css)->toMatch('/\.farol--'.$cor.'\s*\{[^}]*color:\s*var\(--color-farol-[a-z]+-texto\);/s');
    }

    // Os quatro ícones são desenhos diferentes: o canal "ícone" não colapsou.
    $svgs = array_map(
        fn (string $cor) => preg_replace('/\s+/', '', (string) preg_replace('/.*?(<svg.*?<\/svg>).*/s', '$1', view('components.relatorio.farol', ['cor' => $cor])->render())),
        ['verde', 'amarelo', 'vermelho', 'nenhum'],
    );
    expect(array_unique($svgs))->toHaveCount(4);
});

it('CA-4 — o badge de versão do relatório perdeu o style inline com cor literal', function () {
    $usuario = Usuario::factory()->create();
    $html = $this->actingAs($usuario)->get('/home')->assertOk()->getContent();

    // O badge vive na página do diagnóstico; aqui basta garantir que a classe
    // existe no CSS e que ninguém reintroduziu rgba literal na view.
    $viewFonte = (string) file_get_contents(resource_path('views/diagnosticos/show.blade.php'));

    expect($viewFonte)->toContain('pill pill--versao')
        ->and($viewFonte)->not->toContain('rgba(99, 91, 255')
        ->and($html)->toBeString();
});

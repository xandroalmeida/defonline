<?php

declare(strict_types=1);

use App\Domain\Drm\ConteudoDrm;

/*
|-------------------------------------------------------------------------
| STORY-052 CA-4, CA-6, CA-8, CA-10 e CA-11 — o mecanismo de sobreposição.
|
| O que estes testes protegem não é o texto (esse muda quando a EBC responder)
| e sim o **mecanismo**: validado ganha do provisório, promover é mover a
| linha, e ausência de texto é uma instrução — não um buraco a preencher com
| genérico.
|-------------------------------------------------------------------------
*/

beforeEach(function () {
    // Desliga o I/O do inventário: o arquivo de lacunas é doc do PO, não
    // efeito colateral de suíte.
    config()->set('drm.lacunas_path', '');
});

it('CA-10 — a chave validada ganha da provisória', function () {
    config()->set('drm.help.campos.D07', 'Texto validado pela EBC.');
    config()->set('drm.provisorio.help.campos.D07', 'Texto provisório.');

    expect((new ConteudoDrm)->help('D07'))->toBe('Texto validado pela EBC.');
});

it('CA-10 — sem entrada validada, cai no provisório', function () {
    config()->set('drm.help.campos', []);

    expect((new ConteudoDrm)->help('D07'))->toContain('receber');
});

it('CA-10 — promover uma entrada é mover a linha, sem tocar em código', function () {
    $conteudo = new ConteudoDrm;

    // Estado de hoje: servido do provisório.
    expect($conteudo->help('D10'))->toBe(config('drm.provisorio.help.campos.D10'));

    // A EBC validou: a linha muda de arquivo. Nada mais acontece.
    config()->set('drm.help.campos.D10', 'Texto da EBC para taxa de cartão.');
    config()->set('drm.provisorio.help.campos.D10', null);

    expect($conteudo->help('D10'))->toBe('Texto da EBC para taxa de cartão.');
});

it('CA-4 — campo sem tooltip devolve null, para o <x-help> não renderizar', function () {
    // `null` aqui é instrução, não falha: nem ícone, nem balão vazio.
    expect((new ConteudoDrm)->help('D99'))->toBeNull();
});

it('CA-4 — os cinco pares de confusão do K.10 nomeiam o outro lado do par', function () {
    $conteudo = new ConteudoDrm;

    // Um texto que só explica o próprio campo não desfaz confusão entre dois
    // campos. Estes cinco precisam apontar para onde vai o outro lado.
    expect($conteudo->help('D07'))->toContain('entrega')            // frete aquisição × entrega
        ->and($conteudo->help('D09'))->toContain('aquisição')
        ->and($conteudo->help('D10'))->toContain('Juros')           // taxa cartão × juros antecipação
        ->and($conteudo->help('D38'))->toContain('maquininha')
        ->and($conteudo->help('D16'))->toContain('Pró-labore')      // salários × retirada
        ->and($conteudo->help('D40'))->toContain('salário')
        ->and($conteudo->help('D02'))->toContain('DAS')             // guia única do Simples
        ->and($conteudo->help('D20'))->toContain('mês em que o dinheiro saiu')
        ->and($conteudo->help('D21'))->toContain('13º');
});

it('CA-4 — todos os 43 campos do Anexo K têm tooltip', function () {
    $conteudo = new ConteudoDrm;
    $campos = array_merge(['D00'], array_map(fn (int $n) => sprintf('D%02d', $n), range(1, 42)));

    $sem = array_values(array_filter($campos, fn (string $c) => $conteudo->help($c) === null));

    expect($sem)->toBe([], 'Campos sem tooltip: '.implode(', ', $sem));
});

it('CA-7 — os 4 bloqueios e os 6 alertas do K.6 existem desde o dia um', function () {
    $conteudo = new ConteudoDrm;

    foreach (['B1', 'B2', 'B3', 'B4'] as $codigo) {
        $b = $conteudo->bloqueio($codigo);
        expect($b)->toBeArray()
            // Barrar alguém sem apontar a saída é hostil.
            ->and($b['titulo'])->toBeString()->not->toBe('')
            ->and($b['texto'])->toBeString()->not->toBe('')
            ->and($b['acao_primaria'])->toBeString()->not->toBe('');
    }

    foreach (['A1', 'A2', 'A3', 'A4', 'A5', 'A6'] as $codigo) {
        $a = $conteudo->alerta($codigo);
        expect($a)->toBeArray()
            ->and($a['titulo'])->toBeString()->not->toBe('')
            ->and($a['texto'])->toBeString()->not->toBe('')
            // Alerta deixa passar: precisa das duas saídas.
            ->and($a['acao_primaria'])->toBeString()->not->toBe('')
            ->and($a['acao_secundaria'])->toBeString()->not->toBe('');
    }
});

it('CA-7 — o alerta de prejuízo informa sem culpar', function () {
    $a = (new ConteudoDrm)->alerta('A3');

    // A3 é o mais delicado dos seis: o usuário acabou de digitar 38 valores.
    expect($a['texto'])->toContain('Não é erro')
        ->and($a['texto'])->not->toContain('atenção!')
        ->and($a['consequencia'])->toContain('onde o dinheiro foi');
});

it('CA-6 — não existe texto de leitura, e não existe provisório de leitura', function () {
    // Onde o farol cala, a leitura cala. Ausência é a decisão (PDR-009 §1).
    expect((new ConteudoDrm)->leitura('L5', 'verde'))->toBeNull()
        ->and(config('drm.leitura.por_linha_e_farol'))->toBe([])
        ->and(config('drm.provisorio.leitura'))->toBeNull();
});

it('CA-11 — o módulo se chama "Raio X do Negócio" na interface', function () {
    expect((new ConteudoDrm)->nomeModulo())->toBe('Raio X do Negócio');
});

it('CA-11 — o nome de interface é conteúdo validado, não provisório', function () {
    // Não depende da EBC: é decisão de PO (PDR-009 §3).
    expect(config('drm.rotulos.modulo.nome'))->toBe('Raio X do Negócio')
        ->and(config('drm.provisorio.rotulos'))->toBeNull();
});

it('CA-3 — o rótulo do grupo de custo muda nos três setores (Anexo K §K.4)', function () {
    $conteudo = new ConteudoDrm;

    expect($conteudo->rotuloGrupoCusto('industria'))->toBe('Custo dos Produtos Vendidos (CPV)')
        ->and($conteudo->rotuloGrupoCusto('comercio'))->toBe('Custo das Mercadorias Vendidas (CMV)')
        ->and($conteudo->rotuloGrupoCusto('servicos'))->toBe('Custo dos Serviços Prestados (CSP)');
});

it('CA-3 — a linha L4 resolve o rótulo por setor, e as demais não', function () {
    $conteudo = new ConteudoDrm;

    expect($conteudo->rotuloLinha('L4', 'fora', 'comercio'))->toBe('Custo das Mercadorias Vendidas (CMV)')
        ->and($conteudo->rotuloLinha('L4', 'fora', 'servicos'))->toBe('Custo dos Serviços Prestados (CSP)')
        ->and($conteudo->rotuloLinha('L5', 'fora', 'comercio'))->toBe('Lucro Bruto')
        ->and($conteudo->rotuloLinha('L5', 'fora', 'servicos'))->toBe('Lucro Bruto');
});

it('CA-8 — os rótulos de campo saem pelo resolvedor, com o mesmo contrato dos demais', function () {
    $conteudo = new ConteudoDrm;

    // Achado F-2 da validação: o método era público e não tinha teste.
    expect($conteudo->rotuloCampo('D01'))->toBe('Quanto vendeu no mês')
        ->and($conteudo->rotuloCampo('D40'))->toBe('Pró-labore (retirada dos sócios/dono)')
        // Campo inexistente devolve null como qualquer outra chave ausente — a
        // tela não recebe string vazia para renderizar como rótulo em branco.
        ->and($conteudo->rotuloCampo('D99'))->toBeNull();
});

it('CA-8 — rótulo de campo validado ganha do provisório, como o resto do pacote', function () {
    // Rótulos nascem validados e não têm contrapartida provisória hoje. O teste
    // existe porque o dia em que alguém criar `provisorio/rotulos.php` sem
    // decisão de PO, a sobreposição precisa se comportar como no resto.
    config()->set('drm.rotulos.campos.D01', 'Faturamento do mês');

    expect((new ConteudoDrm)->rotuloCampo('D01'))->toBe('Faturamento do mês');
});

it('CA-2 — a linha L7 só se chama EBITDA no modo em que ela é EBITDA', function () {
    $conteudo = new ConteudoDrm;

    // No modo "fora" o pró-labore é subtraído depois, então L7 não é EBITDA na
    // definição padrão — divergência deliberada de estrutura-drm §3.1.
    expect($conteudo->rotuloLinha('L7', 'fora', 'industria'))->toBe('Resultado Operacional')
        ->and($conteudo->rotuloLinha('L7', 'dentro', 'industria'))->toBe('Resultado Operacional (EBITDA)');
});

it('CA-2 — L9 e L10 não existem no modo "pró-labore dentro"', function () {
    $conteudo = new ConteudoDrm;

    expect($conteudo->rotuloLinha('L9', 'dentro', 'industria'))->toBeNull()
        ->and($conteudo->rotuloLinha('L10', 'dentro', 'industria'))->toBeNull()
        // …e existem no modo default.
        ->and($conteudo->rotuloLinha('L9', 'fora', 'industria'))->toBe('Resultado antes do pró-labore')
        ->and($conteudo->rotuloLinha('L10', 'fora', 'industria'))->toBe('Pró-labore');
});

it('CA-10 — o inventário de lacunas registra a chave servida do provisório', function () {
    $tmp = sys_get_temp_dir().'/drm-lacunas-'.uniqid().'.md';
    config()->set('drm.lacunas_path', $tmp);

    $conteudo = new ConteudoDrm;
    $conteudo->help('D07');
    $conteudo->help('D07'); // idempotente: a mesma linha não é gravada duas vezes.
    $conteudo->alerta('A1');

    $registrado = (string) file_get_contents($tmp);
    @unlink($tmp);

    expect(substr_count($registrado, '`help` → `campos.D07`'))->toBe(1)
        ->and($registrado)->toContain('`alertas` → `alertas.A1`');
});

it('CA-10 — chave validada não entra no inventário de lacunas', function () {
    $tmp = sys_get_temp_dir().'/drm-lacunas-'.uniqid().'.md';
    config()->set('drm.lacunas_path', $tmp);
    config()->set('drm.help.campos.D07', 'Validado.');

    (new ConteudoDrm)->help('D07');

    $existe = is_file($tmp);
    @unlink($tmp);

    expect($existe)->toBeFalse('Chave validada não é lacuna e não pode ser registrada como tal.');
});

it('STORY-054 — frase-modelo da leitura existe e a ausência é erro de configuração', function () {
    $conteudo = new ConteudoDrm;

    expect($conteudo->fraseDeLeitura('abertura.lucro'))->toContain(':resultado');

    config()->set('drm.leitura_frases.abertura.lucro', null);
    expect(fn () => $conteudo->fraseDeLeitura('abertura.lucro'))->toThrow(LogicException::class);
});

<?php

declare(strict_types=1);

use App\Domain\Drm\Faixas;

/*
|-------------------------------------------------------------------------
| STORY-052 CA-5 — faixa não calibrada não produz farol.
|
| A regra é do motor, não da tela (PDR-009 §2 item 3): se morasse na view, a
| próxima superfície — planilha, PDF, e-mail de resumo — poderia contorná-la
| sem querer. Estes testes provam o comportamento **hoje** (nenhuma faixa
| calibrada, nenhum farol) e o comportamento **depois** (faixa calibrada
| classifica), para que calibrar seja edição de config e nada mais.
|-------------------------------------------------------------------------
*/

it('CA-5 — hoje nenhuma linha produz farol, nos três setores', function () {
    $faixas = new Faixas;

    foreach (['industria', 'comercio', 'servicos'] as $setor) {
        foreach (array_keys((array) config("drm.faixas-{$setor}.linhas")) as $linha) {
            expect($faixas->farol((string) $linha, 42.0, $setor))->toBeNull(
                "A linha {$linha} do setor {$setor} produziu farol sem faixa calibrada.",
            );
        }
    }
});

it('CA-5 — e isso é o estado declarado do pacote, não um esquecimento', function () {
    foreach (['industria', 'comercio', 'servicos'] as $setor) {
        foreach ((array) config("drm.faixas-{$setor}.linhas") as $linha => $faixa) {
            expect($faixa['estado'])->toBe(Faixas::NAO_CALIBRADA)
                ->and($faixa['origem'])->toBeNull()
                ->and($faixa['validado_em'])->toBeNull()
                // `sentido` é estrutura, não valor: precisa existir desde já,
                // senão um motor futuro teria que adivinhar se 40% de despesa
                // administrativa é bom — e adivinhar aqui é o mesmo erro de
                // publicar faixa não validada.
                ->and($faixa['sentido'])->toBeIn(['maior_melhor', 'menor_melhor']);
        }
    }
});

it('CA-5 — calibrar uma faixa é editar config, sem tocar em código', function () {
    config()->set('drm.faixas-industria.linhas.L5', [
        'rotulo_referencia' => 'Lucro Bruto (Margem Bruta)',
        'sentido' => 'maior_melhor',
        'estado' => Faixas::CALIBRADA,
        'origem' => 'EBC — Anexo X, revisão de teste',
        'validado_em' => '2026-09-01',
        'verde' => ['min' => 30.0, 'max' => null],
        'amarelo' => ['min' => 15.0, 'max' => 30.0],
        'vermelho' => ['min' => null, 'max' => 15.0],
    ]);

    $faixas = new Faixas;

    expect($faixas->farol('L5', 45.0))->toBe('verde')
        ->and($faixas->farol('L5', 22.0))->toBe('amarelo')
        ->and($faixas->farol('L5', 9.0))->toBe('vermelho')
        // Fronteiras: `min` inclusivo, `max` exclusivo.
        ->and($faixas->farol('L5', 30.0))->toBe('verde')
        ->and($faixas->farol('L5', 29.99))->toBe('amarelo')
        ->and($faixas->farol('L5', 15.0))->toBe('amarelo')
        ->and($faixas->farol('L5', 14.99))->toBe('vermelho')
        // E a linha ao lado continua sem farol — calibrar uma não calibra todas.
        ->and($faixas->farol('L11', 45.0))->toBeNull();
});

it('CA-5 — percentual indisponível não produz farol (Anexo K §K.7)', function () {
    config()->set('drm.faixas-industria.linhas.L5.estado', Faixas::CALIBRADA);
    config()->set('drm.faixas-industria.linhas.L5.verde', ['min' => null, 'max' => null]);

    // Mês sem faturamento: o percentual é null e não há o que classificar. O
    // valor nominal continua sendo exibido — mas isso é da tela, não daqui.
    expect((new Faixas)->farol('L5', null))->toBeNull();
});

it('CA-5 — linha sem faixa configurada não produz farol', function () {
    expect((new Faixas)->farol('L99', 10.0))->toBeNull();
});

it('CA-5 (borda) — faixa calibrada com buraco devolve null, não a cor mais próxima', function () {
    config()->set('drm.faixas-industria.linhas.L7', [
        'estado' => Faixas::CALIBRADA,
        'sentido' => 'maior_melhor',
        'verde' => ['min' => 20.0, 'max' => null],
        'amarelo' => ['min' => 10.0, 'max' => 15.0], // buraco entre 15 e 20
        'vermelho' => ['min' => null, 'max' => 10.0],
    ]);

    // Escolher a cor mais próxima esconderia o erro de calibração; devolver
    // null o deixa visível.
    expect((new Faixas)->farol('L7', 17.0))->toBeNull();
});

it('CA-5 (borda) — faixa vazia não vira "serve para tudo"', function () {
    // Este é o formato de uma faixa NÃO preenchida. Se `min`/`max` nulos
    // classificassem qualquer valor, a primeira cor da lista venceria sempre —
    // e o produto pintaria tudo de verde no dia em que alguém marcasse
    // `calibrada` sem preencher os limites.
    config()->set('drm.faixas-industria.linhas.L11.estado', Faixas::CALIBRADA);

    expect((new Faixas)->farol('L11', 12.0))->toBeNull();
});

it('CA-5 — `calibrada()` responde o que a tela precisa saber antes de reservar espaço', function () {
    $faixas = new Faixas;

    expect($faixas->calibrada('L5'))->toBeFalse();

    config()->set('drm.faixas-industria.linhas.L5.estado', Faixas::CALIBRADA);

    expect($faixas->calibrada('L5'))->toBeTrue();
});

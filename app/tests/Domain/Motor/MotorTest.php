<?php

declare(strict_types=1);

use App\Domain\Motor\Farol;
use App\Domain\Motor\MensagensFarol;
use App\Domain\Motor\MotivosIndisponibilidade;
use App\Domain\Motor\Motor;
use App\Domain\Motor\QuizPayloadCanonicalizer;

function payloadIndustriaSaudavel(): array
{
    return [
        'Q01' => 1,
        'Q02' => '50000',
        'Q03' => '80000',
        'Q04' => '60000',
        'Q05' => '300000',
        'Q06' => '40000',
        'Q07' => '30000',
        'Q08' => '50000',
        'Q09' => '100000',
        'Q10' => 45,
        'Q11' => 30,
        'Q12' => 30,
        'Q13' => '2',
        'Q14' => '20000',
        'Q15' => '8000',
        'Q16' => '2000',
    ];
}

it('retorna estrutura canônica com motor_version, matrix_version, setor, indicadores e resumo', function () {
    $r = (new Motor)->calcular(payloadIndustriaSaudavel(), 'industria');
    expect($r)->toHaveKeys(['motor_version', 'matrix_version', 'setor', 'indicadores_calculados', 'resumo_executivo']);
    expect($r['motor_version'])->toBe('1.0.0');
    expect($r['matrix_version'])->toBe('dez-2025');
    expect($r['setor'])->toBe('industria');
});

it('produz exatamente 8 indicadores na V1 (7 com farol + NCG abs)', function () {
    $r = (new Motor)->calcular(payloadIndustriaSaudavel(), 'industria');
    expect(array_keys($r['indicadores_calculados']))->toBe([
        'margem_bruta',
        'margem_liquida',
        'divida_liquida_ebitda',
        'ncg_vendas',
        'pmr',
        'pmc',
        'ciclo_financeiro',
        'ncg_absoluto',
    ]);
});

it('cada indicador tem chaves valor/farol/motivo/mensagem', function () {
    $r = (new Motor)->calcular(payloadIndustriaSaudavel(), 'industria');
    foreach ($r['indicadores_calculados'] as $chave => $linha) {
        expect($linha)->toHaveKeys(['valor', 'farol', 'motivo', 'mensagem'], "indicador {$chave}");
    }
});

it('é determinístico — chamadas repetidas produzem saída idêntica', function () {
    $payload = payloadIndustriaSaudavel();
    $motor = new Motor;
    $r1 = $motor->calcular($payload, 'industria');
    $r2 = $motor->calcular($payload, 'industria');
    expect($r1)->toBe($r2);
});

it('determinismo sobrevive a reordenação de chaves do payload (canonicalização)', function () {
    $a = payloadIndustriaSaudavel();
    $b = array_reverse($a, preserve_keys: true);
    $motor = new Motor;
    expect($motor->calcular($a, 'industria'))->toBe($motor->calcular($b, 'industria'));
});

it('rejeita setor desconhecido com InvalidArgumentException', function () {
    (new Motor)->calcular(payloadIndustriaSaudavel(), 'agropecuaria');
})->throws(InvalidArgumentException::class, "Setor 'agropecuaria' não suportado");

it('rejeita comercio e servicos no motor V1 (só Indústria)', function () {
    expect(fn () => (new Motor)->calcular(payloadIndustriaSaudavel(), 'comercio'))
        ->toThrow(InvalidArgumentException::class);
    expect(fn () => (new Motor)->calcular(payloadIndustriaSaudavel(), 'servicos'))
        ->toThrow(InvalidArgumentException::class);
});

it('NCG absoluto sempre tem farol nenhum (informativo)', function () {
    $r = (new Motor)->calcular(payloadIndustriaSaudavel(), 'industria');
    expect($r['indicadores_calculados']['ncg_absoluto']['farol'])->toBe('nenhum');
});

it('payload todo null → todos indicadores indisponíveis', function () {
    $payload = ['Q01' => 1];   // só setor
    $r = (new Motor)->calcular($payload, 'industria');
    foreach ($r['indicadores_calculados'] as $chave => $linha) {
        expect($linha['valor'])->toBeNull("indicador {$chave} deveria ser null");
        expect($linha['farol'])->toBe('nenhum', "indicador {$chave} deveria ter farol nenhum");
        expect($linha['motivo'])->toStartWith('indisponivel:', "indicador {$chave} deveria ter motivo");
    }
});

it('resumo executivo é placeholder pendente_story=STORY-031', function () {
    $r = (new Motor)->calcular(payloadIndustriaSaudavel(), 'industria');
    expect($r['resumo_executivo'])->toBe([
        'pendente_story' => 'STORY-031',
        'fallback_acionado' => false,
    ]);
});

it('aceita industria sem exception', function () {
    expect(fn () => (new Motor)->calcular(payloadIndustriaSaudavel(), 'industria'))
        ->not->toThrow(Throwable::class);
});

it('MensagensFarol::paraFarol(nenhum) lança exception (esse caso é tratado pelo motivo)', function () {
    MensagensFarol::paraFarol(Farol::NENHUM);
})->throws(InvalidArgumentException::class, "MensagensFarol::paraFarol() chamado com farol 'nenhum'");

it('MotivosIndisponibilidade::mensagem(motivo desconhecido) lança exception', function () {
    MotivosIndisponibilidade::mensagem('motivo:nao-existe');
})->throws(InvalidArgumentException::class, "Motivo de indisponibilidade desconhecido: 'motivo:nao-existe'");

it('QuizPayloadCanonicalizer::toJson lança RuntimeException quando json_encode falha', function () {
    // Resources não são serializáveis em JSON — força o caminho de erro.
    $handle = fopen('php://memory', 'r');
    try {
        expect(fn () => QuizPayloadCanonicalizer::toJson(['recurso' => $handle]))
            ->toThrow(RuntimeException::class, 'Falha ao serializar quiz_payload canonical');
    } finally {
        fclose($handle);
    }
});

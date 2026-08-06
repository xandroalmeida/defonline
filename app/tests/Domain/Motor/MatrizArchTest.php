<?php

declare(strict_types=1);

use App\Domain\Motor\Farol;
use App\Domain\Motor\MatrizRecomendacoes;
use App\Domain\Motor\ResumoExecutivo;

/*
|-------------------------------------------------------------------------
| Testes arquiteturais da matriz dez-2025 (STORY-032).
|
| Garantem que:
|   - O config tem cobertura completa (13 com farol × 3 cores).
|   - Nenhum indicador continua usando MensagensFarol::paraFarol (CA-3).
|   - A seção `_referencia_editorial` (STORY-060 CA-5) é a única exceção
|     conhecida ao "toda chave de primeiro nível é indicador consumido" — e é
|     tratada como exceção declarada, não como acusação.
|-------------------------------------------------------------------------
*/

/**
 * Chaves de primeiro nível da matriz que **não** são indicadores consumidos em
 * runtime. Exceção declarada da STORY-060 CA-5: a lista existe para que o teste
 * saiba da seção editorial em vez de acusá-la, e para que qualquer chave nova
 * fora dessa lista continue sendo acusada.
 *
 * @var list<string>
 */
const CHAVES_NAO_CONSUMIDAS = ['_referencia_editorial'];

it('config matriz dez-2025 industria tem entrada para os 13 indicadores com farol × 3 cores', function () {
    /** @var array<string, array<string, string>> $matriz */
    $matriz = config('motor.matriz-dez-2025-industria');

    expect($matriz)->toBeArray();

    // 13 indicadores com farol (CODIGOS_ANEXO_D menos NCG abs).
    $indicadoresComFarol = array_diff(ResumoExecutivo::CODIGOS_ANEXO_D, ['ncg_absoluto']);
    expect($indicadoresComFarol)->toHaveCount(13);

    foreach ($indicadoresComFarol as $codigo) {
        expect(array_key_exists($codigo, $matriz))->toBeTrue("Faltando entrada para indicador {$codigo}");
        expect($matriz[$codigo])
            ->toHaveKey(Farol::VERDE)
            ->toHaveKey(Farol::AMARELO)
            ->toHaveKey(Farol::VERMELHO);
        foreach ([Farol::VERDE, Farol::AMARELO, Farol::VERMELHO] as $cor) {
            expect($matriz[$codigo][$cor])->toBeString();
            expect($matriz[$codigo][$cor])->not->toBe('', "Texto vazio para {$codigo}.{$cor}");
        }
    }

    // NCG absoluto NÃO é chave de primeiro nível: não é consumido (STORY-060 CA-5).
    expect($matriz)->not->toHaveKey('ncg_absoluto');
});

it('STORY-060 CA-5 — toda chave de primeiro nível é indicador consumido, salvo a exceção declarada', function () {
    /** @var array<string, mixed> $matriz */
    $matriz = config('motor.matriz-dez-2025-industria');

    $indicadoresComFarol = array_values(array_diff(ResumoExecutivo::CODIGOS_ANEXO_D, ['ncg_absoluto']));
    $inesperadas = array_diff(array_keys($matriz), $indicadoresComFarol, CHAVES_NAO_CONSUMIDAS);

    expect($inesperadas)->toBe(
        [],
        'Chave de primeiro nível que não é indicador com farol nem exceção declarada: '.
        implode(', ', $inesperadas).'. Ou ela é consumida em runtime, ou desce para `_referencia_editorial`.',
    );
});

it('STORY-060 CA-5 — a referência editorial preserva o texto do Anexo F §F.13 fora do runtime', function () {
    /** @var array<string, mixed> $matriz */
    $matriz = config('motor.matriz-dez-2025-industria');

    expect($matriz['_referencia_editorial']['ncg_absoluto'])
        ->toHaveKey('positiva')
        ->toHaveKey('negativa');
});

it('STORY-060 CA-5 — pedir `ncg_absoluto` à matriz vira lacuna registrada, não texto silencioso', function () {
    // O modo de falha que a estória chama de "armadilha": receber texto de uma
    // entrada que o produto nunca exibe. Depois do CA-5 isso não acontece mais —
    // o par cai no fallback e é reportado como lacuna.
    config()->set('motor.matriz_lacunas_path', '');

    expect((new MatrizRecomendacoes)->texto('ncg_absoluto', 'positiva'))
        ->toBe(MatrizRecomendacoes::FALLBACK);
});

it('nenhum indicador continua usando MensagensFarol::paraFarol para mensagem', function () {
    $arquivos = glob(__DIR__.'/../../../app/Domain/Motor/Indicadores/*.php');
    expect($arquivos)->toBeArray()->not->toBeEmpty();

    foreach ($arquivos as $arquivo) {
        $conteudo = (string) file_get_contents($arquivo);
        expect($conteudo)->not->toContain(
            'MensagensFarol::paraFarol',
            'Arquivo '.basename($arquivo).' ainda usa MensagensFarol::paraFarol — substituir por MatrizRecomendacoes::texto.',
        );
    }
});

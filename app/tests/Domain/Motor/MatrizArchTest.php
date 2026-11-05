<?php

declare(strict_types=1);

use App\Domain\Motor\Farol;
use App\Domain\Motor\MatrizRecomendacoes;
use App\Domain\Motor\ResumoExecutivo;
use Tests\Support\RecomendacoesCongeladas;

/*
|-------------------------------------------------------------------------
| Testes arquiteturais da matriz dez-2025 (STORY-032).
|
| Garantem que:
|   - O config tem cobertura completa (13 com farol × 3 cores).
|   - Nenhum indicador continua usando MensagensFarol::paraFarol (CA-3).
|   - O NCG absoluto, sem farol, tem positiva e negativa (PDR-018).
|-------------------------------------------------------------------------
*/

it('config matriz dez-2025 industria tem entrada para os 13 indicadores com farol × 3 cores', function () {
    /** @var array<string, array<string, string>> $matriz */
    $matriz = RecomendacoesCongeladas::matriz();

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

});

it('PDR-018 — o NCG absoluto tem texto para positiva e negativa, e a matriz não guarda mais nada', function () {
    /** @var array<string, array<string, string>> $matriz */
    $matriz = RecomendacoesCongeladas::matriz();

    expect(array_keys($matriz['ncg_absoluto']))->toEqualCanonicalizing(['positiva', 'negativa'])
        ->and(array_keys($matriz))->toEqualCanonicalizing(ResumoExecutivo::CODIGOS_ANEXO_D)
        ->and((new MatrizRecomendacoes)->texto('ncg_absoluto', 'positiva'))->toBe($matriz['ncg_absoluto']['positiva']);
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

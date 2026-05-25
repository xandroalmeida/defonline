<?php

declare(strict_types=1);

use App\Domain\Motor\Farol;
use App\Domain\Motor\ResumoExecutivo;

/*
|-------------------------------------------------------------------------
| Testes arquiteturais da matriz dez-2025 (STORY-032).
|
| Garantem que:
|   - O config tem cobertura completa (13 com farol × 3 cores + NCG abs).
|   - Nenhum indicador continua usando MensagensFarol::paraFarol (CA-3).
|-------------------------------------------------------------------------
*/

it('config matriz dez-2025 industria tem entrada para os 13 indicadores com farol × 3 cores + NCG abs', function () {
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

    // NCG absoluto: cenários positiva/negativa (F.13).
    expect($matriz)->toHaveKey('ncg_absoluto');
    expect($matriz['ncg_absoluto'])
        ->toHaveKey('positiva')
        ->toHaveKey('negativa');
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

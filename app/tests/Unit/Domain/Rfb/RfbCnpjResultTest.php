<?php

declare(strict_types=1);

use App\Domain\Rfb\RfbCnpjResult;
use App\Domain\SituacaoCadastral;
use Illuminate\Support\Carbon;

/**
 * UnitPure — DTO devolvido pela consulta RFB (STORY-015 CA-1).
 */
it('projeta dados completos para o formulário Livewire (CA-2)', function () {
    $resultado = new RfbCnpjResult(
        razaoSocial: 'Marcenaria Roberto LTDA',
        nomeFantasia: 'Marcenaria Roberto',
        cnae: '1622699',
        municipio: 'São Paulo',
        uf: 'SP',
        situacaoCadastral: SituacaoCadastral::Ativa,
        dataFundacao: Carbon::create(2010, 3, 15),
        fonteProvedor: 'mock',
        consultadoAt: Carbon::create(2026, 5, 23, 10, 30, 0),
    );

    expect($resultado->paraFormulario())->toBe([
        'razao_social' => 'Marcenaria Roberto LTDA',
        'nome_fantasia' => 'Marcenaria Roberto',
        'cnae' => '1622699',
        'municipio' => 'São Paulo',
        'uf' => 'SP',
        'situacao_cadastral' => 'ativa',
        'data_fundacao' => '2010-03-15',
    ]);
});

it('projeta campos nullables como string vazia para o formulário', function () {
    $resultado = new RfbCnpjResult(
        razaoSocial: 'Confeitaria sem fantasia',
        nomeFantasia: null,
        cnae: null,
        municipio: 'Belo Horizonte',
        uf: 'MG',
        situacaoCadastral: SituacaoCadastral::Ativa,
        dataFundacao: null,
        fonteProvedor: 'mock',
        consultadoAt: Carbon::create(2026, 5, 23, 10, 30, 0),
    );

    $form = $resultado->paraFormulario();
    expect($form['nome_fantasia'])->toBe('');
    expect($form['cnae'])->toBe('');
    expect($form['data_fundacao'])->toBe('');
});

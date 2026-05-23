<?php

declare(strict_types=1);

use App\Domain\FonteEnriquecimento;
use App\Domain\TipoDocumento;
use App\Livewire\Empresa\Cadastrar;
use App\Models\EmpresaAnalisada;
use App\Models\Usuario;
use Database\Factories\EmpresaAnalisadaFactory;
use Livewire\Livewire;

/**
 * Feature da STORY-015 CA-3 — fonte_enriquecimento correta após submit.
 */
beforeEach(function () {
    $this->usuario = Usuario::factory()->create();
    $this->actingAs($this->usuario);
});

it('grava fonte_enriquecimento=rfb e enriquecido_at quando o submit segue uma consulta bem-sucedida', function () {
    $cnpj = EmpresaAnalisadaFactory::cnpjComRaiz('12345678');

    Livewire::test(Cadastrar::class)
        ->set('tipo_documento', TipoDocumento::Cnpj->value)
        ->set('documento', $cnpj)
        ->call('consultarReceita')
        ->assertSet('enriquecido', true)
        ->call('submit')
        ->assertRedirect();

    $empresa = EmpresaAnalisada::firstWhere('documento', $cnpj);
    expect($empresa->fonte_enriquecimento)->toBe(FonteEnriquecimento::Rfb);
    expect($empresa->enriquecido_at)->not->toBeNull();
});

it('grava fonte_enriquecimento=manual quando o CNPJ é editado após a consulta', function () {
    $cnpjConsultado = EmpresaAnalisadaFactory::cnpjComRaiz('12345678');
    $cnpjFinal = EmpresaAnalisadaFactory::cnpjComRaiz('87654321');

    Livewire::test(Cadastrar::class)
        ->set('tipo_documento', TipoDocumento::Cnpj->value)
        ->set('documento', $cnpjConsultado)
        ->call('consultarReceita')
        ->assertSet('enriquecido', true)
        ->set('documento', $cnpjFinal)            // updatedDocumento limpa flag
        ->set('razao_social', 'Razão manual')      // preenche manualmente
        ->set('municipio', 'Curitiba')
        ->set('uf', 'PR')
        ->call('submit')
        ->assertRedirect();

    $empresa = EmpresaAnalisada::firstWhere('documento', $cnpjFinal);
    expect($empresa)->not->toBeNull();
    expect($empresa->fonte_enriquecimento)->toBe(FonteEnriquecimento::Manual);
    expect($empresa->enriquecido_at)->toBeNull();
});

it('cadastro manual puro continua gravando fonte=manual (regressão STORY-014)', function () {
    $cnpj = EmpresaAnalisadaFactory::gerarCnpjValido();

    Livewire::test(Cadastrar::class)
        ->set('tipo_documento', TipoDocumento::Cnpj->value)
        ->set('documento', $cnpj)
        ->set('razao_social', 'Marcenaria Roberto LTDA')
        ->set('cnae', '1622699')
        ->set('municipio', 'São Paulo')
        ->set('uf', 'SP')
        ->set('situacao_cadastral', 'ativa')
        ->call('submit')
        ->assertRedirect();

    $empresa = EmpresaAnalisada::firstWhere('documento', $cnpj);
    expect($empresa->fonte_enriquecimento)->toBe(FonteEnriquecimento::Manual);
    expect($empresa->enriquecido_at)->toBeNull();
});

it('tela /empresas/{id} mostra badge "Receita Federal" para empresas enriquecidas', function () {
    $cnpj = EmpresaAnalisadaFactory::cnpjComRaiz('12345678');

    Livewire::test(Cadastrar::class)
        ->set('tipo_documento', TipoDocumento::Cnpj->value)
        ->set('documento', $cnpj)
        ->call('consultarReceita')
        ->call('submit');

    $empresa = EmpresaAnalisada::firstWhere('documento', $cnpj);

    $this->get("/empresas/{$empresa->id}")
        ->assertOk()
        ->assertSee('Receita Federal');
});

it('tela /empresas/{id} mostra badge "Preenchimento manual" para cadastro sem RFB (regressão STORY-014)', function () {
    $cnpj = EmpresaAnalisadaFactory::gerarCnpjValido();
    $empresa = EmpresaAnalisada::factory()->create([
        'usuario_id' => $this->usuario->id,
        'documento' => $cnpj,
        'fonte_enriquecimento' => FonteEnriquecimento::Manual->value,
        'enriquecido_at' => null,
    ]);

    $this->get("/empresas/{$empresa->id}")
        ->assertOk()
        ->assertSee('Preenchimento manual');
});

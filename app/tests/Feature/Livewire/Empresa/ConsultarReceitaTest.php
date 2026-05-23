<?php

declare(strict_types=1);

use App\Domain\TipoDocumento;
use App\Livewire\Empresa\Cadastrar;
use App\Models\Usuario;
use Database\Factories\EmpresaAnalisadaFactory;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/**
 * Feature da STORY-015 CA-2 — botão "Consultar Receita" no form Livewire.
 */
beforeEach(function () {
    $this->usuario = Usuario::factory()->create();
    $this->actingAs($this->usuario);
});

it('pré-preenche os campos do form após sucesso na consulta (CA-2)', function () {
    $cnpj = EmpresaAnalisadaFactory::cnpjComRaiz('12345678');

    Livewire::test(Cadastrar::class)
        ->set('tipo_documento', TipoDocumento::Cnpj->value)
        ->set('documento', $cnpj)
        ->call('consultarReceita')
        ->assertSet('enriquecido', true)
        ->assertSet('statusConsultaRfb', 'sucesso')
        ->assertSet('mensagemFallback', null)
        ->assertNotSet('razao_social', '')
        ->assertNotSet('municipio', '')
        ->assertNotSet('uf', '');
});

it('mostra o aviso amarelo e mantém o form em branco em qualquer falha (CA-2)', function (string $raiz, string $statusEsperado) {
    $cnpj = EmpresaAnalisadaFactory::cnpjComRaiz($raiz);

    $component = Livewire::test(Cadastrar::class)
        ->set('tipo_documento', TipoDocumento::Cnpj->value)
        ->set('documento', $cnpj)
        ->call('consultarReceita')
        ->assertSet('enriquecido', false)
        ->assertSet('statusConsultaRfb', $statusEsperado)
        ->assertSet('razao_social', '')
        ->assertSet('municipio', '')
        ->assertSet('uf', '');

    expect($component->get('mensagemFallback'))
        ->toBe('Não conseguimos consultar a Receita agora — preencha os campos manualmente.');
})->with([
    ['00112233', 'cnpj_inexistente'],
    ['99887766', 'timeout'],
    ['88776655', 'erro_5xx'],
    ['77665544', 'erro_rede'],
]);

it('rejeita CNPJ inválido sem chamar o provedor e mostra aviso específico', function () {
    Livewire::test(Cadastrar::class)
        ->set('tipo_documento', TipoDocumento::Cnpj->value)
        ->set('documento', '11.111.111/1111-11')
        ->call('consultarReceita')
        ->assertSet('enriquecido', false)
        ->assertSet('statusConsultaRfb', 'cnpj_invalido')
        ->assertSet('mensagemFallback', 'Informe um CNPJ válido para consultar a Receita.');

    expect(DB::table('business_metrics')->where('tipo', 'rfb_consulta')->count())->toBe(0);
});

it('é no-op quando tipo_documento=cpf (CA-2)', function () {
    $cpf = EmpresaAnalisadaFactory::gerarCpfValido();

    Livewire::test(Cadastrar::class)
        ->set('tipo_documento', TipoDocumento::Cpf->value)
        ->set('documento', $cpf)
        ->call('consultarReceita')
        ->assertSet('enriquecido', false)
        ->assertSet('mensagemFallback', null);

    expect(DB::table('business_metrics')->where('tipo', 'rfb_consulta')->count())->toBe(0);
});

it('limpa o estado enriquecido quando o CNPJ é editado depois da consulta', function () {
    $cnpj1 = EmpresaAnalisadaFactory::cnpjComRaiz('12345678');
    $cnpj2 = EmpresaAnalisadaFactory::cnpjComRaiz('87654321');

    Livewire::test(Cadastrar::class)
        ->set('tipo_documento', TipoDocumento::Cnpj->value)
        ->set('documento', $cnpj1)
        ->call('consultarReceita')
        ->assertSet('enriquecido', true)
        ->set('documento', $cnpj2) // dispara updatedDocumento
        ->assertSet('enriquecido', false)
        ->assertSet('enriquecidoAt', null);
});

it('limpa o estado enriquecido quando o tipo do documento muda', function () {
    $cnpj = EmpresaAnalisadaFactory::cnpjComRaiz('12345678');

    Livewire::test(Cadastrar::class)
        ->set('tipo_documento', TipoDocumento::Cnpj->value)
        ->set('documento', $cnpj)
        ->call('consultarReceita')
        ->assertSet('enriquecido', true)
        ->set('tipo_documento', TipoDocumento::Cpf->value)
        ->assertSet('enriquecido', false);
});

it('renderiza o botão "Consultar Receita" para tipo CNPJ', function () {
    Livewire::test(Cadastrar::class)
        ->set('tipo_documento', TipoDocumento::Cnpj->value)
        ->assertSee('Consultar Receita');
});

it('não renderiza o botão "Consultar Receita" para tipo CPF', function () {
    Livewire::test(Cadastrar::class)
        ->set('tipo_documento', TipoDocumento::Cpf->value)
        ->assertDontSee('Consultar Receita');
});

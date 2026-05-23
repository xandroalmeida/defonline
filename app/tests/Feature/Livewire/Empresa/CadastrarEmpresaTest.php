<?php

declare(strict_types=1);

use App\Domain\FonteEnriquecimento;
use App\Domain\SituacaoCadastral;
use App\Domain\TipoDocumento;
use App\Livewire\Empresa\Cadastrar;
use App\Models\AuditLog;
use App\Models\EmpresaAnalisada;
use App\Models\Scopes\BelongsToUsuarioScope;
use App\Models\Usuario;
use Database\Factories\EmpresaAnalisadaFactory;
use Livewire\Livewire;

/**
 * Feature da STORY-014 cobrindo CA-1..CA-6.
 */
beforeEach(function () {
    $this->usuario = Usuario::factory()->create();
    $this->actingAs($this->usuario);
});

it('exige autenticação para acessar /empresas/nova', function () {
    auth()->logout();

    $this->get('/empresas/nova')->assertRedirect('/login');
});

it('exibe o formulário de cadastro em /empresas/nova', function () {
    $this->get('/empresas/nova')
        ->assertOk()
        ->assertSee('Cadastrar empresa')
        ->assertSee('Razão social')
        ->assertSee('CNAE');
});

it('cria empresa com CNPJ válido e redireciona para detalhes (CA-1, CA-2)', function () {
    $cnpj = EmpresaAnalisadaFactory::gerarCnpjValido();

    Livewire::test(Cadastrar::class)
        ->set('tipo_documento', TipoDocumento::Cnpj->value)
        ->set('documento', $cnpj)
        ->set('razao_social', 'Marcenaria Roberto LTDA')
        ->set('nome_fantasia', 'Marcenaria Roberto')
        ->set('cnae', '1622699')
        ->set('municipio', 'São Paulo')
        ->set('uf', 'SP')
        ->set('situacao_cadastral', SituacaoCadastral::Ativa->value)
        ->set('data_fundacao', '2010-03-15')
        ->call('submit')
        ->assertRedirect();

    $empresa = EmpresaAnalisada::firstWhere('documento', $cnpj);

    expect($empresa)->not->toBeNull();
    expect($empresa->usuario_id)->toBe($this->usuario->id);
    expect($empresa->tipo_documento)->toBe(TipoDocumento::Cnpj);
    expect($empresa->razao_social)->toBe('Marcenaria Roberto LTDA');
    expect($empresa->fonte_enriquecimento)->toBe(FonteEnriquecimento::Manual);
    expect($empresa->enriquecido_at)->toBeNull();
    expect($empresa->uf)->toBe('SP');
    expect($empresa->cnae)->toBe('1622699');
});

it('cria empresa com CPF válido (autônomo) e fonte manual (CA-2)', function () {
    $cpf = EmpresaAnalisadaFactory::gerarCpfValido();

    Livewire::test(Cadastrar::class)
        ->set('tipo_documento', TipoDocumento::Cpf->value)
        ->set('documento', $cpf)
        ->set('razao_social', 'Joana — Costureira Autônoma')
        ->set('municipio', 'Belo Horizonte')
        ->set('uf', 'MG')
        ->set('situacao_cadastral', SituacaoCadastral::NaoInformada->value)
        ->call('submit')
        ->assertRedirect();

    $empresa = EmpresaAnalisada::firstWhere('documento', $cpf);

    expect($empresa)->not->toBeNull();
    expect($empresa->tipo_documento)->toBe(TipoDocumento::Cpf);
    expect($empresa->fonte_enriquecimento)->toBe(FonteEnriquecimento::Manual);
});

it('rejeita CNPJ inválido (DV errado) com mensagem específica (CA-3)', function () {
    Livewire::test(Cadastrar::class)
        ->set('tipo_documento', TipoDocumento::Cnpj->value)
        ->set('documento', '11.222.333/0001-82')               // DV errado
        ->set('razao_social', 'Empresa X')
        ->set('municipio', 'São Paulo')
        ->set('uf', 'SP')
        ->call('submit')
        ->assertHasErrors(['documento'])
        ->assertNoRedirect();

    expect(EmpresaAnalisada::count())->toBe(0);
});

it('rejeita CPF inválido com mensagem específica (CA-3)', function () {
    Livewire::test(Cadastrar::class)
        ->set('tipo_documento', TipoDocumento::Cpf->value)
        ->set('documento', '111.111.111-11')                    // sequência trivial
        ->set('razao_social', 'Autônomo')
        ->set('municipio', 'BH')
        ->set('uf', 'MG')
        ->call('submit')
        ->assertHasErrors(['documento'])
        ->assertNoRedirect();
});

it('exige razão social, município e UF (CA-3)', function () {
    Livewire::test(Cadastrar::class)
        ->set('tipo_documento', TipoDocumento::Cnpj->value)
        ->set('documento', EmpresaAnalisadaFactory::gerarCnpjValido())
        ->set('razao_social', '')
        ->set('municipio', '')
        ->set('uf', '')
        ->call('submit')
        ->assertHasErrors(['razao_social', 'municipio', 'uf']);
});

it('rejeita UF fora da lista (CA-3)', function () {
    Livewire::test(Cadastrar::class)
        ->set('tipo_documento', TipoDocumento::Cnpj->value)
        ->set('documento', EmpresaAnalisadaFactory::gerarCnpjValido())
        ->set('razao_social', 'X')
        ->set('municipio', 'Y')
        ->set('uf', 'XX')                                       // UF inexistente
        ->call('submit')
        ->assertHasErrors(['uf']);
});

it('rejeita CNAE com formato inválido (CA-3)', function () {
    Livewire::test(Cadastrar::class)
        ->set('tipo_documento', TipoDocumento::Cnpj->value)
        ->set('documento', EmpresaAnalisadaFactory::gerarCnpjValido())
        ->set('razao_social', 'X')
        ->set('cnae', '12345')                                  // 5 dígitos, não 7
        ->set('municipio', 'Y')
        ->set('uf', 'SP')
        ->call('submit')
        ->assertHasErrors(['cnae']);
});

it('rejeita data de fundação no futuro (CA-3)', function () {
    Livewire::test(Cadastrar::class)
        ->set('tipo_documento', TipoDocumento::Cnpj->value)
        ->set('documento', EmpresaAnalisadaFactory::gerarCnpjValido())
        ->set('razao_social', 'X')
        ->set('municipio', 'Y')
        ->set('uf', 'SP')
        ->set('data_fundacao', now()->addDay()->toDateString())
        ->call('submit')
        ->assertHasErrors(['data_fundacao']);
});

it('aceita CNAE no formato com pontuação e normaliza para 7 dígitos', function () {
    Livewire::test(Cadastrar::class)
        ->set('tipo_documento', TipoDocumento::Cnpj->value)
        ->set('documento', EmpresaAnalisadaFactory::gerarCnpjValido())
        ->set('razao_social', 'Empresa Z')
        ->set('cnae', '1622-6/99')
        ->set('municipio', 'Y')
        ->set('uf', 'SP')
        ->call('submit')
        ->assertHasNoErrors();

    expect(EmpresaAnalisada::first()->cnae)->toBe('1622699');
});

it('grava audit_log "empresa.cadastrada" SEM documento (CA-6)', function () {
    $cnpj = EmpresaAnalisadaFactory::gerarCnpjValido();

    Livewire::test(Cadastrar::class)
        ->set('tipo_documento', TipoDocumento::Cnpj->value)
        ->set('documento', $cnpj)
        ->set('razao_social', 'Empresa do Roberto')
        ->set('municipio', 'São Paulo')
        ->set('uf', 'SP')
        ->call('submit');

    $audit = AuditLog::where('action', 'empresa.cadastrada')->first();
    expect($audit)->not->toBeNull();
    expect($audit->subject_type)->toBe('EmpresaAnalisada');
    expect($audit->usuario_id)->toBe($this->usuario->id);

    $after = (array) $audit->after;
    expect($after)->toHaveKeys(['tipo_documento', 'fonte_enriquecimento', 'uf']);
    expect($after)->not->toHaveKey('documento');                // PII fora do log
    expect(json_encode($audit->getAttributes()))->not->toContain($cnpj);
});

it('proíbe o mesmo Usuário cadastrar duas empresas com o mesmo documento', function () {
    $cnpj = EmpresaAnalisadaFactory::gerarCnpjValido();

    EmpresaAnalisada::factory()->create([
        'usuario_id' => $this->usuario->id,
        'documento' => $cnpj,
    ]);

    Livewire::test(Cadastrar::class)
        ->set('tipo_documento', TipoDocumento::Cnpj->value)
        ->set('documento', $cnpj)
        ->set('razao_social', 'Mesma empresa de novo')
        ->set('municipio', 'X')
        ->set('uf', 'SP')
        ->call('submit')
        ->assertHasErrors(['documento']);
});

it('permite Usuário B cadastrar empresa com mesmo documento de Usuário A (multi-tenancy, CA-4)', function () {
    $cnpj = EmpresaAnalisadaFactory::gerarCnpjValido();

    EmpresaAnalisada::factory()->create([
        'usuario_id' => $this->usuario->id,
        'documento' => $cnpj,
    ]);

    $outro = Usuario::factory()->create();
    $this->actingAs($outro);

    Livewire::test(Cadastrar::class)
        ->set('tipo_documento', TipoDocumento::Cnpj->value)
        ->set('documento', $cnpj)
        ->set('razao_social', 'A mesma empresa, outro consultor')
        ->set('municipio', 'X')
        ->set('uf', 'SP')
        ->call('submit')
        ->assertRedirect();

    expect(EmpresaAnalisada::withoutGlobalScope(BelongsToUsuarioScope::class)
        ->where('documento', $cnpj)->count())->toBe(2);
});

it('permite recadastrar empresa após soft delete (índice único parcial)', function () {
    $cnpj = EmpresaAnalisadaFactory::gerarCnpjValido();

    $original = EmpresaAnalisada::factory()->create([
        'usuario_id' => $this->usuario->id,
        'documento' => $cnpj,
    ]);
    $original->delete();                                          // soft delete

    Livewire::test(Cadastrar::class)
        ->set('tipo_documento', TipoDocumento::Cnpj->value)
        ->set('documento', $cnpj)
        ->set('razao_social', 'Recadastrei')
        ->set('municipio', 'X')
        ->set('uf', 'SP')
        ->call('submit')
        ->assertRedirect();

    expect(EmpresaAnalisada::where('documento', $cnpj)->count())->toBe(1);
});

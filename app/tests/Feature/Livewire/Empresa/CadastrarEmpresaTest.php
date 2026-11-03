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
 * Feature da STORY-014 cobrindo CA-1..CA-6 — desde a STORY-063 (PDR-010) o
 * formulário manual é exclusivo do autônomo com CPF; o caminho CNPJ está em
 * CadastroPorCnpjTest.
 */
beforeEach(function () {
    $this->usuario = Usuario::factory()->create();
    $this->actingAs($this->usuario);
});

it('exige autenticação para acessar /empresas/nova', function () {
    auth()->logout();

    $this->get('/empresas/nova')->assertRedirect('/login');
});

it('abre em modo CNPJ/CPF e o caminho de autônomo leva ao formulário manual de CPF (STORY-063 CA-9)', function () {
    $this->get('/empresas/nova')
        ->assertOk()
        ->assertSee('Cadastrar empresa')
        ->assertSee('CNPJ ou CPF')
        ->assertSee('Autônomo sem CNPJ? Informe seu CPF')
        ->assertDontSee('Nome completo');

    Livewire::test(Cadastrar::class)
        ->call('usarCpf')
        ->assertSet('tipo_documento', TipoDocumento::Cpf->value)
        ->assertSee('Nome completo')
        ->assertSee('CNAE')
        ->call('usarCnpj')
        ->assertSet('tipo_documento', TipoDocumento::Cnpj->value)
        ->assertDontSee('Nome completo');
});

it('cria empresa com CPF válido (autônomo) e fonte manual (CA-2)', function () {
    $cpf = EmpresaAnalisadaFactory::gerarCpfValido();

    Livewire::test(Cadastrar::class)
        ->set('tipo_documento', TipoDocumento::Cpf->value)
        ->set('documento', $cpf)
        ->set('observacoes', '  Cliente indicada pelo contador.  ')
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
    expect($empresa->ficha_rfb)->toBeNull();
    expect($empresa->observacoes)->toBe('Cliente indicada pelo contador.');
});

it('rejeita observações acima de 2.000 caracteres no cadastro por CPF (STORY-063 CA-5)', function () {
    Livewire::test(Cadastrar::class)
        ->set('tipo_documento', TipoDocumento::Cpf->value)
        ->set('documento', EmpresaAnalisadaFactory::gerarCpfValido())
        ->set('razao_social', 'Autônomo')
        ->set('municipio', 'BH')
        ->set('uf', 'MG')
        ->set('observacoes', str_repeat('a', 2001))
        ->call('submit')
        ->assertHasErrors(['observacoes']);

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
        ->set('tipo_documento', TipoDocumento::Cpf->value)
        ->set('documento', EmpresaAnalisadaFactory::gerarCpfValido())
        ->set('razao_social', '')
        ->set('municipio', '')
        ->set('uf', '')
        ->call('submit')
        ->assertHasErrors(['razao_social', 'municipio', 'uf']);
});

it('rejeita UF fora da lista (CA-3)', function () {
    Livewire::test(Cadastrar::class)
        ->set('tipo_documento', TipoDocumento::Cpf->value)
        ->set('documento', EmpresaAnalisadaFactory::gerarCpfValido())
        ->set('razao_social', 'X')
        ->set('municipio', 'Y')
        ->set('uf', 'XX')                                       // UF inexistente
        ->call('submit')
        ->assertHasErrors(['uf']);
});

it('rejeita CNAE com formato inválido (CA-3)', function () {
    Livewire::test(Cadastrar::class)
        ->set('tipo_documento', TipoDocumento::Cpf->value)
        ->set('documento', EmpresaAnalisadaFactory::gerarCpfValido())
        ->set('razao_social', 'X')
        ->set('cnae', '12345')                                  // 5 dígitos, não 7
        ->set('municipio', 'Y')
        ->set('uf', 'SP')
        ->call('submit')
        ->assertHasErrors(['cnae']);
});

it('rejeita CNAE de 7 dígitos que não existe na tabela do IBGE', function () {
    Livewire::test(Cadastrar::class)
        ->set('tipo_documento', TipoDocumento::Cpf->value)
        ->set('documento', EmpresaAnalisadaFactory::gerarCpfValido())
        ->set('razao_social', 'Empresa Z')
        ->set('cnae', '9999999')
        ->set('municipio', 'Y')
        ->set('uf', 'SP')
        ->call('submit')
        ->assertHasErrors(['cnae']);
});

it('rejeita data de fundação no futuro (CA-3)', function () {
    Livewire::test(Cadastrar::class)
        ->set('tipo_documento', TipoDocumento::Cpf->value)
        ->set('documento', EmpresaAnalisadaFactory::gerarCpfValido())
        ->set('razao_social', 'X')
        ->set('municipio', 'Y')
        ->set('uf', 'SP')
        ->set('data_fundacao', now()->addDay()->toDateString())
        ->call('submit')
        ->assertHasErrors(['data_fundacao']);
});

it('aceita CNAE no formato com pontuação e normaliza para 7 dígitos', function () {
    Livewire::test(Cadastrar::class)
        ->set('tipo_documento', TipoDocumento::Cpf->value)
        ->set('documento', EmpresaAnalisadaFactory::gerarCpfValido())
        ->set('razao_social', 'Empresa Z')
        ->set('cnae', '1622-6/99')
        ->set('municipio', 'Y')
        ->set('uf', 'SP')
        ->call('submit')
        ->assertHasNoErrors();

    expect(EmpresaAnalisada::first()->cnae)->toBe('1622699');
});

it('grava audit_log "empresa.cadastrada" SEM documento (CA-6)', function () {
    $cpf = EmpresaAnalisadaFactory::gerarCpfValido();

    Livewire::test(Cadastrar::class)
        ->set('tipo_documento', TipoDocumento::Cpf->value)
        ->set('documento', $cpf)
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
    expect(json_encode($audit->getAttributes()))->not->toContain($cpf);
});

it('proíbe o mesmo Usuário cadastrar duas empresas com o mesmo documento', function () {
    $cpf = EmpresaAnalisadaFactory::gerarCpfValido();

    EmpresaAnalisada::factory()->create([
        'usuario_id' => $this->usuario->id,
        'documento' => $cpf,
    ]);

    Livewire::test(Cadastrar::class)
        ->set('tipo_documento', TipoDocumento::Cpf->value)
        ->set('documento', $cpf)
        ->set('razao_social', 'Mesma empresa de novo')
        ->set('municipio', 'X')
        ->set('uf', 'SP')
        ->call('submit')
        ->assertHasErrors(['documento']);
});

it('permite Usuário B cadastrar empresa com mesmo documento de Usuário A (multi-tenancy, CA-4)', function () {
    $cpf = EmpresaAnalisadaFactory::gerarCpfValido();

    EmpresaAnalisada::factory()->create([
        'usuario_id' => $this->usuario->id,
        'documento' => $cpf,
    ]);

    $outro = Usuario::factory()->create();
    $this->actingAs($outro);

    Livewire::test(Cadastrar::class)
        ->set('tipo_documento', TipoDocumento::Cpf->value)
        ->set('documento', $cpf)
        ->set('razao_social', 'A mesma empresa, outro consultor')
        ->set('municipio', 'X')
        ->set('uf', 'SP')
        ->call('submit')
        ->assertRedirect();

    expect(EmpresaAnalisada::withoutGlobalScope(BelongsToUsuarioScope::class)
        ->where('documento', $cpf)->count())->toBe(2);
});

it('permite recadastrar empresa após soft delete (índice único parcial)', function () {
    $cpf = EmpresaAnalisadaFactory::gerarCpfValido();

    $original = EmpresaAnalisada::factory()->create([
        'usuario_id' => $this->usuario->id,
        'documento' => $cpf,
    ]);
    $original->delete();                                          // soft delete

    Livewire::test(Cadastrar::class)
        ->set('tipo_documento', TipoDocumento::Cpf->value)
        ->set('documento', $cpf)
        ->set('razao_social', 'Recadastrei')
        ->set('municipio', 'X')
        ->set('uf', 'SP')
        ->call('submit')
        ->assertRedirect();

    expect(EmpresaAnalisada::where('documento', $cpf)->count())->toBe(1);
});

it('autônomo: sem situação cadastral no form, grava "não informada" e a tela usa rótulos de pessoa física', function () {
    Livewire::test(Cadastrar::class)
        ->call('usarCpf')
        ->assertSee('Cadastrar autônomo')
        ->assertSee('Nome completo')
        ->assertSee('Início da atividade')
        ->assertDontSee('Razão social')
        ->assertDontSee('Situação cadastral')
        ->set('documento', EmpresaAnalisadaFactory::gerarCpfValido())
        ->set('razao_social', 'Joana Souza')
        ->set('situacao_cadastral', SituacaoCadastral::Baixada->value) // forjado pelo cliente
        ->set('municipio', 'Recife')
        ->set('uf', 'PE')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertRedirect();

    $empresa = EmpresaAnalisada::first();
    expect($empresa->situacao_cadastral)->toBe(SituacaoCadastral::NaoInformada);

    $this->get(route('empresas.show', $empresa))
        ->assertOk()
        ->assertSee('Nome completo')
        ->assertSee('Nome comercial')
        ->assertSee('Atividade principal')
        ->assertDontSee('Razão social')
        ->assertDontSee('Situação cadastral');
});

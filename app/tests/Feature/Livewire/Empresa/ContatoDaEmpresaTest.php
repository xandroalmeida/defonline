<?php

declare(strict_types=1);

use App\Livewire\Empresa\Cadastrar;
use App\Livewire\Empresa\Contato;
use App\Models\AuditLog;
use App\Models\EmpresaAnalisada;
use App\Models\Usuario;
use Database\Factories\EmpresaAnalisadaFactory;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

/**
 * PDR-019 — e-mail e telefone/WhatsApp da empresa, opcionais.
 */
beforeEach(function () {
    $this->usuario = Usuario::factory()->create();
    $this->actingAs($this->usuario);
    $this->cnpj = EmpresaAnalisadaFactory::cnpjComRaiz('45237000');
    $this->fichaConsultada = fn () => Livewire::test(Cadastrar::class)
        ->set('documento', $this->cnpj)
        ->call('consultarReceita')
        ->assertSet('statusConsultaRfb', 'sucesso');
    $this->autonomo = fn () => Livewire::test(Cadastrar::class)
        ->call('usarCpf')
        ->set('documento', '111.444.777-35')
        ->set('razao_social', 'Maria da Silva')
        ->set('municipio', 'Santos')
        ->set('uf', 'SP');
});

it('cadastro por CNPJ grava e-mail e telefone só com dígitos', function () {
    ($this->fichaConsultada)()
        ->assertSee('Telefone / WhatsApp')
        ->set('email', '  contato@empresa.com.br ')
        ->set('telefone', '(11) 98888-7777')
        ->call('confirmar')
        ->assertHasNoErrors();

    $empresa = EmpresaAnalisada::query()->firstOrFail();
    expect($empresa->email)->toBe('contato@empresa.com.br')
        ->and($empresa->telefone)->toBe('11988887777')
        ->and($empresa->telefoneFormatado())->toBe('(11) 98888-7777');
});

it('cadastro de autônomo grava e-mail e telefone', function () {
    ($this->autonomo)()
        ->set('email', 'maria@exemplo.com')
        ->set('telefone', '(13) 3333-4444')
        ->call('submit')
        ->assertHasNoErrors();

    $empresa = EmpresaAnalisada::query()->firstOrFail();
    expect($empresa->email)->toBe('maria@exemplo.com')
        ->and($empresa->telefone)->toBe('1333334444')
        ->and($empresa->telefoneFormatado())->toBe('(13) 3333-4444');
});

it('os dois campos são opcionais nos dois caminhos', function (string $caminho) {
    $tela = $caminho === 'cnpj' ? ($this->fichaConsultada)()->call('confirmar') : ($this->autonomo)()->call('submit');
    $tela->assertHasNoErrors();

    $empresa = EmpresaAnalisada::query()->firstOrFail();
    expect($empresa->email)->toBeNull()
        ->and($empresa->telefone)->toBeNull()
        ->and($empresa->telefoneFormatado())->toBeNull();
})->with(['cnpj', 'cpf']);

it('recusa e-mail e telefone inválidos sem cadastrar', function (string $caminho) {
    $tela = $caminho === 'cnpj' ? ($this->fichaConsultada)() : ($this->autonomo)();

    $tela->set('email', 'nao-e-email')->set('telefone', '(11) 123')
        ->call($caminho === 'cnpj' ? 'confirmar' : 'submit')
        ->assertHasErrors(['email', 'telefone'])
        ->assertSee('Informe um e-mail válido.')
        ->assertSee('Informe um telefone válido (DDD + número).');

    expect(EmpresaAnalisada::query()->count())->toBe(0);
})->with(['cnpj', 'cpf']);

it('nova consulta não leva o contato digitado para outra empresa', function () {
    ($this->fichaConsultada)()
        ->set('email', 'contato@empresa.com.br')
        ->set('telefone', '(11) 98888-7777')
        ->set('documento', EmpresaAnalisadaFactory::cnpjComRaiz('45237001'))
        ->call('consultarReceita')
        ->assertSet('statusConsultaRfb', 'sucesso')
        ->assertSet('email', '')
        ->assertSet('telefone', '');
});

it('trocar de CNPJ para CPF limpa o contato digitado', function () {
    ($this->fichaConsultada)()
        ->set('email', 'contato@empresa.com.br')
        ->set('telefone', '(11) 98888-7777')
        ->call('usarCpf')
        ->assertSet('email', '')
        ->assertSet('telefone', '');
});

it('o audit e o evento do cadastro não levam o contato', function () {
    ($this->fichaConsultada)()
        ->set('email', 'contato@empresa.com.br')
        ->set('telefone', '(11) 98888-7777')
        ->call('confirmar');

    $log = AuditLog::query()->where('action', 'empresa.cadastrada')->firstOrFail();
    expect(json_encode($log->getAttributes()))->not->toContain('contato@empresa')->not->toContain('988887777');
});

it('na tela da empresa, mostra, corrige e audita sem os valores', function () {
    $empresa = EmpresaAnalisada::factory()->create([
        'usuario_id' => $this->usuario->id,
        'email' => 'antigo@empresa.com.br',
        'telefone' => '11988887777',
    ]);

    $this->get(route('empresas.show', $empresa))->assertOk()
        ->assertSee('antigo@empresa.com.br')
        ->assertSee('Salvar contato');

    Livewire::test(Contato::class, ['empresa' => $empresa])
        ->assertSet('telefone', '(11) 98888-7777')
        ->set('email', 'novo@empresa.com.br')
        ->call('salvar')
        ->assertHasNoErrors()
        ->assertSee('Contato salvo.')
        ->assertSet('telefone', '(11) 98888-7777');

    expect($empresa->fresh()->email)->toBe('novo@empresa.com.br');

    $log = AuditLog::query()->where('action', 'empresa.contato_alterado')->firstOrFail();
    expect($log->context->toArray())->toBe(['campos' => ['email']])
        ->and(json_encode($log->getAttributes()))->not->toContain('novo@empresa');
});

it('na tela da empresa, limpar o campo apaga o dado e salvar sem mudança não audita', function () {
    $empresa = EmpresaAnalisada::factory()->create([
        'usuario_id' => $this->usuario->id,
        'email' => 'antigo@empresa.com.br',
        'telefone' => '11988887777',
    ]);

    $tela = Livewire::test(Contato::class, ['empresa' => $empresa])->call('salvar')->assertHasNoErrors();
    expect(AuditLog::query()->where('action', 'empresa.contato_alterado')->exists())->toBeFalse();

    $tela->set('email', '')->set('telefone', '')->call('salvar')->assertHasNoErrors();
    expect($empresa->fresh()->email)->toBeNull()
        ->and($empresa->fresh()->telefone)->toBeNull();
});

it('na tela da empresa, valor inválido não grava', function () {
    $empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $this->usuario->id, 'email' => null]);

    Livewire::test(Contato::class, ['empresa' => $empresa])
        ->set('email', 'nao-e-email')
        ->call('salvar')
        ->assertHasErrors('email')
        ->assertDontSee('Contato salvo.');

    expect($empresa->fresh()->email)->toBeNull();
});

it('não deixa trocar a empresa alvo nem alcançar empresa de outro usuário', function () {
    $minha = EmpresaAnalisada::factory()->create(['usuario_id' => $this->usuario->id]);
    $alheia = EmpresaAnalisada::factory()->create(['usuario_id' => Usuario::factory()->create()->id, 'email' => null]);

    $tela = Livewire::test(Contato::class, ['empresa' => $minha]);
    expect(fn () => $tela->set('empresaId', $alheia->id))->toThrow(CannotUpdateLockedPropertyException::class);

    // Mesmo que o id chegasse, o Global Scope não resolve empresa alheia.
    $forjado = new Contato;
    $forjado->empresaId = $alheia->id;
    $forjado->email = 'invasor@exemplo.com';
    expect(fn () => $forjado->salvar())->toThrow(ModelNotFoundException::class);
    expect(EmpresaAnalisada::withoutGlobalScopes()->find($alheia->id)->email)->toBeNull();
});

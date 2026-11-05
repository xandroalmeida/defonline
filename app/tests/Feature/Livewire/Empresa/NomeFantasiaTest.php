<?php

declare(strict_types=1);

use App\Actions\CalcularDiagnostico;
use App\Domain\TipoDocumento;
use App\Livewire\Empresa\Cadastrar;
use App\Livewire\Empresa\NomeFantasia;
use App\Models\AuditLog;
use App\Models\EmpresaAnalisada;
use App\Models\Usuario;
use App\Services\Rfb\MockRfbCnpjClient;
use App\Support\Relatorio\PdfDiagnostico;
use Database\Factories\EmpresaAnalisadaFactory;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

/** PDR-017 — nome fantasia informado pelo usuário, sem tocar no dado da Receita. */
beforeEach(function () {
    $this->usuario = Usuario::factory()->create();
    $this->actingAs($this->usuario);
});

function empresaDaReceita(Usuario $usuario, ?string $fantasiaRfb = 'Fantasia Receita', array $extras = []): EmpresaAnalisada
{
    return EmpresaAnalisada::factory()->create([
        'usuario_id' => $usuario->id,
        'tipo_documento' => TipoDocumento::Cnpj,
        'razao_social' => 'Razão Social LTDA',
        'nome_fantasia' => $fantasiaRfb,
        ...$extras,
    ]);
}

it('no cadastro, o campo vem com o nome da Receita e o informado não sobrescreve o oficial', function () {
    $cnpj = EmpresaAnalisadaFactory::cnpjComRaiz('45237000');
    $daReceita = (new MockRfbCnpjClient)->consultarCnpj($cnpj)->nomeFantasia;

    Livewire::test(Cadastrar::class)
        ->set('documento', $cnpj)
        ->call('consultarReceita')
        ->assertSet('nome_fantasia', $daReceita)
        ->set('nome_fantasia', '  Marca do Cliente  ')
        ->call('confirmar')
        ->assertHasNoErrors();

    $empresa = EmpresaAnalisada::query()->where('documento', $cnpj)->firstOrFail();
    expect($empresa->nome_fantasia)->toBe($daReceita)
        ->and($empresa->nome_fantasia_informado)->toBe('Marca do Cliente')
        ->and($empresa->nomeExibicao())->toBe('Marca do Cliente');
});

it('no cadastro, manter o nome da Receita ou apagá-lo não grava nome informado', function (string $digitado) {
    $cnpj = EmpresaAnalisadaFactory::cnpjComRaiz('45237000');
    $daReceita = (new MockRfbCnpjClient)->consultarCnpj($cnpj)->nomeFantasia;

    Livewire::test(Cadastrar::class)
        ->set('documento', $cnpj)
        ->call('consultarReceita')
        ->set('nome_fantasia', $digitado === 'igual' ? $daReceita : $digitado)
        ->call('confirmar')
        ->assertHasNoErrors();

    $empresa = EmpresaAnalisada::query()->where('documento', $cnpj)->firstOrFail();
    expect($empresa->nome_fantasia)->toBe($daReceita)
        ->and($empresa->nome_fantasia_informado)->toBeNull();
})->with(['igual', '']);

it('no cadastro, recusa nome com mais de 255 caracteres', function () {
    $cnpj = EmpresaAnalisadaFactory::cnpjComRaiz('45237000');

    Livewire::test(Cadastrar::class)
        ->set('documento', $cnpj)
        ->call('consultarReceita')
        ->set('nome_fantasia', str_repeat('a', 256))
        ->call('confirmar')
        ->assertHasErrors(['nome_fantasia' => 'max']);

    expect(EmpresaAnalisada::query()->where('documento', $cnpj)->exists())->toBeFalse();
});

it('no cadastro, edita no próprio lugar: só mostra o campo ao editar e aplica o nome', function () {
    $cnpj = EmpresaAnalisadaFactory::cnpjComRaiz('45237000');
    $daReceita = (new MockRfbCnpjClient)->consultarCnpj($cnpj)->nomeFantasia;

    Livewire::test(Cadastrar::class)
        ->set('documento', $cnpj)
        ->call('consultarReceita')
        ->assertDontSeeHtml('wire:model="nomeFantasiaRascunho"')
        ->assertSeeHtml('aria-label="Editar nome fantasia"')
        ->call('editarNomeFantasia')
        ->assertSeeHtml('wire:model="nomeFantasiaRascunho"')
        ->assertSet('nomeFantasiaRascunho', $daReceita)
        ->set('nomeFantasiaRascunho', '  Marca  ')
        ->call('aplicarNomeFantasia')
        ->assertSet('editandoNomeFantasia', false)
        ->assertSet('nome_fantasia', 'Marca')
        ->assertSee('Na Receita: '.$daReceita)
        ->call('confirmar')
        ->assertHasNoErrors();

    expect(EmpresaAnalisada::query()->where('documento', $cnpj)->firstOrFail()->nome_fantasia_informado)->toBe('Marca');
});

it('no cadastro, cancelar descarta o rascunho e aplicar vazio volta ao nome da Receita', function () {
    $cnpj = EmpresaAnalisadaFactory::cnpjComRaiz('45237000');
    $daReceita = (new MockRfbCnpjClient)->consultarCnpj($cnpj)->nomeFantasia;

    Livewire::test(Cadastrar::class)
        ->set('documento', $cnpj)
        ->call('consultarReceita')
        ->call('editarNomeFantasia')
        ->set('nomeFantasiaRascunho', 'Rascunho')
        ->call('cancelarNomeFantasia')
        ->assertSet('editandoNomeFantasia', false)
        ->assertSet('nome_fantasia', $daReceita)
        ->call('editarNomeFantasia')
        ->set('nomeFantasiaRascunho', '   ')
        ->call('aplicarNomeFantasia')
        ->assertSet('nome_fantasia', $daReceita)
        ->assertDontSee('Na Receita:');
});

it('no cadastro, confirmar com a edição aberta grava o que foi digitado', function () {
    $cnpj = EmpresaAnalisadaFactory::cnpjComRaiz('45237000');

    Livewire::test(Cadastrar::class)
        ->set('documento', $cnpj)
        ->call('consultarReceita')
        ->call('editarNomeFantasia')
        ->set('nomeFantasiaRascunho', 'Digitado Sem Aplicar')
        ->call('confirmar')
        ->assertHasNoErrors();

    expect(EmpresaAnalisada::query()->where('documento', $cnpj)->firstOrFail()->nome_fantasia_informado)->toBe('Digitado Sem Aplicar');
});

it('no cadastro, nome longo na edição aberta bloqueia a confirmação e mantém o campo', function () {
    $cnpj = EmpresaAnalisadaFactory::cnpjComRaiz('45237000');

    Livewire::test(Cadastrar::class)
        ->set('documento', $cnpj)
        ->call('consultarReceita')
        ->call('editarNomeFantasia')
        ->set('nomeFantasiaRascunho', str_repeat('a', 256))
        ->call('confirmar')
        ->assertHasErrors(['nomeFantasiaRascunho' => 'max'])
        ->assertSet('editandoNomeFantasia', true);

    expect(EmpresaAnalisada::query()->where('documento', $cnpj)->exists())->toBeFalse();
});

it('no cadastro, nova consulta descarta a edição aberta', function () {
    Livewire::test(Cadastrar::class)
        ->set('documento', EmpresaAnalisadaFactory::cnpjComRaiz('45237000'))
        ->call('consultarReceita')
        ->call('editarNomeFantasia')
        ->set('documento', EmpresaAnalisadaFactory::cnpjComRaiz('45237001'))
        ->assertSet('editandoNomeFantasia', false)
        ->assertSet('nomeFantasiaRascunho', '');
});

it('a consulta seguida de CPF não leva o nome da Receita para o formulário do autônomo', function () {
    Livewire::test(Cadastrar::class)
        ->set('documento', EmpresaAnalisadaFactory::cnpjComRaiz('45237000'))
        ->call('consultarReceita')
        ->call('usarCpf')
        ->assertSet('nome_fantasia', '');
});

it('na tela da empresa, salva o nome em CNPJ, preserva o da Receita e audita', function () {
    $empresa = empresaDaReceita($this->usuario);

    Livewire::test(NomeFantasia::class, ['empresa' => $empresa])
        ->assertSet('nomeFantasia', 'Fantasia Receita')
        ->call('editar')
        ->set('nomeFantasia', 'Marca Nova')
        ->call('salvar')
        ->assertHasNoErrors()
        ->assertRedirect(route('empresas.show', $empresa));

    $empresa->refresh();
    expect($empresa->nome_fantasia)->toBe('Fantasia Receita')
        ->and($empresa->nome_fantasia_informado)->toBe('Marca Nova')
        ->and(AuditLog::query()->where('action', 'empresa.nome_fantasia_alterado')->where('subject_id', $empresa->id)->count())->toBe(1);

    $this->get(route('empresas.show', $empresa))
        ->assertSee('Marca Nova')
        ->assertSee('Nome salvo.')
        ->assertSee('Na Receita: Fantasia Receita');
});

it('apagar o nome informado volta para o da Receita', function () {
    $empresa = empresaDaReceita($this->usuario, extras: ['nome_fantasia_informado' => 'Marca']);

    Livewire::test(NomeFantasia::class, ['empresa' => $empresa])
        ->assertSet('nomeFantasia', 'Marca')
        ->set('nomeFantasia', '')
        ->call('salvar');

    expect($empresa->refresh()->nome_fantasia_informado)->toBeNull()
        ->and($empresa->nomeExibicao())->toBe('Fantasia Receita');
});

it('em CPF, o nome comercial é alterado e pode ser apagado', function () {
    $empresa = EmpresaAnalisada::factory()->create([
        'usuario_id' => $this->usuario->id,
        'tipo_documento' => TipoDocumento::Cpf,
        'razao_social' => 'Fulana de Tal',
        'nome_fantasia' => 'Ateliê',
    ]);

    $componente = Livewire::test(NomeFantasia::class, ['empresa' => $empresa])
        ->assertSeeHtml('aria-label="Editar nome comercial"')
        ->set('nomeFantasia', 'Ateliê Novo')
        ->call('salvar');
    expect($empresa->refresh()->nome_fantasia)->toBe('Ateliê Novo')
        ->and($empresa->nome_fantasia_informado)->toBeNull();

    $componente->set('nomeFantasia', '')->call('salvar');
    expect($empresa->refresh()->nome_fantasia)->toBeNull()
        ->and($empresa->nomeExibicao())->toBe('Fulana de Tal');
});

it('salvar sem alterar não audita', function () {
    $empresa = empresaDaReceita($this->usuario);

    Livewire::test(NomeFantasia::class, ['empresa' => $empresa])
        ->call('editar')
        ->call('salvar')
        ->assertSet('editando', false)
        ->assertNoRedirect();

    expect(AuditLog::query()->where('action', 'empresa.nome_fantasia_alterado')->count())->toBe(0);
});

it('cancelar descarta o texto e volta à exibição', function () {
    $empresa = empresaDaReceita($this->usuario);

    Livewire::test(NomeFantasia::class, ['empresa' => $empresa])
        ->assertDontSeeHtml('wire:model="nomeFantasia"')
        ->call('editar')
        ->assertSeeHtml('wire:model="nomeFantasia"')
        ->set('nomeFantasia', 'Rascunho')
        ->call('cancelar')
        ->assertSet('editando', false)
        ->assertSet('nomeFantasia', 'Fantasia Receita')
        ->assertDontSeeHtml('wire:model="nomeFantasia"');

    expect($empresa->refresh()->nome_fantasia_informado)->toBeNull();
});

it('nome longo demais mantém a edição aberta e mostra o erro', function () {
    $empresa = empresaDaReceita($this->usuario);

    Livewire::test(NomeFantasia::class, ['empresa' => $empresa])
        ->call('editar')
        ->set('nomeFantasia', str_repeat('a', 256))
        ->call('salvar')
        ->assertHasErrors(['nomeFantasia' => 'max'])
        ->assertSet('editando', true);
});

it('empresa com ficha e sem nome mostra o aviso da Receita no lugar do nome', function () {
    $cnpj = EmpresaAnalisadaFactory::cnpjComRaiz('45237000');
    $empresa = empresaDaReceita($this->usuario, null, [
        'documento' => $cnpj,
        'ficha_rfb' => (new MockRfbCnpjClient)->consultarCnpj($cnpj)->ficha->toArray(),
    ]);

    $this->get(route('empresas.show', $empresa))
        ->assertOk()
        ->assertSee('Não informado pela Receita')
        ->assertSeeHtml('aria-label="Editar nome fantasia"')
        ->assertDontSee('Na Receita:');
});

it('não altera empresa de outro usuário', function () {
    $alheia = EmpresaAnalisada::factory()->create([
        'usuario_id' => Usuario::factory()->create()->id,
        'nome_fantasia' => 'Original',
    ]);

    $forjado = new NomeFantasia;
    $forjado->empresaId = $alheia->id;
    $forjado->nomeFantasia = 'Invasão';

    expect(fn () => $forjado->salvar())->toThrow(ModelNotFoundException::class);
    expect(EmpresaAnalisada::withoutGlobalScopes()->find($alheia->id)->nome_fantasia_informado)->toBeNull();
});

it('o relatório usa o nome informado e a capa do PDF mantém a razão social abaixo', function () {
    $empresa = empresaDaReceita($this->usuario, extras: ['nome_fantasia_informado' => 'Marca Informada']);
    $diag = app(CalcularDiagnostico::class)->execute($empresa, [
        'Q01' => 1, 'Q02' => '50000', 'Q03' => '20000', 'Q04' => '10000',
        'Q05' => '300000', 'Q06' => '40000', 'Q07' => '80000', 'Q08' => '50000',
        'Q09' => '100000', 'Q10' => 90, 'Q11' => 15, 'Q12' => 15, 'Q13' => '2',
        'Q14' => '20000', 'Q15' => '8000', 'Q16' => '2000',
    ]);

    $html = PdfDiagnostico::padrao()->html($diag->fresh());
    expect($html)
        ->toContain('<p class="capa__empresa">Marca Informada</p>')
        ->toContain('<p class="capa__razao">Razão Social LTDA</p>')
        ->not->toContain('Fantasia Receita');

    $this->get(route('diagnosticos.show', $diag))
        ->assertSee('Marca Informada')
        ->assertSeeInOrder(['Marca Informada', 'Razão Social LTDA', 'Diagnóstico de']);
});

it('sem nome fantasia, o relatório mostra a razão social uma vez só na capa', function () {
    $empresa = empresaDaReceita($this->usuario, null);
    $diag = app(CalcularDiagnostico::class)->execute($empresa, [
        'Q01' => 1, 'Q02' => '50000', 'Q03' => '20000', 'Q04' => '10000',
        'Q05' => '300000', 'Q06' => '40000', 'Q07' => '80000', 'Q08' => '50000',
        'Q09' => '100000', 'Q10' => 90, 'Q11' => 15, 'Q12' => 15, 'Q13' => '2',
        'Q14' => '20000', 'Q15' => '8000', 'Q16' => '2000',
    ]);

    expect(PdfDiagnostico::padrao()->html($diag->fresh()))
        ->toContain('<p class="capa__empresa">Razão Social LTDA</p>')
        ->not->toContain('<p class="capa__razao">Razão Social LTDA</p>');

    $this->get(route('diagnosticos.show', $diag))
        ->assertSee('Razão Social LTDA')
        ->assertDontSeeHtml('page-header-detalhe');
});

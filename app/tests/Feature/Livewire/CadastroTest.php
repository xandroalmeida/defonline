<?php

declare(strict_types=1);

use App\Livewire\Cadastro;
use App\Models\AuditLog;
use App\Models\Usuario;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;

/**
 * CA-1, CA-2, CA-5, CA-6 da STORY-011.
 */
const CPF_VALIDO_A = '529.982.247-25';
const CPF_VALIDO_B = '111.444.777-35';

it('exibe o formulário de cadastro na rota /cadastro', function () {
    $this->get('/cadastro')
        ->assertOk()
        ->assertSee('Criar conta')
        ->assertSee('CPF')
        ->assertSee('Telefone WhatsApp');
});

it('persiste usuário em "usuarios" com senha hash e redireciona para login (CA-1, CA-5)', function () {
    Livewire::test(Cadastro::class)
        ->set('cpf', CPF_VALIDO_A)
        ->set('nome', 'Roberto Souza')
        ->set('email', 'roberto@exemplo.com.br')
        ->set('senha', 'Senha1234')
        ->set('senha_confirmation', 'Senha1234')
        ->set('telefone', '(11) 98888-7777')
        ->call('submit')
        ->assertRedirect('/login');

    $usuario = Usuario::firstWhere('email', 'roberto@exemplo.com.br');
    expect($usuario)->not->toBeNull();
    expect($usuario->cpf)->toBe('52998224725');                  // normalizado a dígitos
    expect($usuario->nome)->toBe('Roberto Souza');
    expect($usuario->telefone)->toBe('11988887777');
    expect(Hash::check('Senha1234', $usuario->senha_hash))->toBeTrue();
    expect($usuario->senha_hash)->not->toBe('Senha1234');         // nunca texto claro
});

it('grava audit_log "usuario.cadastrado" após criar conta (CA-6)', function () {
    Livewire::test(Cadastro::class)
        ->set('cpf', CPF_VALIDO_A)
        ->set('nome', 'Roberto Souza')
        ->set('email', 'roberto@exemplo.com.br')
        ->set('senha', 'Senha1234')
        ->set('senha_confirmation', 'Senha1234')
        ->set('telefone', '11988887777')
        ->call('submit');

    $usuario = Usuario::firstWhere('email', 'roberto@exemplo.com.br');
    $audit = AuditLog::where('action', 'usuario.cadastrado')->first();
    expect($audit)->not->toBeNull();
    expect($audit->subject_type)->toBe('Usuario');
    expect($audit->subject_id)->toBe($usuario->id);
    expect($audit->usuario_id)->toBe($usuario->id);
    expect($audit->actor_type)->toBe('user');
});

it('rejeita CPF inválido com mensagem específica (CA-2)', function () {
    Livewire::test(Cadastro::class)
        ->set('cpf', '111.111.111-11')               // sequência trivial — DV ok mas regra RFB rejeita
        ->set('nome', 'Roberto')
        ->set('email', 'roberto@exemplo.com.br')
        ->set('senha', 'Senha1234')
        ->set('senha_confirmation', 'Senha1234')
        ->set('telefone', '11988887777')
        ->call('submit')
        ->assertHasErrors(['cpf'])
        ->assertNoRedirect();

    expect(Usuario::count())->toBe(0);
});

it('rejeita email inválido (CA-2)', function () {
    Livewire::test(Cadastro::class)
        ->set('cpf', CPF_VALIDO_A)
        ->set('nome', 'Roberto')
        ->set('email', 'nao-eh-email')
        ->set('senha', 'Senha1234')
        ->set('senha_confirmation', 'Senha1234')
        ->set('telefone', '11988887777')
        ->call('submit')
        ->assertHasErrors(['email']);

    expect(Usuario::count())->toBe(0);
});

it('rejeita CPF já existente com mensagem genérica (CA-2)', function () {
    Usuario::factory()->create(['cpf' => '52998224725']);

    $component = Livewire::test(Cadastro::class)
        ->set('cpf', CPF_VALIDO_A)                    // mesmo CPF, normalizado bate
        ->set('nome', 'Roberto')
        ->set('email', 'outro@exemplo.com.br')
        ->set('senha', 'Senha1234')
        ->set('senha_confirmation', 'Senha1234')
        ->set('telefone', '11988887777')
        ->call('submit')
        ->assertHasErrors(['cpf']);

    $erros = $component->errors()->get('cpf');
    expect($erros[0])->toBe('Este dado já está em uso.');         // sem vazar "já existe"
    expect(Usuario::count())->toBe(1);
});

it('rejeita email já existente com mensagem genérica (CA-2)', function () {
    Usuario::factory()->create(['email' => 'roberto@exemplo.com.br']);

    $component = Livewire::test(Cadastro::class)
        ->set('cpf', CPF_VALIDO_B)
        ->set('nome', 'Roberto')
        ->set('email', 'roberto@exemplo.com.br')
        ->set('senha', 'Senha1234')
        ->set('senha_confirmation', 'Senha1234')
        ->set('telefone', '11988887777')
        ->call('submit')
        ->assertHasErrors(['email']);

    expect($component->errors()->get('email')[0])->toBe('Este dado já está em uso.');
});

it('exige senha com mínimo de 8 caracteres + letras + números (CA-1)', function () {
    Livewire::test(Cadastro::class)
        ->set('cpf', CPF_VALIDO_A)
        ->set('nome', 'Roberto')
        ->set('email', 'roberto@exemplo.com.br')
        ->set('senha', 'curta')
        ->set('senha_confirmation', 'curta')
        ->set('telefone', '11988887777')
        ->call('submit')
        ->assertHasErrors(['senha']);
});

it('exige confirmação de senha igual', function () {
    Livewire::test(Cadastro::class)
        ->set('cpf', CPF_VALIDO_A)
        ->set('nome', 'Roberto')
        ->set('email', 'roberto@exemplo.com.br')
        ->set('senha', 'Senha1234')
        ->set('senha_confirmation', 'Outra1234')
        ->set('telefone', '11988887777')
        ->call('submit')
        ->assertHasErrors(['senha']);
});

it('rejeita telefone fora do formato BR', function () {
    Livewire::test(Cadastro::class)
        ->set('cpf', CPF_VALIDO_A)
        ->set('nome', 'Roberto')
        ->set('email', 'roberto@exemplo.com.br')
        ->set('senha', 'Senha1234')
        ->set('senha_confirmation', 'Senha1234')
        ->set('telefone', '123')
        ->call('submit')
        ->assertHasErrors(['telefone']);
});

it('emite mensagens de validação em pt-BR (não em inglês)', function () {
    $component = Livewire::test(Cadastro::class)
        ->set('cpf', '')
        ->set('nome', '')
        ->set('email', '')
        ->set('senha', '')
        ->set('senha_confirmation', '')
        ->set('telefone', '')
        ->call('submit');

    expect($component->errors()->get('cpf')[0])->toBe('Informe o CPF.');
    expect($component->errors()->get('nome')[0])->toBe('Informe o nome completo.');
    expect($component->errors()->get('email')[0])->toBe('Informe o email.');
    expect($component->errors()->get('senha')[0])->toBe('Informe a senha.');
    expect($component->errors()->get('telefone')[0])->toBe('Informe o telefone WhatsApp.');
});

it('emite log info "usuario.cadastrado" com PII mascarada (CA-6)', function () {
    Log::spy();

    Livewire::test(Cadastro::class)
        ->set('cpf', CPF_VALIDO_A)
        ->set('nome', 'Roberto Souza')
        ->set('email', 'roberto@exemplo.com.br')
        ->set('senha', 'Senha1234')
        ->set('senha_confirmation', 'Senha1234')
        ->set('telefone', '11988887777')
        ->call('submit');

    // O contexto chega ao Monolog e o LogSanitizer mascara em runtime (testado
    // em LogSanitizerTest). Aqui só verificamos que o evento foi emitido.
    Log::shouldHaveReceived('info')->with('usuario.cadastrado', Mockery::any())->once();
});

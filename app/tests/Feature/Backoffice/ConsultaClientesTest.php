<?php

declare(strict_types=1);

use App\Livewire\Backoffice\Empresas;
use App\Livewire\Backoffice\FichaEmpresa;
use App\Livewire\Backoffice\FichaUsuario;
use App\Livewire\Backoffice\Usuarios;
use App\Models\AuditLog;
use App\Models\DemandaCaptacao;
use App\Models\Diagnostico;
use App\Models\EmpresaAnalisada;
use App\Models\LoteCredito;
use App\Models\Usuario;
use App\Support\BuscaBackoffice;
use App\Support\Telefone;
use Livewire\Livewire;

/** STORY-104 — consulta de clientes no backoffice: busca ampliada, empresas e fichas. */
beforeEach(function () {
    $this->admin = Usuario::factory()->create(['nome' => 'Ana Admin']);
    $this->admin->forceFill(['permissoes_admin' => ['usuarios.ver']])->save();

    $this->cliente = Usuario::factory()->create([
        'nome' => 'Roberto Cliente', 'email' => 'roberto@exemplo.com.br', 'cpf' => '52998224725', 'telefone' => '85987654321',
    ]);
    $this->empresa = EmpresaAnalisada::factory()->create([
        'usuario_id' => $this->cliente->id, 'razao_social' => 'Metalúrgica Fortaleza Ltda', 'nome_fantasia' => 'Metal Forte',
        'documento' => '11222333000181', 'telefone' => '8533334444', 'email' => 'contato@metalforte.com.br',
    ]);
    $this->outro = Usuario::factory()->create(['nome' => 'Zélia Outra', 'cpf' => '11144477735']);
});

// ---------------------------------------------------------------- busca em Usuários

it('acha o usuário por CPF, com ou sem máscara', function (string $termo) {
    Livewire::actingAs($this->admin)->test(Usuarios::class)->set('termo', $termo)->call('buscar')
        ->assertSee('Roberto Cliente')->assertDontSee('Zélia Outra');
})->with(['sem máscara' => ['52998224725'], 'com máscara' => ['529.982.247-25'], 'parcial' => ['9822']]);

it('acha o usuário por telefone e pelo CNPJ de uma empresa dele', function (string $termo) {
    Livewire::actingAs($this->admin)->test(Usuarios::class)->set('termo', $termo)->call('buscar')
        ->assertSee('Roberto Cliente')->assertDontSee('Zélia Outra');
})->with(['telefone' => ['(85) 98765-4321'], 'CNPJ' => ['11.222.333/0001-81']]);

it('com poucos dígitos, não busca por documento', function () {
    Livewire::actingAs($this->admin)->test(Usuarios::class)->set('termo', '529')->call('buscar')
        ->assertDontSee('Roberto Cliente');
});

it('a auditoria da busca mascara os dígitos', function () {
    Livewire::actingAs($this->admin)->test(Usuarios::class)->set('termo', '529.982.247-25')->call('buscar');

    $log = AuditLog::query()->where('action', 'backoffice.usuarios_listados')->get()->last(fn ($l) => $l->context['busca'] !== null);
    expect($log->context['busca'])->toBe('***.***.***-**');
});

it('a lista de usuários leva à ficha', function () {
    Livewire::actingAs($this->admin)->test(Usuarios::class)->assertSee(route('backoffice.usuarios.ficha', $this->cliente->id));
});

// ---------------------------------------------------------------- Empresas

it('lista as empresas de todos os usuários e busca por nome, CNPJ, telefone ou e-mail', function (string $termo) {
    EmpresaAnalisada::factory()->create(['usuario_id' => $this->outro->id, 'razao_social' => 'Padaria Outra Ltda', 'nome_fantasia' => 'Padaria', 'documento' => '99888777000166']);

    Livewire::actingAs($this->admin)->test(Empresas::class)->set('termo', $termo)->call('buscar')
        ->assertSee('Metal Forte')->assertDontSee('Padaria');
})->with(['nome' => ['metalúrgica'], 'CNPJ' => ['11.222.333'], 'telefone' => ['3333-4444'], 'e-mail' => ['metalforte']]);

it('sem busca, a lista mostra as empresas com dono e contato', function () {
    Livewire::actingAs($this->admin)->test(Empresas::class)
        ->assertSee('Metal Forte')->assertSee('11.222.333/0001-81')->assertSee('(85) 3333-4444')->assertSee('Roberto Cliente');
});

it('busca de empresa que não existe mostra a lista vazia', function () {
    Livewire::actingAs($this->admin)->test(Empresas::class)->set('termo', 'inexistente')->call('buscar')
        ->assertSee('Nenhuma empresa encontrada.');
});

// ---------------------------------------------------------------- Fichas

it('a ficha do usuário mostra CPF completo, WhatsApp, plano, créditos, empresas e diagnósticos, e é auditada', function () {
    LoteCredito::query()->create(['usuario_id' => $this->cliente->id, 'quantidade_comprada' => 5, 'quantidade_restante' => 3, 'status' => 'ativo', 'data_compra' => now(), 'data_expiracao' => now()->addMonth()]);
    LoteCredito::query()->create(['usuario_id' => $this->cliente->id, 'quantidade_comprada' => 5, 'quantidade_restante' => 5, 'status' => 'ativo', 'data_compra' => now()->subYear(), 'data_expiracao' => now()->subDay()]);
    $diag = Diagnostico::factory()->create(['usuario_id' => $this->cliente->id, 'empresa_analisada_id' => $this->empresa->id]);

    $this->actingAs($this->admin)->get('/backoffice/usuarios/'.$this->cliente->id)->assertOk()
        ->assertSee('Roberto Cliente')
        ->assertSee('529.982.247-25')
        ->assertSee('https://wa.me/5585987654321')
        ->assertSee('Nenhum')
        ->assertSeeInOrder(['Créditos avulsos', '3'])
        ->assertSee('Metal Forte')
        ->assertSee(route('backoffice.empresas.ficha', $this->empresa->id))
        ->assertSee('dusk="ficha-diagnostico-'.$diag->id.'"', false);

    expect(AuditLog::query()->where('action', 'backoffice.usuario_visto')->where('subject_id', $this->cliente->id)->count())->toBe(1);
});

it('a ficha do usuário anonimizado não mostra dados pessoais', function () {
    $this->cliente->forceFill(['anonimizado_at' => now()])->save();

    $this->actingAs($this->admin)->get('/backoffice/usuarios/'.$this->cliente->id)->assertOk()
        ->assertSee('Usuário anonimizado')->assertDontSee('529.982.247-25');
});

it('a ficha da empresa mostra documento, contato, dono e diagnósticos, e é auditada', function () {
    $diag = Diagnostico::factory()->create(['usuario_id' => $this->cliente->id, 'empresa_analisada_id' => $this->empresa->id]);
    DemandaCaptacao::query()->create(['diagnostico_id' => $diag->id, 'usuario_id' => $this->cliente->id, 'empresa_analisada_id' => $this->empresa->id, 'valor_captar' => '1000', 'autoriza_contato' => true]);

    $this->actingAs($this->admin)->get('/backoffice/empresas/'.$this->empresa->id)->assertOk()
        ->assertSee('Metal Forte')
        ->assertSee('11.222.333/0001-81')
        ->assertSee('https://wa.me/558533334444')
        ->assertSee('contato@metalforte.com.br')
        ->assertSee(route('backoffice.usuarios.ficha', $this->cliente->id))
        ->assertSee('dusk="ficha-diagnostico-'.$diag->id.'"', false)
        // Sem a permissão de captação, só indica que houve demanda.
        ->assertDontSee('Ver demanda');

    expect(AuditLog::query()->where('action', 'backoffice.empresa_vista')->where('subject_id', $this->empresa->id)->count())->toBe(1);
});

it('com a permissão de captação, a ficha leva à demanda', function () {
    $this->admin->forceFill(['permissoes_admin' => ['usuarios.ver', 'captacao.gerenciar']])->save();
    $diag = Diagnostico::factory()->create(['usuario_id' => $this->cliente->id, 'empresa_analisada_id' => $this->empresa->id]);
    $demanda = DemandaCaptacao::query()->create(['diagnostico_id' => $diag->id, 'usuario_id' => $this->cliente->id, 'empresa_analisada_id' => $this->empresa->id, 'valor_captar' => '1000', 'autoriza_contato' => true]);

    $this->actingAs($this->admin)->get('/backoffice/empresas/'.$this->empresa->id)->assertOk()
        ->assertSee(route('backoffice.captacao', ['demanda' => $demanda->id]), false);
});

it('a ficha da empresa excluída continua acessível, marcada', function () {
    $this->empresa->delete();

    $this->actingAs($this->admin)->get('/backoffice/empresas/'.$this->empresa->id)->assertOk()->assertSee('Excluída');
});

it('id inexistente responde 404', function (string $rota) {
    $this->actingAs($this->admin)->get($rota.'019a0000-0000-7000-8000-000000000000')->assertNotFound();
})->with(['usuário' => ['/backoffice/usuarios/'], 'empresa' => ['/backoffice/empresas/']]);

it('sem a permissão de ver usuários, tudo responde 404', function (string $rota) {
    $outroAdmin = Usuario::factory()->create();
    $outroAdmin->forceFill(['permissoes_admin' => ['glossario.editar']])->save();
    $rota = str_replace(['{u}', '{e}'], [$this->cliente->id, $this->empresa->id], $rota);

    $this->actingAs($outroAdmin)->get($rota)->assertNotFound();
})->with(['/backoffice/empresas', '/backoffice/usuarios/{u}', '/backoffice/empresas/{e}']);

it('as ações das fichas também exigem a permissão', function () {
    $outroAdmin = Usuario::factory()->create();
    $outroAdmin->forceFill(['permissoes_admin' => ['glossario.editar']])->save();

    Livewire::actingAs($outroAdmin)->test(FichaUsuario::class, ['usuario' => $this->cliente->id])->assertNotFound();
    Livewire::actingAs($outroAdmin)->test(FichaEmpresa::class, ['empresa' => $this->empresa->id])->assertNotFound();
    Livewire::actingAs($outroAdmin)->test(Empresas::class)->assertNotFound();
});

it('o menu mostra Empresas para quem vê usuários', function () {
    $this->actingAs($this->admin)->get('/backoffice/empresas')->assertOk()->assertSee('dusk="app-nav-backoffice-empresas"', false);
});

// ---------------------------------------------------------------- helpers

it('formata telefone e monta o link do WhatsApp', function (?string $entrada, ?string $formatado, ?string $link) {
    expect(Telefone::formatar($entrada))->toBe($formatado)->and(Telefone::whatsapp($entrada))->toBe($link);
})->with([
    'celular' => ['85987654321', '(85) 98765-4321', 'https://wa.me/5585987654321'],
    'fixo' => ['8533334444', '(85) 3333-4444', 'https://wa.me/558533334444'],
    'com máscara' => ['(85) 98765-4321', '(85) 98765-4321', 'https://wa.me/5585987654321'],
    'curto' => ['12345', '12345', null],
    'vazio' => [null, null, null],
]);

it('a busca separa texto e dígitos e mascara os dígitos na auditoria', function () {
    $b = new BuscaBackoffice('  Ana 529.982 ');
    expect($b->texto)->toBe('Ana 529.982')->and($b->digitos)->toBe('529982')->and($b->paraAuditoria())->toBe('Ana ***.***')
        ->and((new BuscaBackoffice('ana'))->paraAuditoria())->toBe('ana')
        ->and((new BuscaBackoffice('ana'))->digitos)->toBeNull()
        ->and((new BuscaBackoffice(''))->paraAuditoria())->toBeNull();
});

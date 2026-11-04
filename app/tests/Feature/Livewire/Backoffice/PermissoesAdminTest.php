<?php

declare(strict_types=1);

use App\Livewire\Backoffice\Administradores;
use App\Livewire\Backoffice\Glossario;
use App\Livewire\Backoffice\Usuarios;
use App\Models\AuditLog;
use App\Models\Usuario;
use Illuminate\Support\Js;
use Livewire\Livewire;

/**
 * STORY-067 / PDR-013 — permissões de admin.
 */
beforeEach(function () {
    config(['backoffice.equipe_emails' => ['raiz@ebparcerias.com.br']]);
    $this->raiz = Usuario::factory()->create(['nome' => 'Admin Raiz', 'email' => 'Raiz@EBParcerias.com.br']);
    $this->comPermissoes = function (array $permissoes): Usuario {
        $usuario = Usuario::factory()->create();
        $usuario->forceFill(['permissoes_admin' => $permissoes])->save();

        return $usuario;
    };
});

describe('CA-1 acesso por permissão', function () {
    it('libera cada tela só para quem tem a permissão dela, com 404 nas demais', function (string $permissao, string $liberada) {
        $admin = ($this->comPermissoes)([$permissao]);

        foreach (['/backoffice/usuarios', '/backoffice/administradores', '/backoffice/glossario'] as $url) {
            $resposta = $this->actingAs($admin)->get($url);
            $url === $liberada ? $resposta->assertOk() : $resposta->assertNotFound();
        }
    })->with([
        ['usuarios.ver', '/backoffice/usuarios'],
        ['admins.gerenciar', '/backoffice/administradores'],
        ['glossario.editar', '/backoffice/glossario'],
    ]);

    it('dá todas as telas a quem está em BACKOFFICE_EMAILS', function () {
        foreach (['/backoffice/usuarios', '/backoffice/administradores', '/backoffice/glossario'] as $url) {
            $this->actingAs($this->raiz)->get($url)->assertOk();
        }
    });

    it('devolve 404 ao usuário comum e manda o anônimo para o login', function () {
        foreach (['/backoffice/administradores', '/backoffice/glossario'] as $url) {
            $this->actingAs(Usuario::factory()->create())->get($url)->assertNotFound();
        }
        auth()->logout();
        $this->get('/backoffice/glossario')->assertRedirect('/login');
    });

    it('ignora permissão desconhecida gravada na conta', function () {
        $admin = ($this->comPermissoes)(['nao.existe']);

        $this->actingAs($admin)->get('/backoffice/usuarios')->assertNotFound();
    });

    it('barra a ação Livewire de quem perdeu a permissão com a tela aberta', function () {
        $admin = ($this->comPermissoes)(['admins.gerenciar']);
        $alvo = Usuario::factory()->create(['email' => 'maria@exemplo.com']);
        $tela = Livewire::actingAs($admin)->test(Administradores::class)
            ->set('email', 'maria@exemplo.com')
            ->set('permissoes', ['admins.gerenciar']);

        $admin->forceFill(['permissoes_admin' => ['usuarios.ver']])->save();
        auth()->setUser($admin);

        $tela->call('salvar')->assertStatus(404);

        expect($alvo->fresh()->permissoes_admin)->toBeNull();
    });

    it('recusa montar as telas sem a permissão', function (string $componente) {
        Livewire::actingAs(($this->comPermissoes)(['nao.existe']))->test($componente)->assertStatus(404);
    })->with([Usuarios::class, Administradores::class, Glossario::class]);
});

describe('CA-2 menu', function () {
    it('mostra só os itens das permissões do admin', function () {
        $html = $this->actingAs(($this->comPermissoes)(['glossario.editar']))->get('/home')->assertOk()->getContent();

        expect($html)->toContain('app-nav-admin')
            ->toContain('app-nav-backoffice-glossario')
            ->not->toContain('app-nav-backoffice-usuarios')
            ->not->toContain('app-nav-backoffice-administradores');
    });

    it('mostra tudo para quem está em BACKOFFICE_EMAILS e nada para o usuário comum', function () {
        $this->actingAs($this->raiz)->get('/home')
            ->assertSee('app-nav-backoffice-usuarios', escape: false)
            ->assertSee('app-nav-backoffice-administradores', escape: false)
            ->assertSee('app-nav-backoffice-glossario', escape: false);

        $this->actingAs(Usuario::factory()->create())->get('/home')
            ->assertDontSee('app-nav-admin', escape: false);
    });
});

describe('CA-3 cadastro de admin', function () {
    it('dá as permissões selecionadas a uma conta existente, sem diferenciar maiúsculas', function () {
        $alvo = Usuario::factory()->create(['email' => 'maria@exemplo.com']);

        Livewire::actingAs($this->raiz)->test(Administradores::class)
            ->set('email', ' MARIA@exemplo.com ')
            ->set('permissoes', ['glossario.editar'])
            ->call('salvar')
            ->assertHasNoErrors()
            ->assertSet('email', '')
            ->assertSee('maria@exemplo.com')
            ->assertSee('Manter glossário');

        expect($alvo->fresh()->permissoes_admin)->toBe(['glossario.editar']);
    });

    it('Logar como traz junto Ver usuários', function () {
        $alvo = Usuario::factory()->create(['email' => 'maria@exemplo.com']);

        Livewire::actingAs($this->raiz)->test(Administradores::class)
            ->set('email', 'maria@exemplo.com')->set('permissoes', ['usuarios.logar_como'])->call('salvar');

        expect($alvo->fresh()->permissoes_admin)->toBe(['usuarios.ver', 'usuarios.logar_como']);
    });

    it('recusa e-mail vazio, inválido ou sem conta, e seleção vazia ou inválida', function (string $email, array $permissoes, string $campo) {
        Usuario::factory()->create(['email' => 'maria@exemplo.com']);

        Livewire::actingAs($this->raiz)->test(Administradores::class)
            ->set('email', $email)->set('permissoes', $permissoes)->call('salvar')
            ->assertHasErrors($campo);
    })->with([
        'vazio' => ['', ['usuarios.ver'], 'email'],
        'inválido' => ['abc', ['usuarios.ver'], 'email'],
        'sem conta' => ['ninguem@exemplo.com', ['usuarios.ver'], 'email'],
        'nenhuma permissão' => ['maria@exemplo.com', [], 'permissoes'],
        'permissão inexistente' => ['maria@exemplo.com', ['tudo'], 'permissoes.0'],
    ]);

    it('recusa conta sem e-mail confirmado, excluída ou anonimizada', function (array $atributos) {
        Usuario::factory()->create(['email' => 'maria@exemplo.com', ...$atributos]);

        Livewire::actingAs($this->raiz)->test(Administradores::class)
            ->set('email', 'maria@exemplo.com')->set('permissoes', ['usuarios.ver'])->call('salvar')
            ->assertHasErrors('email');

        expect(Usuario::withTrashed()->where('email', 'maria@exemplo.com')->first()->permissoes_admin)->toBeNull();
    })->with([
        'não confirmada' => [['email_confirmed_at' => null]],
        'excluída' => [['deleted_at' => now()]],
        'anonimizada' => [['anonimizado_at' => now()]],
    ]);

    it('ninguém altera a si mesmo nem quem está em BACKOFFICE_EMAILS', function () {
        $gestor = ($this->comPermissoes)(['admins.gerenciar']);

        Livewire::actingAs($gestor)->test(Administradores::class)
            ->set('email', $gestor->email)->set('permissoes', ['glossario.editar'])->call('salvar')
            ->assertHasErrors('email')
            ->call('remover', $this->raiz->id)
            ->assertHasErrors('email');

        expect($gestor->fresh()->permissoes_admin)->toBe(['admins.gerenciar'])
            ->and($this->raiz->fresh()->ehEquipeBackoffice())->toBeTrue();
    });

    it('edita e remove, tirando o acesso de quem fica sem permissão', function () {
        $alvo = ($this->comPermissoes)(['usuarios.ver']);

        $tela = Livewire::actingAs($this->raiz)->test(Administradores::class)
            ->call('editar', $alvo->id)
            ->assertSet('email', $alvo->email)
            ->assertSet('permissoes', ['usuarios.ver'])
            ->set('permissoes', ['glossario.editar'])
            ->call('salvar');
        expect($alvo->fresh()->permissoes_admin)->toBe(['glossario.editar']);

        $tela->call('remover', $alvo->id);
        expect($alvo->fresh()->permissoes_admin)->toBeNull()
            ->and($alvo->fresh()->ehEquipeBackoffice())->toBeFalse();
    });

    it('o Remover pede confirmação no alerta do app, citando o nome, em botão de alerta', function () {
        $alvo = ($this->comPermissoes)(['usuarios.ver']);
        $alvo->forceFill(['nome' => 'Maria Pereira'])->save();

        $html = $this->actingAs($this->raiz)->get('/backoffice/administradores')->assertOk()->getContent();

        expect($html)->toContain('btn btn--destructive btn--sm')
            ->toContain('pedir-confirmacao')
            ->toContain(Js::from('Remover o acesso de administração de “Maria Pereira”?')->toHtml())
            ->not->toContain('wire:confirm');
    });

    it('não permite definir permissões no cadastro em massa', function () {
        $usuario = new Usuario(['permissoes_admin' => ['admins.gerenciar']]);

        expect($usuario->permissoes_admin)->toBeNull();
    });

    it('audita a alteração com antes, depois e ator', function () {
        $alvo = ($this->comPermissoes)(['usuarios.ver']);

        Livewire::actingAs($this->raiz)->test(Administradores::class)->call('remover', $alvo->id);

        $log = AuditLog::where('action', 'admin.permissoes_alteradas')->first();
        expect($log->actor_id)->toBe($this->raiz->id)
            ->and($log->subject_id)->toBe($alvo->id)
            ->and($log->before['permissoes'])->toBe(['usuarios.ver'])
            ->and($log->after['permissoes'])->toBeNull();
    });
});

describe('CA-4 Logar como', function () {
    it('exige a permissão, e a tela esconde o botão de quem só pode ver', function () {
        $alvo = Usuario::factory()->create();
        $soVer = ($this->comPermissoes)(['usuarios.ver']);
        $pode = ($this->comPermissoes)(['usuarios.ver', 'usuarios.logar_como']);

        $this->actingAs($soVer)->get('/backoffice/usuarios')->assertOk()
            ->assertDontSee('/usuarios/'.$alvo->id.'/logar-como', escape: false);
        $this->actingAs($soVer)->post('/backoffice/usuarios/'.$alvo->id.'/logar-como')->assertNotFound();

        $this->actingAs($pode)->get('/backoffice/usuarios')
            ->assertSee('/usuarios/'.$alvo->id.'/logar-como', escape: false);
        $this->actingAs($pode)->post('/backoffice/usuarios/'.$alvo->id.'/logar-como')->assertRedirect('/home');
    });

    it('não assume admin que também pode Logar como', function () {
        $outroAdmin = ($this->comPermissoes)(['usuarios.logar_como']);

        $this->actingAs($this->raiz)->get('/backoffice/usuarios')
            ->assertDontSee('/usuarios/'.$outroAdmin->id.'/logar-como', escape: false);
        $this->actingAs($this->raiz)->post('/backoffice/usuarios/'.$outroAdmin->id.'/logar-como')->assertNotFound();
    });

    it('assume admin sem essa permissão, e a sessão não usa o backoffice dele', function () {
        $outroAdmin = ($this->comPermissoes)(['glossario.editar']);

        $this->actingAs($this->raiz)->get('/backoffice/usuarios')
            ->assertSee('/usuarios/'.$outroAdmin->id.'/logar-como', escape: false);
        $this->actingAs($this->raiz)->post('/backoffice/usuarios/'.$outroAdmin->id.'/logar-como')->assertRedirect('/home');

        expect(auth()->id())->toBe($outroAdmin->id);
        $this->get('/backoffice/glossario')->assertNotFound();
        $this->get('/home')->assertOk()->assertDontSee('app-nav-backoffice-glossario', escape: false);
    });

    it('sessão de Logar como não usa o backoffice se o usuário assumido virar admin', function () {
        $alvo = Usuario::factory()->create();
        $this->actingAs($this->raiz)->post('/backoffice/usuarios/'.$alvo->id.'/logar-como');

        $alvo->forceFill(['permissoes_admin' => ['glossario.editar']])->save();
        auth()->setUser($alvo->fresh());

        $this->get('/backoffice/glossario')->assertNotFound();
    });

    it('derruba a sessão de quem perde a permissão de Logar como', function () {
        $alvo = Usuario::factory()->create();
        $admin = ($this->comPermissoes)(['usuarios.logar_como']);
        $this->actingAs($admin)->post('/backoffice/usuarios/'.$alvo->id.'/logar-como');

        $admin->forceFill(['permissoes_admin' => ['usuarios.ver']])->save();

        $this->get('/home')->assertRedirect('/login');
    });
});

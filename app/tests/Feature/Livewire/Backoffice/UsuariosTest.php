<?php

declare(strict_types=1);

use App\Livewire\Backoffice\Usuarios;
use App\Models\AuditLog;
use App\Models\Diagnostico;
use App\Models\EmpresaAnalisada;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/**
 * STORY-065 — backoffice: lista de usuários (só-leitura, PDR-011).
 */
beforeEach(function () {
    config(['backoffice.equipe_emails' => ['equipe@ebparcerias.com.br']]);
    $this->equipe = Usuario::factory()->create([
        'nome' => 'Equipe EBP',
        'email' => 'Equipe@EBParcerias.com.br',
        'created_at' => now()->subYear(),
    ]);
});

describe('CA-1 acesso', function () {
    it('manda o anônimo para o login', function () {
        $this->get('/backoffice/usuarios')->assertRedirect('/login');
    });

    it('devolve 404 para usuário logado fora da allowlist', function () {
        $this->actingAs(Usuario::factory()->create())
            ->get('/backoffice/usuarios')
            ->assertNotFound();
    });

    it('libera o membro da equipe, sem diferenciar maiúsculas no e-mail', function () {
        $this->actingAs($this->equipe)->get('/backoffice/usuarios')->assertOk()
            ->assertSee('Usuários cadastrados');
    });

    it('bloqueia todo mundo quando a allowlist está vazia', function () {
        config(['backoffice.equipe_emails' => []]);

        $this->actingAs($this->equipe)->get('/backoffice/usuarios')->assertNotFound();
    });

    it('mostra a seção Administração no menu só para a equipe (CA-8)', function () {
        $this->actingAs($this->equipe)->get('/home')->assertOk()
            ->assertSee('dusk="app-nav-admin"', escape: false)
            ->assertSee('Administração')
            ->assertSee(route('backoffice.usuarios'), escape: false);

        $this->actingAs(Usuario::factory()->create())->get('/home')->assertOk()
            ->assertDontSee('app-nav-admin', escape: false)
            ->assertDontSee('Administração')
            ->assertDontSee('/backoffice', escape: false);
    });

    it('marca o item Usuários como ativo na própria tela (CA-8)', function () {
        $html = $this->actingAs($this->equipe)->get('/backoffice/usuarios')->getContent();

        expect($html)->toMatch('/<a href="[^"]*\/backoffice\/usuarios"\s+class="nav-item\s+is-active\s*"\s+aria-current="page"/');
    });
});

it('lê a allowlist de BACKOFFICE_EMAILS separada por vírgula, normalizada', function () {
    $original = [$_SERVER['BACKOFFICE_EMAILS'] ?? null, $_ENV['BACKOFFICE_EMAILS'] ?? null, getenv('BACKOFFICE_EMAILS')];
    $valor = ' A@x.com , ,b@Y.com ';
    $_SERVER['BACKOFFICE_EMAILS'] = $_ENV['BACKOFFICE_EMAILS'] = $valor;
    putenv("BACKOFFICE_EMAILS={$valor}");

    try {
        $config = require config_path('backoffice.php');
        expect($config['equipe_emails'])->toBe(['a@x.com', 'b@y.com']);
    } finally {
        [$server, $env, $put] = $original;
        if ($server === null) {
            unset($_SERVER['BACKOFFICE_EMAILS']);
        } else {
            $_SERVER['BACKOFFICE_EMAILS'] = $server;
        }
        if ($env === null) {
            unset($_ENV['BACKOFFICE_EMAILS']);
        } else {
            $_ENV['BACKOFFICE_EMAILS'] = $env;
        }
        putenv($put === false ? 'BACKOFFICE_EMAILS' : "BACKOFFICE_EMAILS={$put}");
    }
});

describe('CA-2 colunas', function () {
    it('mostra os dados, contagens e o último login de cada usuário', function () {
        $u = Usuario::factory()->create([
            'nome' => 'Roberto Souza',
            'email' => 'roberto@exemplo.com.br',
            'cpf' => '12345678909',
            'telefone' => '11987654321',
        ]);
        $empresas = EmpresaAnalisada::factory()->count(2)->create(['usuario_id' => $u->id]);
        EmpresaAnalisada::factory()->create(['usuario_id' => $u->id, 'deleted_at' => now()]);
        Diagnostico::factory()->create(['usuario_id' => $u->id, 'empresa_analisada_id' => $empresas[0]->id]);

        foreach (['2026-10-01 10:00:00', '2026-10-20 15:30:00'] as $quando) {
            // DB::table para gravar o offset explícito (o cast do model descartaria o '+00').
            DB::table('audit_logs')->insert([
                'id' => (string) str()->uuid7(), 'action' => 'usuario.login_sucesso',
                'subject_type' => 'Usuario', 'usuario_id' => $u->id, 'occurred_at' => $quando.'+00',
            ]);
        }

        Livewire::actingAs($this->equipe)->test(Usuarios::class)
            ->assertSeeInOrder(['Roberto Souza', 'roberto@exemplo.com.br', '***.456.789-**', '(11) 98765-4321', 'Sim ·'])
            ->assertSee('20/10/2026 12:30')
            ->assertDontSee('12345678909')
            ->assertViewHas('usuarios', function ($pagina) use ($u) {
                $linha = $pagina->firstWhere('id', $u->id);

                return (int) $linha->empresas_count === 2 && (int) $linha->diagnosticos_count === 1;
            });
    });

    it('mostra "—" para quem nunca logou e "Não" para e-mail não confirmado', function () {
        Usuario::factory()->unconfirmed()->create(['nome' => 'Zé Novo']);

        Livewire::actingAs($this->equipe)->test(Usuarios::class)
            ->assertSeeInOrder(['Zé Novo', 'Não', '—', 'Equipe EBP'])
            ->assertSee('2 usuários · 1 com e-mail confirmado');
    });

    it('mascara CPF ausente ou inválido como "—"', function (?string $cpf) {
        expect((new Usuario(['cpf' => $cpf]))->cpfMascarado())->toBe('—');
    })->with([null, '', '123']);
});

describe('CA-3 situação', function () {
    it('marca usuário excluído', function () {
        Usuario::factory()->create(['nome' => 'Fulano Excluído', 'deleted_at' => now()]);

        Livewire::actingAs($this->equipe)->test(Usuarios::class)
            ->assertSee('Fulano Excluído')->assertSee('Excluído');
    });

    it('mostra o anonimizado sem nenhum dado pessoal', function () {
        $u = Usuario::factory()->create([
            'nome' => 'Nome Secreto', 'email' => 'secreto@x.com', 'cpf' => '12345678909',
            'telefone' => '11911112222', 'anonimizado_at' => now(),
        ]);

        Livewire::actingAs($this->equipe)->test(Usuarios::class)
            ->assertSee('Anonimizado')
            ->assertDontSee('Nome Secreto')->assertDontSee('secreto@x.com')
            ->assertDontSee('***.456.789-**')->assertDontSee('(11) 91111-2222');
        expect($u->anonimizado())->toBeTrue();
    });
});

describe('CA-4 navegação', function () {
    it('ordena pelo cadastro mais recente primeiro', function () {
        Usuario::factory()->create(['nome' => 'Antigo Silva', 'created_at' => now()->subDays(5)]);
        Usuario::factory()->create(['nome' => 'Novo Souza', 'created_at' => now()]);

        Livewire::actingAs($this->equipe)->test(Usuarios::class)
            ->assertSeeInOrder(['Novo Souza', 'Antigo Silva', 'Equipe EBP']);
    });

    it('busca por nome ou e-mail, parcial e sem diferenciar maiúsculas', function () {
        Usuario::factory()->create(['nome' => 'Maria Pereira', 'email' => 'maria@a.com']);
        Usuario::factory()->create(['nome' => 'João Lima', 'email' => 'JOAO.lima@b.com']);

        Livewire::actingAs($this->equipe)->test(Usuarios::class)
            ->set('termo', '  pereira ')->call('buscar')
            ->assertSee('Maria Pereira')->assertDontSee('João Lima')
            ->assertSet('busca', 'pereira')
            ->assertSee('1 usuário ·')
            ->set('termo', 'joao.LIMA')->call('buscar')
            ->assertSee('João Lima')->assertDontSee('Maria Pereira');
    });

    it('trata % e _ da busca como texto literal', function () {
        Usuario::factory()->create(['nome' => 'Ana Costa']);

        Livewire::actingAs($this->equipe)->test(Usuarios::class)
            ->set('termo', '%')->call('buscar')
            ->assertDontSee('Ana Costa')
            ->assertSee('Nenhum usuário encontrado.');
    });

    it('aceita a busca pela URL (?q=)', function () {
        Usuario::factory()->create(['nome' => 'Carla Dias']);
        Usuario::factory()->create(['nome' => 'Pedro Alves']);

        $this->actingAs($this->equipe)->get('/backoffice/usuarios?q=carla')
            ->assertSee('Carla Dias')->assertDontSee('Pedro Alves');
    });

    it('pagina de 50 em 50 e mostra o total e os confirmados no topo', function () {
        Usuario::factory()->count(50)->create();
        Usuario::factory()->unconfirmed()->count(2)->create();

        Livewire::actingAs($this->equipe)->test(Usuarios::class)
            ->assertSee('53 usuários · 51 com e-mail confirmado')
            ->assertViewHas('usuarios', fn ($p) => $p->count() === 50 && $p->total() === 53 && $p->lastPage() === 2);
    });
});

describe('CA-5 só-leitura e sem N+1', function () {
    it('sem outros usuários na lista, não tem botão nem ação além da busca (Logar como: STORY-066)', function () {
        $html = $this->actingAs($this->equipe)->get('/backoffice/usuarios')->getContent();

        expect(substr_count($html, 'wire:click'))->toBe(0)
            ->and(preg_match_all('/<form\b/', $html))->toBe(2); // busca + logout do shell
    });

    it('o número de queries não cresce com o tamanho da página', function () {
        $contar = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            Livewire::actingAs($this->equipe)->test(Usuarios::class);
            $n = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $n;
        };

        $poucos = $contar();
        foreach (Usuario::factory()->count(20)->create() as $u) {
            EmpresaAnalisada::factory()->create(['usuario_id' => $u->id]);
        }

        expect($contar())->toBe($poucos);
    });
});

describe('CA-6 auditoria', function () {
    it('registra o acesso com o ator e o termo de busca, sem PII de terceiros', function () {
        Usuario::factory()->create(['nome' => 'Terceiro Qualquer', 'email' => 'terceiro@x.com']);

        Livewire::actingAs($this->equipe)->test(Usuarios::class)
            ->set('termo', 'terceiro')->call('buscar');

        $logs = AuditLog::where('action', 'backoffice.usuarios_listados')->orderBy('occurred_at')->orderBy('id')->get();
        expect($logs)->toHaveCount(2)
            ->and($logs[0]->actor_id)->toBe($this->equipe->id)
            ->and($logs[0]->context['busca'])->toBeNull()
            ->and($logs[1]->context['busca'])->toBe('terceiro')
            ->and(json_encode($logs->toArray()))->not->toContain('terceiro@x.com');
    });

    it('não registra acesso negado como listagem', function () {
        $this->actingAs(Usuario::factory()->create())->get('/backoffice/usuarios');

        expect(AuditLog::where('action', 'backoffice.usuarios_listados')->count())->toBe(0);
    });
});

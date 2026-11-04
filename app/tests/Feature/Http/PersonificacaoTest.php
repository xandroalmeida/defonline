<?php

declare(strict_types=1);

use App\Livewire\Backoffice\Usuarios;
use App\Livewire\Login;
use App\Models\AuditLog;
use App\Models\Diagnostico;
use App\Models\EmpresaAnalisada;
use App\Models\Usuario;
use App\Observabilidade\EventLogger;
use App\Services\Rfb\RfbConsultarCnpj;
use App\Support\Relatorio\PdfDiagnostico;
use Database\Factories\EmpresaAnalisadaFactory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/**
 * STORY-066 / PDR-012 — "Logar como" do backoffice.
 */
beforeEach(function () {
    config(['backoffice.equipe_emails' => ['equipe@ebparcerias.com.br', 'colega@ebparcerias.com.br']]);
    $this->admin = Usuario::factory()->create(['nome' => 'Equipe EBP', 'email' => 'equipe@ebparcerias.com.br']);
    $this->alvo = Usuario::factory()->create(['nome' => 'Roberto Souza', 'email' => 'roberto@exemplo.com.br']);
    $this->logarComo = fn (?Usuario $alvo = null) => $this->actingAs($this->admin)
        ->post('/backoffice/usuarios/'.($alvo ?? $this->alvo)->id.'/logar-como');
});

/** @return list<AuditLog> */
function auditoria(string $action): array
{
    return AuditLog::where('action', $action)->orderBy('occurred_at')->orderBy('id')->get()->all();
}

describe('CA-1 quem pode', function () {
    it('não deixa o usuário comum entrar, com 404', function () {
        $this->actingAs(Usuario::factory()->create())
            ->post('/backoffice/usuarios/'.$this->alvo->id.'/logar-como')
            ->assertNotFound();

        expect(Auth::id())->not->toBe($this->alvo->id)
            ->and(auditoria('personificacao.iniciada'))->toBeEmpty();
    });

    it('manda o anônimo para o login', function () {
        $this->post('/backoffice/usuarios/'.$this->alvo->id.'/logar-como')->assertRedirect('/login');
    });

    it('recusa GET, para o acesso não acontecer por um link', function () {
        $this->actingAs($this->admin)
            ->get('/backoffice/usuarios/'.$this->alvo->id.'/logar-como')
            ->assertMethodNotAllowed();
    });

    it('recusa com 404 alvo da equipe, ele mesmo, excluído e anonimizado', function (string $caso) {
        $alvo = match ($caso) {
            'colega' => Usuario::factory()->create(['email' => 'Colega@EBParcerias.com.br']),
            'ele mesmo' => $this->admin,
            'excluído' => Usuario::factory()->create(['deleted_at' => now()]),
            'anonimizado' => Usuario::factory()->create(['anonimizado_at' => now()]),
        };

        ($this->logarComo)($alvo)->assertNotFound();

        expect(Auth::id())->toBe($this->admin->id)
            ->and(auditoria('personificacao.iniciada'))->toBeEmpty();
    })->with(['colega', 'ele mesmo', 'excluído', 'anonimizado']);

    it('recusa id inexistente ou malformado', function () {
        $this->actingAs($this->admin)->post('/backoffice/usuarios/'.str()->uuid7().'/logar-como')->assertNotFound();
        $this->actingAs($this->admin)->post('/backoffice/usuarios/abc/logar-como')->assertNotFound();
    });

    it('mostra o botão só para quem pode ser assumido', function () {
        $colega = Usuario::factory()->create(['email' => 'colega@ebparcerias.com.br']);
        $excluido = Usuario::factory()->create(['deleted_at' => now()]);
        $anonimizado = Usuario::factory()->create(['anonimizado_at' => now()]);

        $resposta = $this->actingAs($this->admin)->get('/backoffice/usuarios')->assertOk();

        $resposta->assertSee(route('backoffice.usuarios.logar-como', $this->alvo), escape: false);
        foreach ([$this->admin, $colega, $excluido, $anonimizado] as $semBotao) {
            $resposta->assertDontSee('/backoffice/usuarios/'.$semBotao->id.'/logar-como', escape: false);
        }
    });
});

describe('CA-2 assume o acesso', function () {
    it('entra na home do usuário, vendo só o que é dele', function () {
        ($this->logarComo)()->assertRedirect('/home');

        expect(Auth::id())->toBe($this->alvo->id);

        $empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $this->alvo->id]);
        $alheia = EmpresaAnalisada::factory()->create(['usuario_id' => $this->admin->id]);

        $this->get('/empresas/'.$empresa->id)->assertOk();
        $this->get('/empresas/'.$alheia->id)->assertForbidden();
    });

    it('não dá acesso ao backoffice durante a sessão', function () {
        ($this->logarComo)();

        $this->get('/backoffice/usuarios')->assertNotFound();
        $outro = Usuario::factory()->create();
        $this->post('/backoffice/usuarios/'.$outro->id.'/logar-como')->assertNotFound();
        expect(Auth::id())->toBe($this->alvo->id);
    });

    it('não conta como login do usuário na coluna Último login', function () {
        ($this->logarComo)();

        expect(auditoria('usuario.login_sucesso'))->toBeEmpty();
    });
});

describe('CA-3 aviso e volta', function () {
    it('mostra o aviso com o nome de quem foi assumido', function () {
        ($this->logarComo)();

        $this->get('/home')->assertOk()
            ->assertSee('dusk="personificacao-aviso"', escape: false)
            ->assertSee('Roberto Souza')
            ->assertSee('Voltar ao admin');
    });

    it('não mostra aviso fora da personificação', function () {
        $this->actingAs($this->admin)->get('/home')->assertOk()->assertDontSee('personificacao-aviso', escape: false);
        $this->actingAs($this->alvo)->get('/home')->assertOk()->assertDontSee('personificacao-aviso', escape: false);
    });

    it('volta ao admin, na lista de usuários', function () {
        ($this->logarComo)();

        $this->post('/personificacao/encerrar')->assertRedirect('/backoffice/usuarios');

        expect(Auth::id())->toBe($this->admin->id);
        $this->get('/backoffice/usuarios')->assertOk();
        $this->get('/home')->assertDontSee('personificacao-aviso', escape: false);
    });

    it('sem personificação, encerrar não troca ninguém de conta', function () {
        $this->actingAs($this->alvo)->post('/personificacao/encerrar')->assertRedirect('/home');

        expect(Auth::id())->toBe($this->alvo->id)
            ->and(auditoria('personificacao.encerrada'))->toBeEmpty();
    });

    it('exige login para encerrar', function () {
        $this->post('/personificacao/encerrar')->assertRedirect('/login');
    });
});

describe('CA-4 segurança da sessão', function () {
    it('Sair encerra tudo e não devolve ao admin', function () {
        ($this->logarComo)();

        $this->post('/logout')->assertRedirect('/login');

        expect(Auth::check())->toBeFalse();
        expect(auditoria('personificacao.encerrada')[0]->context['motivo'])->toBe('logout');
    });

    it('derruba a sessão quando o admin sai da allowlist', function () {
        ($this->logarComo)();
        config(['backoffice.equipe_emails' => []]);

        $this->get('/home')->assertRedirect('/login');

        expect(Auth::check())->toBeFalse();
        expect(auditoria('personificacao.encerrada')[0]->context['motivo'])->toBe('admin_sem_acesso');
    });

    it('ignora a chave velha quando outra conta está logada, sem virar admin', function () {
        ($this->logarComo)();
        $outro = Usuario::factory()->create();
        Auth::login($outro);

        $this->post('/personificacao/encerrar')->assertRedirect('/home');

        expect(Auth::id())->toBe($outro->id);
        $this->get('/home')->assertDontSee('personificacao-aviso', escape: false);
    });
});

describe('CA-5 auditoria', function () {
    it('registra início e fim, com o admin como ator e o usuário como alvo', function () {
        ($this->logarComo)();
        $this->post('/personificacao/encerrar');

        [$inicio] = auditoria('personificacao.iniciada');
        [$fim] = auditoria('personificacao.encerrada');

        expect($inicio->actor_id)->toBe($this->admin->id)
            ->and($inicio->usuario_id)->toBe($this->alvo->id)
            ->and($inicio->subject_id)->toBe($this->alvo->id)
            ->and($inicio->context['personificacao']['sessao_id'])->not->toBeEmpty()
            ->and($fim->actor_id)->toBe($this->admin->id)
            ->and($fim->context['motivo'])->toBe('voltou_ao_admin')
            ->and($fim->context['personificacao']['sessao_id'])->toBe($inicio->context['personificacao']['sessao_id']);
    });

    it('registra cada requisição, inclusive a que falha e a que encerra', function () {
        ($this->logarComo)();
        $this->get('/home?pagina=2');
        $this->get('/empresas/'.str()->uuid7());
        $this->post('/personificacao/encerrar');

        $logs = auditoria('personificacao.requisicao');

        expect($logs)->toHaveCount(3)
            ->and($logs[0]->actor_id)->toBe($this->admin->id)
            ->and($logs[0]->usuario_id)->toBe($this->alvo->id)
            ->and($logs[0]->context['metodo'])->toBe('GET')
            ->and($logs[0]->context['caminho'])->toBe('home')
            ->and($logs[0]->context['rota'])->toBe('home')
            ->and($logs[0]->context['query']['pagina'])->toBe('2')
            ->and($logs[0]->context['status'])->toBe(200)
            ->and($logs[1]->context['status'])->toBe(404)
            ->and($logs[2]->context['rota'])->toBe('personificacao.encerrar');
    });

    it('registra a ação Livewire com componente, campos e chamada, sem a senha', function () {
        $snapshot = Livewire::test(Login::class)->snapshot;
        ($this->logarComo)();

        $this->withHeaders(['X-Livewire' => '1'])->postJson(app('livewire')->getUpdateUri(), [
            '_token' => csrf_token(),
            'components' => [[
                'snapshot' => json_encode($snapshot),
                'updates' => ['email' => 'nao-e-email', 'senha' => 'segredo-123'],
                'calls' => [['method' => 'submit', 'params' => [], 'metadata' => []]],
            ]],
        ]);

        [$log] = auditoria('personificacao.requisicao');
        $entrada = $log->context['entrada'][0];

        expect($entrada['componente'])->toBe('login')
            ->and($entrada['campos']['email'])->toBe('nao-e-email')
            ->and($entrada['campos']['senha'])->toBe('[REDACTED]')
            ->and($entrada['chamadas'][0]['method'])->toBe('submit')
            ->and(json_encode($log->toArray()))->not->toContain('segredo-123');
    });

    it('mascara credencial também em formulário comum e na query', function () {
        ($this->logarComo)();

        $this->post('/logout', ['senha_atual' => 'segredo-1', 'motivo' => 'teste']);
        [$log] = auditoria('personificacao.requisicao');

        expect($log->context['entrada']['senha_atual'])->toBe('[REDACTED]')
            ->and($log->context['entrada']['motivo'])->toBe('teste');
    });

    it('atribui ao admin os eventos de auditoria do domínio, marcando a sessão', function () {
        ($this->logarComo)();
        $alheia = EmpresaAnalisada::factory()->create(['usuario_id' => $this->admin->id]);

        $this->get('/empresas/'.$alheia->id)->assertForbidden();

        [$evento] = auditoria('empresa.acesso_negado');

        expect($evento->usuario_id)->toBe($this->alvo->id)
            ->and($evento->actor_id)->toBe($this->admin->id)
            ->and($evento->context['personificacao']['admin_id'])->toBe($this->admin->id);
    });

    it('não registra requisição nem altera o ator fora da personificação', function () {
        $alheia = EmpresaAnalisada::factory()->create(['usuario_id' => $this->admin->id]);

        $this->actingAs($this->alvo)->get('/empresas/'.$alheia->id)->assertForbidden();

        expect(auditoria('personificacao.requisicao'))->toBeEmpty();
        [$evento] = auditoria('empresa.acesso_negado');
        expect($evento->actor_id)->toBe($this->alvo->id)
            ->and($evento->context['personificacao'] ?? null)->toBeNull();
    });
});

describe('CA-6 ações e métricas indicam o admin', function () {
    it('grava o admin no registro criado durante a sessão, e null fora dela', function () {
        $antes = EmpresaAnalisada::factory()->create(['usuario_id' => $this->alvo->id]);
        ($this->logarComo)();

        $empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $this->alvo->id]);
        $diagnostico = Diagnostico::factory()->create(['usuario_id' => $this->alvo->id, 'empresa_analisada_id' => $empresa->id]);

        expect($antes->personificado_por_id)->toBeNull()
            ->and($empresa->personificado_por_id)->toBe($this->admin->id)
            ->and($empresa->personificadoPor->nome)->toBe('Equipe EBP')
            ->and($diagnostico->personificado_por_id)->toBe($this->admin->id);
    });

    it('marca a alteração feita na sessão e limpa quando o próprio usuário grava depois', function () {
        $empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $this->alvo->id]);
        ($this->logarComo)();

        $empresa->update(['observacoes' => 'pelo admin']);
        expect($empresa->fresh()->personificado_por_id)->toBe($this->admin->id);

        $this->post('/personificacao/encerrar');
        $this->post('/logout');
        Auth::login($this->alvo);

        $empresa->fresh()->update(['observacoes' => 'pelo usuário']);
        expect($empresa->fresh()->personificado_por_id)->toBeNull();
    });

    it('mantém o nome do admin mesmo com a conta dele excluída', function () {
        ($this->logarComo)();
        $empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $this->alvo->id]);
        $this->post('/personificacao/encerrar');

        $this->admin->delete();

        expect($empresa->fresh()->personificadoPor->nome)->toBe('Equipe EBP');
    });

    it('mostra o admin na tela da empresa e do diagnóstico, também depois da sessão', function () {
        ($this->logarComo)();
        $empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $this->alvo->id]);
        $diagnostico = Diagnostico::factory()->create(['usuario_id' => $this->alvo->id, 'empresa_analisada_id' => $empresa->id]);
        $this->post('/personificacao/encerrar');

        $this->actingAs($this->alvo);

        $this->get('/empresas/'.$empresa->id)->assertOk()->assertSee('Última alteração feita por Equipe EBP');
        $this->get('/diagnosticos/'.$diagnostico->id)->assertOk()->assertSee('Última alteração feita por Equipe EBP');
    });

    it('não mostra marca no registro feito pelo próprio usuário', function () {
        $empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $this->alvo->id]);

        $this->actingAs($this->alvo)->get('/empresas/'.$empresa->id)->assertOk()
            ->assertDontSee('personificacao-marca', escape: false);
    });

    it('indica o admin na capa do PDF só quando ele fez o diagnóstico', function () {
        $empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $this->alvo->id]);
        $doUsuario = Diagnostico::factory()->create(['usuario_id' => $this->alvo->id, 'empresa_analisada_id' => $empresa->id]);
        ($this->logarComo)();
        $doAdmin = Diagnostico::factory()->create(['usuario_id' => $this->alvo->id, 'empresa_analisada_id' => $empresa->id]);

        $html = fn (Diagnostico $d) => PdfDiagnostico::padrao()->html($d);

        expect($html($doAdmin))->toContain('Equipe EBP, da equipe DEFOnline')
            ->and($html($doUsuario))->not->toContain('da equipe DEFOnline');
    });

    it('marca o evento de produto com o admin e a sessão, sem PII', function () {
        $fora = EventLogger::emit('teste_fora', ['origem' => 'pest']);
        ($this->logarComo)();

        $dentro = EventLogger::emit('teste_dentro', ['origem' => 'pest'], $this->alvo->id);
        [$inicio] = auditoria('personificacao.iniciada');

        expect($fora->propriedades['personificacao'] ?? null)->toBeNull()
            ->and($dentro->usuario_id)->toBe($this->alvo->id)
            ->and($dentro->propriedades['origem'])->toBe('pest')
            ->and($dentro->propriedades['personificacao']['admin_id'])->toBe($this->admin->id)
            ->and($dentro->propriedades['personificacao']['sessao_id'])->toBe($inicio->context['personificacao']['sessao_id'])
            ->and(json_encode($dentro->propriedades))->not->toContain($this->admin->email);
    });

    it('marca a métrica de requisição com o admin só durante a sessão', function () {
        $this->actingAs($this->alvo)->get('/home?antes=1');
        $this->post('/logout');
        ($this->logarComo)();
        $this->get('/home?durante=1');

        $metricas = DB::table('request_metrics')->where('path', '/home')->orderBy('id')->get();

        expect($metricas)->toHaveCount(2)
            ->and($metricas[0]->personificado_por_id)->toBeNull()
            ->and($metricas[1]->personificado_por_id)->toBe($this->admin->id)
            ->and($metricas[1]->usuario_id)->toBe($this->alvo->id);
    });

    it('marca a métrica de negócio da consulta à Receita', function () {
        ($this->logarComo)();

        app(RfbConsultarCnpj::class)->executar(EmpresaAnalisadaFactory::cnpjComRaiz('12345678'));

        $meta = json_decode((string) DB::table('business_metrics')->where('tipo', 'rfb_consulta')->value('meta'), true);

        expect($meta['personificacao']['admin_id'])->toBe($this->admin->id);
    });

    it('separa na lista do backoffice o que foi feito via admin', function () {
        EmpresaAnalisada::factory()->create(['usuario_id' => $this->alvo->id]);
        ($this->logarComo)();
        $empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $this->alvo->id]);
        Diagnostico::factory()->create(['usuario_id' => $this->alvo->id, 'empresa_analisada_id' => $empresa->id]);
        $this->post('/personificacao/encerrar');

        Livewire::actingAs($this->admin)->test(Usuarios::class)
            ->assertSee('1 via admin')
            ->assertViewHas('usuarios', function ($pagina) {
                $linha = $pagina->firstWhere('id', $this->alvo->id);

                return (int) $linha->empresas_count === 2
                    && (int) $linha->empresas_via_admin_count === 1
                    && (int) $linha->diagnosticos_via_admin_count === 1;
            });
    });
});

it('todas as telas ocupam a área de trabalho inteira', function () {
    $this->actingAs($this->admin);

    $conteudo = fn (string $url) => str($this->get($url)->getContent())->between('data-testid="app-main"', '</main>')->toString();

    expect($conteudo('/backoffice/usuarios'))->not->toContain('max-w-[960px]')
        ->and($conteudo('/home'))->not->toContain('max-w-[960px]');
});

<?php

declare(strict_types=1);

use App\Domain\Cobranca\MotivoRecusaCupom;
use App\Domain\Cobranca\TipoDesconto;
use App\Livewire\Backoffice\Cupons;
use App\Models\AuditLog;
use App\Models\Cupom;
use App\Models\CupomConcessao;
use App\Models\CupomUso;
use App\Models\PacoteCredito;
use App\Models\Plano;
use App\Models\Usuario;
use App\Support\PermissaoAdmin;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;

/**
 * STORY-072 / PDR-020 — criação e concessão de cupons no backoffice.
 */
beforeEach(function () {
    config(['backoffice.equipe_emails' => []]);
    $this->admin = Usuario::factory()->create();
    $this->admin->forceFill(['permissoes_admin' => ['cupons.gerenciar']])->save();
    $this->tela = fn () => Livewire::actingAs($this->admin)->test(Cupons::class);
    $this->plano = Plano::query()->orderBy('ordem')->firstOrFail();
    $this->pacote = PacoteCredito::query()->orderBy('ordem')->firstOrFail();

    /** Preenche o formulário com um cupom válido, aceitando trocas campo a campo. */
    $this->preencher = function ($tela, array $troca = []) {
        $campos = [
            'codigo' => 'BETA25', 'tipo' => 'percentual', 'valor' => '25',
            'vigencia_inicio' => '2026-11-01', 'vigencia_fim' => '2026-12-31',
            'limite_usos' => '', 'duracao' => 'assinatura', 'ciclos' => '',
            'itens' => ["plano:{$this->plano->id}:mensal"],
            ...$troca,
        ];
        foreach ($campos as $campo => $valor) {
            $tela->set("cupom.{$campo}", $valor);
        }

        return $tela;
    };

    $this->cupom = fn (array $attrs = []) => Cupom::create([
        'codigo' => 'EXISTE', 'tipo' => TipoDesconto::Percentual, 'valor' => 10,
        'vigencia_inicio' => '2026-11-01', 'vigencia_fim' => '2026-12-31', 'ativo' => true, ...$attrs,
    ]);
    $this->auditorias = fn (string $acao) => AuditLog::where('action', $acao)->get();
});

describe('CA-1 permissão', function () {
    it('abre a tela só com cupons.gerenciar, com 404 sem ela e para o usuário comum', function () {
        $this->actingAs($this->admin)->get('/backoffice/cupons')->assertOk();

        $outro = Usuario::factory()->create();
        $outro->forceFill(['permissoes_admin' => ['planos.gerenciar']])->save();
        $this->actingAs($outro)->get('/backoffice/cupons')->assertNotFound();
        $this->actingAs(Usuario::factory()->create())->get('/backoffice/cupons')->assertNotFound();
    });

    it('manda o anônimo para o login', function () {
        $this->get('/backoffice/cupons')->assertRedirect('/login');
    });

    it('mostra o item no menu só a quem tem a permissão', function () {
        $this->actingAs($this->admin)->get('/backoffice/cupons')->assertSee('app-nav-backoffice-cupons');

        $outro = Usuario::factory()->create();
        $outro->forceFill(['permissoes_admin' => ['planos.gerenciar']])->save();
        $this->actingAs($outro)->get('/backoffice/planos')->assertOk()->assertDontSee('app-nav-backoffice-cupons');
    });

    it('recusa as ações do Livewire de quem não tem a permissão', function () {
        $cupom = ($this->cupom)();
        $comum = Usuario::factory()->create();

        Livewire::actingAs($comum)->test(Cupons::class)->assertNotFound();
        expect($cupom->fresh()->ativo)->toBeTrue();
    });

    it('a permissão aparece para o raiz conceder', function () {
        expect(array_map(fn ($p) => $p->value, PermissaoAdmin::cases()))->toContain('cupons.gerenciar');
        expect(PermissaoAdmin::GerenciarCupons->rotulo())->toBe('Gerenciar cupons');
    });
});

describe('CA-2 criação', function () {
    it('cria um cupom percentual com código normalizado, itens e auditoria', function () {
        $tela = ($this->preencher)(($this->tela)(), ['codigo' => '  beta25 ', 'limite_usos' => '100', 'itens' => [
            "plano:{$this->plano->id}:mensal", "plano:{$this->plano->id}:anual", "pacote:{$this->pacote->id}",
        ]]);
        $tela->call('salvarCupom')->assertHasNoErrors()->assertSet('cupom.codigo', '')->assertSee('BETA25');

        $cupom = Cupom::where('codigo', 'BETA25')->firstOrFail();
        expect($cupom->tipo)->toBe(TipoDesconto::Percentual)
            ->and($cupom->valor)->toBe(25)
            ->and($cupom->limite_usos)->toBe(100)
            ->and($cupom->ciclos_duracao)->toBeNull()
            ->and($cupom->ativo)->toBeTrue()
            ->and($cupom->vigencia_inicio->toDateString())->toBe('2026-11-01')
            ->and($cupom->alterado_por_usuario_id)->toBe($this->admin->id)
            ->and($cupom->itens->map->chave()->sort()->values()->all())->toBe(collect([
                "plano:{$this->plano->id}:mensal", "plano:{$this->plano->id}:anual", "pacote:{$this->pacote->id}",
            ])->sort()->values()->all());
    });

    it('cria um cupom de valor fixo em reais, por um número de ciclos', function () {
        $tela = ($this->preencher)(($this->tela)(), ['codigo' => 'MENOS50', 'tipo' => 'valor_fixo', 'valor' => '50,00', 'duracao' => 'ciclos', 'ciclos' => '3']);
        $tela->call('salvarCupom')->assertHasNoErrors();

        $cupom = Cupom::where('codigo', 'MENOS50')->firstOrFail();
        expect($cupom->tipo)->toBe(TipoDesconto::ValorFixo)->and($cupom->valor)->toBe(5000)->and($cupom->ciclos_duracao)->toBe(3);
    });

    it('aceita as pontas: 1%, 100% e vigência de um dia só', function (string $valor) {
        ($this->preencher)(($this->tela)(), ['valor' => $valor, 'vigencia_inicio' => '2026-11-10', 'vigencia_fim' => '2026-11-10'])
            ->call('salvarCupom')->assertHasNoErrors();

        expect(Cupom::where('codigo', 'BETA25')->value('valor'))->toBe((int) $valor);
    })->with(['1', '100']);

    it('mostra o que o admin escolheu: lista com desconto, vigência e itens', function () {
        ($this->cupom)()->itens()->create(['plano_id' => $this->plano->id, 'ciclo' => 'anual']);

        ($this->tela)()->assertSee('EXISTE')->assertSee('10%')->assertSee('01/11/2026 a 31/12/2026')
            ->assertSee($this->plano->nome.' (anual)')->assertSee('toda a assinatura');
    });

    it('mostra a lista vazia sem cupons', function () {
        ($this->tela)()->assertSee('Nenhum cupom criado ainda.');
    });
});

describe('CA-3 validação por campo', function () {
    it('recusa código duplicado sem diferenciar maiúsculas', function (string $digitado) {
        ($this->cupom)(['codigo' => 'BETA25']);

        ($this->preencher)(($this->tela)(), ['codigo' => $digitado])->call('salvarCupom')
            ->assertHasErrors(['cupom.codigo' => 'Já existe um cupom com este código.']);

        expect(Cupom::count())->toBe(1);
    })->with(['BETA25', 'beta25', 'Beta25', ' beta25 ']);

    it('recusa código vazio, curto, longo ou com caracteres inválidos', function (string $codigo) {
        ($this->preencher)(($this->tela)(), ['codigo' => $codigo])->call('salvarCupom')->assertHasErrors(['cupom.codigo']);
    })->with(['', '  ', 'AB', 'COM ESPACO', 'ÇÃO#1', str_repeat('A', 41)]);

    it('aceita o código nos limites de tamanho', function (string $codigo) {
        ($this->preencher)(($this->tela)(), ['codigo' => $codigo])->call('salvarCupom')->assertHasNoErrors();
    })->with(['ABC', 'A_B-1', str_repeat('A', 40)]);

    it('recusa percentual fora de 1 a 100 e não inteiro', function (string $valor, string $mensagem) {
        ($this->preencher)(($this->tela)(), ['valor' => $valor])->call('salvarCupom')->assertHasErrors(['cupom.valor' => $mensagem]);
    })->with([
        'zero' => ['0', 'O percentual deve ficar entre 1 e 100.'],
        '101' => ['101', 'O percentual deve ficar entre 1 e 100.'],
        'negativo' => ['-10', 'O percentual deve ficar entre 1 e 100.'],
        'decimal' => ['10,5', 'Informe o percentual como número inteiro, de 1 a 100.'],
        'texto' => ['dez', 'Informe o percentual como número inteiro, de 1 a 100.'],
        'vazio' => ['', 'Informe o valor do desconto.'],
    ]);

    it('recusa valor fixo menor ou igual a zero e malformado', function (string $valor, string $mensagem) {
        ($this->preencher)(($this->tela)(), ['tipo' => 'valor_fixo', 'valor' => $valor])->call('salvarCupom')->assertHasErrors(['cupom.valor' => $mensagem]);
    })->with([
        'zero' => ['0', 'O valor fixo deve ser maior que zero.'],
        'zero com centavos' => ['0,00', 'O valor fixo deve ser maior que zero.'],
        'negativo' => ['-5,00', 'O valor fixo deve ser maior que zero.'],
        'texto' => ['abc', 'Informe o valor em reais, por exemplo 50,00.'],
    ]);

    it('aceita valor fixo acima de 100 (não é percentual)', function () {
        ($this->preencher)(($this->tela)(), ['tipo' => 'valor_fixo', 'valor' => '1.500,00'])->call('salvarCupom')->assertHasNoErrors();

        expect(Cupom::where('codigo', 'BETA25')->value('valor'))->toBe(150000);
    });

    it('recusa vigência invertida, mas aceita início igual ao fim', function () {
        ($this->preencher)(($this->tela)(), ['vigencia_inicio' => '2026-12-02', 'vigencia_fim' => '2026-12-01'])->call('salvarCupom')
            ->assertHasErrors(['cupom.vigencia_fim' => 'O fim da vigência não pode ser anterior ao início.']);
    });

    it('recusa datas ausentes ou inexistentes', function (string $inicio, string $fim, string $campo) {
        ($this->preencher)(($this->tela)(), ['vigencia_inicio' => $inicio, 'vigencia_fim' => $fim])->call('salvarCupom')->assertHasErrors([$campo]);
    })->with([
        'sem início' => ['', '2026-12-01', 'cupom.vigencia_inicio'],
        'sem fim' => ['2026-11-01', '', 'cupom.vigencia_fim'],
        'data inexistente' => ['2026-02-30', '2026-12-01', 'cupom.vigencia_inicio'],
        'formato errado' => ['01/11/2026', '2026-12-01', 'cupom.vigencia_inicio'],
    ]);

    it('recusa limite de usos inválido, mas aceita em branco', function (string $limite) {
        ($this->preencher)(($this->tela)(), ['limite_usos' => $limite])->call('salvarCupom')->assertHasErrors(['cupom.limite_usos']);
    })->with(['0', '-1', '1,5', 'x', '100001']);

    it('recusa duração em ciclos sem número válido', function (string $ciclos) {
        ($this->preencher)(($this->tela)(), ['duracao' => 'ciclos', 'ciclos' => $ciclos])->call('salvarCupom')->assertHasErrors(['cupom.ciclos']);
    })->with(['', '0', '-2', 'x']);

    it('ignora o campo de ciclos quando o desconto vale por toda a assinatura', function () {
        ($this->preencher)(($this->tela)(), ['duracao' => 'assinatura', 'ciclos' => 'lixo'])->call('salvarCupom')->assertHasNoErrors();

        expect(Cupom::where('codigo', 'BETA25')->value('ciclos_duracao'))->toBeNull();
    });

    it('exige ao menos um item e recusa item desconhecido ou de outro formato', function (array $itens) {
        ($this->preencher)(($this->tela)(), ['itens' => $itens])->call('salvarCupom')->assertHasErrors();
        expect(Cupom::count())->toBe(0);
    })->with([
        'nenhum' => [[]],
        'plano inexistente' => [['plano:0192f9a2-0000-7000-8000-000000000000:mensal']],
        'ciclo inválido' => [['plano:0192f9a2-0000-7000-8000-000000000000:semanal']],
        'pacote inexistente' => [['pacote:0192f9a2-0000-7000-8000-000000000000']],
        'lixo' => [['qualquer']],
        'id que não é uuid' => [['pacote:1; drop table cupons']],
    ]);

    it('o erro aparece junto ao campo na tela', function () {
        ($this->preencher)(($this->tela)(), ['valor' => '0', 'itens' => []])->call('salvarCupom')
            ->assertSeeHtml('dusk="erro-cupom-valor"')->assertSeeHtml('dusk="erro-cupom-itens"');
    });

    it('trata a corrida de código duplicado do índice único como erro de campo', function () {
        $tela = ($this->preencher)(($this->tela)(), ['codigo' => 'CORRIDA']);

        // Outro admin grava o mesmo código entre a validação e o INSERT.
        Cupom::creating(function (Cupom $c) {
            Cupom::flushEventListeners();
            DB::table('cupons')->insert(['id' => (string) Str::uuid7(), 'codigo' => 'CORRIDA', 'tipo' => 'percentual', 'valor' => 5,
                'vigencia_inicio' => '2026-11-01', 'vigencia_fim' => '2026-11-02', 'ativo' => true, 'created_at' => now(), 'updated_at' => now()]);
        });

        $tela->call('salvarCupom')->assertHasErrors(['cupom.codigo' => 'Já existe um cupom com este código.']);
    });
});

describe('edição e CA-6 desativação', function () {
    it('carrega o cupom no formulário e salva a alteração, sem tratar o próprio código como duplicado', function () {
        $cupom = ($this->cupom)(['codigo' => 'EDITA', 'limite_usos' => 5, 'ciclos_duracao' => 2]);
        $cupom->itens()->create(['plano_id' => $this->plano->id, 'ciclo' => 'mensal']);

        $tela = ($this->tela)()->call('editarCupom', $cupom->id)
            ->assertSet('cupom.codigo', 'EDITA')->assertSet('cupom.valor', '10')->assertSet('cupom.duracao', 'ciclos')
            ->assertSet('cupom.ciclos', '2')->assertSet('cupom.limite_usos', '5')
            ->assertSet('cupom.itens', ["plano:{$this->plano->id}:mensal"]);

        $tela->set('cupom.valor', '30')->set('cupom.itens', ["pacote:{$this->pacote->id}"])->call('salvarCupom')->assertHasNoErrors()->assertSet('cupom.id', null);

        $cupom->refresh()->load('itens');
        expect($cupom->valor)->toBe(30)->and($cupom->itens->map->chave()->all())->toBe(["pacote:{$this->pacote->id}"]);

        $log = ($this->auditorias)('cupons.cupom_alterado')->first();
        expect($log->subject_id)->toBe($cupom->id)->and($log->before['valor'])->toBe(10)->and($log->after['valor'])->toBe(30)
            ->and($log->before['itens'])->toBe(["plano:{$this->plano->id}:mensal"])->and($log->after['itens'])->toBe(["pacote:{$this->pacote->id}"])
            ->and($log->actor_id)->toBe($this->admin->id);
    });

    it('edita um cupom de valor fixo mostrando o valor em reais', function () {
        $cupom = ($this->cupom)(['codigo' => 'FIXO', 'tipo' => TipoDesconto::ValorFixo, 'valor' => 12345]);

        ($this->tela)()->call('editarCupom', $cupom->id)->assertSet('cupom.valor', '123,45')->assertSet('cupom.tipo', 'valor_fixo')->assertSet('cupom.duracao', 'assinatura');
    });

    it('não grava nem audita quando nada mudou', function () {
        $cupom = ($this->cupom)(['codigo' => 'IGUAL']);
        $cupom->itens()->create(['plano_id' => $this->plano->id, 'ciclo' => 'mensal']);

        ($this->tela)()->call('editarCupom', $cupom->id)->call('salvarCupom')->assertHasNoErrors();

        expect(($this->auditorias)('cupons.cupom_alterado'))->toHaveCount(0);
    });

    it('recusa baixar o limite abaixo dos usos já feitos', function () {
        $cupom = ($this->cupom)(['codigo' => 'USADO', 'limite_usos' => 10]);
        $cupom->itens()->create(['plano_id' => $this->plano->id, 'ciclo' => 'mensal']);
        foreach (Usuario::factory()->count(3)->create() as $u) {
            CupomUso::create(['cupom_id' => $cupom->id, 'usuario_id' => $u->id, 'usado_em' => now()]);
        }

        ($this->tela)()->call('editarCupom', $cupom->id)->set('cupom.limite_usos', '2')->call('salvarCupom')
            ->assertHasErrors(['cupom.limite_usos' => 'O limite não pode ser menor que os 3 usos já feitos.'])
            ->set('cupom.limite_usos', '3')->call('salvarCupom')->assertHasNoErrors();
    });

    it('cancelar a edição limpa o formulário', function () {
        $cupom = ($this->cupom)();

        ($this->tela)()->call('editarCupom', $cupom->id)->call('limparCupom')->assertSet('cupom.id', null)->assertSet('cupom.codigo', '');
    });

    it('desativa e reativa, auditando, sem tocar nas concessões nem nos usos', function () {
        $cupom = ($this->cupom)();
        $u = Usuario::factory()->create();
        CupomConcessao::create(['cupom_id' => $cupom->id, 'usuario_id' => $u->id, 'concedida_em' => now()]);
        CupomUso::create(['cupom_id' => $cupom->id, 'usuario_id' => $u->id, 'usado_em' => now()]);

        $tela = ($this->tela)()->call('alternarCupom', $cupom->id);
        expect($cupom->fresh()->ativo)->toBeFalse()->and(CupomConcessao::count())->toBe(1)->and(CupomUso::count())->toBe(1);

        $log = ($this->auditorias)('cupons.cupom_desativado')->first();
        expect($log->before->toArray())->toBe(['ativo' => true])->and($log->after->toArray())->toBe(['ativo' => false])->and($log->actor_id)->toBe($this->admin->id);

        $tela->call('alternarCupom', $cupom->id);
        expect($cupom->fresh()->ativo)->toBeTrue()->and(($this->auditorias)('cupons.cupom_reativado'))->toHaveCount(1);
    });

    it('um cupom desativado impede novos usos pela regra de domínio', function () {
        $cupom = ($this->cupom)(['vigencia_inicio' => '2026-01-01', 'vigencia_fim' => '2026-12-31']);
        $cupom->itens()->create(['plano_id' => $this->plano->id, 'ciclo' => 'mensal']);
        $usuario = Usuario::factory()->create();
        $agora = Carbon::parse('2026-11-05');

        expect($cupom->recusaPara($usuario->id, $this->plano->id, 'mensal', null, $agora))->toBeNull();

        ($this->tela)()->call('alternarCupom', $cupom->id);

        expect($cupom->fresh()->recusaPara($usuario->id, $this->plano->id, 'mensal', null, $agora))->toBe(MotivoRecusaCupom::Inativo);
    });

    it('não deixa o botão de conceder aparecer para cupom desativado', function () {
        $cupom = ($this->cupom)(['ativo' => false]);

        ($this->tela)()->assertDontSeeHtml('dusk="cupom-conceder-'.$cupom->id.'"');
    });
});

describe('CA-4 concessão', function () {
    beforeEach(function () {
        $this->cupom = ($this->cupom)(['codigo' => 'BETA', 'ciclos_duracao' => 6]);
        $this->contas = Usuario::factory()->count(3)->create();
    });

    it('mostra quantas contas serão afetadas antes de confirmar, sem gravar nada', function () {
        $emails = $this->contas->take(2)->pluck('email')->implode("\n");

        ($this->tela)()->call('abrirConcessao', $this->cupom->id)
            ->set('concessao.emails', $emails)->call('calcularConcessao')
            ->assertSet('previa', ['total' => 2, 'novas' => 2, 'ja_tinham' => 0])
            ->assertSeeHtml('dusk="concessao-previa"')->assertSeeHtml('dusk="concessao-confirmar"');

        expect(CupomConcessao::count())->toBe(0);
    });

    it('concede a contas específicas por e-mail (maiúsculas, vírgulas e linhas), com a duração do cupom', function () {
        $a = $this->contas[0];
        $b = $this->contas[1];
        $texto = strtoupper($a->email).",\n  {$b->email} ;{$a->email}";

        ($this->tela)()->call('abrirConcessao', $this->cupom->id)
            ->set('concessao.emails', $texto)->call('calcularConcessao')->assertSet('previa.total', 2)
            ->call('confirmarConcessao')->assertHasNoErrors()->assertSet('concessao.cupom_id', null)->assertSet('previa', null);

        $concessoes = CupomConcessao::where('cupom_id', $this->cupom->id)->get();
        expect($concessoes->pluck('usuario_id')->sort()->values()->all())->toBe(collect([$a->id, $b->id])->sort()->values()->all())
            ->and($concessoes->pluck('ciclos_restantes')->unique()->all())->toBe([6])
            ->and($concessoes->first()->concedida_por_usuario_id)->toBe($this->admin->id);
    });

    it('avisa o resultado na tela', function () {
        ($this->tela)()->call('abrirConcessao', $this->cupom->id)
            ->set('concessao.emails', $this->contas[0]->email)->call('calcularConcessao')->call('confirmarConcessao')
            ->assertSee('1 conta recebeu o cupom BETA.');
    });

    it('recusa e-mail sem conta, listando quais, e não concede a ninguém', function () {
        ($this->tela)()->call('abrirConcessao', $this->cupom->id)
            ->set('concessao.emails', $this->contas[0]->email."\nninguem@exemplo.com")->call('calcularConcessao')
            ->assertHasErrors('concessao.emails')->assertSet('previa', null);

        expect(CupomConcessao::count())->toBe(0);
    });

    it('não considera contas anonimizadas nem excluídas', function () {
        $anonima = Usuario::factory()->create();
        $anonima->forceFill(['anonimizado_at' => now()])->save();
        $excluida = Usuario::factory()->create();
        $excluida->delete();

        foreach ([$anonima, $excluida] as $conta) {
            ($this->tela)()->call('abrirConcessao', $this->cupom->id)->set('concessao.emails', $conta->email)->call('calcularConcessao')->assertHasErrors('concessao.emails');
        }
    });

    it('exige ao menos um e-mail', function (string $texto) {
        ($this->tela)()->call('abrirConcessao', $this->cupom->id)->set('concessao.emails', $texto)->call('calcularConcessao')
            ->assertHasErrors(['concessao.emails']);
    })->with(['', '   ', ' , ;']);

    it('concede a todas as contas cadastradas até o fim do dia da data em Brasília, inclusive no próprio dia', function () {
        Usuario::query()->delete();
        $antiga = Usuario::factory()->create();
        $antiga->forceFill(['created_at' => '2026-10-01 08:00:00'])->save();
        // Virada do dia: 02:59:59 UTC de 01/11 ainda é 23:59:59 de 31/10 em Brasília; 03:00:00 UTC já é 01/11.
        $ultimoSegundo = Usuario::factory()->create();
        $ultimoSegundo->forceFill(['created_at' => '2026-11-01 02:59:59'])->save();
        $nodiaUtc = Usuario::factory()->create();
        $nodiaUtc->forceFill(['created_at' => '2026-10-31 23:59:59'])->save();
        $primeiroSegundoDoDiaSeguinte = Usuario::factory()->create();
        $primeiroSegundoDoDiaSeguinte->forceFill(['created_at' => '2026-11-01 03:00:00'])->save();
        $anonima = Usuario::factory()->create();
        $anonima->forceFill(['created_at' => '2026-10-02 00:00:00', 'anonimizado_at' => now()])->save();
        $excluida = Usuario::factory()->create();
        $excluida->forceFill(['created_at' => '2026-10-02 00:00:00'])->save();
        $excluida->delete();

        ($this->tela)()->call('abrirConcessao', $this->cupom->id)
            ->set('concessao.modo', 'todas')->set('concessao.ate', '2026-10-31')->call('calcularConcessao')
            ->assertSet('previa', ['total' => 3, 'novas' => 3, 'ja_tinham' => 0])
            ->call('confirmarConcessao');

        expect(CupomConcessao::pluck('usuario_id')->sort()->values()->all())
            ->toBe(collect([$antiga->id, $ultimoSegundo->id, $nodiaUtc->id])->sort()->values()->all());
    });

    it('concede a mais de um lote de contas', function () {
        Usuario::query()->delete();
        Usuario::factory()->count(530)->create();

        ($this->tela)()->call('abrirConcessao', $this->cupom->id)
            ->set('concessao.modo', 'todas')->set('concessao.ate', now()->toDateString())->call('calcularConcessao')
            ->assertSet('previa.total', 530)->call('confirmarConcessao');

        expect(CupomConcessao::count())->toBe(530);
    });

    it('exige uma data válida no modo de todas as contas', function (string $ate) {
        ($this->tela)()->call('abrirConcessao', $this->cupom->id)->set('concessao.modo', 'todas')->set('concessao.ate', $ate)->call('calcularConcessao')
            ->assertHasErrors(['concessao.ate']);
    })->with(['', '2026-02-30', '31/10/2026', 'ontem']);

    it('mudar o formulário depois do cálculo invalida a prévia', function () {
        ($this->tela)()->call('abrirConcessao', $this->cupom->id)->set('concessao.emails', $this->contas[0]->email)->call('calcularConcessao')
            ->assertNotSet('previa', null)->set('concessao.emails', $this->contas[1]->email)->assertSet('previa', null);
    });

    it('não oferece confirmar quando todas já têm o cupom', function () {
        CupomConcessao::create(['cupom_id' => $this->cupom->id, 'usuario_id' => $this->contas[0]->id, 'concedida_em' => now()]);

        ($this->tela)()->call('abrirConcessao', $this->cupom->id)->set('concessao.emails', $this->contas[0]->email)->call('calcularConcessao')
            ->assertSet('previa', ['total' => 1, 'novas' => 0, 'ja_tinham' => 1])->assertDontSeeHtml('dusk="concessao-confirmar"');
    });

    it('não abre nem concede cupom desativado', function () {
        $this->cupom->update(['ativo' => false]);

        ($this->tela)()->call('abrirConcessao', $this->cupom->id)->assertNotFound();

        // Desativado depois de aberta a concessão.
        $this->cupom->update(['ativo' => true]);
        $tela = ($this->tela)()->call('abrirConcessao', $this->cupom->id)->set('concessao.emails', $this->contas[0]->email)->call('calcularConcessao');
        $this->cupom->update(['ativo' => false]);
        $tela->call('confirmarConcessao')->assertHasErrors('concessao.cupom_id');

        expect(CupomConcessao::count())->toBe(0);
    });

    it('desativar o cupom fecha a concessão aberta dele', function () {
        ($this->tela)()->call('abrirConcessao', $this->cupom->id)->call('alternarCupom', $this->cupom->id)->assertSet('concessao.cupom_id', null);
    });

    it('cancelar fecha a concessão', function () {
        ($this->tela)()->call('abrirConcessao', $this->cupom->id)->assertSee('Conceder o cupom BETA')->call('cancelarConcessao')->assertDontSee('Conceder o cupom BETA');
    });

    it('abrir um cupom inexistente dá 404', function () {
        ($this->tela)()->call('abrirConcessao', '0192f9a2-0000-7000-8000-000000000000')->assertNotFound();
    });
});

describe('CA-5 sem duplicidade', function () {
    it('conceder de novo à mesma conta não cria outra linha e informa quantas já tinham', function () {
        $cupom = ($this->cupom)(['codigo' => 'BETA']);
        $a = Usuario::factory()->create();
        $b = Usuario::factory()->create();

        $conceder = fn (string $emails) => ($this->tela)()->call('abrirConcessao', $cupom->id)->set('concessao.emails', $emails)->call('calcularConcessao');

        $conceder($a->email)->call('confirmarConcessao');
        expect(CupomConcessao::count())->toBe(1);

        $conceder("{$a->email}\n{$b->email}")->assertSet('previa', ['total' => 2, 'novas' => 1, 'ja_tinham' => 1])
            ->call('confirmarConcessao')->assertSee('1 conta recebeu o cupom BETA (1 já tinham).');

        expect(CupomConcessao::count())->toBe(2)
            ->and(CupomConcessao::where('usuario_id', $a->id)->count())->toBe(1);

        $conceder($a->email)->call('confirmarConcessao');
        expect(CupomConcessao::count())->toBe(2);
    });

    it('a concessão em massa também é idempotente', function () {
        $cupom = ($this->cupom)(['codigo' => 'BETA']);
        Usuario::factory()->count(2)->create();

        foreach ([1, 2] as $_) {
            ($this->tela)()->call('abrirConcessao', $cupom->id)->set('concessao.modo', 'todas')->set('concessao.ate', now()->toDateString())->call('calcularConcessao')->call('confirmarConcessao');
        }

        expect(CupomConcessao::count())->toBe(Usuario::query()->count());
    });

    it('o banco impede a duplicata mesmo fora da tela', function () {
        $cupom = ($this->cupom)();
        $u = Usuario::factory()->create();
        CupomConcessao::create(['cupom_id' => $cupom->id, 'usuario_id' => $u->id, 'concedida_em' => now()]);

        expect(fn () => CupomConcessao::create(['cupom_id' => $cupom->id, 'usuario_id' => $u->id, 'concedida_em' => now()]))->toThrow(UniqueConstraintViolationException::class);
    });
});

describe('CA-7 concessões e usos por cupom', function () {
    it('lista a contagem de concessões e usos por cupom', function () {
        $cupom = ($this->cupom)(['codigo' => 'LISTA', 'limite_usos' => 10]);
        $outro = ($this->cupom)(['codigo' => 'OUTRO']);
        $contas = Usuario::factory()->count(3)->create();
        foreach ($contas as $c) {
            CupomConcessao::create(['cupom_id' => $cupom->id, 'usuario_id' => $c->id, 'concedida_em' => now()]);
        }
        CupomUso::create(['cupom_id' => $cupom->id, 'usuario_id' => $contas[0]->id, 'usado_em' => now()]);
        CupomUso::create(['cupom_id' => $cupom->id, 'usuario_id' => $contas[1]->id, 'usado_em' => now()]);
        CupomConcessao::create(['cupom_id' => $outro->id, 'usuario_id' => $contas[2]->id, 'concedida_em' => now()]);

        ($this->tela)()
            ->assertSeeHtmlInOrder(['dusk="cupom-concessoes-'.$cupom->id.'">3<', 'dusk="cupom-usos-'.$cupom->id.'">2 / 10<'])
            ->assertSeeHtml('dusk="cupom-concessoes-'.$outro->id.'">1<')
            ->assertSeeHtml('dusk="cupom-usos-'.$outro->id.'">0<');
    });

    it('detalha quem recebeu e quem usou, e fecha ao clicar de novo', function () {
        $cupom = ($this->cupom)(['codigo' => 'DETALHE']);
        $recebeu = Usuario::factory()->create(['nome' => 'Maria Concedida']);
        $usou = Usuario::factory()->create(['nome' => 'José Usuário']);
        CupomConcessao::create(['cupom_id' => $cupom->id, 'usuario_id' => $recebeu->id, 'concedida_em' => '2026-11-03 10:00:00']);
        CupomUso::create(['cupom_id' => $cupom->id, 'usuario_id' => $usou->id, 'desconto_centavos' => 4990, 'usado_em' => '2026-11-04 10:00:00']);

        ($this->tela)()->assertDontSee('Maria Concedida')
            ->call('detalhar', $cupom->id)
            ->assertSee('Maria Concedida')->assertSee($recebeu->email)->assertSee('03/11/2026')
            ->assertSee('José Usuário')->assertSee('04/11/2026')->assertSee('R$ 49,90')
            ->call('detalhar', $cupom->id)->assertDontSee('Maria Concedida');
    });

    it('mostra o vazio quando não há concessões nem usos', function () {
        $cupom = ($this->cupom)();

        ($this->tela)()->call('detalhar', $cupom->id)->assertSee('Nenhuma concessão.')->assertSee('Nenhum uso.');
    });

    it('detalhar um cupom inexistente dá 404', function () {
        ($this->tela)()->call('detalhar', '0192f9a2-0000-7000-8000-000000000000')->assertNotFound();
    });
});

describe('CA-8 auditoria', function () {
    it('audita a abertura da tela, a criação, a desativação e a concessão', function () {
        $tela = ($this->preencher)(($this->tela)(), ['codigo' => 'AUDITA'])->call('salvarCupom');
        $cupom = Cupom::where('codigo', 'AUDITA')->firstOrFail();
        $conta = Usuario::factory()->create();

        $tela->call('abrirConcessao', $cupom->id)->set('concessao.emails', $conta->email)->call('calcularConcessao')->call('confirmarConcessao')
            ->call('alternarCupom', $cupom->id);

        $criado = ($this->auditorias)('cupons.cupom_criado')->first();
        expect($criado->subject_type)->toBe('Cupom')->and($criado->subject_id)->toBe($cupom->id)->and($criado->before)->toBeNull()
            ->and($criado->after['codigo'])->toBe('AUDITA')->and($criado->after['tipo'])->toBe('percentual')->and($criado->after['valor'])->toBe(25)
            ->and($criado->after['vigencia_fim'])->toBe('2026-12-31')->and($criado->after['itens'])->toBe(["plano:{$this->plano->id}:mensal"])
            ->and($criado->actor_id)->toBe($this->admin->id);

        $concedido = ($this->auditorias)('cupons.cupom_concedido')->first();
        expect($concedido->subject_id)->toBe($cupom->id)->and($concedido->after['modo'])->toBe('contas')
            ->and($concedido->after['concedidas'])->toBe(1)->and($concedido->after['ja_tinham'])->toBe(0)
            ->and($concedido->after['usuarios'])->toBe([$conta->id]);

        expect(($this->auditorias)('cupons.cupom_desativado'))->toHaveCount(1)
            ->and(($this->auditorias)('backoffice.cupons_listados'))->toHaveCount(1);
    });

    it('audita a concessão em massa com a data e as contagens, sem listar todos os ids', function () {
        $cupom = ($this->cupom)(['codigo' => 'MASSA']);
        Usuario::factory()->count(2)->create();

        ($this->tela)()->call('abrirConcessao', $cupom->id)->set('concessao.modo', 'todas')->set('concessao.ate', '2099-12-31')->call('calcularConcessao')->call('confirmarConcessao');

        $log = ($this->auditorias)('cupons.cupom_concedido')->first();
        expect($log->after['modo'])->toBe('todas')->and($log->after['cadastradas_ate'])->toBe('2099-12-31')
            ->and($log->after['concedidas'])->toBeGreaterThanOrEqual(3)->and($log->after['usuarios'])->toBeNull();
    });
});

describe('itens cobertos e regra de uso para o checkout (STORY-083)', function () {
    it('cobre planos por id e ciclo, e pacotes por id', function () {
        $cupom = ($this->cupom)();
        $cupom->itens()->create(['plano_id' => $this->plano->id, 'ciclo' => 'mensal']);
        $cupom->itens()->create(['pacote_credito_id' => $this->pacote->id]);
        $outroPlano = Plano::query()->where('id', '!=', $this->plano->id)->firstOrFail();
        $outroPacote = PacoteCredito::query()->where('id', '!=', $this->pacote->id)->firstOrFail();

        expect($cupom->cobre($this->plano->id, 'mensal', null))->toBeTrue()
            ->and($cupom->cobre($this->plano->id, 'anual', null))->toBeFalse()
            ->and($cupom->cobre($outroPlano->id, 'mensal', null))->toBeFalse()
            ->and($cupom->cobre(null, null, $this->pacote->id))->toBeTrue()
            ->and($cupom->cobre(null, null, $outroPacote->id))->toBeFalse();
    });

    it('avalia vigência, limite, cobertura e uso anterior', function () {
        $cupom = ($this->cupom)(['vigencia_inicio' => '2026-11-01', 'vigencia_fim' => '2026-11-30', 'limite_usos' => 1]);
        $cupom->itens()->create(['plano_id' => $this->plano->id, 'ciclo' => 'mensal']);
        [$a, $b] = [Usuario::factory()->create(), Usuario::factory()->create()];
        $agora = Carbon::parse('2026-11-15');

        expect($cupom->recusaPara($a->id, $this->plano->id, 'mensal', null, $agora))->toBeNull()
            ->and($cupom->recusaPara($a->id, $this->plano->id, 'anual', null, $agora))->toBe(MotivoRecusaCupom::ItemNaoCoberto)
            ->and($cupom->recusaPara($a->id, $this->plano->id, 'mensal', null, Carbon::parse('2026-12-01')))->toBe(MotivoRecusaCupom::ForaDaVigencia);

        CupomUso::create(['cupom_id' => $cupom->id, 'usuario_id' => $a->id, 'usado_em' => now()]);

        expect($cupom->recusaPara($a->id, $this->plano->id, 'mensal', null, $agora))->toBe(MotivoRecusaCupom::LimiteEsgotado)
            ->and($cupom->recusaPara($b->id, $this->plano->id, 'mensal', null, $agora))->toBe(MotivoRecusaCupom::LimiteEsgotado);

        $cupom->update(['limite_usos' => null]);
        expect($cupom->fresh()->recusaPara($a->id, $this->plano->id, 'mensal', null, $agora))->toBe(MotivoRecusaCupom::JaUtilizado)
            ->and($cupom->fresh()->recusaPara($b->id, $this->plano->id, 'mensal', null, $agora))->toBeNull();
    });

    it('o banco recusa tipo, faixa e vigência inválidos mesmo fora da tela', function (array $attrs) {
        expect(fn () => ($this->cupom)($attrs))->toThrow(QueryException::class);
    })->with([
        'percentual 101' => [['valor' => 101]],
        'valor zero' => [['valor' => 0]],
        'vigência invertida' => [['vigencia_inicio' => '2026-12-31', 'vigencia_fim' => '2026-12-30']],
    ]);

    it('um item não pode ser plano e pacote ao mesmo tempo, nem repetir', function () {
        $cupom = ($this->cupom)();
        $cupom->itens()->create(['plano_id' => $this->plano->id, 'ciclo' => 'mensal']);

        expect(fn () => $cupom->itens()->create(['plano_id' => $this->plano->id, 'ciclo' => 'mensal']))->toThrow(QueryException::class)
            ->and(fn () => $cupom->itens()->create(['plano_id' => $this->plano->id, 'ciclo' => 'anual', 'pacote_credito_id' => $this->pacote->id]))->toThrow(QueryException::class);
    });

    it('mostra o item de plano inativo na lista de opções, marcado', function () {
        $this->plano->update(['ativo' => false]);

        ($this->tela)()->assertSee($this->plano->nome.' (mensal) - inativo');
    });
});

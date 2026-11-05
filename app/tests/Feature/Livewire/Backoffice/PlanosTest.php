<?php

declare(strict_types=1);

use App\Livewire\Backoffice\Planos;
use App\Models\AuditLog;
use App\Models\PacoteCredito;
use App\Models\Plano;
use App\Models\TrialConfig;
use App\Models\Usuario;
use App\Support\PermissaoAdmin;
use Livewire\Livewire;

/**
 * STORY-071 / PDR-020 — catálogo de planos, pacotes e trial no backoffice.
 */
beforeEach(function () {
    config(['backoffice.equipe_emails' => []]);
    $this->admin = Usuario::factory()->create();
    $this->admin->forceFill(['permissoes_admin' => ['planos.gerenciar']])->save();
    $this->tela = fn () => Livewire::actingAs($this->admin)->test(Planos::class);
    $this->auditorias = fn (string $acao) => AuditLog::where('action', $acao)->get();
});

describe('CA-1 carga inicial', function () {
    it('traz Básico e Pro, os pacotes e o trial do PDR-020', function () {
        $planos = Plano::query()->orderBy('ordem')->get();
        $pacotes = PacoteCredito::query()->orderBy('ordem')->get();

        expect($planos->map->only('nome', 'cota_mensal', 'preco_mensal_centavos', 'preco_anual_centavos', 'ativo')->all())->toBe([
            ['nome' => 'Básico', 'cota_mensal' => 1, 'preco_mensal_centavos' => 4990, 'preco_anual_centavos' => 39900, 'ativo' => true],
            ['nome' => 'Pro', 'cota_mensal' => 10, 'preco_mensal_centavos' => 19900, 'preco_anual_centavos' => 159900, 'ativo' => true],
        ])->and($pacotes->map->only('nome', 'quantidade_creditos', 'preco_centavos', 'validade_meses', 'ativo')->all())->toBe([
            ['nome' => 'Mini', 'quantidade_creditos' => 1, 'preco_centavos' => 8990, 'validade_meses' => 12, 'ativo' => true],
            ['nome' => 'Plus', 'quantidade_creditos' => 5, 'preco_centavos' => 34990, 'validade_meses' => 12, 'ativo' => true],
            ['nome' => 'Max', 'quantidade_creditos' => 10, 'preco_centavos' => 59990, 'validade_meses' => 12, 'ativo' => true],
        ])->and(TrialConfig::atual()->only('ativo', 'dias', 'analises'))->toBe(['ativo' => true, 'dias' => 7, 'analises' => 1]);
    });

    it('mostra a carga inicial na tela', function () {
        ($this->tela)()->assertSee('Básico')->assertSee('R$ 199,00')->assertSee('R$ 1.599,00')
            ->assertSee('Mini')->assertSee('R$ 349,90')->assertSet('trial.dias', '7')->assertSet('trial.analises', '1');
    });
});

describe('CA-2 permissão', function () {
    it('abre a tela só com planos.gerenciar, com 404 sem ela e para o usuário comum', function () {
        $this->actingAs($this->admin)->get('/backoffice/planos')->assertOk();

        $outro = Usuario::factory()->create();
        $outro->forceFill(['permissoes_admin' => ['glossario.editar']])->save();
        $this->actingAs($outro)->get('/backoffice/planos')->assertNotFound();
        $this->actingAs(Usuario::factory()->create())->get('/backoffice/planos')->assertNotFound();
    });

    it('manda o anônimo para o login', function () {
        $this->get('/backoffice/planos')->assertRedirect('/login');
    });

    it('mostra o item no menu só a quem tem a permissão', function () {
        $this->actingAs($this->admin)->get('/backoffice/planos')->assertSee('app-nav-backoffice-planos');

        $outro = Usuario::factory()->create();
        $outro->forceFill(['permissoes_admin' => ['glossario.editar']])->save();
        $this->actingAs($outro)->get('/backoffice/glossario')->assertOk()->assertDontSee('app-nav-backoffice-planos');
    });

    it('recusa as ações do Livewire de quem perdeu a permissão', function () {
        $plano = Plano::query()->firstOrFail();
        $comum = Usuario::factory()->create();

        foreach (['salvarPlano', 'salvarPacote', 'salvarTrial'] as $acao) {
            Livewire::actingAs($comum)->test(Planos::class)->assertNotFound();
        }

        expect($plano->fresh()->nome)->toBe($plano->nome);
    });

    it('a permissão aparece para o raiz conceder', function () {
        expect(array_map(fn ($p) => $p->value, PermissaoAdmin::cases()))->toContain('planos.gerenciar');
    });
});

describe('CA-3 planos', function () {
    it('cria um plano com preços em reais e código gerado', function () {
        ($this->tela)()
            ->set('plano.nome', '  Equipe ')->set('plano.cota_mensal', '25')
            ->set('plano.preco_mensal', '349,90')->set('plano.preco_anual', '3.499,00')->set('plano.ordem', '3')
            ->call('salvarPlano')->assertHasNoErrors()->assertSet('plano.nome', '')->assertSee('Equipe');

        $plano = Plano::query()->where('nome', 'Equipe')->firstOrFail();

        expect($plano->only('codigo', 'cota_mensal', 'preco_mensal_centavos', 'preco_anual_centavos', 'ordem', 'ativo'))->toBe([
            'codigo' => 'equipe', 'cota_mensal' => 25, 'preco_mensal_centavos' => 34990, 'preco_anual_centavos' => 349900, 'ordem' => 3, 'ativo' => true,
        ])->and($plano->alterado_por_usuario_id)->toBe($this->admin->id);
    });

    it('gera código único quando o nome gera o mesmo código', function () {
        foreach (['Pro', 'Pró'] as $nome) {
            ($this->tela)()->set('plano.nome', $nome)->set('plano.cota_mensal', '1')
                ->set('plano.preco_mensal', '1')->set('plano.preco_anual', '10')->call('salvarPlano')->assertHasNoErrors();
        }

        expect(Plano::query()->pluck('codigo')->sort()->values()->all())->toBe(['basico', 'pro', 'pro-2', 'pro-3']);
    });

    it('edita o plano mantendo o código e o carregamento mostra preços em reais', function () {
        $pro = Plano::query()->where('nome', 'Pro')->firstOrFail();

        ($this->tela)()->call('editarPlano', $pro->id)
            ->assertSet('plano.preco_mensal', '199,00')->assertSet('plano.preco_anual', '1599,00')
            ->set('plano.nome', 'Profissional')->set('plano.preco_mensal', '219.90')->set('plano.cota_mensal', '12')
            ->call('salvarPlano')->assertHasNoErrors()->assertSet('plano.id', null);

        expect($pro->fresh()->only('codigo', 'nome', 'preco_mensal_centavos', 'cota_mensal'))
            ->toBe(['codigo' => 'pro', 'nome' => 'Profissional', 'preco_mensal_centavos' => 21990, 'cota_mensal' => 12]);
    });

    it('desativa e reativa sem excluir', function () {
        $basico = Plano::query()->where('nome', 'Básico')->firstOrFail();

        ($this->tela)()->call('alternarPlano', $basico->id);
        expect($basico->fresh()->ativo)->toBeFalse();

        ($this->tela)()->call('alternarPlano', $basico->id);
        expect($basico->fresh()->ativo)->toBeTrue()->and(Plano::query()->count())->toBe(2);
    });

    it('cancelar a edição limpa o formulário com a próxima ordem', function () {
        $pro = Plano::query()->where('nome', 'Pro')->firstOrFail();

        ($this->tela)()->call('editarPlano', $pro->id)->call('limparPlano')
            ->assertSet('plano.id', null)->assertSet('plano.nome', '')->assertSet('plano.ordem', '3');
    });

    it('recusa editar um plano inexistente', function () {
        ($this->tela)()->call('editarPlano', '00000000-0000-0000-0000-000000000000')->assertNotFound();
    });
});

describe('CA-4 pacotes', function () {
    it('cria um pacote', function () {
        ($this->tela)()
            ->set('pacote.nome', 'Giga')->set('pacote.quantidade_creditos', '20')->set('pacote.preco', '999,00')
            ->set('pacote.validade_meses', '6')->set('pacote.ordem', '4')
            ->call('salvarPacote')->assertHasNoErrors()->assertSet('pacote.nome', '')->assertSet('pacote.validade_meses', '12');

        expect(PacoteCredito::query()->where('nome', 'Giga')->firstOrFail()->only('codigo', 'quantidade_creditos', 'preco_centavos', 'validade_meses', 'ordem', 'ativo'))
            ->toBe(['codigo' => 'giga', 'quantidade_creditos' => 20, 'preco_centavos' => 99900, 'validade_meses' => 6, 'ordem' => 4, 'ativo' => true]);
    });

    it('edita e desativa um pacote', function () {
        $mini = PacoteCredito::query()->where('nome', 'Mini')->firstOrFail();

        ($this->tela)()->call('editarPacote', $mini->id)->assertSet('pacote.preco', '89,90')
            ->set('pacote.preco', '79,90')->set('pacote.ativo', false)->call('salvarPacote')->assertHasNoErrors();

        expect($mini->fresh()->only('preco_centavos', 'ativo', 'codigo'))->toBe(['preco_centavos' => 7990, 'ativo' => false, 'codigo' => 'mini']);

        ($this->tela)()->call('alternarPacote', $mini->id);
        expect($mini->fresh()->ativo)->toBeTrue();
    });

    it('cancelar a edição limpa o formulário', function () {
        $mini = PacoteCredito::query()->where('nome', 'Mini')->firstOrFail();

        ($this->tela)()->call('editarPacote', $mini->id)->call('limparPacote')->assertSet('pacote.id', null)->assertSet('pacote.nome', '');
    });
});

describe('CA-5 trial', function () {
    it('edita ativo, dias e análises', function () {
        ($this->tela)()->set('trial.ativo', false)->set('trial.dias', '14')->set('trial.analises', '2')
            ->call('salvarTrial')->assertHasNoErrors()->assertSet('mensagemTrial', 'Trial salvo.');

        expect(TrialConfig::atual()->only('ativo', 'dias', 'analises'))->toBe(['ativo' => false, 'dias' => 14, 'analises' => 2])
            ->and(TrialConfig::query()->count())->toBe(1);
    });
});

describe('CA-6 valores inválidos', function () {
    it('recusa plano inválido com mensagem por campo', function (array $campos, array $erros) {
        $tela = ($this->tela)();
        foreach ($campos as $campo => $valor) {
            $tela->set("plano.{$campo}", $valor);
        }
        $tela->call('salvarPlano');

        foreach ($erros as $campo => $mensagem) {
            expect($tela->errors()->first("plano.{$campo}"))->toBe($mensagem);
        }
        expect(Plano::query()->count())->toBe(2);
    })->with([
        'tudo vazio' => [['ordem' => ''], [
            'nome' => 'Informe o nome do plano.', 'cota_mensal' => 'Informe a cota mensal.',
            'preco_mensal' => 'Informe o preço mensal.', 'preco_anual' => 'Informe o preço anual.', 'ordem' => 'Informe a ordem.',
        ]],
        'preço negativo' => [['nome' => 'X', 'cota_mensal' => '1', 'preco_mensal' => '-10,00', 'preco_anual' => '-1', 'ordem' => '1'], [
            'preco_mensal' => 'O preço não pode ser negativo.', 'preco_anual' => 'O preço não pode ser negativo.',
        ]],
        'cota zero' => [['nome' => 'X', 'cota_mensal' => '0', 'preco_mensal' => '1', 'preco_anual' => '1'], ['cota_mensal' => 'A cota mensal deve ser de pelo menos 1 análise.']],
        'cota fracionada' => [['nome' => 'X', 'cota_mensal' => '1,5', 'preco_mensal' => '1', 'preco_anual' => '1'], ['cota_mensal' => 'A cota mensal deve ser um número inteiro.']],
        'cota enorme' => [['nome' => 'X', 'cota_mensal' => '999999999', 'preco_mensal' => '1', 'preco_anual' => '1'], ['cota_mensal' => 'A cota mensal é grande demais.']],
        'preço texto' => [['nome' => 'X', 'cota_mensal' => '1', 'preco_mensal' => 'abc', 'preco_anual' => '1,234'], [
            'preco_mensal' => 'Informe o preço em reais, por exemplo 49,90.', 'preco_anual' => 'Informe o preço em reais, por exemplo 49,90.',
        ]],
        'preço enorme' => [['nome' => 'X', 'cota_mensal' => '1', 'preco_mensal' => '1.000.000,00', 'preco_anual' => '1'], ['preco_mensal' => 'O preço é alto demais.']],
        'nome longo e ordem zero' => [['nome' => str_repeat('a', 61), 'cota_mensal' => '1', 'preco_mensal' => '1', 'preco_anual' => '1', 'ordem' => '0'], [
            'nome' => 'O nome pode ter até 60 caracteres.', 'ordem' => 'A ordem deve ser 1 ou mais.',
        ]],
    ]);

    it('aceita preço zero (plano gratuito) e formatos com R$ e ponto decimal', function () {
        ($this->tela)()->set('plano.nome', 'Grátis')->set('plano.cota_mensal', '1')->set('plano.preco_mensal', 'R$ 0')->set('plano.preco_anual', '0.00')
            ->call('salvarPlano')->assertHasNoErrors();

        expect(Plano::query()->where('nome', 'Grátis')->firstOrFail()->only('preco_mensal_centavos', 'preco_anual_centavos'))->toBe(['preco_mensal_centavos' => 0, 'preco_anual_centavos' => 0]);
    });

    it('recusa pacote inválido com mensagem por campo', function (array $campos, array $erros) {
        $tela = ($this->tela)();
        foreach ($campos as $campo => $valor) {
            $tela->set("pacote.{$campo}", $valor);
        }
        $tela->call('salvarPacote');

        foreach ($erros as $campo => $mensagem) {
            expect($tela->errors()->first("pacote.{$campo}"))->toBe($mensagem);
        }
        expect(PacoteCredito::query()->count())->toBe(3);
    })->with([
        'tudo vazio' => [['validade_meses' => '', 'ordem' => ''], [
            'nome' => 'Informe o nome do pacote.', 'quantidade_creditos' => 'Informe a quantidade de créditos.', 'preco' => 'Informe o preço.',
            'validade_meses' => 'Informe a validade em meses.', 'ordem' => 'Informe a ordem.',
        ]],
        'quantidade zero e preço negativo' => [['nome' => 'X', 'quantidade_creditos' => '0', 'preco' => '-1,00'], [
            'quantidade_creditos' => 'A quantidade deve ser de pelo menos 1 crédito.', 'preco' => 'O preço não pode ser negativo.',
        ]],
        'quantidade negativa' => [['nome' => 'X', 'quantidade_creditos' => '-3', 'preco' => '1'], ['quantidade_creditos' => 'A quantidade deve ser de pelo menos 1 crédito.']],
        'validade zero' => [['nome' => 'X', 'quantidade_creditos' => '1', 'preco' => '1', 'validade_meses' => '0'], ['validade_meses' => 'A validade deve ser de pelo menos 1 mês.']],
    ]);

    it('recusa trial sem dias ou sem análises', function (string $dias, string $analises, array $erros) {
        $tela = ($this->tela)()->set('trial.dias', $dias)->set('trial.analises', $analises)->call('salvarTrial');

        foreach ($erros as $campo => $mensagem) {
            expect($tela->errors()->first("trial.{$campo}"))->toBe($mensagem);
        }
        expect(TrialConfig::atual()->only('dias', 'analises'))->toBe(['dias' => 7, 'analises' => 1]);
    })->with([
        'sem dias' => ['', '1', ['dias' => 'Informe os dias do trial.']],
        'zero dias' => ['0', '1', ['dias' => 'O trial deve ter pelo menos 1 dia.']],
        'sem análises' => ['7', '', ['analises' => 'Informe as análises incluídas.']],
        'zero análises' => ['7', '0', ['analises' => 'O trial deve incluir pelo menos 1 análise.']],
    ]);
});

describe('CA-7 aviso de preço do crédito', function () {
    it('avisa e deixa salvar quando o unitário fica abaixo do de um plano ativo', function () {
        $tela = ($this->tela)()->set('pacote.nome', 'Barato')->set('pacote.quantidade_creditos', '10')->set('pacote.preco', '400,00')
            ->assertSee('o crédito sai mais barato que a análise do plano Básico')
            ->assertDontSee('plano Básico, Pro');

        $tela->call('salvarPacote')->assertHasNoErrors();

        expect(PacoteCredito::query()->where('nome', 'Barato')->exists())->toBeTrue();
        $tela->assertSee('Abaixo do unitário do plano Básico.');
    });

    it('não avisa com preço dentro da regra, e o seed está dentro', function () {
        ($this->tela)()->set('pacote.quantidade_creditos', '10')->set('pacote.preco', '599,90')->assertDontSee('sai mais barato')
            ->assertDontSee('Abaixo do unitário');
    });

    it('ignora plano inativo no aviso', function () {
        Plano::query()->where('nome', 'Básico')->update(['ativo' => false]);

        ($this->tela)()->set('pacote.quantidade_creditos', '10')->set('pacote.preco', '400,00')->assertDontSee('sai mais barato');
    });

    it('não avisa enquanto o preço ou a quantidade estão incompletos', function () {
        ($this->tela)()->set('pacote.preco', 'abc')->assertDontSee('sai mais barato')
            ->set('pacote.preco', '1')->set('pacote.quantidade_creditos', '')->assertDontSee('sai mais barato');
    });
});

describe('CA-8 auditoria', function () {
    it('grava criação, alteração e desativação com antes, depois e autor', function () {
        $tela = ($this->tela)()->set('plano.nome', 'Equipe')->set('plano.cota_mensal', '5')->set('plano.preco_mensal', '99')->set('plano.preco_anual', '990')->call('salvarPlano');
        $plano = Plano::query()->where('nome', 'Equipe')->firstOrFail();

        $criado = ($this->auditorias)('planos.plano_criado')->sole();
        expect($criado->subject_type)->toBe('Plano')->and($criado->subject_id)->toBe($plano->id)->and($criado->actor_id)->toBe($this->admin->id)
            ->and($criado->before)->toBeNull()->and($criado->after['preco_mensal_centavos'])->toBe(9900);

        $tela->call('editarPlano', $plano->id)->set('plano.preco_mensal', '89,90')->call('salvarPlano');
        $alterado = ($this->auditorias)('planos.plano_alterado')->sole();
        expect($alterado->before['preco_mensal_centavos'])->toBe(9900)->and($alterado->after['preco_mensal_centavos'])->toBe(8990)
            ->and($alterado->actor_id)->toBe($this->admin->id);

        $tela->call('alternarPlano', $plano->id);
        $desativado = ($this->auditorias)('planos.plano_alterado')->last();
        expect($desativado->before->getArrayCopy())->toBe(['ativo' => true])->and($desativado->after->getArrayCopy())->toBe(['ativo' => false]);
    });

    it('audita pacote e trial', function () {
        $mini = PacoteCredito::query()->where('nome', 'Mini')->firstOrFail();

        ($this->tela)()->call('editarPacote', $mini->id)->set('pacote.preco', '99,90')->call('salvarPacote')
            ->set('trial.dias', '10')->call('salvarTrial');

        $pacote = ($this->auditorias)('planos.pacote_credito_alterado')->sole();
        expect($pacote->subject_type)->toBe('PacoteCredito')->and($pacote->before['preco_centavos'])->toBe(8990)->and($pacote->after['preco_centavos'])->toBe(9990);

        $trial = ($this->auditorias)('planos.trial_config_alterado')->sole();
        expect($trial->subject_type)->toBe('TrialConfig')->and($trial->before['dias'])->toBe(7)->and($trial->after['dias'])->toBe(10)->and($trial->actor_id)->toBe($this->admin->id);

        ($this->tela)()->set('pacote.nome', 'Novo')->set('pacote.quantidade_creditos', '2')->set('pacote.preco', '150')->call('salvarPacote');
        expect(($this->auditorias)('planos.pacote_credito_criado'))->toHaveCount(1);
    });

    it('não grava nada quando salvar não muda nada', function () {
        $pro = Plano::query()->where('nome', 'Pro')->firstOrFail();
        $mini = PacoteCredito::query()->where('nome', 'Mini')->firstOrFail();
        $antes = AuditLog::query()->count();
        $atualizadoEm = $pro->updated_at;

        ($this->tela)()->call('editarPlano', $pro->id)->call('salvarPlano')->assertHasNoErrors()
            ->call('editarPacote', $mini->id)->call('salvarPacote')->assertHasNoErrors()
            ->call('salvarTrial')->assertHasNoErrors();

        expect(AuditLog::query()->where('action', 'like', 'planos.%')->count())->toBe(0)
            ->and(AuditLog::query()->count())->toBe($antes + 1) // só a listagem do mount
            ->and($pro->fresh()->updated_at->equalTo($atualizadoEm))->toBeTrue();
    });

    it('registra a abertura da tela', function () {
        ($this->tela)();

        expect(($this->auditorias)('backoffice.planos_listados'))->toHaveCount(1);
    });
});

describe('formatação de preços', function () {
    it('converte texto em centavos e de volta', function (string $texto, ?int $centavos) {
        expect(Planos::centavos($texto))->toBe($centavos);
    })->with([
        ['49,90', 4990], ['49.90', 4990], ['1.599,00', 159900], ['R$ 49', 4900], ['0', 0], ['0,5', 50],
        ['', null], ['abc', null], ['-1', null], ['1,234', null], ['1,2,3', null],
    ]);

    it('formata centavos em reais sem separador de milhar', function () {
        expect(Planos::reais(159900))->toBe('1599,00')->and(Planos::reais(5))->toBe('0,05');
    });
});

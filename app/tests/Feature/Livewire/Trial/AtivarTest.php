<?php

declare(strict_types=1);

use App\Domain\Cobranca\Carteira;
use App\Domain\Cobranca\DocumentoHash;
use App\Domain\Cobranca\Trial;
use App\Livewire\Trial\Ativar;
use App\Models\Assinatura;
use App\Models\CobrancaConfig;
use App\Models\EmpresaAnalisada;
use App\Models\Plano;
use App\Models\TrialAtivacao;
use App\Models\TrialConfig;
use App\Models\Usuario;
use App\Observabilidade\AlertaDaEquipe;
use Livewire\Livewire;

/**
 * STORY-074 / spec STORY-070-trial — oferta, ativação em um clique, confirmação, andamento e recusas.
 */
beforeEach(function () {
    app()->instance(AlertaDaEquipe::class, Mockery::mock(AlertaDaEquipe::class)->shouldIgnoreMissing());
    CobrancaConfig::atual()->update(['ativa' => true]);
    $this->usuario = Usuario::factory()->create();
    $this->tela = fn () => Livewire::actingAs($this->usuario)->test(Ativar::class);
});

describe('CA-9 interruptor e acesso', function () {
    it('responde 404 com a cobrança desligada e exige login', function () {
        CobrancaConfig::atual()->update(['ativa' => false]);

        $this->actingAs($this->usuario)->get('/teste-gratis')->assertNotFound();

        CobrancaConfig::atual()->update(['ativa' => true]);
        auth()->logout();
        $this->get('/teste-gratis')->assertRedirect('/login');
    });
});

describe('CA-1 oferta e ativação', function () {
    it('mostra a oferta com os valores da configuração, sem ativar só por abrir a página', function () {
        TrialConfig::atual()->update(['dias' => 10, 'analises' => 2]);

        ($this->tela)()->assertSee('10 dias, 2 análises e o Raio X do Negócio.')->assertSee('Sem cartão e sem cobrança no fim.')
            ->assertSee('Começar teste grátis');

        expect(Assinatura::query()->count())->toBe(0);
    });

    it('ativa com um clique, confirma com a data e leva a cadastrar a primeira empresa', function () {
        $this->travelTo(now()->setDate(2026, 11, 5)->setTime(12, 0));

        ($this->tela)()->call('ativar')
            ->assertSee('Seu teste começou')->assertSee('até 12/11/2026 para fazer 1 análise')
            ->assertSee('Cadastrar uma empresa')->assertDontSee('Fazer uma análise')
            ->assertDontSee('Começar teste grátis');

        $a = Assinatura::query()->sole();
        expect($a->trial)->toBeTrue()->and($a->cota_snapshot)->toBe(1);
    });

    it('com empresa cadastrada, o primário é fazer uma análise', function () {
        EmpresaAnalisada::factory()->create(['usuario_id' => $this->usuario->id]);

        ($this->tela)()->call('ativar')->assertSee('Fazer uma análise')->assertSee('Cadastrar uma empresa');
    });

    it('mostra o andamento a quem já está em teste, sem oferta', function () {
        app(Trial::class)->ativar($this->usuario->id);

        ($this->tela)()->assertSee('Teste grátis: termina em')->assertDontSee('Começar teste grátis')->assertSee('Ver planos');
    });

    it('não ativa quem está logado como o usuário (administrador)', function () {
        $admin = Usuario::factory()->create(['email' => 'equipe@ebparcerias.com.br']);
        config(['backoffice.equipe_emails' => ['equipe@ebparcerias.com.br']]);
        $this->actingAs($admin)->post('/backoffice/usuarios/'.$this->usuario->id.'/logar-como')->assertRedirect('/home');

        $this->get('/teste-gratis')->assertOk()->assertSee('o botão abaixo fica desativado')->assertSee('aria-disabled="true"', false);

        // A chamada vem pelo endpoint do Livewire, com a sessão da personificação.
        $snapshot = Livewire::actingAs($this->usuario)->test(Ativar::class)->snapshot;
        $this->withHeaders(['X-Livewire' => '1'])->postJson(app('livewire')->getUpdateUri(), [
            '_token' => csrf_token(),
            'components' => [['snapshot' => json_encode($snapshot), 'updates' => [], 'calls' => [['method' => 'ativar', 'params' => [], 'metadata' => []]]]],
        ])->assertOk();

        expect(Assinatura::query()->count())->toBe(0);
    });
});

describe('CA-2 recusas', function () {
    it('explica que o CPF já usou o teste e oferece os planos', function () {
        TrialAtivacao::query()->create(['usuario_id' => null, 'cpf_hash' => DocumentoHash::de($this->usuario->cpf), 'ativado_em' => now()->subMonth()]);

        ($this->tela)()->assertSee('Já existe um teste gratuito ativado com este CPF, então não conseguimos abrir outro.')
            ->assertSee('Ver planos e preços')->assertDontSee('Começar teste grátis')
            ->assertSee(route('planos'), false);
    });

    it('recusa ao tentar ativar por baixo dos panos quando o CPF foi usado entre abrir e clicar', function () {
        $tela = ($this->tela)();
        TrialAtivacao::query()->create(['usuario_id' => null, 'cpf_hash' => DocumentoHash::de($this->usuario->cpf), 'ativado_em' => now()]);

        $tela->call('ativar')->assertSee('Já existe um teste gratuito ativado com este CPF');

        expect(Assinatura::query()->count())->toBe(0);
    });

    it('explica que já há um plano ativo', function () {
        $plano = Plano::query()->firstOrFail();
        app(Carteira::class)->concederCota($this->usuario->id, 1, now(), now()->addMonth(), $plano->id, 'mensal');

        ($this->tela)()->assertSee('Você já tem um plano ativo, então não precisa do teste gratuito.');
    });

    it('mostra o erro genérico com "Tentar de novo" se a ativação falha de forma inesperada', function () {
        $falho = Mockery::mock(Trial::class);
        $falho->shouldReceive('ativar')->andThrow(new RuntimeException('banco caiu'));
        app()->instance(Trial::class, $falho);

        ($this->tela)()->call('ativar')->assertSee('Não conseguimos ativar o teste agora. Tente de novo em instantes.')->assertSee('Tentar de novo');
    });
});

describe('CA-4 trial desativado', function () {
    it('não mostra a oferta e recusa a ativação por URL', function () {
        TrialConfig::atual()->update(['ativo' => false]);

        ($this->tela)()->assertSee('O teste grátis não está disponível no momento.')->assertDontSee('Começar teste grátis')->assertSee('Ver planos')
            ->call('ativar')->assertSee('O teste grátis não está disponível no momento.');

        expect(Assinatura::query()->count())->toBe(0);
    });
});

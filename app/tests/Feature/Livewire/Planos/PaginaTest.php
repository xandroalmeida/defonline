<?php

declare(strict_types=1);

use App\Domain\Cobranca\Bloqueio;
use App\Domain\Cobranca\Carteira;
use App\Domain\Cobranca\MotivoBloqueio;
use App\Livewire\Planos\Pagina;
use App\Models\CobrancaConfig;
use App\Models\PacoteCredito;
use App\Models\Plano;
use App\Models\TrialConfig;
use App\Models\Usuario;
use App\Support\Cobranca\RotasCobranca;
use App\Support\Cobranca\TextoBloqueio;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

/**
 * STORY-075 — página de planos e preços (spec STORY-070-planos-e-precos). O catálogo de seed
 * (Básico, Pro, Mini, Plus, Max, trial 7 dias/1 análise) vem das migrations.
 */
beforeEach(function () {
    CobrancaConfig::atual()->update(['ativa' => true]);
});

function rotaFalsa(string $nome): void
{
    Route::get('/falsa-'.$nome, fn () => 'ok')->name($nome);
    Route::getRoutes()->refreshNameLookups();
}

describe('CA-7 interruptor', function () {
    it('responde 404 a todos com a cobrança desligada', function () {
        CobrancaConfig::atual()->update(['ativa' => false]);

        $this->get('/planos')->assertNotFound();
        $this->actingAs(Usuario::factory()->create())->get('/planos')->assertNotFound();
    });

    it('esconde o item do menu com a cobrança desligada e mostra com ela ligada', function () {
        $usuario = Usuario::factory()->create();

        $this->actingAs($usuario)->get('/home')->assertSee('app-nav-planos', false);

        CobrancaConfig::atual()->update(['ativa' => false]);
        $this->actingAs($usuario)->get('/home')->assertDontSee('app-nav-planos', false);
    });
});

describe('CA-1 planos', function () {
    it('lista os planos ativos com cota, preço mensal e os benefícios, incluindo o Raio X', function () {
        $this->get('/planos')->assertOk()
            ->assertSee('Planos e preços')
            ->assertSee('Básico')->assertSee('R$ 49,90')->assertSee('1 análise por mês')
            ->assertSee('Pro')->assertSee('R$ 199,00')->assertSee('10 análises por mês')
            ->assertSee('Empresas sem limite')->assertSee('Raio X do Negócio incluído');
    });

    it('no ciclo anual mostra o preço por ano, o equivalente mensal e a economia', function () {
        Livewire::test(Pagina::class)->set('ciclo', 'anual')
            ->assertSee('R$ 399,00')->assertSee('por ano')->assertSee('R$ 33,25 por mês')->assertSee('economia de 33%')
            ->assertSee('Anual — economize 33%');
    });

    it('ignora um ciclo inventado', function () {
        Livewire::test(Pagina::class)->set('ciclo', 'bienal')->assertSet('ciclo', 'mensal');
    });

    it('não mostra a economia quando o anual não é mais barato', function () {
        Plano::query()->update(['ativo' => false]);
        Plano::query()->create(['codigo' => 'caro', 'nome' => 'Caro', 'cota_mensal' => 2, 'preco_mensal_centavos' => 1000, 'preco_anual_centavos' => 12000, 'ativo' => true, 'ordem' => 9]);

        Livewire::test(Pagina::class)->set('ciclo', 'anual')->assertSee('R$ 120,00')->assertSee('R$ 10,00 por mês')
            ->assertDontSee('economia de')->assertSee('Anual')->assertDontSee('economize');
    });

    it('não lista plano inativo', function () {
        Plano::query()->where('nome', 'Pro')->update(['ativo' => false]);

        $this->get('/planos')->assertSee('Básico')->assertDontSee('R$ 199,00');
    });
});

describe('CA-2 pacotes', function () {
    it('lista os pacotes com quantidade, preço, unitário, validade e a negativa do Raio X', function () {
        $this->get('/planos')->assertOk()
            ->assertSee('Créditos avulsos')
            ->assertSee('5 análises')->assertSee('R$ 349,90')->assertSee('R$ 69,98 por análise')->assertSee('válidas por 12 meses')
            ->assertSee('1 análise')->assertSee('R$ 89,90')
            ->assertSee('Não incluem o Raio X do Negócio')->assertSee('Não inclui o Raio X do Negócio');
    });

    it('usa o singular na validade de um mês', function () {
        PacoteCredito::query()->update(['ativo' => false]);
        PacoteCredito::query()->create(['codigo' => 'um', 'nome' => 'Um mês', 'quantidade_creditos' => 2, 'preco_centavos' => 10000, 'validade_meses' => 1, 'ativo' => true, 'ordem' => 1]);

        $this->get('/planos')->assertSee('válidas por 1 mês');
    });

    it('some a seção inteira sem pacotes ativos', function () {
        PacoteCredito::query()->update(['ativo' => false]);

        $this->get('/planos')->assertOk()->assertDontSee('Créditos avulsos')->assertSee('Básico');
    });
});

describe('CA-3 teste', function () {
    it('mostra a oferta com os dias e as análises da configuração', function () {
        $this->get('/planos')->assertSee('Teste grátis')->assertSee('7 dias, 1 análise e o Raio X do Negócio. Sem cartão.');

        TrialConfig::atual()->update(['dias' => 1, 'analises' => 3]);
        $this->get('/planos')->assertSee('1 dia, 3 análises e o Raio X do Negócio. Sem cartão.');
    });

    it('não mostra a oferta com o trial desativado', function () {
        TrialConfig::atual()->update(['ativo' => false]);

        $this->get('/planos')->assertOk()->assertDontSee('Teste grátis')->assertDontSee('screen-planos-teste', false);
    });

    it('não mostra a oferta se a linha de configuração sumiu', function () {
        TrialConfig::query()->delete();

        $this->get('/planos')->assertOk()->assertDontSee('Teste grátis');
    });

    it('não mostra a oferta ao logado que já usou o teste', function () {
        $usuario = Usuario::factory()->create();
        app(Carteira::class)->concederCota($usuario->id, 1, now()->subDays(20), now()->subDays(13), trial: true);

        $this->actingAs($usuario)->get('/planos')->assertOk()->assertDontSee('screen-planos-teste', false);
    });

    it('mostra a oferta ao logado elegível, levando à ativação do teste', function () {
        $usuario = Usuario::factory()->create();

        $this->actingAs($usuario)->get('/planos')->assertSee('screen-planos-teste', false)
            ->assertSee('Começar teste grátis')->assertSee(route(RotasCobranca::TESTE), false);
    });

    it('leva o visitante a criar a conta para começar o teste', function () {
        $this->get('/planos')->assertSee('Criar conta e começar o teste')->assertSee(route('cadastro'), false);
    });
});

describe('CA-4 catálogo vivo', function () {
    it('mostra a alteração do catálogo na hora, sem deploy', function () {
        $pagina = Livewire::test(Pagina::class)->assertSee('R$ 49,90');

        Plano::query()->where('nome', 'Básico')->update(['preco_mensal_centavos' => 5990, 'nome' => 'Essencial']);

        $pagina->call('$refresh')->assertSee('R$ 59,90')->assertSee('Essencial')->assertDontSee('R$ 49,90');
    });

    it('avisa quando não há plano ativo e mantém os créditos', function () {
        Plano::query()->update(['ativo' => false]);

        $this->get('/planos')->assertSee('Não há planos à venda neste momento.')->assertSee('Créditos avulsos');
    });

    it('avisa que voltamos em breve quando nada está ativo', function () {
        Plano::query()->update(['ativo' => false]);
        PacoteCredito::query()->update(['ativo' => false]);

        $this->get('/planos')->assertSee('Voltamos em breve com novas ofertas.')->assertSee('Voltar')->assertDontSee('Créditos avulsos');
    });

    it('avisa e oferece tentar de novo se o catálogo não carrega, sem mostrar preço', function () {
        Schema::rename('planos', 'planos_fora');

        $this->get('/planos')->assertOk()->assertSee('Não conseguimos carregar os planos agora.')->assertSee('Tentar de novo')
            ->assertDontSee('R$ 49,90');
    });
});

describe('CA-5 destino dos botões', function () {
    it('leva o visitante ao cadastro em cada oferta', function () {
        $html = $this->get('/planos')->assertSee('Criar conta para assinar')->assertSee('Criar conta para comprar')->getContent();

        expect(substr_count($html, route('cadastro')))->toBeGreaterThanOrEqual(1 + Plano::count() + PacoteCredito::count());
    });

    it('leva o logado ao checkout do item, com o ciclo escolhido', function () {
        rotaFalsa(RotasCobranca::COMPRAR);
        $basico = Plano::query()->where('nome', 'Básico')->firstOrFail();
        $plus = PacoteCredito::query()->where('nome', 'Plus')->firstOrFail();

        Livewire::actingAs(Usuario::factory()->create())->test(Pagina::class)->set('ciclo', 'anual')
            ->assertSee('Assinar')
            ->assertSee(route(RotasCobranca::ASSINAR, ['plano' => $basico->id, 'ciclo' => 'anual']))
            ->assertSee(route(RotasCobranca::COMPRAR, ['pacote' => $plus->id]), false);
    });

    it('mostra Assinar em cada plano ativo, apontando ao checkout existente com plano e ciclo, e mostra Comprar', function () {
        expect(Route::has(RotasCobranca::ASSINAR))->toBeTrue();

        $resposta = $this->actingAs(Usuario::factory()->create())->get('/planos')->assertOk()
            ->assertSee('screen-planos-comprar-', false);

        foreach (Plano::query()->where('ativo', true)->get() as $plano) {
            $resposta->assertSee('screen-planos-assinar-'.$plano->id, false)
                ->assertSee(route(RotasCobranca::ASSINAR, ['plano' => $plano->id, 'ciclo' => 'mensal']), false);
        }
    });

    it('não mostra Assinar para o plano inativo', function () {
        $inativo = Plano::query()->where('nome', 'Pro')->firstOrFail();
        $inativo->update(['ativo' => false]);

        $this->actingAs(Usuario::factory()->create())->get('/planos')->assertOk()
            ->assertDontSee('screen-planos-assinar-'.$inativo->id, false);
    });

    it('marca o plano atual e oferece a troca nos outros', function () {
        rotaFalsa(RotasCobranca::TROCAR);
        $usuario = Usuario::factory()->create();
        $basico = Plano::query()->where('nome', 'Básico')->firstOrFail();
        $pro = Plano::query()->where('nome', 'Pro')->firstOrFail();
        app(Carteira::class)->concederCota($usuario->id, 1, now(), now()->addMonth(), $basico->id, 'mensal');

        $this->actingAs($usuario)->get('/planos')->assertOk()
            ->assertSee('Seu plano')->assertSee('Trocar para este')
            ->assertSee(route(RotasCobranca::TROCAR, ['plano' => $pro->id, 'ciclo' => 'mensal']))
            ->assertDontSee(route(RotasCobranca::TROCAR, ['plano' => $basico->id, 'ciclo' => 'mensal']));
    });

    it('desativa os botões de quem está logado como o usuário', function () {
        config(['backoffice.equipe_emails' => ['equipe@ebparcerias.com.br']]);
        $admin = Usuario::factory()->create(['email' => 'equipe@ebparcerias.com.br']);
        $alvo = Usuario::factory()->create();
        $this->actingAs($admin)->post('/backoffice/usuarios/'.$alvo->id.'/logar-como')->assertRedirect('/home');

        $this->get('/planos')->assertOk()->assertSee('os botões abaixo ficam desativados')->assertSee('aria-disabled="true"', false);
    });
});

describe('CA-6 acesso', function () {
    it('a mensagem de bloqueio passa a oferecer "Ver planos"', function () {
        $acoes = TextoBloqueio::disponiveis(TextoBloqueio::para(new Bloqueio(MotivoBloqueio::SemNada))['acoes']);

        expect(collect($acoes)->pluck('href')->all())->toContain(route('planos'));
    });
});

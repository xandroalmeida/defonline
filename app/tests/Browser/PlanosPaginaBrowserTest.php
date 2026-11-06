<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Models\CobrancaConfig;
use App\Models\PacoteCredito;
use App\Models\Plano;
use App\Models\TrialConfig;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Dusk\Browser;
use Tests\Browser\Concerns\VerificaAlinhamento;
use Tests\DuskTestCase;

/**
 * STORY-075 — o visitante compara planos e créditos, alterna o ciclo e é levado ao cadastro; o
 * logado chega pelo menu. Com a cobrança desligada a página some. Spec STORY-070-planos-e-precos.
 *
 * NÃO está no grupo `smoke` por design — faz writes.
 */
final class PlanosPaginaBrowserTest extends DuskTestCase
{
    use DatabaseTruncation, VerificaAlinhamento;

    /** @var list<string> */
    protected array $tablesToTruncate = ['usuarios', 'assinaturas', 'audit_logs', 'sessions', 'cache'];

    /** O TRUNCATE de `usuarios` é CASCADE e leva o catálogo junto (ver BloqueioSemSaldoBrowserTest): recria o mínimo. */
    protected function afterTruncatingDatabase(): void
    {
        Plano::query()->firstOrCreate(['codigo' => 'basico'], ['nome' => 'Básico', 'cota_mensal' => 1, 'preco_mensal_centavos' => 4990, 'preco_anual_centavos' => 39900, 'ativo' => true, 'ordem' => 1]);
        PacoteCredito::query()->firstOrCreate(['codigo' => 'plus'], ['nome' => 'Plus', 'quantidade_creditos' => 5, 'preco_centavos' => 34990, 'validade_meses' => 12, 'ativo' => true, 'ordem' => 1]);
        TrialConfig::query()->firstOrCreate([], ['ativo' => true, 'dias' => 7, 'analises' => 1]);
        CobrancaConfig::atual();
    }

    protected function tearDown(): void
    {
        CobrancaConfig::atual()->update(['ativa' => false]);

        parent::tearDown();
    }

    public function test_visitante_compara_alterna_o_ciclo_e_vai_ao_cadastro(): void
    {
        CobrancaConfig::atual()->update(['ativa' => true]);
        $plano = Plano::query()->firstOrFail();

        $this->browse(function (Browser $browser) use ($plano) {
            $browser->visit('/planos')
                ->waitFor('@screen-planos')
                ->assertSee('Planos e preços')
                ->assertSee('7 dias, 1 análise e o Raio X do Negócio. Sem cartão.')
                ->assertSeeIn('@screen-planos-preco-'.$plano->id, 'R$ 49,90')
                ->assertSee('Não incluem o Raio X do Negócio')
                ->click('@screen-planos-ciclo-anual + span')
                ->waitForTextIn('@screen-planos-preco-'.$plano->id, 'R$ 399,00')
                ->assertSeeIn('@screen-planos-preco-'.$plano->id, 'R$ 33,25 por mês · economia de 33%')
                ->click('@screen-planos-assinar-'.$plano->id)
                ->waitForLocation('/cadastro')
                ->assertPathIs('/cadastro');
        });
    }

    /** RNF-UI-02: planos e pacotes vizinhos com a mesma altura e as ações na mesma linha. */
    public function test_cards_de_planos_e_pacotes_ficam_alinhados(): void
    {
        CobrancaConfig::atual()->update(['ativa' => true]);
        $basico = Plano::query()->where('codigo', 'basico')->firstOrFail();
        $pro = Plano::query()->firstOrCreate(['codigo' => 'pro'], ['nome' => 'Pro', 'cota_mensal' => 10, 'preco_mensal_centavos' => 19900, 'preco_anual_centavos' => 159900, 'ativo' => true, 'ordem' => 2]);
        $plus = PacoteCredito::query()->where('codigo', 'plus')->firstOrFail();
        $mini = PacoteCredito::query()->firstOrCreate(['codigo' => 'mini'], ['nome' => 'Mini', 'quantidade_creditos' => 1, 'preco_centavos' => 8990, 'validade_meses' => 12, 'ativo' => true, 'ordem' => 0]);
        $max = PacoteCredito::query()->firstOrCreate(['codigo' => 'max'], ['nome' => 'Max', 'quantidade_creditos' => 10, 'preco_centavos' => 59990, 'validade_meses' => 12, 'ativo' => true, 'ordem' => 2]);

        $this->browse(function (Browser $browser) use ($basico, $pro, $mini, $plus, $max) {
            $browser->resize(1280, 900)->visit('/planos')->waitFor('@screen-planos');
            $this->assertAlinhados($browser, [
                ["screen-planos-plano-{$basico->id}", "screen-planos-plano-{$pro->id}"],
                ["screen-planos-assinar-{$basico->id}", "screen-planos-assinar-{$pro->id}"],
                ["screen-planos-pacote-{$mini->id}", "screen-planos-pacote-{$plus->id}"],
                ["screen-planos-pacote-{$plus->id}", "screen-planos-pacote-{$max->id}"],
                ["screen-planos-comprar-{$mini->id}", "screen-planos-comprar-{$plus->id}"],
                ["screen-planos-comprar-{$plus->id}", "screen-planos-comprar-{$max->id}"],
            ]);

            $browser->resize(390, 1200)->visit('/planos')->waitFor('@screen-planos');
            $this->assertFalse($browser->script('return document.documentElement.scrollWidth > window.innerWidth')[0], 'A tela não pode rolar para o lado no celular.');
            $browser->resize(1920, 1080); // O navegador é o mesmo no teste seguinte, que usa o menu lateral.
        });
    }

    public function test_logado_chega_pelo_menu_e_a_pagina_some_com_a_cobranca_desligada(): void
    {
        $usuario = Usuario::factory()->create();

        $this->browse(function (Browser $browser) use ($usuario) {
            $browser->loginAs($usuario)->visit('/home')->waitFor('@app-nav-minhas-empresas')
                ->assertMissing('@app-nav-planos')
                ->visit('/planos')->assertSee('404');

            CobrancaConfig::atual()->update(['ativa' => true]);

            $browser->visit('/home')->waitFor('@app-nav-planos')
                ->click('@app-nav-planos')
                ->waitForLocation('/planos')
                ->assertSee('Planos e preços')
                ->assertSee('Teste grátis')
                ->assertMissing('@screen-planos-teste-cadastro');
        });
    }
}

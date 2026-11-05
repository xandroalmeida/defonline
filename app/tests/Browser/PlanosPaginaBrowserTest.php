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
use Tests\DuskTestCase;

/**
 * STORY-075 — o visitante compara planos e créditos, alterna o ciclo e é levado ao cadastro; o
 * logado chega pelo menu. Com a cobrança desligada a página some. Spec STORY-070-planos-e-precos.
 *
 * NÃO está no grupo `smoke` por design — faz writes.
 */
final class PlanosPaginaBrowserTest extends DuskTestCase
{
    use DatabaseTruncation;

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

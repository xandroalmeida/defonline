<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Domain\Cobranca\DocumentoHash;
use App\Models\CobrancaConfig;
use App\Models\PacoteCredito;
use App\Models\Plano;
use App\Models\TrialAtivacao;
use App\Models\TrialConfig;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * STORY-074 — Roberto vê a oferta na Home, ativa o teste em um clique e lê a confirmação; quem já usou
 * o teste (CPF) lê o motivo e segue para os planos. Spec STORY-070-trial.
 *
 * NÃO está no grupo `smoke` por design — faz writes.
 */
final class TrialBrowserTest extends DuskTestCase
{
    use DatabaseTruncation;

    /** @var list<string> */
    protected array $tablesToTruncate = ['usuarios', 'assinaturas', 'trial_ativacoes', 'trial_empresas', 'audit_logs', 'sessions', 'cache'];

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

    public function test_usuario_ativa_o_teste_da_home_e_ve_a_confirmacao(): void
    {
        CobrancaConfig::atual()->update(['ativa' => true]);
        $usuario = Usuario::factory()->create();

        $this->browse(function (Browser $browser) use ($usuario) {
            $browser->loginAs($usuario)->visit('/home')
                ->waitFor('@screen-trial-oferta-home')
                ->assertSee('7 dias, 1 análise e o Raio X do Negócio. Sem cartão.')
                ->click('@screen-trial-oferta-home-cta')
                ->waitForLocation('/teste-gratis')
                ->waitFor('@screen-trial-ativar')
                ->click('@screen-trial-ativar')
                ->waitFor('@screen-trial-confirmacao')
                ->assertSee('Seu teste começou')
                ->assertSee('para fazer 1 análise e lançar no Raio X do Negócio.')
                ->assertPresent('@screen-trial-cadastrar-empresa')
                ->assertMissing('@screen-trial-ativar')
                ->visit('/home')
                ->waitFor('@screen-trial-aviso')
                ->assertSeeIn('@screen-trial-aviso', 'Teste grátis: termina em')
                ->assertMissing('@screen-trial-oferta-home');
        });
    }

    public function test_quem_ja_usou_o_cpf_le_o_motivo_e_segue_para_os_planos(): void
    {
        CobrancaConfig::atual()->update(['ativa' => true]);
        $usuario = Usuario::factory()->create();
        TrialAtivacao::query()->create(['usuario_id' => null, 'cpf_hash' => DocumentoHash::de($usuario->cpf), 'ip' => null, 'ativado_em' => now()->subMonth()]);

        $this->browse(function (Browser $browser) use ($usuario) {
            $browser->loginAs($usuario)->visit('/teste-gratis')
                ->waitFor('@screen-trial-error-inelegivel')
                ->assertSeeIn('@screen-trial-error-inelegivel', 'Já existe um teste gratuito ativado com este CPF, então não conseguimos abrir outro.')
                ->assertMissing('@screen-trial-ativar')
                ->click('@screen-trial-ver-planos')
                ->waitForLocation('/planos')
                ->assertSee('Planos e preços')
                ->assertMissing('@screen-planos-teste');
        });
    }

    public function test_a_pagina_do_teste_some_com_a_cobranca_desligada(): void
    {
        $usuario = Usuario::factory()->create();

        $this->browse(function (Browser $browser) use ($usuario) {
            $browser->loginAs($usuario)->visit('/home')->waitFor('@app-nav-minhas-empresas')
                ->assertMissing('@screen-trial-oferta-home')
                ->visit('/teste-gratis')->assertSee('404');
        });
    }
}

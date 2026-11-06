<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Domain\Cobranca\Carteira;
use App\Models\CobrancaConfig;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\Browser\Concerns\VerificaAlinhamento;
use Tests\DuskTestCase;

/** STORY-097 — do menu à conta: dados salvos, erro na senha, senha trocada e resumo no celular. */
class MinhaContaBrowserTest extends DuskTestCase
{
    use DatabaseMigrations, VerificaAlinhamento;

    public function test_usuario_edita_dados_e_senha_e_ve_o_resumo(): void
    {
        CobrancaConfig::atual()->update(['ativa' => true]);
        $usuario = Usuario::factory()->create(['nome' => 'Maria da Silva', 'telefone' => '11987654321', 'senha_hash' => 'SenhaAtual123', 'email_confirmed_at' => now()]);
        app(Carteira::class)->concederCreditos($usuario->id, 3, now()->addMonth());

        $this->browse(function (Browser $browser) use ($usuario) {
            $browser->loginAs($usuario)->visit('/home')
                ->click('@app-nav-conta')
                ->waitFor('@screen-conta-titulo')
                ->assertValue('@screen-conta-nome', 'Maria da Silva')
                ->assertValue('@screen-conta-telefone', '(11) 98765-4321')
                ->assertSeeIn('@screen-conta-creditos-total', '3');

            $this->assertAlinhados($browser, [
                ['screen-conta-dados', 'screen-conta-senha'],
                ['screen-conta-plano', 'screen-conta-creditos'],
                ['screen-conta-cupons', 'screen-conta-cobrancas'],
                ['screen-conta-salvar-dados', 'screen-conta-trocar-senha'],
                ['screen-conta-ver-planos', 'screen-conta-comprar-creditos'],
            ]);

            $browser->clear('@screen-conta-nome')->type('@screen-conta-nome', 'Ana Paula')
                ->clear('@screen-conta-telefone')->type('@screen-conta-telefone', '21988887777')
                ->assertValue('@screen-conta-telefone', '(21) 98888-7777')
                ->click('@screen-conta-salvar-dados')
                ->waitFor('@screen-conta-dados-salvos')
                ->assertSee('Olá, Ana');

            $browser->type('@screen-conta-senha-atual', 'errada1234')
                ->type('@screen-conta-nova-senha', 'SenhaNova456')
                ->type('@screen-conta-nova-senha-confirmacao', 'SenhaNova456')
                ->click('@screen-conta-trocar-senha')
                ->waitFor('@screen-conta-senha-atual-erro')
                ->clear('@screen-conta-senha-atual')->type('@screen-conta-senha-atual', 'SenhaAtual123')
                ->click('@screen-conta-trocar-senha')
                ->waitFor('@screen-conta-senha-alterada');

            $browser->resize(390, 1600)->visit('/conta')->waitFor('@screen-conta-titulo')->pause(500);
            $this->assertFalse($browser->script('return document.documentElement.scrollWidth > window.innerWidth')[0], 'A tela não pode rolar para o lado no celular.');
        });

        $this->assertSame('21988887777', $usuario->fresh()->telefone);
    }
}

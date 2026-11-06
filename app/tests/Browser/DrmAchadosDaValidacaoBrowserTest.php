<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Actions\Drm\FecharLancamento;
use App\Domain\Drm\Competencia;
use App\Domain\Drm\Lancamento as LancamentoDrm;
use App\Domain\Drm\Modo;
use App\Domain\Drm\Setor;
use App\Models\EmpresaAnalisada;
use App\Models\Usuario;
use Facebook\WebDriver\Chrome\ChromeDevToolsDriver;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * STORY-095 CA-5 e CA-7 — achados da validação do EPIC-010 que só um browser real prova:
 * contraste do botão de trocar o modo (D-4), botão "Fechar o mês" travado de verdade com alerta
 * pendente (O-3) e bloco "fora do movimento normal" que não se recolhe sozinho (O-4).
 */
final class DrmAchadosDaValidacaoBrowserTest extends DuskTestCase
{
    use DatabaseTruncation;

    /** @var list<string> */
    protected array $tablesToTruncate = [
        'drm_lancamentos', 'drm_rascunhos', 'drm_preferencias', 'empresas_analisadas', 'usuarios',
        'audit_logs', 'business_metrics', 'term_acceptances', 'sessions', 'evento_produto', 'cache',
    ];

    private function empresa(Usuario $usuario): EmpresaAnalisada
    {
        return EmpresaAnalisada::factory()->create(['usuario_id' => $usuario->id, 'cnae' => '4711301']);
    }

    /** Razão de contraste WCAG entre a cor do texto e o fundo efetivo do elemento. */
    private function contraste(Browser $browser, string $seletor): float
    {
        return (float) $browser->script(<<<JS
            const el = document.querySelector('{$seletor}');
            const rgb = (c) => c.match(/[\d.]+/g).slice(0, 4).map(Number);
            const lum = ([r, g, b]) => { const f = (v) => { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); }; return 0.2126 * f(r) + 0.7152 * f(g) + 0.0722 * f(b); };
            let fundo = null;
            for (let n = el; n && !fundo; n = n.parentElement) {
                const c = rgb(getComputedStyle(n).backgroundColor);
                if ((c[3] ?? 1) > 0) fundo = c;
            }
            fundo = fundo ?? [255, 255, 255];
            const a = lum(rgb(getComputedStyle(el).color)), b = lum(fundo);
            return (Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05);
        JS)[0];
    }

    public function test_botao_de_trocar_o_modo_tem_contraste_aa_sobre_o_fundo_da_pagina(): void
    {
        $usuario = Usuario::factory()->create();
        $empresa = $this->empresa($usuario);
        app(FecharLancamento::class)->execute($empresa, Competencia::deTexto('2026-09'), LancamentoDrm::deTexto(
            Setor::Comercio,
            ['custo' => Modo::Agregado, 'vendas' => Modo::Agregado, 'administrativas' => Modo::Agregado, 'financeiras' => Modo::Agregado],
            ['D01' => '1.000,00', 'D02' => '100,00', 'D03' => '200,00', 'D08' => '50,00', 'D15' => '60,00', 'D37' => '10,00', 'D40' => '100,00'],
        ), [], null);

        $this->browse(function (Browser $browser) use ($usuario, $empresa) {
            $browser->resize(360, 800)->loginAs($usuario)->visit(route('drm.mes', [$empresa, '2026-09']))->waitFor('@drm-trocar-modo');
            $this->assertGreaterThanOrEqual(4.5, $this->contraste($browser, '[dusk="drm-trocar-modo"]'), 'contraste do botão de trocar o modo');
        });
    }

    public function test_fechar_o_mes_fica_disabled_com_alerta_pendente_e_destrava_ao_aceitar(): void
    {
        $usuario = Usuario::factory()->create();
        $empresa = $this->empresa($usuario);

        $this->browse(function (Browser $browser) use ($usuario, $empresa) {
            $browser->resize(360, 800)->loginAs($usuario)->visit(route('drm.lancar', $empresa))->waitFor('@drm-lancamento-bloco-1')
                ->type('@drm-lancamento-campo-D01', '100000')->type('@drm-lancamento-campo-D02', '1000')->pause(500);
            foreach ([2, 3, 4] as $bloco) {
                $browser->press('Próximo')->waitFor('@drm-lancamento-bloco-'.$bloco);
            }
            $browser->press('Próximo')->waitFor('@drm-lancamento-bloco-5')
                ->type('@drm-lancamento-campo-D40', '0')->pause(500)   // sem retirada: alerta A5
                ->press('Próximo')->waitFor('@drm-lancamento-conferencia')->waitFor('@drm-lancamento-aceitar-alertas')->pause(800);

            // O atributo, não só o aria-disabled: o clique do teclado e do leitor de tela respeita `disabled`.
            $this->assertSame('true', $browser->attribute('@drm-lancamento-fechar', 'disabled'));
            $this->assertSame('true', $browser->attribute('@drm-lancamento-fechar', 'aria-disabled'));

            $browser->click('@drm-lancamento-aceitar-alertas')->waitFor('@drm-lancamento-alertas-aceitos')->pause(500);
            $this->assertNull($browser->attribute('@drm-lancamento-fechar', 'disabled'));
        });
    }

    public function test_bloco_fora_do_movimento_normal_nao_recolhe_com_a_resposta_do_servidor(): void
    {
        $usuario = Usuario::factory()->create();
        $empresa = $this->empresa($usuario);

        $this->browse(function (Browser $browser) use ($usuario, $empresa) {
            $dev = new ChromeDevToolsDriver($browser->driver);
            $dev->execute('Network.enable');
            $browser->resize(360, 800)->loginAs($usuario)->visit(route('drm.lancar', $empresa))->waitFor('@drm-lancamento-bloco-1')
                ->type('@drm-lancamento-campo-D01', '100000')->type('@drm-lancamento-campo-D02', '1000')->pause(500);
            foreach ([2, 3, 4, 5] as $bloco) {
                $browser->press('Próximo')->waitFor('@drm-lancamento-bloco-'.$bloco);
            }

            // Abre como a pessoa abre: tocando no resumo. O D41 ainda não tem valor.
            $browser->script("document.querySelector('[dusk=\"drm-lancamento-nao-operacionais\"] summary').click()");
            $browser->pause(300)->assertVisible('@drm-lancamento-campo-D41');

            // Qualquer resposta do servidor (aqui, a do campo D40 ao lado, com latência) não pode fechá-lo.
            $dev->execute('Network.emulateNetworkConditions', ['offline' => false, 'latency' => 200, 'downloadThroughput' => -1, 'uploadThroughput' => -1]);
            $browser->type('@drm-lancamento-campo-D40', '5000')->pause(1800);
            $aberto = $browser->script("return document.querySelector('[dusk=\"drm-lancamento-nao-operacionais\"]').open")[0];
            $this->assertTrue($aberto, 'o bloco recolheu com a resposta do servidor');

            $browser->type('@drm-lancamento-campo-D41', '12345')->pause(1800);
            $dev->execute('Network.emulateNetworkConditions', ['offline' => false, 'latency' => 0, 'downloadThroughput' => -1, 'uploadThroughput' => -1]);
            $this->assertSame('123,45', $browser->value('@drm-lancamento-campo-D41'));
        });
    }
}

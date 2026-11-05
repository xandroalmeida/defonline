<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Domain\Drm\Competencia;
use App\Domain\Drm\Grupo;
use App\Domain\Drm\Modo;
use App\Models\DrmLancamento;
use App\Models\DrmPreferencia;
use App\Models\DrmRascunho;
use App\Models\EmpresaAnalisada;
use App\Models\Usuario;
use App\Support\DataExibicao;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * STORY-047 CA-11 e CA-12 — o lançamento mensal do DRM num browser real, em 360×800.
 *
 * Prova o que o Pest não alcança: a máscara BRL sincronizada com o Livewire (o valor
 * PERSISTIDO é o que a tela mostra, BUG-001), ausência de scroll horizontal, alvos de
 * toque ≥ 44px, a confirmação destrutiva inline e o caminho de dois toques do mês seguinte.
 */
final class DrmLancamentoBrowserTest extends DuskTestCase
{
    use DatabaseTruncation;

    /** @var list<string> */
    protected array $tablesToTruncate = [
        'drm_lancamentos', 'drm_rascunhos', 'drm_preferencias', 'empresas_analisadas', 'usuarios',
        'audit_logs', 'business_metrics', 'term_acceptances', 'sessions', 'evento_produto', 'cache',
    ];

    private function empresa(Usuario $usuario): EmpresaAnalisada
    {
        // CNAE 4711-3/01: comércio varejista → setor Comércio.
        return EmpresaAnalisada::factory()->create([
            'usuario_id' => $usuario->id, 'cnae' => '4711301', 'nome_fantasia' => 'Padaria do Bairro',
        ]);
    }

    private function clicarModo(Browser $browser, string $grupo, string $modo): void
    {
        // O rádio é visualmente oculto (sr-only) dentro de um <label>; o clique real é no rótulo.
        $browser->script("document.querySelector('[dusk=\"drm-lancamento-modo-{$grupo}-{$modo}\"]').closest('label').click()");
        $browser->pause(400);
    }

    private function semScrollHorizontal(Browser $browser): void
    {
        $larguras = $browser->script('return [document.documentElement.scrollWidth, window.innerWidth]')[0];
        $this->assertLessThanOrEqual($larguras[1], $larguras[0], 'scroll horizontal em 360px');
    }

    /** @return list<float> Menor altura entre os controles interativos visíveis do formulário. */
    private function menorAlvoDeToque(Browser $browser): array
    {
        return $browser->script(<<<'JS'
            const alvos = [...document.querySelectorAll('form .input-affix, form button.btn, form select, form label:has(input[type=radio]) > span')];
            return [Math.min(...alvos.filter(e => e.offsetParent !== null).map(e => e.getBoundingClientRect().height))];
        JS)[0];
    }

    public function test_mobile_percorre_o_lancamento_completo_em_modo_agregado(): void
    {
        $usuario = Usuario::factory()->create(['nome' => 'Joana Souza']);
        $empresa = $this->empresa($usuario);

        $this->browse(function (Browser $browser) use ($usuario, $empresa) {
            $browser->resize(360, 800)
                ->loginAs($usuario)
                ->visit(route('drm.lancar', $empresa))
                ->waitFor('@drm-lancamento-bloco-1')
                ->assertSee('Padaria do Bairro · Comércio')
                ->assertSee('Este é o primeiro mês desta empresa.')
                // A máscara mostra 84.300,00 e é isso que tem de ficar guardado.
                ->type('@drm-lancamento-campo-D01', '8430000')
                ->type('@drm-lancamento-campo-D02', '505800')
                ->assertInputValue('@drm-lancamento-campo-D01', '84.300,00');
            $this->semScrollHorizontal($browser);

            $browser->press('Próximo')->waitFor('@drm-lancamento-bloco-2');
            $this->clicarModo($browser, 'custo', 'agregado');
            $browser->waitFor('@drm-lancamento-campo-D03')
                ->type('@drm-lancamento-campo-D03', '4120000')
                ->press('Próximo')->waitFor('@drm-lancamento-bloco-3');

            $this->clicarModo($browser, 'vendas', 'agregado');
            $browser->type('@drm-lancamento-campo-D08', '900000')
                ->press('Próximo')->waitFor('@drm-lancamento-bloco-4');

            $this->clicarModo($browser, 'administrativas', 'agregado');
            $browser->type('@drm-lancamento-campo-D15', '2070200')
                ->press('Próximo')->waitFor('@drm-lancamento-bloco-5');

            $this->clicarModo($browser, 'financeiras', 'agregado');
            $browser->type('@drm-lancamento-campo-D40', '400000')
                ->type('@drm-lancamento-campo-D37', '110000');
            $this->semScrollHorizontal($browser);
            $browser->press('Próximo')->waitFor('@drm-lancamento-conferencia')
                ->assertSee('O que vai ser gravado')
                ->assertSee('R$ 84.300,00')
                ->assertSee('informado pelo total');
            $this->semScrollHorizontal($browser);

            $this->assertGreaterThanOrEqual(44.0, $this->menorAlvoDeToque($browser)[0]);

            $browser->press('Fechar o mês')
                ->waitForRoute('drm.mes', [$empresa, Competencia::padrao(DataExibicao::agora())->texto()])
                // STORY-049 CA-12: o fechamento termina na Demonstração do Mês, lida do snapshot gravado.
                ->waitFor('@drm-demonstracao')
                ->assertSee('QUANTO VENDEU')
                ->assertSee('R$ 84.300,00')
                ->assertSee('SOBROU DEPOIS DA SUA RETIRADA')
                ->assertSee('R$ 3.240,00')
                ->assertSeeIn('@drm-rodape', 'motor v1.0.0');
            $this->semScrollHorizontal($browser);
        });

        $l = DrmLancamento::query()->firstOrFail();
        $this->assertSame('84300.00', $l->lancamento_payload['D01']);
        $this->assertSame('5058.00', $l->lancamento_payload['D02']);
        $this->assertSame('41200.00', $l->lancamento_payload['D03']);
        $this->assertSame('20702.00', $l->lancamento_payload['D15']);
        $this->assertNull($l->lancamento_payload['D05']);
        $this->assertSame('agregado', $l->lancamento_payload['modos']['custo']);
        $this->assertSame('comercio', $l->setor);
    }

    public function test_mobile_bloco_detalhado_de_21_itens_cabe_em_360px_com_alvos_de_toque(): void
    {
        $usuario = Usuario::factory()->create();
        $empresa = $this->empresa($usuario);

        $this->browse(function (Browser $browser) use ($usuario, $empresa) {
            $browser->resize(360, 800)
                ->loginAs($usuario)
                ->visit(route('drm.lancar', $empresa))
                ->waitFor('@drm-lancamento-bloco-1')
                ->type('@drm-lancamento-campo-D01', '1000000')
                ->type('@drm-lancamento-campo-D02', '100000')
                ->press('Próximo')->waitFor('@drm-lancamento-bloco-2')
                ->press('Próximo')->waitFor('@drm-lancamento-bloco-3')
                ->press('Próximo')->waitFor('@drm-lancamento-bloco-4')
                ->assertSee('21 campos')
                ->assertPresent('@drm-lancamento-campo-D16')
                ->assertPresent('@drm-lancamento-campo-D36');
            $this->semScrollHorizontal($browser);
            $this->assertGreaterThanOrEqual(44.0, $this->menorAlvoDeToque($browser)[0]);

            // Foco no cabeçalho do bloco ao trocar de bloco (spec §6).
            $focado = $browser->script('return document.activeElement.id')[0];
            $this->assertSame('drm-bloco-titulo', $focado);
        });
    }

    public function test_segundo_mes_em_dois_toques_vai_a_conferencia_e_nao_fecha_sozinho(): void
    {
        $usuario = Usuario::factory()->create();
        $empresa = $this->empresa($usuario);
        $anterior = Competencia::padrao(DataExibicao::agora()->subMonthNoOverflow());
        DrmLancamento::factory()->create([
            'empresa_analisada_id' => $empresa->id, 'competencia' => $anterior->dia01(),
        ]);
        // A preferência acompanha o último fechamento (o factory grava os quatro grupos agregados).
        DrmPreferencia::gravarModos($empresa, array_fill_keys(
            array_map(fn (Grupo $g) => $g->value, Grupo::cases()),
            Modo::Agregado,
        ));

        $this->browse(function (Browser $browser) use ($usuario, $empresa) {
            $browser->resize(360, 800)
                ->loginAs($usuario)
                ->visit(route('drm.lancar', $empresa))
                ->waitFor('@drm-lancamento-conferir-tudo')
                ->assertInputValue('@drm-lancamento-campo-D01', '84.300,00')
                ->assertSee('veio de ')
                ->press('Conferir tudo e ir ao fechamento')
                ->waitFor('@drm-lancamento-conferencia');

            // Conferir tudo nunca é o último ato: ainda estamos no formulário, com o mês por fechar.
            $this->assertSame(1, DrmLancamento::query()->count());
            $browser->assertSee('Conferir e fechar')
                ->press('Fechar o mês')
                ->waitForRoute('drm.mes', [$empresa, Competencia::padrao(DataExibicao::agora())->texto()]);
        });

        $this->assertSame(2, DrmLancamento::query()->count());
        $this->assertSame(
            DrmLancamento::query()->oldest('competencia')->first()->payload_hash,
            DrmLancamento::query()->latest('competencia')->first()->payload_hash,
            'mês repetido sem alteração tem o mesmo conteúdo, logo o mesmo hash',
        );
    }

    public function test_troca_destrutiva_de_modo_confirma_inline_e_o_radio_nao_antecipa(): void
    {
        $usuario = Usuario::factory()->create();
        $empresa = $this->empresa($usuario);

        $this->browse(function (Browser $browser) use ($usuario, $empresa) {
            $browser->resize(360, 800)
                ->loginAs($usuario)
                ->visit(route('drm.lancar', $empresa))
                ->waitFor('@drm-lancamento-bloco-1')
                ->type('@drm-lancamento-campo-D01', '1000000')
                ->type('@drm-lancamento-campo-D02', '100000')
                ->press('Próximo')->waitFor('@drm-lancamento-bloco-2')
                ->press('Próximo')->waitFor('@drm-lancamento-bloco-3');

            $this->clicarModo($browser, 'vendas', 'agregado');
            $browser->type('@drm-lancamento-campo-D08', '90000');
            $this->clicarModo($browser, 'vendas', 'detalhado');

            $browser->waitFor('@drm-lancamento-modo-vendas-confirmar')
                ->assertSee('Detalhar os itens deste grupo?')
                ->assertSee('será descartado');
            $agregadoMarcado = $browser->script("return document.querySelector('[dusk=\"drm-lancamento-modo-vendas-agregado\"]').checked")[0];
            $this->assertTrue($agregadoMarcado, 'o rádio permanece na opção anterior enquanto a confirmação está aberta');

            // Cancelar devolve tudo intacto.
            $browser->press('Cancelar')->pause(400)
                ->assertMissing('@drm-lancamento-modo-vendas-confirmar')
                ->assertInputValue('@drm-lancamento-campo-D08', '900,00');

            $this->clicarModo($browser, 'vendas', 'detalhado');
            $browser->waitFor('@drm-lancamento-modo-vendas-confirmar')
                ->press('Detalhar por item')->waitFor('@drm-lancamento-campo-D09')
                ->assertMissing('@drm-lancamento-campo-D08');
        });
    }

    public function test_rascunho_retoma_em_outra_sessao_no_bloco_em_que_parou(): void
    {
        $usuario = Usuario::factory()->create();
        $empresa = $this->empresa($usuario);

        $this->browse(function (Browser $browser) use ($usuario, $empresa) {
            $browser->resize(360, 800)
                ->loginAs($usuario)
                ->visit(route('drm.lancar', $empresa))
                ->waitFor('@drm-lancamento-bloco-1')
                ->type('@drm-lancamento-campo-D01', '1000000')
                ->type('@drm-lancamento-campo-D02', '100000')
                ->press('Próximo')->waitFor('@drm-lancamento-bloco-2')
                ->press('Próximo')->waitFor('@drm-lancamento-bloco-3');
        });

        $this->assertSame(1, DrmRascunho::query()->count());

        $this->browse(function (Browser $browser) use ($empresa) {
            $browser->visit(route('drm.lancar', $empresa))
                ->waitFor('@drm-lancamento-retomando')
                ->assertSee('Retomando seu rascunho — você parou em Despesas com Vendas.')
                ->assertPresent('@drm-lancamento-bloco-3');
        });
    }
}

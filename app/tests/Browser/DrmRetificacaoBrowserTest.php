<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Domain\Drm\Competencia;
use App\Models\DrmLancamento;
use App\Models\DrmRascunho;
use App\Models\EmpresaAnalisada;
use App\Models\Usuario;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * STORY-050 CA-9 — lançar → retificar → conferir a sinalização, num browser real em 360×800.
 *
 * Prova o que o Pest não alcança: a máscara BRL preenchida a partir da versão vigente (o valor
 * persistido é o que a tela mostra), os botões "Corrigir" realmente ligados (clique, não só href),
 * o selo e o rodapé de correção na tela de verdade, e o no-op sem criar versão.
 */
final class DrmRetificacaoBrowserTest extends DuskTestCase
{
    use DatabaseTruncation;

    /** @var list<string> */
    protected array $tablesToTruncate = [
        'drm_lancamentos', 'drm_rascunhos', 'drm_preferencias', 'empresas_analisadas', 'usuarios',
        'audit_logs', 'business_metrics', 'term_acceptances', 'sessions', 'evento_produto', 'cache',
    ];

    private function empresa(Usuario $usuario): EmpresaAnalisada
    {
        return EmpresaAnalisada::factory()->create(['usuario_id' => $usuario->id, 'cnae' => '4711301', 'nome_fantasia' => 'Padaria do Bairro']);
    }

    private function semScrollHorizontal(Browser $browser): void
    {
        $larguras = $browser->script('return [document.documentElement.scrollWidth, window.innerWidth]')[0];
        $this->assertLessThanOrEqual($larguras[1], $larguras[0], 'scroll horizontal em 360px');
    }

    private function clicarModo(Browser $browser, string $grupo): void
    {
        $browser->script("document.querySelector('[dusk=\"drm-lancamento-modo-{$grupo}-agregado\"]').closest('label').click()");
        $browser->pause(400);
    }

    /** Lança o mês anterior pela tela, tudo agregado (o mesmo caminho do E2E da STORY-047). */
    private function lancar(Browser $browser, EmpresaAnalisada $empresa): void
    {
        $browser->visit(route('drm.lancar', $empresa))->waitFor('@drm-lancamento-bloco-1')
            ->type('@drm-lancamento-campo-D01', '8430000')
            ->type('@drm-lancamento-campo-D02', '505800')
            ->press('Próximo')->waitFor('@drm-lancamento-bloco-2');
        $this->clicarModo($browser, 'custo');
        $browser->waitFor('@drm-lancamento-campo-D03')->type('@drm-lancamento-campo-D03', '4120000')
            ->press('Próximo')->waitFor('@drm-lancamento-bloco-3');
        $this->clicarModo($browser, 'vendas');
        $browser->type('@drm-lancamento-campo-D08', '900000')
            ->press('Próximo')->waitFor('@drm-lancamento-bloco-4');
        $this->clicarModo($browser, 'administrativas');
        $browser->type('@drm-lancamento-campo-D15', '2070200')
            ->press('Próximo')->waitFor('@drm-lancamento-bloco-5');
        $this->clicarModo($browser, 'financeiras');
        $browser->type('@drm-lancamento-campo-D40', '400000')
            ->type('@drm-lancamento-campo-D37', '110000')
            ->press('Próximo')->waitFor('@drm-lancamento-conferencia')
            ->press('Fechar o mês')->waitFor('@drm-demonstracao');
    }

    private function ateOBloco6(Browser $browser): void
    {
        for ($bloco = 1; $bloco < 6; $bloco++) {
            $browser->waitFor("@drm-lancamento-bloco-{$bloco}")->press('Próximo');
        }
        $browser->waitFor('@drm-lancamento-conferencia');
    }

    public function test_mobile_lanca_retifica_e_confere_a_sinalizacao(): void
    {
        $usuario = Usuario::factory()->create(['nome' => 'Joana Souza']);
        $empresa = $this->empresa($usuario);
        $mes = Competencia::padrao(CarbonImmutable::now())->texto();

        $this->browse(function (Browser $browser) use ($usuario, $empresa, $mes) {
            $browser->resize(360, 800)->loginAs($usuario);
            $this->lancar($browser, $empresa);

            // Versão 1: sem selo, sem rodapé de correção, e o botão de corrigir está ligado.
            $browser->assertMissing('@drm-demonstracao-retificado')
                ->assertDontSeeIn('@drm-rodape', 'corrigido')
                ->assertSee('R$ 3.240,00');
            $this->assertNull($browser->attribute('@drm-corrigir', 'disabled'));

            // Corrigir este mês → mesma tela, com os valores atuais (máscara fiel), aviso e título próprios.
            $browser->click('@drm-corrigir')->waitFor('@drm-lancamento-bloco-1')
                ->assertRouteIs('drm.corrigir', [$empresa, $mes])
                ->assertSee('Você está corrigindo um mês já fechado. A versão anterior fica guardada.')
                ->assertPresent('@drm-lancamento-retificacao-aviso')
                ->assertMissing('@drm-lancamento-primeira-vez')
                ->assertInputValue('@drm-lancamento-campo-D01', '84.300,00')
                ->assertInputValue('@drm-lancamento-campo-D02', '5.058,00');
            $this->semScrollHorizontal($browser);

            // Sem mudar nada: no-op, informativo, sem versão nova.
            $this->ateOBloco6($browser);
            $browser->press('Fechar o mês')->waitFor('@drm-lancamento-retificacao-noop')
                ->assertSee('Nenhum valor mudou, então não há o que corrigir.');
            $this->assertSame(1, DrmLancamento::query()->count());

            // Agora corrige de verdade: impostos 5.058,00 → 6.000,00.
            $browser->script("document.querySelector('[dusk=\"drm-lancamento-editar-bloco-1\"]').scrollIntoView({block: 'center'})");
            $browser->click('@drm-lancamento-editar-bloco-1')->waitFor('@drm-lancamento-bloco-1')
                ->type('@drm-lancamento-campo-D02', '600000')
                ->assertInputValue('@drm-lancamento-campo-D02', '6.000,00');
            $this->ateOBloco6($browser);
            $browser->assertSee('R$ 6.000,00')
                ->press('Fechar o mês')
                ->waitForRoute('drm.mes', [$empresa, $mes])->waitFor('@drm-demonstracao');

            // A sinalização, na tela vigente.
            $browser->assertPresent('@drm-demonstracao-retificado')
                ->assertSeeIn('@drm-demonstracao-retificado', 'Retificado')
                ->assertSeeIn('@drm-rodape', 'corrigido 1× — última correção em '.now()->format('d/m/Y'))
                ->assertSee('R$ 2.298,00');   // lucro depois da retirada: 3.240,00 − 942,00 de impostos a mais
            $this->semScrollHorizontal($browser);

            // Corrigir de novo: o contador sobe para 2.
            $browser->click('@drm-corrigir')->waitFor('@drm-lancamento-bloco-1')
                ->assertInputValue('@drm-lancamento-campo-D02', '6.000,00')
                ->type('@drm-lancamento-campo-D02', '610000');
            $this->ateOBloco6($browser);
            $browser->press('Fechar o mês')->waitFor('@drm-demonstracao')
                ->assertSeeIn('@drm-rodape', 'corrigido 2×');
        });

        $versoes = DrmLancamento::query()->orderBy('versao')->get();
        $this->assertSame([1, 2, 3], $versoes->pluck('versao')->all());
        $this->assertSame('5058.00', $versoes[0]->lancamento_payload['D02']);
        $this->assertSame('6000.00', $versoes[1]->lancamento_payload['D02']);
        $this->assertSame('6100.00', $versoes[2]->lancamento_payload['D02']);
        $this->assertSame([$versoes[1]->id, $versoes[2]->id, null], $versoes->pluck('substituido_por_id')->all());
        $this->assertSame(0, DrmRascunho::query()->count());
    }

    public function test_b1_oferece_corrigir_de_verdade_e_leva_a_retificacao(): void
    {
        $usuario = Usuario::factory()->create();
        $empresa = $this->empresa($usuario);
        $mes = Competencia::padrao(CarbonImmutable::now())->texto();
        DrmLancamento::factory()->create([
            'empresa_analisada_id' => $empresa->id, 'competencia' => $mes.'-01', 'versao' => 1,
        ]);

        $this->browse(function (Browser $browser) use ($usuario, $empresa, $mes) {
            $browser->resize(360, 800)->loginAs($usuario)
                ->visit(route('drm.lancar', $empresa))
                ->waitFor('@drm-lancamento-bloqueio-B1')
                ->assertPresent('@drm-lancamento-b1-corrigir');
            $this->assertNull($browser->attribute('@drm-lancamento-b1-corrigir', 'disabled'));
            $this->semScrollHorizontal($browser);

            $browser->click('@drm-lancamento-b1-corrigir')
                ->waitFor('@drm-lancamento-retificacao-aviso')
                ->assertRouteIs('drm.corrigir', [$empresa, $mes])
                ->assertMissing('@drm-lancamento-bloqueio-B1');
        });
    }

    public function test_outra_conta_recebe_404_silente_na_retificacao(): void
    {
        $dono = Usuario::factory()->create();
        $empresa = $this->empresa($dono);
        DrmLancamento::factory()->create(['empresa_analisada_id' => $empresa->id, 'competencia' => '2026-09-01']);
        $intruso = Usuario::factory()->create();

        $this->browse(function (Browser $browser) use ($intruso, $empresa) {
            $browser->loginAs($intruso)->visit(route('drm.corrigir', [$empresa, '2026-09']))
                ->assertDontSee('Corrigir setembro/2026')
                ->assertDontSee('Padaria do Bairro');
        });
    }
}

<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Domain\Drm\Competencia;
use App\Domain\Drm\Grupo;
use App\Domain\Drm\Lancamento;
use App\Domain\Drm\Modo;
use App\Domain\Drm\Setor;
use App\Models\DrmLancamento;
use App\Models\DrmRascunho;
use App\Models\EmpresaAnalisada;
use App\Models\Usuario;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * STORY-051 — a Série histórica num browser real: dois lançamentos consecutivos e o comparativo, a
 * retificação ajustando o comparativo do mês seguinte, e o layout (360×800 sem scroll horizontal;
 * tabela no desktop) com lacuna, rascunho, retificado e mês atípico.
 */
final class DrmSerieBrowserTest extends DuskTestCase
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
        $this->assertLessThanOrEqual($larguras[1], $larguras[0], 'scroll horizontal em '.$larguras[1].'px');
    }

    private function clicarModo(Browser $browser, string $grupo): void
    {
        $browser->script("document.querySelector('[dusk=\"drm-lancamento-modo-{$grupo}-agregado\"]').closest('label').click()");
        $browser->pause(400);
    }

    /** Lança o mês pela tela, tudo agregado (o caminho do E2E da STORY-047). Lucro: 3.240,00. */
    private function lancar(Browser $browser, EmpresaAnalisada $empresa, string $competencia): void
    {
        $browser->visit(route('drm.lancar', [$empresa, 'competencia' => $competencia]))->waitFor('@drm-lancamento-bloco-1')
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

    /**
     * Um mês gravado direto, tudo agregado (receita 10.000,00; lucro = 4.900,00 − custo; D21 só com administrativa detalhada).
     */
    private function mes(EmpresaAnalisada $empresa, string $competencia, string $custo = '3.900,00', ?string $d21 = null): DrmLancamento
    {
        $modos = array_fill_keys(array_map(fn (Grupo $g) => $g->value, Grupo::cases()), Modo::Agregado);
        $texto = ['D01' => '10.000,00', 'D02' => '1.000,00', 'D03' => $custo, 'D08' => '1.000,00', 'D15' => '2.000,00', 'D37' => '100,00', 'D40' => '1.000,00'];
        if ($d21 !== null) {
            $modos['administrativas'] = Modo::Detalhado;
            unset($texto['D15']);
            $texto['D21'] = $d21;
        }

        return DrmLancamento::factory()->create([
            'empresa_analisada_id' => $empresa->id,
            'competencia' => $competencia.'-01',
            'lancamento_payload' => Lancamento::deTexto(Setor::Comercio, $modos, $texto)->payload(),
        ]);
    }

    public function test_dois_lancamentos_consecutivos_e_o_comparativo_na_serie(): void
    {
        $usuario = Usuario::factory()->create(['nome' => 'Joana Souza']);
        $empresa = $this->empresa($usuario);
        $m1 = Competencia::padrao(CarbonImmutable::now());
        $m2 = Competencia::deData($m1->data->subMonthNoOverflow());

        $this->browse(function (Browser $browser) use ($usuario, $empresa, $m1, $m2) {
            $browser->resize(360, 800)->loginAs($usuario);

            // Primeiro mês: lucro de 3.240,00.
            $this->lancar($browser, $empresa, $m2->texto());
            $browser->assertSee('R$ 3.240,00');

            // Segundo mês: tudo vem do primeiro; só a receita muda (84.300,00 → 90.000,00): lucro 8.940,00.
            $browser->visit(route('drm.lancar', [$empresa, 'competencia' => $m1->texto()]))
                ->waitFor('@drm-lancamento-conferir-tudo')
                ->type('@drm-lancamento-campo-D01', '9000000')
                ->press('Conferir tudo e ir ao fechamento')
                ->waitFor('@drm-lancamento-conferencia')
                ->press('Fechar o mês')
                ->waitForRoute('drm.mes', [$empresa, $m1->texto()])->waitFor('@drm-demonstracao')
                ->assertSee('R$ 8.940,00');

            // A Demonstração do Mês tem os comparativos por linha, e o caminho para a série.
            $browser->assertPresent('@drm-comparativos')
                ->click('@drm-demonstracao-ver-serie')
                ->waitFor('@drm-serie')
                ->assertRouteIs('drm.serie', [$empresa]);

            // (8.940 − 3.240) / 3.240 = +175,9%.
            $browser->assertSeeIn('@drm-serie-vs-anterior-'.$m1->texto(), '+175,9% em relação a '.$m2->mesPorExtenso())
                ->assertSeeIn('@drm-serie-vs-media-'.$m1->texto(), 'média disponível a partir do 4º mês lançado')
                ->assertSeeIn('@drm-serie-vs-anterior-'.$m2->texto(), 'primeiro mês lançado')
                ->assertSeeIn('@drm-serie-estado-'.$m1->texto(), 'lançado');
            $this->semScrollHorizontal($browser);
        });
    }

    public function test_retificar_o_primeiro_mes_ajusta_o_comparativo_do_segundo(): void
    {
        $usuario = Usuario::factory()->create();
        $empresa = $this->empresa($usuario);
        $m1 = Competencia::padrao(CarbonImmutable::now());
        $m2 = Competencia::deData($m1->data->subMonthNoOverflow());
        $this->mes($empresa, $m2->texto(), '3.900,00');   // lucro 1.000,00
        $this->mes($empresa, $m1->texto(), '3.400,00');   // lucro 1.500,00  → +50,0%

        $this->browse(function (Browser $browser) use ($usuario, $empresa, $m1, $m2) {
            $browser->resize(360, 800)->loginAs($usuario)
                ->visit(route('drm.serie', $empresa))->waitFor('@drm-serie')
                ->assertSeeIn('@drm-serie-vs-anterior-'.$m1->texto(), '+50,0% em relação a '.$m2->mesPorExtenso())
                ->assertMissing('@drm-serie-retificado-'.$m2->texto());

            // Corrigir o primeiro mês a partir da série: o custo sobe 500,00 (lucro 500,00).
            $browser->click('@drm-serie-acao-'.$m2->texto())->waitFor('@drm-lancamento-bloco-1')
                ->assertRouteIs('drm.corrigir', [$empresa, $m2->texto()]);
            $browser->press('Próximo')->waitFor('@drm-lancamento-bloco-2')
                ->type('@drm-lancamento-campo-D03', '440000');
            for ($bloco = 2; $bloco < 6; $bloco++) {
                $browser->waitFor("@drm-lancamento-bloco-{$bloco}")->press('Próximo');
            }
            $browser->waitFor('@drm-lancamento-conferencia')->press('Fechar o mês')->waitFor('@drm-demonstracao')
                ->assertPresent('@drm-demonstracao-retificado')
                ->click('@drm-demonstracao-ver-serie')->waitFor('@drm-serie');

            // O segundo mês, que ninguém tocou, agora compara com o primeiro retificado: (1.500 − 500) / 500 = +200,0%.
            $browser->assertSeeIn('@drm-serie-vs-anterior-'.$m1->texto(), '+200,0% em relação a '.$m2->mesPorExtenso())
                ->assertPresent('@drm-serie-retificado-'.$m2->texto())
                ->assertSeeIn('@drm-serie-valor-'.$m2->texto(), 'R$ 500,00')
                ->assertMissing('@drm-serie-retificado-'.$m1->texto());
            $this->semScrollHorizontal($browser);
        });

        $this->assertSame([1, 2], DrmLancamento::query()->where('competencia', Competencia::deTexto($m2->texto())->dia01())->orderBy('versao')->pluck('versao')->all());
    }

    public function test_mobile_lista_estados_distintos_sem_scroll_horizontal(): void
    {
        $usuario = Usuario::factory()->create();
        $empresa = $this->empresa($usuario);
        $hoje = CarbonImmutable::now();
        $c = fn (int $atras): string => Competencia::deData($hoje->startOfMonth()->subMonthsNoOverflow($atras))->texto();

        // Do mais antigo ao mais recente: 5 meses de 13º zerado, depois o quarto com 13º (atípico), uma lacuna e um rascunho.
        foreach ([7, 6, 5] as $atras) {
            $this->mes($empresa, $c($atras), '3.900,00', '0,00');
        }
        $this->mes($empresa, $c(4), '3.900,00', '3.000,00');      // atípico
        // $c(3) não lançado (lacuna).
        $this->mes($empresa, $c(2), '3.900,00', '0,00');          // lançado; será retificado
        DrmRascunho::query()->create([
            'usuario_id' => $usuario->id, 'empresa_analisada_id' => $empresa->id, 'competencia' => $c(1).'-01',
            'origem' => 'inicial', 'rascunho_payload' => ['bloco' => 2], 'expires_at' => now()->addDays(30),
        ]);
        DrmLancamento::query()->where('competencia', $c(2).'-01')->update(['versao' => 2]);   // retificado = versão > 1

        $this->browse(function (Browser $browser) use ($usuario, $empresa, $c) {
            $browser->resize(360, 800)->loginAs($usuario)->visit(route('drm.serie', $empresa))->waitFor('@drm-serie-lista');
            $this->semScrollHorizontal($browser);

            // Quatro estados, quatro textos — e a borda tracejada é só reforço.
            $browser->assertSeeIn('@drm-serie-estado-'.$c(3), 'não lançado')
                ->assertSeeIn('@drm-serie-ausencia-'.$c(3), 'Sem lançamento para este mês.')
                ->assertMissing('@drm-serie-valor-'.$c(3))
                ->assertSeeIn('@drm-serie-estado-'.$c(1), 'rascunho')
                ->assertSeeIn('@drm-serie-ausencia-'.$c(1), 'Você parou em')
                ->assertPresent('@drm-serie-rascunho-'.$c(1))
                ->assertPresent('@drm-serie-retificado-'.$c(2))
                ->assertSeeIn('@drm-serie-atipico-explicacao-'.$c(4), 'teve décimo-terceiro salário — por isso o resultado do mês caiu.')
                ->assertPresent('@drm-serie-atipico-'.$c(4));

            // O mês depois da lacuna não compara com um mês zerado.
            $browser->assertSeeIn('@drm-serie-vs-anterior-'.$c(2), 'não foi lançado, então não dá para comparar');

            // Alvos de toque ≥ 44px nas ações e no link do mês.
            $alturas = $browser->script("return ['drm-serie-acao-{$c(3)}', 'drm-serie-acao-{$c(2)}'].map(d => document.querySelector('[dusk=\"'+d+'\"]').getBoundingClientRect().height)")[0];
            foreach ($alturas as $altura) {
                $this->assertGreaterThanOrEqual(44, $altura);
            }

            // A ação de lançar o mês em falta leva ao formulário daquele mês.
            $browser->click('@drm-serie-acao-'.$c(3))->waitFor('@drm-lancamento-bloco-1')
                ->assertRouteIs('drm.lancar', [$empresa]);
            $this->assertStringContainsString('competencia='.$c(3), $browser->driver->getCurrentURL());
        });
    }

    public function test_desktop_mostra_tabela_com_colunas_de_comparativo_e_a_ausencia_atravessa_as_colunas(): void
    {
        $usuario = Usuario::factory()->create();
        $empresa = $this->empresa($usuario);
        $m1 = Competencia::padrao(CarbonImmutable::now());
        $m2 = Competencia::deData($m1->data->subMonthNoOverflow());
        $m3 = Competencia::deData($m2->data->subMonthNoOverflow());
        $this->mes($empresa, $m3->texto());
        $this->mes($empresa, $m1->texto(), '3.400,00');   // m2 ausente

        $this->browse(function (Browser $browser) use ($usuario, $empresa, $m1, $m2) {
            $browser->resize(1280, 900)->loginAs($usuario)->visit(route('drm.serie', $empresa))->waitFor('@drm-serie-tabela');

            $browser->assertVisible('@drm-serie-tabela')
                ->assertMissing('@drm-serie-lista')   // mobile fica escondido no desktop: `assertMissing` aceita invisível
                ->assertSeeIn('@drm-serie-tabela-ausencia-'.$m2->texto(), 'Sem lançamento para este mês.')
                ->assertSeeIn('@drm-serie-tabela-vs-anterior-'.$m1->texto(), 'não foi lançado, então não dá para comparar');

            $this->assertSame('4', $browser->attribute('@drm-serie-tabela-ausencia-'.$m2->texto(), 'colspan'));
            $this->semScrollHorizontal($browser);
        });
    }

    public function test_serie_vazia_leva_ao_primeiro_lancamento(): void
    {
        $usuario = Usuario::factory()->create();
        $empresa = $this->empresa($usuario);

        $this->browse(function (Browser $browser) use ($usuario, $empresa) {
            $browser->resize(360, 800)->loginAs($usuario)
                ->visit(route('empresas.show', $empresa))
                ->click('@empresa-show-ver-meses')->waitFor('@drm-serie-vazio')
                ->assertSee('Você ainda não lançou nenhum mês para esta empresa.');
            $this->semScrollHorizontal($browser);

            $browser->click('@drm-serie-lancar')->waitFor('@drm-lancamento-bloco-1')->assertRouteIs('drm.lancar', [$empresa]);
        });
    }

    public function test_outra_conta_recebe_404_silente_na_serie(): void
    {
        $dono = Usuario::factory()->create();
        $empresa = $this->empresa($dono);
        $this->mes($empresa, '2026-09');
        $intruso = Usuario::factory()->create();

        $this->browse(function (Browser $browser) use ($intruso, $empresa) {
            $browser->loginAs($intruso)->visit(route('drm.serie', $empresa))
                ->assertDontSee('Padaria do Bairro')
                ->assertDontSee('Todos os meses');
        });
    }
}

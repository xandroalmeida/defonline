<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Actions\Drm\FecharLancamento;
use App\Domain\Drm\Competencia;
use App\Domain\Drm\Dinheiro;
use App\Domain\Drm\Grupo;
use App\Domain\Drm\Lancamento as LancamentoDrm;
use App\Domain\Drm\Modo;
use App\Domain\Drm\Setor;
use App\Models\DrmRascunho;
use App\Models\EmpresaAnalisada;
use App\Models\Usuario;
use Facebook\WebDriver\Chrome\ChromeDevToolsDriver;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * STORY-095 CA-1/CA-2 (D-1 da validação do EPIC-010) — digitação tecla a tecla com latência.
 *
 * O Dusk `type()` envia o valor de uma vez e nunca pegou o defeito: em campo ainda não
 * preenchido, o ritmo de um celular numa rede lenta perdia dígitos e o valor errado ia
 * para o snapshot (`123456` virava `134,56`). Aqui cada tecla é enviada isolada, com pausa e
 * latência emulada (CDP). O que vale é o valor PERSISTIDO (rascunho no banco), não só o que a
 * tela mostra. Causa: chave ausente em `valores` vira atualização do array inteiro, e a
 * resposta reescreve o <input> com o valor que a requisição levou (ver `Lancamento::dehydrate`).
 */
final class DrmDigitacaoTeclaATeclaBrowserTest extends DuskTestCase
{
    use DatabaseTruncation;

    /** @var list<string> */
    protected array $tablesToTruncate = [
        'drm_lancamentos', 'drm_rascunhos', 'drm_preferencias', 'empresas_analisadas', 'usuarios',
        'audit_logs', 'business_metrics', 'term_acceptances', 'sessions', 'evento_produto', 'cache',
    ];

    private function latencia(ChromeDevToolsDriver $dev, int $ms): void
    {
        $dev->execute('Network.emulateNetworkConditions', [
            'offline' => false, 'latency' => $ms, 'downloadThroughput' => -1, 'uploadThroughput' => -1,
        ]);
    }

    private function digitar(Browser $browser, string $dusk, string $digitos, int $intervaloMs): void
    {
        $browser->click($dusk);
        foreach (str_split($digitos) as $tecla) {
            $browser->driver->getKeyboard()->sendKeys($tecla);
            usleep($intervaloMs * 1000);
        }
    }

    /** Conta as requisições do Livewire em voo; precisa ser chamado depois de cada `visit`. */
    private function monitorar(Browser $browser): void
    {
        $browser->script(<<<'JS'
            window.__pendentes = 0;
            Livewire.interceptRequest(({ onSend, onFinish }) => {
                onSend(() => { window.__pendentes++; });
                onFinish(() => { window.__pendentes--; });
            });
        JS);
    }

    /** Espera o debounce de 300 ms e as requisições em voo terminarem. */
    private function aguardarAssentar(Browser $browser, int $latenciaMs): void
    {
        $browser->pause(400 + 3 * $latenciaMs);
        $browser->waitUntil('window.__pendentes === 0', 20);
        $browser->pause(200);
    }

    private function limparCampo(Browser $browser, string $campo): void
    {
        $browser->script("const e=document.getElementById('campo-{$campo}');e.value='';e.dispatchEvent(new Event('input'))");
        $browser->pause(1000);
    }

    /** @return array<string, mixed> */
    private function rascunho(EmpresaAnalisada $empresa): array
    {
        return (array) (DrmRascunho::maisRecente($empresa)?->rascunho_payload['valores'] ?? []);
    }

    /** @return array{0: Usuario, 1: EmpresaAnalisada} */
    private function usuarioEEmpresa(): array
    {
        $usuario = Usuario::factory()->create();
        $empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $usuario->id, 'cnae' => '4711301']);

        return [$usuario, $empresa];
    }

    /** Vai ao bloco $alvo com o que o bloco 1 exige (sem latência no trajeto). */
    private function irAoBloco(Browser $browser, int $alvo): void
    {
        $browser->waitFor('@drm-lancamento-bloco-1')
            ->type('@drm-lancamento-campo-D01', '100000')->type('@drm-lancamento-campo-D02', '1000')->pause(600);
        for ($b = 1; $b < $alvo; $b++) {
            $browser->press('Próximo')->waitFor('@drm-lancamento-bloco-'.($b + 1));
        }
    }

    public function test_campo_novo_no_lancamento_grava_o_digitado_em_todas_as_combinacoes_de_ritmo_e_latencia(): void
    {
        [$usuario, $empresa] = $this->usuarioEEmpresa();

        $this->browse(function (Browser $browser) use ($usuario, $empresa) {
            $dev = new ChromeDevToolsDriver($browser->driver);
            $dev->execute('Network.enable');
            $browser->resize(360, 800)->loginAs($usuario);

            // (latência, intervalo entre teclas) da matriz do relatório da STORY-057.
            foreach ([[0, 350], [100, 350], [300, 350], [300, 500], [300, 200], [100, 800]] as $i => [$lat, $iv]) {
                DrmRascunho::query()->delete();
                $this->latencia($dev, 0);
                $browser->visit(route('drm.lancar', $empresa).'?v='.$i)->waitFor('@drm-lancamento-bloco-1');
                $this->monitorar($browser);
                $this->latencia($dev, $lat);

                $this->digitar($browser, '@drm-lancamento-campo-D01', '123456', $iv);
                $this->aguardarAssentar($browser, $lat);

                $this->assertSame('1.234,56', $browser->value('@drm-lancamento-campo-D01'), "tela lat={$lat} iv={$iv}");
                $this->latencia($dev, 0);
                $browser->type('@drm-lancamento-campo-D02', '100')->pause(600)
                    ->press('Próximo')->waitFor('@drm-lancamento-bloco-2');
                $this->assertSame('1.234,56', $this->rascunho($empresa)['D01'] ?? null, "gravado lat={$lat} iv={$iv}");
            }
            $this->latencia($dev, 0);
        });
    }

    public function test_bloco_de_21_itens_grava_o_digitado_trocando_de_campo_com_ritmo_e_latencia(): void
    {
        [$usuario, $empresa] = $this->usuarioEEmpresa();
        $campos = Grupo::Administrativas->itens(Setor::Comercio);
        $this->assertCount(21, $campos);

        $this->browse(function (Browser $browser) use ($usuario, $empresa, $campos) {
            $dev = new ChromeDevToolsDriver($browser->driver);
            $dev->execute('Network.enable');
            $browser->resize(360, 800)->loginAs($usuario);

            // (tecla, pausa entre campos, latência) das linhas do relatório que mais falhavam.
            foreach ([[120, 150, 0], [120, 150, 200], [200, 400, 600], [300, 1000, 600]] as $i => [$tecla, $pausa, $lat]) {
                DrmRascunho::query()->delete();
                $this->latencia($dev, 0);
                $browser->visit(route('drm.lancar', $empresa).'?v='.$i);
                $this->irAoBloco($browser, 4);
                $this->monitorar($browser);
                $this->latencia($dev, $lat);

                $esperado = [];
                foreach ($campos as $n => $campo) {
                    $digitos = '117'.sprintf('%02d', $n + 1);
                    $esperado[$campo] = Dinheiro::mascara((int) $digitos);
                    $this->digitar($browser, "@drm-lancamento-campo-{$campo}", $digitos, $tecla);
                    usleep($pausa * 1000);
                }
                $this->aguardarAssentar($browser, $lat);

                foreach ($esperado as $campo => $valor) {
                    $this->assertSame($valor, $browser->value("@drm-lancamento-campo-{$campo}"), "tela {$campo} tecla={$tecla} pausa={$pausa} lat={$lat}");
                }
                $this->latencia($dev, 0);
                $browser->press('Próximo')->waitFor('@drm-lancamento-bloco-5');
                $gravado = $this->rascunho($empresa);
                foreach ($esperado as $campo => $valor) {
                    $this->assertSame($valor, $gravado[$campo] ?? null, "gravado {$campo} tecla={$tecla} pausa={$pausa} lat={$lat}");
                }
            }
            $this->latencia($dev, 0);
        });
    }

    public function test_campo_ja_preenchido_continua_gravando_o_digitado(): void
    {
        [$usuario, $empresa] = $this->usuarioEEmpresa();

        $this->browse(function (Browser $browser) use ($usuario, $empresa) {
            $dev = new ChromeDevToolsDriver($browser->driver);
            $dev->execute('Network.enable');
            $browser->resize(360, 800)->loginAs($usuario)->visit(route('drm.lancar', $empresa));
            $browser->waitFor('@drm-lancamento-bloco-1')->type('@drm-lancamento-campo-D01', '999')->type('@drm-lancamento-campo-D02', '111')->pause(800);
            $this->monitorar($browser);

            foreach ([[100, 350], [300, 350], [300, 500]] as [$lat, $iv]) {
                $this->latencia($dev, 0);
                $this->limparCampo($browser, 'D01');
                $this->latencia($dev, $lat);
                $this->digitar($browser, '@drm-lancamento-campo-D01', '123456', $iv);
                $this->aguardarAssentar($browser, $lat);
                $this->assertSame('1.234,56', $browser->value('@drm-lancamento-campo-D01'), "preenchido lat={$lat} iv={$iv}");
            }
            $this->latencia($dev, 0);
            $browser->press('Próximo')->waitFor('@drm-lancamento-bloco-2');
            $this->assertSame('1.234,56', $this->rascunho($empresa)['D01'] ?? null);
        });
    }

    public function test_retificacao_grava_o_digitado_em_campo_preenchido_e_em_campo_novo(): void
    {
        [$usuario, $empresa] = $this->usuarioEEmpresa();
        $competencia = Competencia::deTexto('2026-09');
        app(FecharLancamento::class)->execute($empresa, $competencia, LancamentoDrm::deTexto(
            Setor::Comercio,
            ['custo' => Modo::Agregado, 'vendas' => Modo::Agregado, 'administrativas' => Modo::Agregado, 'financeiras' => Modo::Agregado],
            ['D01' => '1.000,00', 'D02' => '100,00', 'D03' => '200,00', 'D08' => '50,00', 'D15' => '60,00', 'D37' => '10,00', 'D40' => '100,00'],
        ), [], null);

        $this->browse(function (Browser $browser) use ($usuario, $empresa, $competencia) {
            $dev = new ChromeDevToolsDriver($browser->driver);
            $dev->execute('Network.enable');
            $browser->resize(360, 800)->loginAs($usuario)->visit(route('drm.corrigir', [$empresa, $competencia->texto()]))
                ->waitFor('@drm-lancamento-bloco-1')->assertInputValue('@drm-lancamento-campo-D01', '1.000,00');
            $this->monitorar($browser);

            // Campo já preenchido.
            foreach ([[100, 350], [300, 350]] as [$lat, $iv]) {
                $this->latencia($dev, 0);
                $this->limparCampo($browser, 'D01');
                $this->latencia($dev, $lat);
                $this->digitar($browser, '@drm-lancamento-campo-D01', '123456', $iv);
                $this->aguardarAssentar($browser, $lat);
                $this->assertSame('1.234,56', $browser->value('@drm-lancamento-campo-D01'), "retificação preenchido lat={$lat} iv={$iv}");
            }

            // Campo sem valor na retificação: detalhar o custo descarta o total e deixa os itens vazios.
            $this->latencia($dev, 0);
            $browser->press('Próximo')->waitFor('@drm-lancamento-bloco-2');
            $this->assertSame('1.234,56', $this->rascunho($empresa)['D01'] ?? null);
            $browser->script("document.querySelector('[dusk=\"drm-lancamento-modo-custo-detalhado\"]').closest('label').click()");
            $browser->waitFor('@drm-lancamento-modo-custo-confirmar-sim')->click('@drm-lancamento-modo-custo-confirmar-sim')
                ->waitFor('@drm-lancamento-campo-D04')->pause(800);
            $this->assertSame('', $browser->value('@drm-lancamento-campo-D04'));
            $this->monitorar($browser);
            $this->latencia($dev, 300);
            $this->digitar($browser, '@drm-lancamento-campo-D04', '123456', 350);
            $this->aguardarAssentar($browser, 300);
            $this->assertSame('1.234,56', $browser->value('@drm-lancamento-campo-D04'));
            $this->latencia($dev, 0);
            $browser->press('Próximo')->waitFor('@drm-lancamento-bloco-3');
            $this->assertSame('1.234,56', $this->rascunho($empresa)['D04'] ?? null);
        });
    }

    public function test_bloqueio_depende_do_valor_atualizado_no_servidor_com_digitacao_lenta(): void
    {
        [$usuario, $empresa] = $this->usuarioEEmpresa();

        $this->browse(function (Browser $browser) use ($usuario, $empresa) {
            $dev = new ChromeDevToolsDriver($browser->driver);
            $dev->execute('Network.enable');
            $browser->resize(360, 800)->loginAs($usuario)->visit(route('drm.lancar', $empresa))->waitFor('@drm-lancamento-bloco-1');
            $this->monitorar($browser);
            $this->latencia($dev, 300);

            // Impostos (D02 = 500,00) maiores que a receita (D01 = 100,00): o B4 cita os dois valores que o servidor viu.
            $this->digitar($browser, '@drm-lancamento-campo-D01', '10000', 350);
            $this->digitar($browser, '@drm-lancamento-campo-D02', '50000', 350);
            $this->aguardarAssentar($browser, 300);
            $this->latencia($dev, 0);
            $browser->press('Próximo')->waitFor('@drm-lancamento-bloqueio-B4')
                ->assertSeeIn('@drm-lancamento-bloqueio-B4', '500,00')
                ->assertSeeIn('@drm-lancamento-bloqueio-B4', '100,00');

            $this->limparCampo($browser, 'D02');
            $this->latencia($dev, 300);
            $this->digitar($browser, '@drm-lancamento-campo-D02', '5000', 350);
            $this->aguardarAssentar($browser, 300);
            $this->latencia($dev, 0);
            $browser->press('Próximo')->waitFor('@drm-lancamento-bloco-2');
            $gravado = $this->rascunho($empresa);
            $this->assertSame('100,00', $gravado['D01'] ?? null);
            $this->assertSame('50,00', $gravado['D02'] ?? null);
        });
    }

    public function test_alerta_de_prejuizo_cita_o_valor_digitado_devagar(): void
    {
        [$usuario, $empresa] = $this->usuarioEEmpresa();

        $this->browse(function (Browser $browser) use ($usuario, $empresa) {
            $dev = new ChromeDevToolsDriver($browser->driver);
            $dev->execute('Network.enable');
            $browser->resize(360, 800)->loginAs($usuario)->visit(route('drm.lancar', $empresa))->waitFor('@drm-lancamento-bloco-1');
            $this->monitorar($browser);
            $this->latencia($dev, 200);

            $this->digitar($browser, '@drm-lancamento-campo-D01', '10000', 350);   // 100,00
            $this->digitar($browser, '@drm-lancamento-campo-D02', '0', 350);
            $this->aguardarAssentar($browser, 200);
            $this->latencia($dev, 0);
            $browser->press('Próximo')->waitFor('@drm-lancamento-bloco-2')
                ->press('Próximo')->waitFor('@drm-lancamento-bloco-3')
                ->press('Próximo')->waitFor('@drm-lancamento-bloco-4');
            $this->monitorar($browser);
            $this->latencia($dev, 200);
            $this->digitar($browser, '@drm-lancamento-campo-D16', '123456', 350);   // 1.234,56 de despesa
            $this->aguardarAssentar($browser, 200);
            $this->latencia($dev, 0);
            $browser->press('Próximo')->waitFor('@drm-lancamento-bloco-5');
            $this->monitorar($browser);
            $this->latencia($dev, 200);
            $this->digitar($browser, '@drm-lancamento-campo-D40', '100', 350);     // 1,00 de retirada
            $this->aguardarAssentar($browser, 200);
            $this->latencia($dev, 0);
            $browser->press('Próximo')->waitFor('@drm-lancamento-conferencia')
                ->waitFor('@drm-lancamento-alerta-A3')
                ->assertSeeIn('@drm-lancamento-alerta-A3', '1.135,56');   // 100,00 − 1.234,56 − 1,00
            $gravado = $this->rascunho($empresa);
            $this->assertSame('1.234,56', $gravado['D16'] ?? null);
            $this->assertSame('1,00', $gravado['D40'] ?? null);
        });
    }
}

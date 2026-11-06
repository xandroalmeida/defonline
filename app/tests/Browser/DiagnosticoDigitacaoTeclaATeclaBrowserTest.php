<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Models\EmpresaAnalisada;
use App\Models\QuizRascunho;
use App\Models\Usuario;
use Facebook\WebDriver\Chrome\ChromeDevToolsDriver;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * STORY-095 CA-3 — as máscaras compartilhadas do quiz do Diagnóstico (BRL, dias, %, CPF) sob
 * digitação tecla a tecla e latência emulada. O defeito do DRM (D-1) nasceu da chave nova num array
 * de `wire:model.live`; no quiz as propriedades são escalares, e este teste prova que o mesmo ritmo
 * não perde dígitos aqui. O que vale é o valor PERSISTIDO no rascunho.
 */
final class DiagnosticoDigitacaoTeclaATeclaBrowserTest extends DuskTestCase
{
    use DatabaseTruncation;

    /** @var list<string> */
    protected array $tablesToTruncate = [
        'quiz_rascunhos', 'diagnosticos', 'empresas_analisadas', 'usuarios',
        'audit_logs', 'business_metrics', 'term_acceptances', 'sessions', 'evento_produto', 'cache',
    ];

    private function latencia(ChromeDevToolsDriver $dev, int $ms): void
    {
        $dev->execute('Network.emulateNetworkConditions', [
            'offline' => false, 'latency' => $ms, 'downloadThroughput' => -1, 'uploadThroughput' => -1,
        ]);
    }

    private function digitar(Browser $browser, string $dusk, string $teclas, int $intervaloMs): void
    {
        $browser->click($dusk);
        foreach (str_split($teclas) as $tecla) {
            $browser->driver->getKeyboard()->sendKeys($tecla);
            usleep($intervaloMs * 1000);
        }
    }

    private function aguardarAssentar(Browser $browser, int $latenciaMs): void
    {
        $browser->pause(400 + 3 * $latenciaMs);
        $browser->waitUntil('window.__pendentes === 0', 20);
        $browser->pause(200);
    }

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

    public function test_mascaras_brl_dias_e_percentual_do_quiz_gravam_o_digitado_tecla_a_tecla(): void
    {
        $usuario = Usuario::factory()->create();
        $empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $usuario->id]);

        $this->browse(function (Browser $browser) use ($usuario, $empresa) {
            $dev = new ChromeDevToolsDriver($browser->driver);
            $dev->execute('Network.enable');
            $browser->resize(360, 800)->loginAs($usuario);

            // (latência, intervalo entre teclas, pausa entre campos): a matriz do relatório da STORY-057.
            foreach ([[100, 350, 150], [300, 350, 150], [300, 500, 1000], [200, 120, 150]] as $i => [$lat, $iv, $pausa]) {
                QuizRascunho::query()->delete();
                $this->latencia($dev, 0);
                $browser->visit(route('diagnosticos.novo', $empresa).'?v='.$i)->waitFor('@quiz-bloco-1')
                    ->click('@quiz-proximo')->waitFor('@quiz-bloco-2');
                $this->monitorar($browser);
                $this->latencia($dev, $lat);

                $campos = ['Q08' => ['123456', '1.234,56'], 'Q09' => ['654321', '6.543,21'], 'Q14' => ['111222', '1.112,22'],
                    'Q15' => ['999888', '9.998,88'], 'Q16' => ['100', '1,00'], 'Q10' => ['45', '45'], 'Q11' => ['30', '30'],
                    'Q12' => ['60', '60'], 'Q13' => ['3,46', '3,46']];
                foreach ($campos as $id => [$teclas]) {
                    $this->digitar($browser, "@quiz-{$id}", $teclas, $iv);
                    usleep($pausa * 1000);
                }
                $this->aguardarAssentar($browser, $lat);

                foreach ($campos as $id => [, $esperado]) {
                    $this->assertSame($esperado, $browser->value("@quiz-{$id}"), "tela {$id} lat={$lat} iv={$iv} pausa={$pausa}");
                }
                $this->latencia($dev, 0);
                $browser->click('@quiz-proximo')->waitFor('@quiz-bloco-3');
                $rascunho = (array) QuizRascunho::query()->firstOrFail()->quiz_payload;
                foreach ($campos as $id => [, $esperado]) {
                    $this->assertSame($esperado, $rascunho[$id] ?? null, "gravado {$id} lat={$lat} iv={$iv} pausa={$pausa}");
                }
            }
            $this->latencia($dev, 0);
        });
    }

    public function test_mascara_de_cpf_do_socio_grava_o_digitado_tecla_a_tecla(): void
    {
        $usuario = Usuario::factory()->create();
        $empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $usuario->id]);

        $this->browse(function (Browser $browser) use ($usuario, $empresa) {
            $dev = new ChromeDevToolsDriver($browser->driver);
            $dev->execute('Network.enable');
            $browser->resize(360, 800)->loginAs($usuario)->visit(route('diagnosticos.novo', $empresa))->waitFor('@quiz-bloco-1')
                ->click('@quiz-proximo')->waitFor('@quiz-bloco-2')
                ->type('@quiz-Q08', '1000')->type('@quiz-Q09', '2000')->type('@quiz-Q14', '100')->type('@quiz-Q15', '100')->type('@quiz-Q16', '100')
                ->type('@quiz-Q10', '30')->type('@quiz-Q11', '30')->type('@quiz-Q12', '30')->type('@quiz-Q13', '2')->pause(800)
                ->click('@quiz-proximo')->waitFor('@quiz-bloco-3')
                ->type('@quiz-Q02', '1000')->type('@quiz-Q03', '1000')->type('@quiz-Q04', '1000')->type('@quiz-Q05', '1000')->type('@quiz-Q06', '1000')->type('@quiz-Q07', '1000')->pause(800)
                ->click('@quiz-proximo')->waitFor('@quiz-bloco-4')
                ->click('@quiz-Q17-sim')->waitFor('@quiz-bloco-4-captacao');
            $this->monitorar($browser);
            $this->latencia($dev, 300);

            $this->digitar($browser, '@quiz-socio-0-nome', 'Ana Lima', 150);
            $this->digitar($browser, '@quiz-socio-0-cpf', '52998224725', 350);
            $this->aguardarAssentar($browser, 300);
            $this->assertSame('529.982.247-25', $browser->value('@quiz-socio-0-cpf'));
            $this->assertSame('Ana Lima', $browser->value('@quiz-socio-0-nome'));
            $this->latencia($dev, 0);
        });
    }
}

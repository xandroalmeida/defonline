<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Domain\Cobranca\Carteira;
use App\Models\CobrancaConfig;
use App\Models\Diagnostico;
use App\Models\EmpresaAnalisada;
use App\Models\TrialConfig;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * STORY-073 — o interruptor da cobrança na tela de planos e o bloqueio por falta de saldo no
 * quiz (spec STORY-070-bloqueio-sem-saldo): o bloqueio toma o lugar do quiz, o acesso aos
 * diagnósticos continua e, com saldo, o quiz abre normalmente.
 *
 * NÃO está no grupo `smoke` por design — faz writes.
 */
final class BloqueioSemSaldoBrowserTest extends DuskTestCase
{
    use DatabaseTruncation;

    /** @var list<string> */
    protected array $tablesToTruncate = [
        'consumos_analise', 'diagnosticos', 'lotes_credito', 'assinaturas', 'quiz_rascunhos',
        'empresas_analisadas', 'usuarios', 'audit_logs', 'evento_produto', 'sessions', 'cache',
    ];

    /**
     * O TRUNCATE de `usuarios` é CASCADE e leva junto as tabelas que apontam para ele, entre elas
     * a configuração do trial, que a tela de planos exige. Recria o mínimo para a tela abrir.
     */
    protected function afterTruncatingDatabase(): void
    {
        TrialConfig::query()->firstOrCreate([], ['ativo' => true, 'dias' => 7, 'analises' => 1]);
        CobrancaConfig::atual();
    }

    protected function tearDown(): void
    {
        // O interruptor é configuração do ambiente, não é truncado: devolve desligado.
        CobrancaConfig::atual()->update(['ativa' => false]);

        parent::tearDown();
    }

    public function test_sem_saldo_o_quiz_e_substituido_pelo_bloqueio_e_com_saldo_abre(): void
    {
        $usuario = Usuario::factory()->create();
        $empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $usuario->id]);
        Diagnostico::factory()->create(['usuario_id' => $usuario->id, 'empresa_analisada_id' => $empresa->id]);
        CobrancaConfig::atual()->update(['ativa' => true]);

        $this->browse(function (Browser $browser) use ($usuario, $empresa) {
            $browser->loginAs($usuario)
                ->visit('/empresas/'.$empresa->id.'/diagnosticos/novo')
                ->waitFor('@screen-bloqueio')
                ->assertSeeIn('@screen-bloqueio-titulo', 'Você ainda não tem análises')
                ->assertSeeIn('@screen-bloqueio-causa', 'comece o teste grátis ou escolha um plano')
                ->assertMissing('@quiz-bloco-1')
                ->assertMissing('@screen-bloqueio-rascunho-salvo')
                ->assertSeeIn('@screen-bloqueio-ver-diagnosticos', 'Ver diagnósticos')
                ->click('@screen-bloqueio-ver-diagnosticos')
                ->waitForLocation('/home');

            app(Carteira::class)->concederCreditos($usuario->id, 1, now()->addMonths(12));

            $browser->visit('/empresas/'.$empresa->id.'/diagnosticos/novo')
                ->waitFor('@quiz-bloco-1')
                ->assertMissing('@screen-bloqueio');
        });
    }

    public function test_admin_liga_e_desliga_o_interruptor_na_tela_de_planos(): void
    {
        $admin = Usuario::factory()->create();
        $admin->forceFill(['permissoes_admin' => ['planos.gerenciar']])->save();

        $this->browse(function (Browser $browser) use ($admin) {
            $browser->loginAs($admin)
                ->visit('/backoffice/planos')
                ->waitFor('@cobranca-estado')
                ->assertSeeIn('@cobranca-estado', 'Cobrança desligada')
                ->check('@cobranca-ativa')
                ->press('@cobranca-salvar')
                ->waitFor('@cobranca-mensagem')
                ->assertSeeIn('@cobranca-mensagem', 'Interruptor salvo.')
                ->assertSeeIn('@cobranca-estado', 'Cobrança ligada');
        });

        $this->assertTrue(CobrancaConfig::ligada());
    }
}

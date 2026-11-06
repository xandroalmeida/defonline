<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Domain\Cobranca\CicloCobranca;
use App\Domain\Cobranca\PaymentProvider;
use App\Domain\Cobranca\StatusAssinatura;
use App\Infrastructure\Cobranca\Fake\FakePaymentProvider;
use App\Models\Assinatura;
use App\Models\CobrancaConfig;
use App\Models\Diagnostico;
use App\Models\EmpresaAnalisada;
use App\Models\Invoice;
use App\Models\Plano;
use App\Models\TrialConfig;
use App\Models\Usuario;
use App\Services\Cobranca\IniciarAssinatura;
use App\Services\Cobranca\ReceberWebhook;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\Artisan;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * STORY-079 — o que o assinante vê quando a renovação falha: o quiz dá lugar ao bloqueio "em atraso" (e
 * volta quando o pagamento chega), e, esgotadas as 3 retentativas, ao bloqueio "plano terminou" com o
 * histórico de diagnósticos intacto. O relógio do servidor não anda: o teste empurra as datas no banco e
 * roda a régua como o agendador faria. O provedor é o fake (estado no cache, comum aos dois processos).
 *
 * NÃO está no grupo `smoke` por design — faz writes.
 */
final class RenovacaoFalhaCobrancaBrowserTest extends DuskTestCase
{
    use DatabaseTruncation;

    /** @var list<string> */
    protected array $tablesToTruncate = [
        'provider_events', 'invoices', 'consumos_analise', 'diagnosticos', 'lotes_credito', 'assinaturas', 'quiz_rascunhos',
        'empresas_analisadas', 'term_acceptances', 'usuarios', 'audit_logs', 'evento_produto', 'sessions', 'cache', 'jobs',
    ];

    protected function afterTruncatingDatabase(): void
    {
        TrialConfig::query()->firstOrCreate([], ['ativo' => true, 'dias' => 7, 'analises' => 1]);
        CobrancaConfig::atual();
    }

    protected function setUp(): void
    {
        parent::setUp();

        CobrancaConfig::atual()->update(['ativa' => true]);
    }

    protected function tearDown(): void
    {
        CobrancaConfig::atual()->update(['ativa' => false]);

        parent::tearDown();
    }

    /** Com offset (IDR-018): o `update` em massa não passa pelo formato do modelo e o banco local roda em Brasília. */
    private function haUmMinuto(): string
    {
        return now()->subMinute()->format('Y-m-d H:i:sP');
    }

    private function fake(): FakePaymentProvider
    {
        $provedor = app(PaymentProvider::class);
        $this->assertInstanceOf(FakePaymentProvider::class, $provedor);

        return $provedor;
    }

    private function processarFila(): void
    {
        Artisan::call('queue:work', ['--stop-when-empty' => true, '--tries' => 1, '--quiet' => true]);
    }

    /** Assinante com plano pago, a uma hora do fim do primeiro ciclo (a renovação é "agora"). */
    private function assinanteNoFimDoCiclo(Usuario $usuario): Invoice
    {
        $plano = Plano::query()->where('nome', 'Básico')->firstOrFail();
        $invoice = app(IniciarAssinatura::class)->executar($usuario, $plano, CicloCobranca::Mensal, '203.0.113.7', 'DuskAgent');
        app(ReceberWebhook::class)->tratar($this->fake()->simularPagamento((string) $invoice->checkout_id_externo));
        $this->processarFila();

        Assinatura::query()->where('usuario_id', $usuario->id)->update([
            'data_inicio' => now()->subMonth()->addHour()->format('Y-m-d H:i:sP'),
            'proximo_vencimento' => now()->addHour()->format('Y-m-d H:i:sP'),
        ]);

        return $invoice->fresh();
    }

    private function recusarRenovacao(Invoice $primeira): string
    {
        app(ReceberWebhook::class)->tratar($this->fake()->simularRecusaDeRenovacao((string) $primeira->checkout_id_externo));
        $this->processarFila();

        return (string) Assinatura::query()->sole()->atraso_cobranca_id;
    }

    public function test_renovacao_recusada_avisa_sem_bloquear_e_o_pagamento_restabelece_em_mobile(): void
    {
        $usuario = Usuario::factory()->create();
        $empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $usuario->id]);
        $primeira = $this->assinanteNoFimDoCiclo($usuario);

        $this->browse(function (Browser $browser) use ($usuario, $empresa, $primeira) {
            $browser->resize(360, 800)->loginAs($usuario)
                ->visit('/empresas/'.$empresa->id.'/diagnosticos/novo')
                ->waitFor('@quiz-bloco-1')
                ->assertMissing('@screen-bloqueio');

            $cobranca = $this->recusarRenovacao($primeira);
            $this->assertSame(StatusAssinatura::EmAtraso, Assinatura::query()->sole()->status);

            // STORY-092 CA-4: em atraso o acesso segue durante as retentativas; a Home avisa.
            $browser->visit('/empresas/'.$empresa->id.'/diagnosticos/novo')
                ->waitFor('@quiz-bloco-1')
                ->assertMissing('@screen-bloqueio')
                ->visit('/home')
                ->waitFor('@screen-trial-aviso')
                ->assertSeeIn('@screen-trial-aviso', 'Sua assinatura está em atraso');

            // O cartão passa na retentativa: o banco confirma a mesma cobrança e o quiz volta.
            app(ReceberWebhook::class)->tratar($this->fake()->simularPagamentoDaCobranca($cobranca));
            $this->processarFila();

            $browser->visit('/empresas/'.$empresa->id.'/diagnosticos/novo')
                ->waitFor('@quiz-bloco-1')
                ->assertMissing('@screen-bloqueio');
        });

        $a = Assinatura::query()->sole();
        $this->assertSame(StatusAssinatura::Ativa, $a->status);
        $this->assertSame(2, $a->periodos_pagos);
        $this->assertNull($a->proxima_tentativa_em);
    }

    public function test_tres_retentativas_sem_pagamento_encerram_o_plano_e_o_historico_continua_em_desktop(): void
    {
        $usuario = Usuario::factory()->create();
        $empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $usuario->id]);
        Diagnostico::factory()->create(['usuario_id' => $usuario->id, 'empresa_analisada_id' => $empresa->id]);
        $primeira = $this->assinanteNoFimDoCiclo($usuario);
        $cobranca = $this->recusarRenovacao($primeira);

        // Cada rodada acontece "48 h depois": o teste vence a data agendada antes de rodar a régua.
        foreach ([1, 2, 3] as $n) {
            Assinatura::query()->update(['proxima_tentativa_em' => $this->haUmMinuto()]);
            Artisan::call('cobranca:regua');
            $this->assertSame($n, FakePaymentProvider::tentativasDe($cobranca));
            $this->assertSame(StatusAssinatura::EmAtraso, Assinatura::query()->sole()->status);
        }

        Assinatura::query()->update(['proxima_tentativa_em' => $this->haUmMinuto()]);
        Artisan::call('cobranca:regua');

        $this->assertSame(StatusAssinatura::Expirada, Assinatura::query()->sole()->status);
        $this->assertTrue(FakePaymentProvider::assinaturaCancelada((string) Assinatura::query()->sole()->id_externo));

        $this->browse(function (Browser $browser) use ($usuario, $empresa) {
            $browser->resize(1280, 900)->loginAs($usuario)
                ->visit('/empresas/'.$empresa->id.'/diagnosticos/novo')
                ->waitFor('@screen-bloqueio')
                ->assertSeeIn('@screen-bloqueio-titulo', 'Seu plano terminou')
                ->assertSeeIn('@screen-bloqueio-causa', 'Seus diagnósticos continuam disponíveis')
                ->assertMissing('@quiz-bloco-1')
                ->click('@screen-bloqueio-ver-diagnosticos')
                ->waitForLocation('/home');
        });

        $this->assertSame(1, Diagnostico::query()->where('usuario_id', $usuario->id)->count());
        $this->assertSame(1, Invoice::query()->where('usuario_id', $usuario->id)->count());
    }

    public function test_com_a_cobranca_desligada_a_falha_nao_muda_nada_para_o_usuario(): void
    {
        $usuario = Usuario::factory()->create();
        $empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $usuario->id]);
        $primeira = $this->assinanteNoFimDoCiclo($usuario);
        CobrancaConfig::atual()->update(['ativa' => false]);

        $this->recusarRenovacao($primeira);

        $this->assertSame(StatusAssinatura::Ativa, Assinatura::query()->sole()->status);
        $this->browse(function (Browser $browser) use ($usuario, $empresa) {
            $browser->loginAs($usuario)
                ->visit('/empresas/'.$empresa->id.'/diagnosticos/novo')
                ->waitFor('@quiz-bloco-1')
                ->assertMissing('@screen-bloqueio');
        });
    }
}

<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Models\Assinatura;
use App\Models\CobrancaConfig;
use App\Models\Invoice;
use App\Models\Plano;
use App\Models\TrialConfig;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\Artisan;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * STORY-080 — plano anual com Pix no navegador, com o provedor fake: contratação (só ativa depois do
 * banco confirmar), Pix expirado e renovação por novo QR Code nos últimos 7 dias. O fim do ciclo é
 * simulado no banco (o relógio do servidor do Dusk é outro processo). NÃO está no grupo `smoke`.
 */
final class PixAnualBrowserTest extends DuskTestCase
{
    use DatabaseTruncation;

    /** @var list<string> */
    protected array $tablesToTruncate = [
        'provider_events', 'invoices', 'lotes_credito', 'assinaturas', 'term_acceptances', 'usuarios', 'audit_logs',
        'evento_produto', 'sessions', 'cache', 'jobs',
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

    private function processarFila(): void
    {
        Artisan::call('queue:work', ['--stop-when-empty' => true, '--tries' => 1, '--quiet' => true]);
    }

    private function plano(): Plano
    {
        return Plano::query()->where('nome', 'Básico')->firstOrFail();
    }

    public function test_mensal_so_tem_cartao_e_anual_oferece_pix(): void
    {
        $usuario = Usuario::factory()->create();
        $plano = $this->plano();

        $this->browse(function (Browser $browser) use ($usuario, $plano) {
            $browser->loginAs($usuario)
                ->visit("/assinar/{$plano->id}/mensal")
                ->waitFor('@screen-checkout-resumo')
                ->assertMissing('@screen-checkout-meio')
                ->visit("/assinar/{$plano->id}/anual")
                ->waitFor('@screen-checkout-meio')
                ->assertRadioSelected('meio', 'cartao')
                ->click('@screen-checkout-meio-pix + span')
                ->waitForTextIn('@screen-checkout-primary-cta', 'Gerar QR Code Pix')
                ->assertSeeIn('@screen-checkout-resumo-ciclo', 'Anual, não renova sozinho — avisamos 7 dias antes')
                ->assertSeeIn('@screen-checkout-resumo-proxima', 'Não há cobrança automática');
        });
    }

    public function test_contrata_com_pix_e_so_ativa_depois_da_confirmacao(): void
    {
        $usuario = Usuario::factory()->create();
        $plano = $this->plano();

        $this->browse(function (Browser $browser) use ($usuario, $plano) {
            $browser->loginAs($usuario)
                ->visit("/assinar/{$plano->id}/anual?meio=pix")
                ->waitFor('@screen-checkout-aceite')
                ->check('@screen-checkout-aceite')
                ->click('@screen-checkout-primary-cta')
                ->waitFor('@pagamento-fake');

            $invoice = Invoice::query()->where('usuario_id', $usuario->id)->sole();
            $this->assertSame('pix', $invoice->meio);
            $this->assertSame(0, Assinatura::query()->count(), 'abrir o checkout nunca ativa');

            $browser->visit('/assinar/retorno/'.$invoice->id)
                ->waitFor('@screen-checkout-aguardando')
                ->assertSee('O plano começa quando o banco confirmar o pagamento.');
            $this->assertSame(0, Assinatura::query()->count());

            $browser->visit($invoice->checkout_url)->waitFor('@pagamento-fake-pagar')->click('@pagamento-fake-pagar')
                ->waitForLocation('/assinar/retorno/'.$invoice->id);
            $this->processarFila();

            $browser->waitFor('@screen-checkout-confirmacao', 15)
                ->assertSee('Plano anual ativo')
                ->assertSee('Esta assinatura não renova sozinha: avisaremos 7 dias antes.');
        });

        $a = Assinatura::query()->where('usuario_id', $usuario->id)->sole();
        $this->assertSame('pix', $a->meio_pagamento);
        $this->assertNull($a->id_externo);
    }

    public function test_pix_expirado_nao_ativa_e_permite_gerar_outro(): void
    {
        $usuario = Usuario::factory()->create();
        $plano = $this->plano();

        $this->browse(function (Browser $browser) use ($usuario, $plano) {
            $browser->loginAs($usuario)
                ->visit("/assinar/{$plano->id}/anual?meio=pix")
                ->waitFor('@screen-checkout-aceite')
                ->check('@screen-checkout-aceite')
                ->click('@screen-checkout-primary-cta')
                ->waitFor('@pagamento-fake-expirar')
                ->click('@pagamento-fake-expirar')
                ->waitFor('@screen-checkout-expirado');
            $this->processarFila();

            $browser->waitFor('@screen-checkout-expirado', 15)
                ->assertSee('O QR Code expirou e nada foi cobrado.')
                ->click('@screen-checkout-tentar-de-novo')
                ->waitFor('@screen-checkout-meio')
                ->assertRadioSelected('meio', 'pix');
        });

        $this->assertSame(0, Assinatura::query()->count());
    }

    public function test_renova_com_novo_qr_code_na_janela_e_estende_o_ciclo(): void
    {
        $usuario = Usuario::factory()->create();
        $plano = $this->plano();

        $this->browse(function (Browser $browser) use ($usuario, $plano) {
            $browser->loginAs($usuario)
                ->visit("/assinar/{$plano->id}/anual?meio=pix")
                ->waitFor('@screen-checkout-aceite')
                ->check('@screen-checkout-aceite')
                ->click('@screen-checkout-primary-cta')
                ->waitFor('@pagamento-fake-pagar')
                ->click('@pagamento-fake-pagar')
                ->waitForLocation('/assinar/retorno/'.Invoice::query()->where('usuario_id', $usuario->id)->sole()->id);
            $this->processarFila();

            $a = Assinatura::query()->where('usuario_id', $usuario->id)->sole();
            $fimAntigo = $a->proximo_vencimento->copy();

            // Fora da janela: a tela diz quando abre.
            $browser->visit('/assinar/renovar')->waitFor('@screen-meuplano-renovacao-fechada')
                ->assertSee('Este plano não renova sozinho');

            // Faltam 3 dias: a renovação abre.
            $a->forceFill(['proximo_vencimento' => now()->addDays(3)])->save();
            $browser->visit('/assinar/renovar')->waitFor('@screen-meuplano-aviso-renovacao')
                ->assertSee('Ele não renova sozinho.')
                ->check('@screen-checkout-aceite')
                ->click('@screen-checkout-primary-cta')
                ->waitFor('@pagamento-fake-pagar')
                ->click('@pagamento-fake-pagar');
            $renov = Invoice::query()->where('renovacao', true)->sole();
            $browser->waitForLocation('/assinar/retorno/'.$renov->id);
            $this->processarFila();

            $browser->waitFor('@screen-checkout-confirmacao', 15)->assertSee('Plano renovado');

            $this->assertSame(2, $a->fresh()->periodos_pagos);
            $this->assertTrue($a->fresh()->proximo_vencimento->greaterThan(now()->addYear()->subDays(10)));
            $this->assertSame(1, Assinatura::query()->count());
            unset($fimAntigo);
        });
    }

    public function test_plano_vencido_mostra_encerrado_com_os_caminhos_de_volta(): void
    {
        $usuario = Usuario::factory()->create();
        $plano = $this->plano();

        $this->browse(function (Browser $browser) use ($usuario, $plano) {
            $browser->loginAs($usuario)
                ->visit("/assinar/{$plano->id}/anual?meio=pix")
                ->waitFor('@screen-checkout-aceite')
                ->check('@screen-checkout-aceite')
                ->click('@screen-checkout-primary-cta')
                ->waitFor('@pagamento-fake-pagar')
                ->click('@pagamento-fake-pagar')
                ->waitForLocation('/assinar/retorno/'.Invoice::query()->where('usuario_id', $usuario->id)->sole()->id);
            $this->processarFila();

            Assinatura::query()->where('usuario_id', $usuario->id)->update(['proximo_vencimento' => now()->subDay()]);
            Artisan::call('assinaturas:pix-anual');

            $browser->visit('/assinar/renovar')->waitFor('@screen-meuplano-encerrado')
                ->assertSee('Seus diagnósticos continuam disponíveis.')
                ->assertSee('Assinar com cartão')
                ->click('@screen-meuplano-renovar-pix')
                ->waitFor('@screen-checkout-meio')
                ->assertRadioSelected('meio', 'pix');
        });
    }
}

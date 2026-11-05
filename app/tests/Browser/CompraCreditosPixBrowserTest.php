<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Models\CobrancaConfig;
use App\Models\Invoice;
use App\Models\LoteCredito;
use App\Models\PacoteCredito;
use App\Models\TermAcceptance;
use App\Models\TrialConfig;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\Artisan;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * STORY-076 — compra de créditos com Pix, no navegador, com o provedor fake (a "página do provedor"
 * simula o banco). O processamento do webhook vai pela fila; sem worker no Dusk, o teste a esvazia.
 *
 * NÃO está no grupo `smoke` por design — faz writes.
 */
final class CompraCreditosPixBrowserTest extends DuskTestCase
{
    use DatabaseTruncation;

    /** @var list<string> */
    protected array $tablesToTruncate = [
        'provider_events', 'invoices', 'lotes_credito', 'term_acceptances', 'usuarios', 'audit_logs',
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

    public function test_usuario_revisa_aceita_paga_no_provedor_e_recebe_os_creditos(): void
    {
        $usuario = Usuario::factory()->create();
        $pacote = PacoteCredito::query()->where('codigo', 'plus')->firstOrFail();

        $this->browse(function (Browser $browser) use ($usuario, $pacote) {
            $browser->loginAs($usuario)
                ->visit('/creditos/comprar')
                ->waitFor('@screen-checkout-pacotes')
                ->click('@screen-checkout-comprar-plus')
                ->waitFor('@screen-checkout-resumo')
                ->assertSeeIn('@screen-checkout-resumo-ciclo', 'Compra única, não renova')
                ->assertSeeIn('@screen-checkout-resumo-desconto', 'Sem desconto')
                ->assertSeeIn('@screen-checkout-resumo-a-pagar', 'R$ '.number_format($pacote->preco_centavos / 100, 2, ',', '.'))
                ->assertSeeIn('@screen-checkout-primary-cta', 'Gerar QR Code Pix')
                // Sem aceite: erro sob a caixa, nada é gerado.
                ->click('@screen-checkout-primary-cta')
                ->waitFor('@screen-checkout-error-aceite')
                ->assertSeeIn('@screen-checkout-error-aceite', 'Marque o aceite do Termo para continuar.')
                ->check('@screen-checkout-aceite')
                ->click('@screen-checkout-primary-cta')
                // O "provedor" fake.
                ->waitFor('@pagamento-fake')
                ->assertPathBeginsWith('/pagamentos/fake/');

            $this->assertSame(1, Invoice::query()->where('usuario_id', $usuario->id)->count());
            $this->assertSame(0, LoteCredito::query()->count(), 'abrir o checkout nunca credita');
            $aceite = TermAcceptance::query()->where('usuario_id', $usuario->id)->where('termo_tipo', 'termo_adesao')->firstOrFail();
            $this->assertSame('v2', $aceite->versao);

            // Volta do checkout sem pagar: a tela aguarda o webhook.
            $invoice = Invoice::query()->where('usuario_id', $usuario->id)->firstOrFail();
            $browser->visit('/creditos/pagamentos/'.$invoice->id)
                ->waitFor('@screen-checkout-aguardando')
                ->assertSeeIn('@screen-checkout-aguardando-aviso', 'Aguardando o pagamento…')
                ->assertSeeIn('@screen-checkout-pix-expira', 'restam')
                ->assertPresent('@screen-checkout-cancelar');
            $this->assertSame(0, LoteCredito::query()->count(), 'o retorno por si só não credita');

            // O banco confirma: webhook -> fila -> carteira, e a tela se atualiza sozinha (polling).
            $browser->visit($invoice->fresh()->checkout_url)
                ->waitFor('@pagamento-fake-pagar')
                ->click('@pagamento-fake-pagar')
                ->waitForLocation('/creditos/pagamentos/'.$invoice->id);

            $this->processarFila();

            $browser->waitFor('@screen-checkout-confirmacao', 15)
                ->assertSee('Pagamento confirmado')
                ->assertSee('Você tem '.$pacote->quantidade_creditos.' créditos')
                ->assertSeeIn('@screen-checkout-cta-sucesso', 'Fazer uma análise');
        });

        $lote = LoteCredito::query()->where('usuario_id', $usuario->id)->sole();
        $this->assertSame($pacote->quantidade_creditos, $lote->quantidade_restante);
    }

    public function test_pix_expirado_nao_credita_e_permite_gerar_outro_com_o_aceite_marcado(): void
    {
        $usuario = Usuario::factory()->create();

        $this->browse(function (Browser $browser) use ($usuario) {
            $browser->loginAs($usuario)
                ->visit('/creditos/comprar')
                ->click('@screen-checkout-comprar-mini')
                ->waitFor('@screen-checkout-aceite')
                ->check('@screen-checkout-aceite')
                ->click('@screen-checkout-primary-cta')
                ->waitFor('@pagamento-fake-expirar')
                ->click('@pagamento-fake-expirar')
                ->waitFor('@screen-checkout-expirado');

            $this->processarFila();

            $browser->waitFor('@screen-checkout-expirado', 15)
                ->assertSee('O QR Code expirou e nada foi cobrado.')
                ->click('@screen-checkout-pix-novo')
                ->waitFor('@screen-checkout-aceite')
                ->assertChecked('@screen-checkout-aceite');
        });

        $this->assertSame(0, LoteCredito::query()->count());
    }

    public function test_com_a_cobranca_desligada_a_pagina_nao_existe(): void
    {
        CobrancaConfig::atual()->update(['ativa' => false]);
        $usuario = Usuario::factory()->create();

        $this->browse(function (Browser $browser) use ($usuario) {
            $browser->loginAs($usuario)->visit('/creditos/comprar')->assertDontSee('Comprar créditos');
        });
    }

    public function test_email_nao_confirmado_ve_o_bloqueio_e_nao_o_formulario(): void
    {
        $usuario = Usuario::factory()->unconfirmed()->create();
        $pacote = PacoteCredito::query()->where('codigo', 'plus')->firstOrFail();

        $this->browse(function (Browser $browser) use ($usuario, $pacote) {
            $browser->loginAs($usuario)
                ->visit('/creditos/comprar/'.$pacote->id)
                ->waitFor('@screen-checkout-email-nao-confirmado')
                ->assertSee('Confirme seu e-mail para comprar.')
                ->assertMissing('@screen-checkout-primary-cta');
        });
    }
}

<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Models\Assinatura;
use App\Models\CobrancaConfig;
use App\Models\Invoice;
use App\Models\LoteCredito;
use App\Models\PacoteCredito;
use App\Models\TrialConfig;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\Artisan;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * STORY-078 — compra de créditos com cartão, no navegador, com o provedor fake (a "página do
 * provedor" simula o banco). NÃO está no grupo `smoke` por design — faz writes.
 */
final class CompraCreditosCartaoBrowserTest extends DuskTestCase
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

    public function test_escolhe_cartao_paga_e_recebe_os_creditos_sem_recorrencia(): void
    {
        $usuario = Usuario::factory()->create();
        $pacote = PacoteCredito::query()->where('codigo', 'plus')->firstOrFail();

        $this->browse(function (Browser $browser) use ($usuario, $pacote) {
            $browser->loginAs($usuario)
                ->visit('/creditos/comprar/'.$pacote->id)
                ->waitFor('@screen-checkout-meio')
                ->assertRadioSelected('meio', 'pix')
                ->assertSeeIn('@screen-checkout-primary-cta', 'Gerar QR Code Pix')
                ->click('@screen-checkout-meio-cartao + span')
                ->waitForTextIn('@screen-checkout-primary-cta', 'Pagar com cartão')
                ->assertSeeIn('@screen-checkout-resumo-ciclo', 'Compra única, não renova')
                ->check('@screen-checkout-aceite')
                ->click('@screen-checkout-primary-cta')
                ->waitFor('@pagamento-fake')
                ->assertSee('Simular cartão aprovado');

            $invoice = Invoice::query()->where('usuario_id', $usuario->id)->sole();
            $this->assertSame('cartao', $invoice->meio);
            $this->assertSame(0, LoteCredito::query()->count(), 'abrir o checkout nunca credita');

            $browser->click('@pagamento-fake-pagar')->waitForLocation('/creditos/pagamentos/'.$invoice->id);
            $this->processarFila();

            $browser->waitFor('@screen-checkout-confirmacao', 15)
                ->assertSee('Pagamento confirmado')
                ->assertSee('Pago com cartão Visa final 4242')
                ->assertSee('Você tem '.$pacote->quantidade_creditos.' créditos');
        });

        $this->assertSame(1, LoteCredito::query()->where('usuario_id', $usuario->id)->count());
        $this->assertSame(0, Assinatura::query()->count(), 'comprar créditos não cria recorrência');
    }

    public function test_cartao_recusado_nao_credita_explica_e_oferece_pagar_com_pix(): void
    {
        $usuario = Usuario::factory()->create();

        $this->browse(function (Browser $browser) use ($usuario) {
            $browser->loginAs($usuario)
                ->visit('/creditos/comprar')
                ->click('@screen-checkout-comprar-mini')
                ->waitFor('@screen-checkout-meio')
                ->click('@screen-checkout-meio-cartao + span')
                ->check('@screen-checkout-aceite')
                ->click('@screen-checkout-primary-cta')
                ->waitFor('@pagamento-fake-recusar')
                ->click('@pagamento-fake-recusar')
                ->waitForLocation('/creditos/pagamentos/'.Invoice::query()->sole()->id);

            $this->processarFila();

            $browser->waitFor('@screen-checkout-recusa', 15)
                ->assertSee('Cartão recusado')
                ->assertSee('O banco não autorizou o pagamento.')
                ->assertSee('Você pode tentar outro cartão ou pagar com Pix.')
                ->click('@screen-checkout-pagar-com-pix')
                ->waitFor('@screen-checkout-aceite')
                ->assertChecked('@screen-checkout-aceite')
                ->assertRadioSelected('meio', 'pix')
                ->assertSeeIn('@screen-checkout-primary-cta', 'Gerar QR Code Pix');
        });

        $this->assertSame(0, LoteCredito::query()->count());
    }

    public function test_com_pix_em_aberto_a_tela_avisa_ao_escolher_cartao(): void
    {
        $usuario = Usuario::factory()->create();

        $this->browse(function (Browser $browser) use ($usuario) {
            $browser->loginAs($usuario)
                ->visit('/creditos/comprar')
                ->click('@screen-checkout-comprar-mini')
                ->waitFor('@screen-checkout-aceite')
                ->check('@screen-checkout-aceite')
                ->click('@screen-checkout-primary-cta')
                ->waitFor('@pagamento-fake');

            $pacote = Invoice::query()->sole()->pacote_credito_id;
            $browser->visit('/creditos/comprar/'.$pacote)
                ->waitFor('@screen-checkout-meio')
                ->assertMissing('@screen-checkout-pix-em-aberto')
                ->click('@screen-checkout-meio-cartao + span')
                ->waitFor('@screen-checkout-pix-em-aberto')
                ->assertSeeIn('@screen-checkout-pix-em-aberto', 'QR Code Pix em aberto');
        });
    }
}

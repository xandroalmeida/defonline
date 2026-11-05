<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Models\Assinatura;
use App\Models\CobrancaConfig;
use App\Models\Invoice;
use App\Models\Plano;
use App\Models\TermAcceptance;
use App\Models\TrialConfig;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\Artisan;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * STORY-077 CA-10 — Planos e preços -> checkout -> página do provedor (fake) -> assinatura ativa,
 * em 360x800 e em desktop. O cartão é digitado na página do provedor, que aqui é simulada; o
 * processamento do webhook vai pela fila, que o teste esvazia (não há worker no Dusk).
 *
 * NÃO está no grupo `smoke` por design — faz writes.
 */
final class AssinaturaCartaoBrowserTest extends DuskTestCase
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

    public function test_do_planos_ao_checkout_pagamento_aprovado_e_assinatura_ativa_em_mobile(): void
    {
        $usuario = Usuario::factory()->create();
        $plano = Plano::query()->where('nome', 'Básico')->firstOrFail();

        $this->browse(function (Browser $browser) use ($usuario, $plano) {
            $browser->resize(360, 800)->loginAs($usuario)
                ->visit('/planos')
                ->waitFor('@screen-planos')
                ->click('@screen-planos-ciclo-anual + span')
                ->waitForTextIn('@screen-planos-preco-'.$plano->id, 'R$ 399,00')
                ->click('@screen-planos-assinar-'.$plano->id)
                ->waitFor('@screen-checkout-resumo')
                ->assertPathIs('/assinar/'.$plano->id.'/anual')
                ->assertSeeIn('@screen-checkout-resumo-plano', 'Básico · anual')
                ->assertSeeIn('@screen-checkout-resumo-a-pagar', 'R$ 399,00')
                ->assertSeeIn('@screen-checkout-resumo-ciclo', 'Anual, renova sozinho no cartão')
                ->assertSeeIn('@screen-checkout-resumo-depois', 'R$ 399,00 por ano')
                ->assertMissing('@screen-checkout-cartao-numero')
                // Troca de ciclo sem sair da tela.
                ->click('@screen-checkout-ciclo-mensal + span')
                ->waitForTextIn('@screen-checkout-resumo-a-pagar', 'R$ 49,90')
                ->assertSeeIn('@screen-checkout-primary-cta', 'Assinar e pagar · R$ 49,90')
                // Sem aceite: erro, nada é aberto.
                ->click('@screen-checkout-primary-cta')
                ->waitFor('@screen-checkout-error-aceite')
                ->assertSeeIn('@screen-checkout-error-aceite', 'Marque o aceite do Termo para continuar.')
                ->check('@screen-checkout-aceite')
                ->click('@screen-checkout-primary-cta')
                ->waitFor('@pagamento-fake')
                ->assertPathBeginsWith('/pagamentos/fake/');

            $invoice = Invoice::query()->where('usuario_id', $usuario->id)->sole();
            $this->assertSame('mensal', $invoice->ciclo);
            $this->assertSame(0, Assinatura::query()->count(), 'abrir o checkout nunca ativa');
            $this->assertSame('v2', TermAcceptance::query()->where('usuario_id', $usuario->id)->where('termo_tipo', 'termo_adesao')->firstOrFail()->versao);

            // Voltou sem o webhook: a tela aguarda e não libera nada.
            $browser->visit('/assinar/retorno/'.$invoice->id)
                ->waitFor('@screen-checkout-aguardando')
                ->assertSee('Estamos confirmando seu pagamento');
            $this->assertSame(0, Assinatura::query()->count());

            $browser->visit($invoice->fresh()->checkout_url)
                ->waitFor('@pagamento-fake-pagar')
                ->click('@pagamento-fake-pagar')
                ->waitForLocation('/assinar/retorno/'.$invoice->id);

            $this->processarFila();

            $browser->waitFor('@screen-checkout-confirmacao', 15)
                ->assertSee('Assinatura ativa')
                ->assertSee('Plano Básico')
                ->assertSee('Visa final 4242')
                ->assertSeeIn('@screen-checkout-cta-sucesso', 'Fazer uma análise')
                // Já assinante: o checkout não abre mais formulário (CA-7).
                ->visit('/assinar/'.$plano->id.'/mensal')
                ->waitFor('@screen-checkout-ja-assinante')
                ->assertSee('Você já tem o plano Básico, que renova em')
                ->assertMissing('@screen-checkout-primary-cta');
        });

        $a = Assinatura::query()->where('usuario_id', $usuario->id)->sole();
        $this->assertSame($plano->cota_mensal, $a->cota_restante_ciclo);
    }

    public function test_cartao_recusado_mantem_sem_assinatura_e_oferece_nova_tentativa_em_desktop(): void
    {
        $usuario = Usuario::factory()->create();
        $plano = Plano::query()->where('nome', 'Básico')->firstOrFail();

        $this->browse(function (Browser $browser) use ($usuario, $plano) {
            $browser->resize(1280, 900)->loginAs($usuario)
                ->visit('/assinar/'.$plano->id.'/mensal')
                ->waitFor('@screen-checkout-aceite')
                ->check('@screen-checkout-aceite')
                ->click('@screen-checkout-primary-cta')
                ->waitFor('@pagamento-fake-recusar')
                ->click('@pagamento-fake-recusar')
                ->waitForLocation('/assinar/retorno/'.Invoice::query()->sole()->id);

            $this->processarFila();

            $browser->waitFor('@screen-checkout-recusa', 15)
                ->assertSee('Cartão recusado')
                ->assertSee('O banco não autorizou o pagamento. Tente outro cartão.')
                ->assertSee('Você continua sem assinatura.')
                ->click('@screen-checkout-tentar-de-novo')
                ->waitFor('@screen-checkout-aceite')
                ->assertPathIs('/assinar/'.$plano->id.'/mensal');
        });

        $this->assertSame(0, Assinatura::query()->count());
    }

    public function test_com_a_cobranca_desligada_o_checkout_nao_existe(): void
    {
        CobrancaConfig::atual()->update(['ativa' => false]);
        $usuario = Usuario::factory()->create();
        $plano = Plano::query()->where('nome', 'Básico')->firstOrFail();

        $this->browse(function (Browser $browser) use ($usuario, $plano) {
            $browser->loginAs($usuario)->visit('/assinar/'.$plano->id.'/mensal')->assertDontSee('Assinar plano');
        });
    }

    public function test_email_nao_confirmado_ve_o_bloqueio_e_nao_o_formulario(): void
    {
        $usuario = Usuario::factory()->unconfirmed()->create();
        $plano = Plano::query()->where('nome', 'Básico')->firstOrFail();

        $this->browse(function (Browser $browser) use ($usuario, $plano) {
            $browser->loginAs($usuario)
                ->visit('/assinar/'.$plano->id.'/mensal')
                ->waitFor('@screen-checkout-email-nao-confirmado')
                ->assertSee('Confirme seu e-mail para assinar.')
                ->assertMissing('@screen-checkout-primary-cta');
        });
    }
}

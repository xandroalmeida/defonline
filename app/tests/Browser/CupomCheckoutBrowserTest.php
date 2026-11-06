<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Domain\Cobranca\TipoDesconto;
use App\Models\Assinatura;
use App\Models\CobrancaConfig;
use App\Models\Cupom;
use App\Models\CupomConcessao;
use App\Models\Invoice;
use App\Models\PacoteCredito;
use App\Models\Plano;
use App\Models\TrialConfig;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\Artisan;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * STORY-083 — cupom digitado, cupom concedido (beta) e desconto total no checkout, em 360x800 e em desktop,
 * com a página do provedor simulada. NÃO está no grupo `smoke` por design — faz writes.
 */
final class CupomCheckoutBrowserTest extends DuskTestCase
{
    use DatabaseTruncation;

    /** @var list<string> */
    protected array $tablesToTruncate = [
        'provider_events', 'invoices', 'lotes_credito', 'assinaturas', 'term_acceptances', 'usuarios', 'audit_logs',
        'evento_produto', 'sessions', 'cache', 'jobs', 'cupom_usos', 'cupom_concessoes', 'cupom_itens', 'cupons',
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

    private function cupomDoPlano(string $codigo, int $percentual, ?int $ciclos = 3): Cupom
    {
        $cupom = Cupom::query()->create([
            'codigo' => $codigo, 'tipo' => TipoDesconto::Percentual, 'valor' => $percentual * 10,
            'vigencia_inicio' => now()->subDay()->toDateString(), 'vigencia_fim' => now()->addMonth()->toDateString(),
            'ativo' => true, 'ciclos_duracao' => $ciclos,
        ]);
        $cupom->itens()->create(['plano_id' => Plano::query()->where('nome', 'Básico')->firstOrFail()->id, 'ciclo' => 'mensal']);

        return $cupom;
    }

    public function test_cupom_digitado_mostra_preco_desconto_e_valor_e_a_assinatura_sai_com_o_valor_final_em_mobile(): void
    {
        $usuario = Usuario::factory()->create();
        $plano = Plano::query()->where('nome', 'Básico')->firstOrFail();
        $cupom = $this->cupomDoPlano('BETA50', 50);

        $this->browse(function (Browser $browser) use ($usuario, $plano) {
            $browser->resize(360, 800)->loginAs($usuario)
                ->visit('/assinar/'.$plano->id.'/mensal')
                ->waitFor('@screen-checkout-cupom-toggle')
                ->assertSeeIn('@screen-checkout-resumo-desconto', 'Sem desconto')
                ->click('@screen-checkout-cupom-toggle')
                ->waitFor('@screen-checkout-cupom-campo')
                // Código inexistente: motivo sob o campo, sem desconto.
                ->type('@screen-checkout-cupom-campo', 'naoexiste')
                ->click('@screen-checkout-cupom-aplicar')
                ->waitFor('@screen-checkout-error-cupom')
                ->assertSeeIn('@screen-checkout-error-cupom', 'Não encontramos esse cupom. Confira o código.')
                ->assertSeeIn('@screen-checkout-resumo-a-pagar', 'R$ 49,90')
                // Código certo, em minúsculas: resumo atualizado sem recarregar.
                ->type('@screen-checkout-cupom-campo', 'beta50')
                ->click('@screen-checkout-cupom-aplicar')
                ->waitFor('@screen-checkout-cupom-aplicado')
                ->assertSeeIn('@screen-checkout-cupom-aplicado', 'BETA50 aplicado — 50% de desconto em 3 cobranças')
                ->assertSeeIn('@screen-checkout-resumo-preco', 'R$ 49,90')
                ->assertSeeIn('@screen-checkout-resumo-desconto', '− R$ 24,95 (BETA50)')
                ->assertSeeIn('@screen-checkout-resumo-a-pagar', 'R$ 24,95')
                ->assertSeeIn('@screen-checkout-resumo-depois', 'R$ 49,90 por mês, a partir de')
                ->assertSeeIn('@screen-checkout-primary-cta', 'Assinar e pagar · R$ 24,95')
                // Remover volta ao cheio.
                ->click('@screen-checkout-cupom-remover')
                ->waitForTextIn('@screen-checkout-resumo-a-pagar', 'R$ 49,90')
                ->waitFor('@screen-checkout-cupom-campo')
                ->type('@screen-checkout-cupom-campo', 'BETA50')
                ->click('@screen-checkout-cupom-aplicar')
                ->waitFor('@screen-checkout-cupom-aplicado')
                ->check('@screen-checkout-aceite')
                ->click('@screen-checkout-primary-cta')
                ->waitFor('@pagamento-fake');
        });

        $invoice = Invoice::query()->where('usuario_id', $usuario->id)->sole();
        $this->assertSame(2495, $invoice->valor_centavos);
        $this->assertSame(0, $cupom->usos()->count(), 'o uso só nasce com a contratação concluída');

        $this->browse(function (Browser $browser) use ($invoice) {
            $browser->visit($invoice->fresh()->checkout_url)
                ->waitFor('@pagamento-fake-pagar')
                ->click('@pagamento-fake-pagar')
                ->waitForLocation('/assinar/retorno/'.$invoice->id);
            $this->processarFila();
            $browser->waitFor('@screen-checkout-confirmacao', 15)
                ->assertSee('Assinatura ativa')
                ->visit('/meu-plano')
                ->waitFor('@screen-meuplano-desconto')
                ->assertSeeIn('@screen-meuplano-desconto', 'BETA50')
                ->assertSeeIn('@screen-meuplano-cobrancas', 'R$ 24,95');
        });

        $this->assertSame(1, $cupom->usos()->count());
    }

    public function test_cupom_concedido_de_desconto_total_ativa_sem_pagar_e_sem_campo_de_cupom_em_desktop(): void
    {
        $usuario = Usuario::factory()->create();
        $plano = Plano::query()->where('nome', 'Básico')->firstOrFail();
        $cupom = $this->cupomDoPlano('BETA100', 100, 2);
        CupomConcessao::query()->create(['cupom_id' => $cupom->id, 'usuario_id' => $usuario->id, 'ciclos_restantes' => 2, 'concedida_em' => now()]);

        $this->browse(function (Browser $browser) use ($usuario, $plano) {
            $browser->resize(1280, 900)->loginAs($usuario)
                ->visit('/assinar/'.$plano->id.'/mensal')
                ->waitFor('@screen-checkout-cupom-concedido')
                ->assertSeeIn('@screen-checkout-cupom-concedido', 'Cupom BETA100 aplicado à sua conta')
                ->assertMissing('@screen-checkout-cupom-toggle')
                ->assertMissing('@screen-checkout-cupom-campo')
                ->assertSeeIn('@screen-checkout-resumo-a-pagar', 'R$ 0,00')
                ->assertSeeIn('@screen-checkout-resumo-proxima', 'Nenhuma até')
                ->assertSeeIn('@screen-checkout-resumo-depois', 'Cadastre um cartão em Meu plano para continuar.')
                ->assertSeeIn('@screen-checkout-sem-pagamento', 'Você não precisa pagar nada agora.')
                ->assertSeeIn('@screen-checkout-primary-cta', 'Ativar sem pagar')
                ->click('@screen-checkout-primary-cta')
                ->waitFor('@screen-checkout-error-aceite')
                ->check('@screen-checkout-aceite')
                ->click('@screen-checkout-primary-cta')
                ->waitFor('@screen-checkout-confirmacao', 15)
                ->assertSee('ativado sem cobrança')
                ->assertDontSee('pagamento-fake')
                ->visit('/meu-plano')
                ->waitFor('@screen-meuplano-desconto')
                ->assertSeeIn('@screen-meuplano-desconto', 'Sem cobrança')
                ->assertSeeIn('@screen-meuplano-acesso-ate', 'Sem cobrança por enquanto')
                ->assertSeeIn('@screen-meuplano-cobrancas', 'Sem cobrança');
        });

        $a = Assinatura::query()->where('usuario_id', $usuario->id)->sole();
        $this->assertNull($a->id_externo);
        $this->assertSame(1, $cupom->usos()->count());
        $this->assertSame(0, Invoice::query()->where('usuario_id', $usuario->id)->sole()->valor_centavos);
    }

    public function test_cupom_de_pacote_de_creditos_com_pix(): void
    {
        $usuario = Usuario::factory()->create();
        $pacote = PacoteCredito::query()->where('codigo', 'plus')->firstOrFail();
        $cupom = Cupom::query()->create([
            'codigo' => 'CRED20', 'tipo' => TipoDesconto::Percentual, 'valor' => 200,
            'vigencia_inicio' => now()->subDay()->toDateString(), 'vigencia_fim' => now()->addMonth()->toDateString(), 'ativo' => true,
        ]);
        $cupom->itens()->create(['pacote_credito_id' => $pacote->id]);
        $final = (int) round($pacote->preco_centavos * 0.8);

        $this->browse(function (Browser $browser) use ($usuario, $pacote, $final) {
            $browser->resize(1280, 900)->loginAs($usuario)
                ->visit('/creditos/comprar/'.$pacote->id)
                ->waitFor('@screen-checkout-cupom-toggle')
                ->click('@screen-checkout-cupom-toggle')
                ->waitFor('@screen-checkout-cupom-campo')
                ->type('@screen-checkout-cupom-campo', 'CRED20')
                ->click('@screen-checkout-cupom-aplicar')
                ->waitFor('@screen-checkout-cupom-aplicado')
                ->assertSeeIn('@screen-checkout-resumo-a-pagar', 'R$ '.number_format($final / 100, 2, ',', '.'))
                ->check('@screen-checkout-aceite')
                ->click('@screen-checkout-primary-cta')
                ->waitFor('@pagamento-fake');
        });

        $this->assertSame($final, Invoice::query()->where('usuario_id', $usuario->id)->sole()->valor_centavos);
    }
}

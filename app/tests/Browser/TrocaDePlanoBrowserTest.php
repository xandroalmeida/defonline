<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Domain\Cobranca\CicloCobranca;
use App\Domain\Cobranca\PaymentProvider;
use App\Infrastructure\Cobranca\Fake\FakePaymentProvider;
use App\Models\Assinatura;
use App\Models\CobrancaConfig;
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
 * STORY-082 — troca de plano de ponta a ponta com a "página do provedor" fake: upgrade (resumo, pagamento
 * da diferença, confirmação), upgrade recusado (o plano continua) e downgrade agendado com "Desfazer troca".
 *
 * NÃO está no grupo `smoke` por design — faz writes.
 */
final class TrocaDePlanoBrowserTest extends DuskTestCase
{
    use DatabaseTruncation;

    /** @var list<string> */
    protected array $tablesToTruncate = [
        'trocas_de_cartao', 'provider_events', 'invoices', 'lotes_credito', 'consumos_analise', 'assinaturas',
        'term_acceptances', 'usuarios', 'audit_logs', 'evento_produto', 'sessions', 'cache', 'jobs',
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

    private function plano(string $nome): Plano
    {
        return Plano::query()->where('nome', $nome)->firstOrFail();
    }

    /** Assinante mensal do Básico, com o pagamento aprovado no provedor fake. */
    private function assinante(): Usuario
    {
        $usuario = Usuario::factory()->create();
        $invoice = app(IniciarAssinatura::class)->executar($usuario, $this->plano('Básico'), CicloCobranca::Mensal, '203.0.113.7', 'Dusk');
        $fake = app(PaymentProvider::class);
        $this->assertInstanceOf(FakePaymentProvider::class, $fake);
        app(ReceberWebhook::class)->tratar($fake->simularPagamento((string) $invoice->checkout_id_externo));
        $this->processarFila();

        return $usuario;
    }

    private function urlTroca(string $nome, string $ciclo = 'mensal'): string
    {
        return route('plano.trocar', ['plano' => $this->plano($nome)->id, 'ciclo' => $ciclo], false);
    }

    public function test_upgrade_mostra_o_resumo_paga_a_diferenca_e_vale_na_hora_em_mobile(): void
    {
        $usuario = $this->assinante();

        $this->browse(function (Browser $browser) use ($usuario) {
            $browser->resize(360, 800)->loginAs($usuario)
                ->visit('/meu-plano')
                ->waitFor('@screen-meuplano-trocar-plano')
                ->click('@screen-meuplano-trocar-plano')
                ->waitForLocation('/planos')
                ->visit($this->urlTroca('Pro'))
                ->waitFor('@screen-troca-resumo')
                ->assertSeeIn('@screen-troca-titulo', 'Trocar para o plano Pro')
                ->assertSeeIn('@screen-troca-credito', '−')
                ->assertSeeIn('@screen-troca-resumo-preco', 'R$')
                ->assertSeeIn('@screen-troca-explicacao', 'Hoje você paga só a diferença')
                ->assertSeeIn('@screen-troca-explicacao', 'Sua cota passa a ser de 10 análises a partir de agora.');

            $this->assertTrue($browser->script('return document.documentElement.scrollWidth <= window.innerWidth')[0], 'a página não pode rolar de lado');

            $browser->click('@screen-troca-confirmar')
                ->waitFor('@pagamento-fake')
                ->click('@pagamento-fake-pagar')
                ->waitFor('@screen-troca-aplicada', 15)
                ->assertSeeIn('@screen-troca-aplicada', 'Você agora está no plano Pro. Sua cota é de 10 análises e você já pode usá-las.')
                ->visit('/meu-plano')
                ->waitFor('@screen-meuplano-plano')
                ->assertSeeIn('@screen-meuplano-plano', 'Pro · mensal');
        });

        $a = Assinatura::query()->where('usuario_id', $usuario->id)->sole();
        $this->assertSame($this->plano('Pro')->id, $a->plano_id);
        $this->assertSame(10, $a->cota_snapshot);
        $this->assertSame(19900, (int) $a->preco_snapshot_centavos);
    }

    public function test_upgrade_recusado_mantem_o_plano_atual(): void
    {
        $usuario = $this->assinante();

        $this->browse(function (Browser $browser) use ($usuario) {
            $browser->resize(1280, 900)->loginAs($usuario)
                ->visit($this->urlTroca('Pro'))
                ->waitFor('@screen-troca-confirmar')
                ->click('@screen-troca-confirmar')
                ->waitFor('@pagamento-fake-recusar')
                ->click('@pagamento-fake-recusar')
                ->waitFor('@screen-troca-recusa', 15)
                ->assertSee('Cartão recusado')
                ->assertSee('Seu plano continua o Básico. Nada foi cobrado.');
        });

        $this->assertSame($this->plano('Básico')->id, Assinatura::query()->where('usuario_id', $usuario->id)->sole()->plano_id);
        $this->assertSame(0, Invoice::query()->where('tipo', 'troca_de_plano')->where('status', 'paga')->count());
    }

    public function test_downgrade_fica_agendado_em_meu_plano_e_pode_ser_desfeito(): void
    {
        $usuario = $this->assinante();
        $pro = $this->plano('Pro');
        Assinatura::query()->where('usuario_id', $usuario->id)->sole()
            ->forceFill(['plano_id' => $pro->id, 'cota_snapshot' => 10, 'cota_restante_ciclo' => 10, 'preco_snapshot_centavos' => 19900])->save();

        $this->browse(function (Browser $browser) use ($usuario) {
            $browser->resize(1280, 900)->loginAs($usuario)
                ->visit($this->urlTroca('Básico'))
                ->waitFor('@screen-troca-confirmar')
                ->assertSeeIn('@screen-troca-resumo-a-pagar', 'R$ 0,00')
                ->assertSeeIn('@screen-troca-explicacao', 'Você continua no Pro até')
                ->assertSeeIn('@screen-troca-confirmar', 'Agendar troca para o Básico')
                ->click('@screen-troca-confirmar')
                ->waitFor('@screen-troca-agendado')
                ->assertSeeIn('@screen-troca-agendado', 'Troca agendada para')
                ->visit('/meu-plano')
                ->waitFor('@screen-troca-agendado')
                ->assertSeeIn('@screen-troca-agendado', 'Troca para o plano Básico')
                ->assertSeeIn('@screen-meuplano-plano', 'Pro · mensal')
                ->click('@screen-troca-desfazer')
                ->waitUntilMissing('@screen-troca-agendado')
                ->assertSeeIn('@screen-meuplano-resultado', 'Troca desfeita.');
        });

        $a = Assinatura::query()->where('usuario_id', $usuario->id)->sole();
        $this->assertNull($a->troca_plano_id);
        $this->assertSame($pro->id, $a->plano_id);
        $this->assertSame(19900, FakePaymentProvider::valorDaAssinatura((string) $a->id_externo)['valor']);
    }

    public function test_em_atraso_a_troca_pede_para_atualizar_o_cartao(): void
    {
        $usuario = $this->assinante();
        Assinatura::query()->where('usuario_id', $usuario->id)->sole()->forceFill(['status' => 'em_atraso'])->save();

        $this->browse(function (Browser $browser) use ($usuario) {
            $browser->resize(1280, 900)->loginAs($usuario)
                ->visit($this->urlTroca('Pro'))
                ->waitFor('@screen-troca-negada')
                ->assertSeeIn('@screen-troca-negada', 'Atualize o cartão antes de trocar de plano.')
                ->assertMissing('@screen-troca-confirmar');
        });
    }
}

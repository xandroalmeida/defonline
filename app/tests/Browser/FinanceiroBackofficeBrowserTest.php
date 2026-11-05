<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Domain\Cobranca\MeioPagamento;
use App\Domain\Cobranca\PaymentProvider;
use App\Infrastructure\Cobranca\Fake\FakePaymentProvider;
use App\Models\CobrancaConfig;
use App\Models\Invoice;
use App\Models\LoteCredito;
use App\Models\PacoteCredito;
use App\Models\TrialConfig;
use App\Models\Usuario;
use App\Services\Cobranca\IniciarCompraDeCreditos;
use App\Services\Cobranca\ReceberWebhook;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\Artisan;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * STORY-085 — o admin com `financeiro.gerenciar` chega pelo menu, vê a cobrança paga com o sinal de
 * arrependimento, reembolsa com motivo e confirmação (o provedor é o fake) e marca a nota fiscal de outra.
 * Quem não tem a permissão não vê o item nem abre a tela. NÃO está no grupo `smoke`: faz writes.
 */
final class FinanceiroBackofficeBrowserTest extends DuskTestCase
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

    private function compraPaga(Usuario $comprador): Invoice
    {
        $invoice = app(IniciarCompraDeCreditos::class)->executar(
            $comprador,
            PacoteCredito::query()->where('codigo', 'plus')->firstOrFail(),
            null,
            null,
            MeioPagamento::Cartao,
        );

        $provedor = app(PaymentProvider::class);
        assert($provedor instanceof FakePaymentProvider);
        app(ReceberWebhook::class)->tratar($provedor->simularPagamento((string) $invoice->checkout_id_externo));
        Artisan::call('queue:work', ['--stop-when-empty' => true, '--tries' => 1, '--quiet' => true]);

        return $invoice->fresh();
    }

    public function test_admin_reembolsa_com_motivo_e_confirmacao_e_marca_a_nota_fiscal(): void
    {
        $comprador = Usuario::factory()->create(['nome' => 'Cliente Reembolso']);
        $reembolsada = $this->compraPaga($comprador);
        $comNota = $this->compraPaga(Usuario::factory()->create(['nome' => 'Cliente Nota']));
        $admin = Usuario::factory()->create();
        $admin->forceFill(['permissoes_admin' => ['financeiro.gerenciar']])->save();

        $this->browse(function (Browser $browser) use ($admin, $reembolsada, $comNota) {
            $browser->loginAs($admin)
                ->visit('/home')
                ->waitFor('@app-nav-admin')
                ->assertMissing('@app-nav-backoffice-planos')
                ->click('@app-nav-backoffice-financeiro')
                ->waitForLocation('/backoffice/financeiro')
                ->waitFor('@financeiro-cobrancas')
                ->assertSeeIn('@cobranca-arrependimento-'.$reembolsada->id, 'Cabe arrependimento')
                ->assertSeeIn('@cobranca-arrependimento-'.$reembolsada->id, 'Sem análise desde a contratação')
                // Filtro por usuário.
                ->type('@fin-busca', 'Cliente Reembolso')
                ->press('@fin-filtrar')
                ->waitUntilMissing('@cobranca-'.$comNota->id)
                ->assertPresent('@cobranca-'.$reembolsada->id)
                // Reembolso: motivo obrigatório, depois confirmação.
                ->press('@cobranca-reembolsar-'.$reembolsada->id)
                ->waitFor('@reembolso-painel')
                ->assertSeeIn('@reembolso-previa', 'Nenhum dos')
                ->press('@reembolso-confirmar')
                ->waitFor('@confirmacao-confirmar')
                ->press('@confirmacao-confirmar')
                ->waitFor('@erro-reembolso-motivo')
                ->assertSeeIn('@erro-reembolso-motivo', 'Informe o motivo do reembolso.')
                ->type('@reembolso-motivo', 'Arrependimento em 7 dias')
                ->press('@reembolso-confirmar')
                ->waitFor('@confirmacao-confirmar')
                ->press('@confirmacao-confirmar')
                ->waitFor('@financeiro-mensagem')
                ->assertSeeIn('@financeiro-mensagem', 'Reembolso confirmado pelo provedor')
                ->assertSeeIn('@cobranca-status-'.$reembolsada->id, 'Estornada')
                ->assertMissing('@cobranca-reembolsar-'.$reembolsada->id)
                // Nota fiscal da outra cobrança.
                ->press('@fin-limpar')
                ->waitFor('@sem-nota-'.$comNota->id)
                ->assertMissing('@sem-nota-'.$reembolsada->id)
                ->press('@sem-nota-marcar-'.$comNota->id)
                ->waitFor('@nota-painel')
                ->type('@nota-numero', 'NF-123')
                ->press('@nota-confirmar')
                ->waitUntilMissing('@sem-nota-'.$comNota->id)
                ->assertSeeIn('@cobranca-nota-'.$comNota->id, 'nº NF-123');
        });

        $this->assertSame(0, LoteCredito::query()->where('invoice_id', $reembolsada->id)->value('quantidade_restante'));
        $this->assertSame('Arrependimento em 7 dias', $reembolsada->fresh()->reembolso_motivo);
        $this->assertSame('NF-123', $comNota->fresh()->nf_numero);
    }

    public function test_quem_nao_tem_a_permissao_nao_ve_o_item_nem_abre_a_tela(): void
    {
        $outro = Usuario::factory()->create();
        $outro->forceFill(['permissoes_admin' => ['planos.gerenciar']])->save();

        $this->browse(function (Browser $browser) use ($outro) {
            $browser->loginAs($outro)
                ->visit('/home')
                ->waitFor('@app-nav-admin')
                ->assertMissing('@app-nav-backoffice-financeiro')
                ->visit('/backoffice/financeiro')
                ->assertDontSee('Pagas sem nota fiscal');
        });
    }
}

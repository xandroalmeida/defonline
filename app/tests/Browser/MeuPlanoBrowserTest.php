<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Domain\Cobranca\CicloCobranca;
use App\Domain\Cobranca\MeioPagamento;
use App\Domain\Cobranca\PaymentProvider;
use App\Domain\Cobranca\StatusAssinatura;
use App\Infrastructure\Cobranca\Fake\FakePaymentProvider;
use App\Models\Assinatura;
use App\Models\CobrancaConfig;
use App\Models\Plano;
use App\Models\TrialConfig;
use App\Models\TrocaDeCartao;
use App\Models\Usuario;
use App\Services\Cobranca\IniciarAssinatura;
use App\Services\Cobranca\ReceberWebhook;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\Artisan;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * STORY-081 CA-9 — Meu plano de ponta a ponta: cancelar e reativar a renovação, trocar o cartão (em dia e
 * em atraso) pela "página do provedor" fake, a oferta para quem não tem nada e o "Logar como" somente leitura.
 * O webhook vai pela fila, que o teste esvazia (não há worker no Dusk).
 *
 * NÃO está no grupo `smoke` por design — faz writes.
 */
final class MeuPlanoBrowserTest extends DuskTestCase
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

    /** Assinante mensal com o pagamento aprovado no provedor fake. */
    private function assinante(): Usuario
    {
        $usuario = Usuario::factory()->create();
        $invoice = app(IniciarAssinatura::class)->executar($usuario, Plano::query()->where('nome', 'Básico')->firstOrFail(), CicloCobranca::Mensal, '203.0.113.7', 'Dusk');
        $fake = app(PaymentProvider::class);
        $this->assertInstanceOf(FakePaymentProvider::class, $fake);
        app(ReceberWebhook::class)->tratar($fake->simularPagamento((string) $invoice->checkout_id_externo));
        $this->processarFila();

        $this->assertSame(1, Assinatura::query()->where('usuario_id', $usuario->id)->count(), 'a assinatura devia estar ativa');

        return $usuario;
    }

    public function test_cancelar_e_reativar_a_renovacao_em_mobile(): void
    {
        $usuario = $this->assinante();
        $assinatura = Assinatura::query()->where('usuario_id', $usuario->id)->sole();
        $fim = $assinatura->proximo_vencimento->setTimezone('America/Sao_Paulo')->format('d/m/Y');

        $this->browse(function (Browser $browser) use ($usuario, $fim) {
            $browser->resize(360, 800)->loginAs($usuario)
                ->visit('/meu-plano')
                ->waitFor('@screen-meuplano-plano')
                ->assertSeeIn('@screen-meuplano-situacao', 'Ativo')
                ->assertSeeIn('@screen-meuplano-plano', 'Básico · mensal')
                ->assertSeeIn('@screen-meuplano-renovacao', 'renova em '.$fim)
                ->assertSeeIn('@screen-meuplano-cartao', 'Visa final 4242')
                ->assertSeeIn('@screen-meuplano-cota', 'restante')
                ->assertSeeIn('@screen-meuplano-cobrancas', 'Plano Básico · mensal')
                ->assertSeeIn('@screen-meuplano-cobrancas', 'Pago')
                ->assertSeeIn('@screen-meuplano-arrependimento', 'eb@ebparcerias.com')
                // Cancelar está na primeira dobra e a página não rola de lado.
                ->assertVisible('@screen-meuplano-cancelar');
            $this->assertLessThanOrEqual(0, $browser->script('return document.documentElement.scrollWidth - document.documentElement.clientWidth;')[0]);
            $this->assertLessThan(800, $browser->script("return document.querySelector('[dusk=screen-meuplano-cancelar]').getBoundingClientRect().bottom;")[0], 'Cancelar renovação fora da primeira dobra');

            // Um passo, um diálogo: Voltar não muda nada.
            $browser->click('@screen-meuplano-cancelar')
                ->waitFor('@confirmacao-confirmar')
                ->assertSeeIn('@confirmacao-mensagem', "Você continua com acesso até {$fim} e não será cobrado de novo.")
                ->assertSeeIn('@confirmacao-cancelar', 'Voltar')
                ->click('@confirmacao-cancelar')
                ->assertVisible('@screen-meuplano-cancelar')
                ->click('@screen-meuplano-cancelar')
                ->waitFor('@confirmacao-confirmar')
                ->click('@confirmacao-confirmar')
                ->waitFor('@screen-meuplano-reativar')
                ->assertSeeIn('@screen-meuplano-resultado', "Renovação cancelada. Seu acesso continua até {$fim}.")
                ->assertSeeIn('@screen-meuplano-situacao', 'Cancelado')
                ->assertSeeIn('@screen-meuplano-acesso-ate', "Seu acesso continua até {$fim}. Não haverá nova cobrança.")
                ->assertMissing('@screen-meuplano-cancelar')
                ->assertMissing('@screen-meuplano-trocar-cartao');

            $this->assertSame(StatusAssinatura::Cancelada, Assinatura::query()->where('usuario_id', $usuario->id)->sole()->status);

            // Reativar antes do fim do ciclo.
            $browser->click('@screen-meuplano-reativar')
                ->waitFor('@screen-meuplano-cancelar')
                ->assertSeeIn('@screen-meuplano-resultado', "Renovação reativada. Próxima cobrança em {$fim}.")
                ->assertSeeIn('@screen-meuplano-situacao', 'Ativo');
        });

        $this->assertSame(StatusAssinatura::Ativa, Assinatura::query()->where('usuario_id', $usuario->id)->sole()->status);
    }

    public function test_trocar_o_cartao_em_desktop(): void
    {
        $usuario = $this->assinante();
        $antiga = Assinatura::query()->where('usuario_id', $usuario->id)->sole()->id_externo;

        $this->browse(function (Browser $browser) use ($usuario) {
            $browser->resize(1280, 900)->loginAs($usuario)
                ->visit('/meu-plano')
                ->waitFor('@screen-meuplano-trocar-cartao')
                ->click('@screen-meuplano-trocar-cartao')
                ->waitFor('@screen-meuplano-salvar-cartao')
                ->assertSeeIn('@screen-meuplano-troca-cartao', 'O cartão atual só é desativado depois que o novo for confirmado')
                ->click('@screen-meuplano-salvar-cartao')
                ->waitFor('@pagamento-fake')
                ->assertPathBeginsWith('/pagamentos/fake/troca/')
                ->click('@pagamento-fake-pagar')
                ->waitForLocation('/meu-plano');

            $this->processarFila();

            $browser->waitFor('@screen-meuplano-troca-concluida', 15)
                ->assertSeeIn('@screen-meuplano-troca-concluida', 'Cartão atualizado.')
                ->assertSeeIn('@screen-meuplano-situacao', 'Ativo');
        });

        $nova = Assinatura::query()->where('usuario_id', $usuario->id)->sole()->id_externo;
        $this->assertNotSame($antiga, $nova);
        $this->assertSame('cancelada', FakePaymentProvider::situacaoDaAssinatura((string) $antiga));
        $this->assertSame('ativa', FakePaymentProvider::situacaoDaAssinatura((string) $nova));
    }

    public function test_assinatura_em_atraso_e_cobrada_de_novo_com_o_cartao_novo(): void
    {
        $usuario = $this->assinante();
        Assinatura::query()->where('usuario_id', $usuario->id)->sole()->forceFill(['status' => StatusAssinatura::EmAtraso])->save();

        $this->browse(function (Browser $browser) use ($usuario) {
            $browser->resize(1280, 900)->loginAs($usuario)
                ->visit('/meu-plano')
                ->waitFor('@screen-meuplano-aviso')
                ->assertSeeIn('@screen-meuplano-aviso', 'Atualize o cartão para manter o acesso.')
                ->assertSeeIn('@screen-meuplano-situacao', 'Em atraso')
                ->assertVisible('@screen-meuplano-cancelar')
                ->click('@screen-meuplano-trocar-cartao')
                ->waitFor('@screen-meuplano-salvar-cartao')
                ->assertSeeIn('@screen-meuplano-salvar-cartao', 'Salvar e cobrar agora')
                ->click('@screen-meuplano-salvar-cartao')
                ->waitFor('@pagamento-fake-pagar')
                ->click('@pagamento-fake-pagar')
                ->waitForLocation('/meu-plano');

            $this->processarFila();

            $browser->waitUntilMissing('@screen-meuplano-troca-aguardando', 15)
                ->waitFor('@screen-meuplano-troca-concluida', 15)
                ->assertSeeIn('@screen-meuplano-situacao', 'Ativo')
                ->assertMissing('@screen-meuplano-aviso');
        });

        $this->assertSame(StatusAssinatura::Ativa, Assinatura::query()->where('usuario_id', $usuario->id)->sole()->status);
        $this->assertSame(2, Assinatura::query()->where('usuario_id', $usuario->id)->sole()->periodos_pagos);
    }

    public function test_cancelar_a_renovacao_em_atraso_encerra_o_acesso_na_hora(): void
    {
        $usuario = $this->assinante();
        $assinatura = Assinatura::query()->where('usuario_id', $usuario->id)->sole();
        $assinatura->forceFill(['status' => StatusAssinatura::EmAtraso])->save();
        $fim = $assinatura->proximo_vencimento->setTimezone('America/Sao_Paulo')->format('d/m/Y');

        $this->browse(function (Browser $browser) use ($usuario, $fim) {
            $browser->resize(360, 800)->loginAs($usuario)
                ->visit('/meu-plano')
                ->waitFor('@screen-meuplano-cancelar')
                ->assertSeeIn('@screen-meuplano-situacao', 'Em atraso')
                ->click('@screen-meuplano-cancelar')
                ->waitFor('@confirmacao-confirmar')
                ->assertSeeIn('@confirmacao-mensagem', "Como o ciclo de {$fim} não foi pago, o acesso é encerrado agora, não no fim do ciclo.")
                ->assertSeeIn('@confirmacao-cancelar', 'Voltar')
                // Voltar não muda nada.
                ->click('@confirmacao-cancelar')
                ->assertVisible('@screen-meuplano-cancelar')
                ->click('@screen-meuplano-cancelar')
                ->waitFor('@confirmacao-confirmar')
                ->click('@confirmacao-confirmar')
                ->waitUntilMissing('@screen-meuplano-cancelar')
                ->assertSeeIn('@screen-meuplano-resultado', 'o acesso foi encerrado agora e nada mais será cobrado')
                ->assertSeeIn('@screen-meuplano-situacao', 'Encerrado')
                ->assertSeeIn('@screen-meuplano-encerrado', "Seu plano terminou em {$fim}.")
                ->assertSeeIn('@screen-meuplano-cobrancas', 'Plano Básico · mensal')
                ->assertMissing('@screen-meuplano-trocar-cartao');
            $this->assertLessThanOrEqual(0, $browser->script('return document.documentElement.scrollWidth - document.documentElement.clientWidth;')[0]);
        });

        $a = Assinatura::query()->where('usuario_id', $usuario->id)->sole();
        $this->assertSame(StatusAssinatura::Expirada, $a->status);
        $this->assertNotNull($a->encerrada_em);
        $this->assertSame('cancelada', FakePaymentProvider::situacaoDaAssinatura((string) $a->id_externo));
    }

    public function test_plano_anual_com_pix_avisa_nos_ultimos_dias_e_depois_do_fim_leva_a_renovacao(): void
    {
        $usuario = Usuario::factory()->create();
        $invoice = app(IniciarAssinatura::class)->executar($usuario, Plano::query()->where('nome', 'Básico')->firstOrFail(), CicloCobranca::Anual, '203.0.113.7', 'Dusk', MeioPagamento::Pix);
        $fake = app(PaymentProvider::class);
        $this->assertInstanceOf(FakePaymentProvider::class, $fake);
        app(ReceberWebhook::class)->tratar($fake->simularPagamento((string) $invoice->checkout_id_externo));
        $this->processarFila();
        $assinatura = Assinatura::query()->where('usuario_id', $usuario->id)->sole();
        $assinatura->forceFill(['proximo_vencimento' => now()->addDays(3)])->save();
        $fim = $assinatura->fresh()->proximo_vencimento->setTimezone('America/Sao_Paulo')->format('d/m/Y');

        $this->browse(function (Browser $browser) use ($usuario, $assinatura, $fim) {
            $browser->resize(360, 800)->loginAs($usuario)
                ->visit('/meu-plano')
                ->waitFor('@screen-meuplano-aviso')
                ->assertSeeIn('@screen-meuplano-aviso', "Seu plano anual termina em {$fim}. Pague a renovação com Pix")
                ->assertSeeIn('@screen-meuplano-situacao', 'Ativo')
                ->assertVisible('@screen-meuplano-renovar-pix')
                ->assertMissing('@screen-meuplano-cancelar')
                ->assertMissing('@screen-meuplano-trocar-cartao');
            $this->assertLessThanOrEqual(0, $browser->script('return document.documentElement.scrollWidth - document.documentElement.clientWidth;')[0]);

            $browser->click('@screen-meuplano-renovar-pix')->waitForLocation('/assinar/renovar');

            // Depois do fim: Encerrado, com o caminho de volta.
            $assinatura->forceFill(['proximo_vencimento' => now()->subHour()])->save();
            $browser->visit('/meu-plano')
                ->waitFor('@screen-meuplano-encerrado')
                ->assertSeeIn('@screen-meuplano-situacao', 'Encerrado')
                ->assertSeeIn('@screen-meuplano-encerrado', 'Seus diagnósticos continuam disponíveis.')
                ->assertSeeIn('@screen-meuplano-renovar-pix', 'Renovar com Pix')
                ->assertSeeIn('@screen-meuplano-cobrancas', 'Plano Básico')
                ->assertMissing('@screen-meuplano-cancelar');
        });
    }

    public function test_cartao_recusado_na_troca_mantem_o_anterior(): void
    {
        $usuario = $this->assinante();
        $antiga = Assinatura::query()->where('usuario_id', $usuario->id)->sole()->id_externo;

        $this->browse(function (Browser $browser) use ($usuario) {
            $browser->resize(1280, 900)->loginAs($usuario)
                ->visit('/meu-plano')
                ->waitFor('@screen-meuplano-trocar-cartao')
                ->click('@screen-meuplano-trocar-cartao')
                ->waitFor('@screen-meuplano-salvar-cartao')
                ->click('@screen-meuplano-salvar-cartao')
                ->waitFor('@pagamento-fake-recusar')
                ->click('@pagamento-fake-recusar')
                ->waitForLocation('/meu-plano');

            $this->processarFila();

            $browser->waitFor('@screen-meuplano-troca-falhou', 15)
                ->assertSeeIn('@screen-meuplano-troca-falhou', 'O cartão anterior continua valendo.');
        });

        $this->assertSame($antiga, Assinatura::query()->where('usuario_id', $usuario->id)->sole()->id_externo);
        $this->assertSame('falhou', TrocaDeCartao::query()->sole()->status);
    }

    public function test_quem_nao_tem_plano_nem_creditos_ve_a_oferta(): void
    {
        $usuario = Usuario::factory()->create();

        $this->browse(function (Browser $browser) use ($usuario) {
            $browser->resize(360, 800)->loginAs($usuario)
                ->visit('/meu-plano')
                ->waitFor('@screen-meuplano-oferta')
                ->assertSee('Você ainda não tem um plano')
                ->assertVisible('@screen-meuplano-oferta-teste')
                ->assertSeeIn('@screen-meuplano-cobrancas', 'Você ainda não fez nenhuma compra.')
                ->assertMissing('@screen-meuplano-cancelar');
        });
    }

    public function test_admin_em_logar_como_so_le(): void
    {
        $equipe = Usuario::factory()->create(['nome' => 'Equipe EBP', 'email' => 'equipe@ebparcerias.com.br']);
        $alvo = $this->assinante();

        $this->browse(function (Browser $browser) use ($equipe, $alvo) {
            $browser->resize(1280, 900)->loginAs($equipe)
                ->visit('/backoffice/usuarios')
                ->waitFor('@backoffice-usuarios')
                ->press('@backoffice-logar-como-'.$alvo->id)
                ->waitForLocation('/home')
                ->visit('/meu-plano')
                ->waitFor('@screen-meuplano-admin')
                ->assertSeeIn('@screen-meuplano-admin', 'Aqui é só leitura.')
                ->assertSeeIn('@screen-meuplano-plano', 'Básico · mensal');

            foreach (['cancelar', 'trocar-cartao'] as $botao) {
                $this->assertTrue($browser->script("return document.querySelector('[dusk=screen-meuplano-{$botao}]').disabled;")[0], "{$botao} devia estar desabilitado");
            }
        });

        $this->assertSame(StatusAssinatura::Ativa, Assinatura::query()->where('usuario_id', $alvo->id)->sole()->status);
    }
}

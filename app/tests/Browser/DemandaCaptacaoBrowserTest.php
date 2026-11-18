<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Models\CaptacaoConfig;
use App\Models\DemandaCaptacao;
use App\Models\EmpresaAnalisada;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * STORY-102 / PDR-021 — demanda de captação no navegador real: a autorização vem marcada no bloco 4,
 * o resultado confirma o prazo e a equipe vê a demanda e cadastra o e-mail no backoffice.
 *
 * Escreve no banco (fora do grupo `smoke` por design).
 */
final class DemandaCaptacaoBrowserTest extends DuskTestCase
{
    use DatabaseTruncation;

    /** @var list<string> */
    protected array $tablesToTruncate = [
        'demanda_captacao_eventos', 'demandas_captacao', 'quiz_rascunhos', 'diagnosticos', 'empresas_analisadas', 'usuarios',
        'audit_logs', 'business_metrics', 'term_acceptances', 'sessions', 'evento_produto', 'cache',
    ];

    public function test_autoriza_por_padrao_confirma_o_prazo_e_aparece_no_backoffice(): void
    {
        CaptacaoConfig::atual()->update(['email' => null]);
        $usuario = Usuario::factory()->create(['nome' => 'Roberto Souza']);
        $empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $usuario->id, 'razao_social' => 'Indústria Teste Ltda', 'nome_fantasia' => 'Teste']);
        $admin = Usuario::factory()->create();
        $admin->forceFill(['permissoes_admin' => ['captacao.gerenciar']])->save();

        $this->browse(function (Browser $browser) use ($usuario, $empresa, $admin) {
            $this->preencherAteBloco4($browser->loginAs($usuario), $empresa)
                ->click('@quiz-Q17-sim')
                ->waitFor('@quiz-captacao-contato')
                ->assertChecked('@quiz-captacao-autoriza')
                ->assertSeeIn('@quiz-captacao-prazo', '5 dias úteis')
                // Desmarcar esconde o prazo; marcar de novo o traz de volta.
                ->uncheck('@quiz-captacao-autoriza')
                ->waitUntilMissing('@quiz-captacao-prazo')
                ->check('@quiz-captacao-autoriza')
                ->waitFor('@quiz-captacao-prazo')
                ->type('@quiz-Q18', '5000000')
                ->type('@quiz-Q19', '2000000')
                ->type('@quiz-Q20', '1000000')
                ->type('@quiz-socio-0-nome', 'Ana Lima')
                ->type('@quiz-socio-0-cpf', '52998224725')
                ->pause(600)
                ->click('@quiz-calcular')
                ->waitFor('@diagnostico-mvp-badge')
                ->assertSeeIn('@diagnostico-demanda-captacao', 'Recebemos sua demanda de captação')
                ->assertSeeIn('@diagnostico-demanda-captacao', '5 dias úteis');

            $demanda = DemandaCaptacao::query()->sole();
            $id = $demanda->id;

            $browser->loginAs($admin)
                ->visit(route('backoffice.captacao'))
                ->waitFor('@captacao-quadro')
                ->assertSeeIn('@captacao-email-situacao', 'sem e-mail cadastrado')
                ->type('@captacao-email', 'captacao@exemplo.com.br')
                ->pause(600)
                ->click('@captacao-salvar')
                ->waitFor('@captacao-salvo')
                ->assertSeeIn('@captacao-email-situacao', 'captacao@exemplo.com.br')
                ->assertSeeIn('@captacao-coluna-nova', 'Teste')
                ->assertSeeIn('@captacao-coluna-nova', 'R$ 50.000,00');

            // Arrastar e soltar (eventos HTML5 de verdade, que o WebDriver não gera sozinho).
            $browser->script(<<<JS
                const dt = new DataTransfer();
                const cartao = document.querySelector('[dusk="captacao-cartao-{$id}"]');
                const coluna = document.querySelector('[dusk="captacao-coluna-em_analise"]');
                cartao.dispatchEvent(new DragEvent('dragstart', { dataTransfer: dt, bubbles: true }));
                coluna.dispatchEvent(new DragEvent('dragover', { dataTransfer: dt, bubbles: true, cancelable: true }));
                coluna.dispatchEvent(new DragEvent('drop', { dataTransfer: dt, bubbles: true, cancelable: true }));
                JS);

            $browser->waitUsing(5, 100, fn () => $browser->element('[dusk="captacao-coluna-em_analise"] [dusk="captacao-cartao-'.$id.'"]') !== null)
                // Sem arrastar: o botão leva para a próxima etapa.
                ->click('@captacao-avancar-'.$id)
                ->waitUsing(5, 100, fn () => $browser->element('[dusk="captacao-coluna-respondida"] [dusk="captacao-cartao-'.$id.'"]') !== null)
                ->click('@captacao-abrir-'.$id)
                ->waitFor('@captacao-detalhe')
                ->assertSeeIn('@captacao-detalhe', 'Roberto Souza')
                ->select('@captacao-detalhe-responsavel', $admin->id)
                ->waitForTextIn('@captacao-historico', 'Atribuiu a')
                ->type('@captacao-nota', 'Liguei e enviei a proposta.')
                ->click('@captacao-nota-salvar')
                ->waitForTextIn('@captacao-historico', 'Liguei e enviei a proposta.')
                ->assertSeeIn('@captacao-historico', 'Moveu de Em análise para Respondida.')
                ->assertSeeIn('@captacao-historico', 'Moveu de Nova para Em análise.')
                ->click('@captacao-detalhe-fechar')
                ->waitUntilMissing('@captacao-detalhe');
        });

        $demanda = DemandaCaptacao::query()->sole();
        $this->assertTrue($demanda->autoriza_contato);
        $this->assertSame('respondida', $demanda->status->value);
        $this->assertSame($admin->id, $demanda->responsavel_id);
        $this->assertSame('captacao@exemplo.com.br', CaptacaoConfig::emailDaEquipe());
    }

    /** Preenche os blocos 1 a 3 com valores saudáveis e para no bloco 4 (Q17 em branco). */
    private function preencherAteBloco4(Browser $browser, EmpresaAnalisada $empresa): Browser
    {
        return $browser
            ->visit(route('diagnosticos.novo', $empresa))
            ->resize(1280, 900)
            ->waitFor('@quiz-bloco-1')
            ->click('@quiz-proximo')
            ->waitFor('@quiz-bloco-2')
            ->type('@quiz-Q08', '5000000')
            ->type('@quiz-Q09', '10000000')
            ->type('@quiz-Q14', '2000000')
            ->type('@quiz-Q15', '800000')
            ->type('@quiz-Q16', '200000')
            ->type('@quiz-Q10', '90')
            ->type('@quiz-Q11', '15')
            ->type('@quiz-Q12', '15')
            ->type('@quiz-Q13', '2')
            ->pause(600)
            ->click('@quiz-proximo')
            ->waitFor('@quiz-bloco-3')
            ->type('@quiz-Q02', '5000000')
            ->type('@quiz-Q03', '2000000')
            ->type('@quiz-Q04', '1000000')
            ->type('@quiz-Q05', '30000000')
            ->type('@quiz-Q06', '4000000')
            ->type('@quiz-Q07', '8000000')
            ->pause(600)
            ->click('@quiz-proximo')
            ->waitFor('@quiz-bloco-4');
    }
}

<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Models\EmpresaAnalisada;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * STORY-042 CA-10 — pacote de conteúdo EBC no navegador real.
 *
 * O Pest (`PacoteConteudoEbcTest`) já compara strings no HTML renderizado. O
 * que só o browser prova é o que o **Roberto realmente lê**: `assertDontSee` do
 * Dusk olha texto **visível**, então um texto que sobrevivesse escondido atrás
 * de um `display:none` (o caso dos boxes explicativos) não confunde o resultado,
 * e um texto que quebrasse o layout apareceria na navegação real dos 4 blocos.
 *
 * Percorre o quiz inteiro (Bloco 1 → 4), submete e confere a ordem do glossário
 * no relatório gerado. Escreve no banco — fora do grupo `smoke` por design.
 */
final class PacoteConteudoEbcBrowserTest extends DuskTestCase
{
    use DatabaseTruncation;

    /** @var list<string> */
    protected array $tablesToTruncate = [
        'quiz_rascunhos', 'diagnosticos', 'empresas_analisadas', 'usuarios',
        'audit_logs', 'business_metrics', 'term_acceptances', 'sessions',
        'evento_produto', 'cache',
    ];

    public function test_percorre_o_quiz_inteiro_com_os_textos_revisados_pela_ebc(): void
    {
        $usuario = Usuario::factory()->create(['nome' => 'Roberto Souza']);
        $empresa = EmpresaAnalisada::factory()->create([
            'usuario_id' => $usuario->id,
            'razao_social' => 'Indústria Teste Ltda',
            'nome_fantasia' => 'Teste',
        ]);

        $this->browse(function (Browser $browser) use ($usuario, $empresa) {
            $browser->resize(1280, 900)
                ->loginAs($usuario)
                ->visit(route('diagnosticos.novo', $empresa))

                // ---------- Bloco 1 ----------
                ->waitFor('@quiz-bloco-1')
                ->assertSee('Bloco 1 de 4 — Identificação')
                ->click('@quiz-proximo')

                // ---------- Bloco 2 (CA-1, CA-2, CA-3) ----------
                ->waitFor('@quiz-bloco-2')
                // Default vencido da pendência: o título do Bloco 2 não mudou.
                ->assertSee('Bloco 2 de 4 — DRE e Operação')
                ->assertSee('Médias mensais dos últimos 12 meses.')
                ->assertDontSee('anualiza')
                ->assertSee('Folha de Pagamento, Encargos sociais, contador, aluguel, condomínio, energia, água, telefone, internet, franquia, retirada de sócios, etc.')
                ->assertSee('Fretes, comissões, tributos, despesas de vendas, outras despesas variáveis, etc.')
                // Máscara "centavos": os dígitos entram como centavos.
                ->type('@quiz-Q08', '5000000')    // 50.000,00
                ->type('@quiz-Q09', '10000000')   // 100.000,00
                ->type('@quiz-Q14', '2000000')    // 20.000,00
                ->type('@quiz-Q15', '800000')     // 8.000,00
                ->type('@quiz-Q16', '200000')     // 2.000,00
                ->type('@quiz-Q10', '90')
                ->type('@quiz-Q11', '15')
                ->type('@quiz-Q12', '15')
                ->type('@quiz-Q13', '2')
                ->pause(600)                      // wire:model.live (debounce 300ms)
                ->click('@quiz-proximo')

                // ---------- Bloco 3 (CA-4, CA-5) ----------
                ->waitFor('@quiz-bloco-3')
                ->assertSee('Bloco 3 de 4 — Saldos Atuais – uma foto da situação econômica do negócio hoje')
                ->assertDontSee('Bloco 3 de 4 — Balanço')
                ->assertSee('Imóveis, equipamentos e veículos livres de ônus – valor estimado para venda rápida')
                ->assertDontSee('Imóveis, equipamentos, veículos — valor de venda rápida.')
                // Balanço consistente de propósito: o gate de validações cruzadas
                // (STORY-034) não pode disparar e roubar a navegação deste teste.
                ->type('@quiz-Q02', '5000000')    // 50.000,00
                ->type('@quiz-Q03', '2000000')    // 20.000,00
                ->type('@quiz-Q04', '1000000')    // 10.000,00
                ->type('@quiz-Q05', '30000000')   // 300.000,00
                ->type('@quiz-Q06', '4000000')    // 40.000,00
                ->type('@quiz-Q07', '8000000')    // 80.000,00
                ->pause(600)
                ->click('@quiz-proximo')

                // ---------- Bloco 4 (CA-6) ----------
                ->waitFor('@quiz-bloco-4')
                ->assertSee('Bloco 4 de 4 — Contexto e Captação')
                // Só com "Sim" o Q20 entra no DOM — com "Não" o assertDontSee
                // abaixo passaria sem provar nada.
                ->click('@quiz-Q17-sim')
                ->waitFor('@quiz-bloco-4-captacao')
                // Sanidade pelo seletor, não pelo rótulo: `.label` é `text-transform:
                // uppercase`, e o `getText()` do WebDriver devolve o texto **renderizado**
                // — comparar com o rótulo em caixa mista falharia por CSS, não por conteúdo.
                ->assertVisible('@quiz-Q20')
                ->assertDontSee('Base de cálculo da capacidade de garantia (3× esse valor)')
                ->assertDontSee('Deve ser inferior à venda mensal total (Q09)')
                // Volta para "Não" para submeter sem o bloco de captação.
                ->click('@quiz-Q17-nao')
                ->waitUntilMissing('@quiz-bloco-4-captacao')
                ->pause(400)
                ->click('@quiz-calcular')

                // ---------- Relatório (CA-8) ----------
                ->waitFor('@diagnostico-mvp-badge')
                ->scrollIntoView('[data-testid="glossario"]')
                ->assertSeeIn('[data-testid="glossario"] details:nth-of-type(2) summary', 'EBITDA / Margem Operacional')
                // O 1º item não mudou — reordenação, não reescrita da lista.
                ->assertSeeIn('[data-testid="glossario"] details:nth-of-type(1) summary', 'Margem Bruta (MB) / Lucro Bruto (LB)')
                // Pendência diferida: nada foi removido do glossário.
                ->assertSee('Farol / Semáforo');
        });
    }
}

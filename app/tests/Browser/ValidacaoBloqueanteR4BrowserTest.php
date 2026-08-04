<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Models\Diagnostico;
use App\Models\EmpresaAnalisada;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * STORY-043 CA-8 — a R4 bloqueante em navegador real.
 *
 * Percorre o quiz inteiro, marca captação, informa recebíveis (Q20) acima da
 * venda mensal (Q09) e tenta calcular o diagnóstico: o bloco de erro aparece
 * acima do botão, sem "Continuar mesmo assim", e nenhum diagnóstico é gerado.
 * Depois corrige o valor no mesmo formulário e conclui — provando que o
 * bloqueio é uma parada, não um beco sem saída.
 *
 * Escreve no banco (fora do grupo `smoke` por design).
 */
final class ValidacaoBloqueanteR4BrowserTest extends DuskTestCase
{
    use DatabaseTruncation;

    /** @var list<string> */
    protected array $tablesToTruncate = [
        'quiz_rascunhos', 'diagnosticos', 'empresas_analisadas', 'usuarios',
        'audit_logs', 'business_metrics', 'term_acceptances', 'sessions',
        'evento_produto', 'cache',
    ];

    public function test_recebiveis_acima_da_venda_mensal_bloqueiam_o_calculo_e_corrigir_libera(): void
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
                // Bloco 1 → 2
                ->waitFor('@quiz-bloco-1')
                ->click('@quiz-proximo')
                // Bloco 2 (saudável): cents-mask interpreta dígitos como centavos.
                ->waitFor('@quiz-bloco-2')
                ->type('@quiz-Q08', '5000000')    // 50.000,00
                ->type('@quiz-Q09', '10000000')   // 100.000,00 — a venda mensal
                ->type('@quiz-Q14', '2000000')    // 20.000,00
                ->type('@quiz-Q15', '800000')     // 8.000,00
                ->type('@quiz-Q16', '200000')     // 2.000,00
                ->type('@quiz-Q10', '90')
                ->type('@quiz-Q11', '15')
                ->type('@quiz-Q12', '15')
                ->type('@quiz-Q13', '2')
                ->pause(600)                      // deixa o wire:model.live (debounce 300ms) sincronizar
                ->click('@quiz-proximo')
                // Bloco 3 (saudável): ativo > passivo, nenhuma R1/R2/R3 dispara.
                ->waitFor('@quiz-bloco-3')
                ->type('@quiz-Q02', '5000000')    // 50.000,00
                ->type('@quiz-Q03', '2000000')    // 20.000,00
                ->type('@quiz-Q04', '1000000')    // 10.000,00
                ->type('@quiz-Q05', '30000000')   // 300.000,00
                ->type('@quiz-Q06', '4000000')    // 40.000,00
                ->type('@quiz-Q07', '8000000')    // 80.000,00
                ->pause(600)
                ->click('@quiz-proximo')
                ->waitFor('@quiz-bloco-4')
                ->assertMissing('@quiz-alertas-cruzados')
                // Captação: recebíveis acima da venda mensal.
                ->click('@quiz-Q17-sim')
                ->waitFor('@quiz-bloco-4-captacao')
                ->type('@quiz-Q18', '5000000')    // 50.000,00
                ->type('@quiz-Q19', '2000000')    // 20.000,00
                ->type('@quiz-Q20', '15000000')   // 150.000,00 > Q09 → R4
                ->type('@quiz-socio-0-nome', 'Ana Lima')
                ->type('@quiz-socio-0-cpf', '52998224725')
                ->pause(600)
                ->click('@quiz-calcular')
                // Bloqueio: erro visível, sem escape, ainda no Bloco 4.
                ->waitFor('@quiz-bloqueios')
                ->assertVisible('@quiz-bloqueio-R4')
                // Texto invariante, não os valores: a máscara BRL tem um bug de
                // sincronização com o Livewire (BUG-001, ver Notas do agente da
                // STORY-043) que faz o componente receber 1/10 do que o campo
                // mostra. Não muda o veredito da R4 — muda os números impressos.
                // Amarrar o teste de browser aos valores travaria a correção do
                // bug; a conferência numérica exata vive no teste de feature.
                ->assertSeeIn('@quiz-bloqueios', 'é maior ou igual à venda mensal média')
                ->assertSeeIn('@quiz-bloqueios', 'deve ser inferior à venda mensal')
                ->assertMissing('@quiz-alertas-continuar')
                ->assertVisible('@quiz-bloco-4');

            $this->assertSame(0, Diagnostico::withoutGlobalScopes()->count());

            // Corrige o valor pelo botão de foco e conclui.
            $browser->click('@quiz-bloqueio-corrigir-R4')
                ->waitUntilMissing('@quiz-bloqueios')
                ->assertFocused('@quiz-Q20')
                ->clear('@quiz-Q20')
                ->type('@quiz-Q20', '2600000')    // 26.000,00 < Q09
                ->pause(600)
                ->click('@quiz-calcular')
                ->waitFor('@diagnostico-mvp-badge');
        });

        $diagnostico = Diagnostico::withoutGlobalScopes()
            ->where('empresa_analisada_id', $empresa->id)
            ->firstOrFail();

        $payload = (array) $diagnostico->quiz_payload;

        // A invariante que a R4 existe para garantir, conferida no payload que foi
        // ao motor — e não no valor absoluto, pelo motivo explicado acima.
        $this->assertLessThan((float) $payload['Q09'], (float) $payload['Q20']);

        // O bloqueio não deixou rastro na auditoria: não há o que "aceitar", então
        // a chave sequer é escrita (a action só a mescla quando há alerta aceito).
        $this->assertArrayNotHasKey('alertas_aceitos', $payload);
    }
}

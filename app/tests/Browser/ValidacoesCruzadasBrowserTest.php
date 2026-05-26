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
 * STORY-034 CA-7 — validações cruzadas DRE × Balanço no navegador real.
 *
 * Fluxo: preenche o quiz com um balanço inconsistente (passivo ≫ ativo → R3),
 * vê o banner não-bloqueante ao fim do Bloco 3, clica "Continuar mesmo assim",
 * gera o diagnóstico normalmente e confirma que `alertas_aceitos` foi gravado.
 *
 * Escreve no banco (fora do grupo `smoke` por design).
 */
final class ValidacoesCruzadasBrowserTest extends DuskTestCase
{
    use DatabaseTruncation;

    /** @var list<string> */
    protected array $tablesToTruncate = [
        'quiz_rascunhos', 'diagnosticos', 'empresas_analisadas', 'usuarios',
        'audit_logs', 'business_metrics', 'term_acceptances', 'sessions',
        'evento_produto', 'cache',
    ];

    public function test_banner_inconsistencia_aparece_continuar_gera_diagnostico_com_alertas_aceitos(): void
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
                ->type('@quiz-Q09', '10000000')   // 100.000,00
                ->type('@quiz-Q14', '2000000')    // 20.000,00
                ->type('@quiz-Q15', '800000')     // 8.000,00
                ->type('@quiz-Q16', '200000')     // 2.000,00
                ->type('@quiz-Q10', '90')
                ->type('@quiz-Q11', '15')
                ->type('@quiz-Q12', '15')
                ->type('@quiz-Q13', '2')
                ->pause(600)                       // deixa o wire:model.live (debounce 300ms) sincronizar
                ->click('@quiz-proximo')
                // Bloco 3 (inconsistente): passivo 200.000 ≫ ativo 4.000.
                ->waitFor('@quiz-bloco-3')
                ->type('@quiz-Q02', '100000')     // 1.000,00
                ->type('@quiz-Q03', '100000')
                ->type('@quiz-Q04', '100000')
                ->type('@quiz-Q05', '100000')
                ->type('@quiz-Q06', '10000000')   // 100.000,00
                ->type('@quiz-Q07', '10000000')
                ->pause(600)
                ->click('@quiz-proximo')
                // Gate: banner não-bloqueante aparece, ainda no Bloco 3.
                ->waitFor('@quiz-alertas-cruzados')
                ->assertVisible('@quiz-alerta-R3')
                ->assertSeeIn('@quiz-alertas-cruzados', 'passivo total')
                ->assertVisible('@quiz-bloco-3')
                // Continuar mesmo assim → avança para o Bloco 4.
                ->click('@quiz-alertas-continuar')
                ->waitFor('@quiz-bloco-4')
                ->assertMissing('@quiz-alertas-cruzados')
                // Q17 = não → submeter.
                ->click('@quiz-Q17-nao')
                ->pause(400)
                ->click('@quiz-calcular')
                ->waitFor('@diagnostico-mvp-badge');
        });

        $diagnostico = Diagnostico::withoutGlobalScopes()
            ->where('empresa_analisada_id', $empresa->id)
            ->firstOrFail();

        $payload = (array) $diagnostico->quiz_payload;

        $this->assertArrayHasKey('alertas_aceitos', $payload);
        $this->assertSame('R3', $payload['alertas_aceitos'][0]['regra']);
    }

    public function test_banner_exibe_r1_e_r2_juntas_e_corrigir_leva_ao_campo(): void
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
                ->waitFor('@quiz-bloco-1')
                ->click('@quiz-proximo')
                // Bloco 2: dispara R1 (Q16×12 > 2×Q06) e R2 (custos anuais > receita anual).
                ->waitFor('@quiz-bloco-2')
                ->type('@quiz-Q08', '3000000')    // 30.000,00
                ->type('@quiz-Q09', '5000000')    // 50.000,00 (receita)
                ->type('@quiz-Q14', '4000000')    // 40.000,00
                ->type('@quiz-Q15', '4000000')    // 40.000,00
                ->type('@quiz-Q16', '1000000')    // 10.000,00 (despesas financeiras)
                ->type('@quiz-Q10', '90')
                ->type('@quiz-Q11', '15')
                ->type('@quiz-Q12', '15')
                ->type('@quiz-Q13', '2')
                ->pause(600)
                ->click('@quiz-proximo')
                // Bloco 3: ativo ≫ passivo (R3 NÃO dispara).
                ->waitFor('@quiz-bloco-3')
                ->type('@quiz-Q02', '10000000')   // 100.000,00
                ->type('@quiz-Q03', '10000000')
                ->type('@quiz-Q04', '10000000')
                ->type('@quiz-Q05', '10000000')
                ->type('@quiz-Q06', '1000000')    // 10.000,00 (dívida baixa → R1 dispara)
                ->type('@quiz-Q07', '1000000')
                ->pause(600)
                ->click('@quiz-proximo')
                // Banner com R1 e R2; R3 ausente.
                ->waitFor('@quiz-alertas-cruzados')
                ->assertVisible('@quiz-alerta-R1')
                ->assertVisible('@quiz-alerta-R2')
                ->assertMissing('@quiz-alerta-R3')
                // "Corrigir" da R1 leva ao Bloco 2 (campo-foco Q16) e fecha o banner.
                ->click('@quiz-alerta-corrigir-R1')
                ->waitFor('@quiz-bloco-2')
                ->assertMissing('@quiz-alertas-cruzados');
        });
    }
}

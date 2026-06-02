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
 * STORY-039 CA-7 — inadimplência (Q13) com 2 casas decimais no navegador real.
 *
 * Regressão da devolutiva do Edvan: o campo `Inadimplência` recusava `3,46%`.
 * A máscara antiga era estilo "centavos" (digitar "100" virava "1,00"); a nova
 * é decimal natural (STORY-039). Aqui digitamos `3,46`, submetemos o quiz e
 * confirmamos que o diagnóstico foi gerado com `Q13 = "3.46"` no payload canônico.
 *
 * Escreve no banco (fora do grupo `smoke` por design).
 */
final class InadimplenciaDecimalBrowserTest extends DuskTestCase
{
    use DatabaseTruncation;

    /** @var list<string> */
    protected array $tablesToTruncate = [
        'quiz_rascunhos', 'diagnosticos', 'empresas_analisadas', 'usuarios',
        'audit_logs', 'business_metrics', 'term_acceptances', 'sessions',
        'evento_produto', 'cache',
    ];

    public function test_inadimplencia_3_46_e_aceita_e_gera_diagnostico(): void
    {
        $usuario = Usuario::factory()->create(['nome' => 'Edvan Tester']);
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
                // Bloco 2 (saudável). Campos R$ usam máscara "centavos"; o Q13
                // usa a máscara decimal natural (digita-se "3,46" literalmente).
                ->waitFor('@quiz-bloco-2')
                ->type('@quiz-Q08', '5000000')    // 50.000,00
                ->type('@quiz-Q09', '10000000')   // 100.000,00
                ->type('@quiz-Q14', '2000000')    // 20.000,00
                ->type('@quiz-Q15', '800000')     // 8.000,00
                ->type('@quiz-Q16', '200000')     // 2.000,00
                ->type('@quiz-Q10', '90')
                ->type('@quiz-Q11', '15')
                ->type('@quiz-Q12', '15')
                ->type('@quiz-Q13', '3,46')
                ->pause(600)                        // deixa o wire:model.live sincronizar
                ->assertInputValue('@quiz-Q13', '3,46')
                ->assertMissing('@quiz-erro-Q13')
                ->click('@quiz-proximo')
                // Sem erro no Q13 → avança para o Bloco 3.
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
                ->click('@quiz-Q17-nao')
                ->pause(400)
                ->click('@quiz-calcular')
                ->waitFor('@diagnostico-mvp-badge');
        });

        $diagnostico = Diagnostico::withoutGlobalScopes()
            ->where('empresa_analisada_id', $empresa->id)
            ->firstOrFail();

        $payload = (array) $diagnostico->quiz_payload;

        $this->assertSame('3.46', $payload['Q13']);
    }
}

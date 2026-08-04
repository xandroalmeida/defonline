<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Models\Diagnostico;
use App\Models\EmpresaAnalisada;
use App\Models\QuizRascunho;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * BUG-001 — regressão da sincronização máscara ↔ Livewire.
 *
 * As máscaras do quiz reescrevem `$el.value` dentro do handler de `input`, o que
 * não gera novo evento: o `wire:model.live` sincronizava o texto do último
 * keystroke **antes** da formatação. Resultado silencioso e grave — a tela
 * mostrava `100.000,00` e o componente guardava `10.000,000` (dez mil).
 *
 * Estes testes conferem o que só um browser real consegue provar: o que ficou
 * PERSISTIDO é igual ao que a tela MOSTRA, para cada tipo de máscara. Nenhuma
 * asserção aqui olha para a UI — a UI já estava certa quando o bug existia.
 *
 * Escreve no banco (fora do grupo `smoke` por design).
 */
final class MascaraSincronizacaoBrowserTest extends DuskTestCase
{
    use DatabaseTruncation;

    /** @var list<string> */
    protected array $tablesToTruncate = [
        'quiz_rascunhos', 'diagnosticos', 'empresas_analisadas', 'usuarios',
        'audit_logs', 'business_metrics', 'term_acceptances', 'sessions',
        'evento_produto', 'cache',
    ];

    public function test_valor_persistido_é_igual_ao_exibido_em_todas_as_mascaras_do_quiz(): void
    {
        $usuario = Usuario::factory()->create(['nome' => 'Roberto Souza']);
        $empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $usuario->id]);

        $this->browse(function (Browser $browser) use ($usuario, $empresa) {
            $browser->resize(1280, 900)
                ->loginAs($usuario)
                ->visit(route('diagnosticos.novo', $empresa))
                ->waitFor('@quiz-bloco-1')
                ->click('@quiz-proximo')
                ->waitFor('@quiz-bloco-2')
                // Máscara BRL: o último dígito é o que promove 10.000 → 100.000.
                ->type('@quiz-Q08', '5000000')     // 50.000,00
                ->type('@quiz-Q09', '10000000')    // 100.000,00
                ->type('@quiz-Q14', '2000000')     // 20.000,00
                ->type('@quiz-Q15', '800000')      // 8.000,00
                ->type('@quiz-Q16', '200000')      // 2.000,00
                // Máscara dias: o 5º dígito é descartado pela máscara (cap 4).
                ->type('@quiz-Q10', '12345')       // vira 1234
                ->type('@quiz-Q11', '15')
                ->type('@quiz-Q12', '15')
                // Máscara %: a 3ª casa decimal é descartada pela máscara.
                ->type('@quiz-Q13', '3,469')       // vira 3,46
                ->pause(700)
                // A tela mostra o valor formatado…
                ->assertValue('@quiz-Q09', '100.000,00')
                ->assertValue('@quiz-Q10', '1234')
                ->assertValue('@quiz-Q13', '3,46')
                ->click('@quiz-proximo')
                ->waitFor('@quiz-bloco-3');
        });

        // …e é isso que precisa ter sido persistido, não o texto pré-máscara.
        $rascunho = (array) QuizRascunho::query()->firstOrFail()->quiz_payload;

        $this->assertSame('100.000,00', $rascunho['Q09'], 'máscara BRL dessincronizada');
        $this->assertSame('50.000,00', $rascunho['Q08'], 'máscara BRL dessincronizada');
        $this->assertSame('1234', $rascunho['Q10'], 'máscara de dias dessincronizada');
        $this->assertSame('3,46', $rascunho['Q13'], 'máscara de % dessincronizada');
    }

    public function test_valor_mascarado_chega_ao_motor_no_submit(): void
    {
        $usuario = Usuario::factory()->create(['nome' => 'Roberto Souza']);
        $empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $usuario->id]);

        $this->browse(function (Browser $browser) use ($usuario, $empresa) {
            $browser->resize(1280, 900)
                ->loginAs($usuario)
                ->visit(route('diagnosticos.novo', $empresa))
                ->waitFor('@quiz-bloco-1')
                ->click('@quiz-proximo')
                ->waitFor('@quiz-bloco-2')
                ->type('@quiz-Q08', '5000000')
                ->type('@quiz-Q09', '10000000')    // 100.000,00
                ->type('@quiz-Q14', '2000000')
                ->type('@quiz-Q15', '800000')
                ->type('@quiz-Q16', '200000')
                ->type('@quiz-Q10', '90')
                ->type('@quiz-Q11', '15')
                ->type('@quiz-Q12', '15')
                ->type('@quiz-Q13', '2')
                ->pause(700)
                ->click('@quiz-proximo')
                ->waitFor('@quiz-bloco-3')
                ->type('@quiz-Q02', '5000000')
                ->type('@quiz-Q03', '2000000')
                ->type('@quiz-Q04', '1000000')
                ->type('@quiz-Q05', '30000000')
                ->type('@quiz-Q06', '4000000')
                ->type('@quiz-Q07', '8000000')
                ->pause(700)
                ->click('@quiz-proximo')
                ->waitFor('@quiz-bloco-4')
                ->click('@quiz-Q17-sim')
                ->waitFor('@quiz-bloco-4-captacao')
                ->type('@quiz-Q18', '5000000')
                ->type('@quiz-Q19', '2000000')
                ->type('@quiz-Q20', '2600000')     // 26.000,00
                ->type('@quiz-socio-0-nome', 'Ana Lima')
                ->type('@quiz-socio-0-cpf', '52998224725')
                ->pause(700)
                ->assertValue('@quiz-Q20', '26.000,00')
                ->assertValue('@quiz-socio-0-cpf', '529.982.247-25')
                ->click('@quiz-calcular')
                ->waitFor('@diagnostico-mvp-badge');
        });

        $payload = (array) Diagnostico::withoutGlobalScopes()->firstOrFail()->quiz_payload;

        // Canônico do motor: ponto-decimal, sem separador de milhar.
        $this->assertSame('100000.00', $payload['Q09']);
        $this->assertSame('26000.00', $payload['Q20']);
        $this->assertSame(['52998224725'], array_column((array) $payload['socios'], 'cpf'));
    }
}

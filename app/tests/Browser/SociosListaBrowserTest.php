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
 * STORY-038 CA-10 — lista dinâmica de sócios (nome + CPF) no navegador real.
 *
 * (a) submeter com 1 sócio só;
 * (b) adicionar 3, remover 1 (confirmando o diálogo), submeter (3 sócios).
 *
 * CPFs válidos: 529.982.247-25 · 111.444.777-35 · 123.456.789-09 · 987.654.321-00.
 *
 * Escreve no banco (fora do grupo `smoke` por design).
 */
final class SociosListaBrowserTest extends DuskTestCase
{
    use DatabaseTruncation;

    /** @var list<string> */
    protected array $tablesToTruncate = [
        'quiz_rascunhos', 'diagnosticos', 'empresas_analisadas', 'usuarios',
        'audit_logs', 'business_metrics', 'term_acceptances', 'sessions',
        'evento_produto', 'cache',
    ];

    public function test_submete_com_um_unico_socio(): void
    {
        $usuario = Usuario::factory()->create(['nome' => 'Roberto Souza']);
        $empresa = EmpresaAnalisada::factory()->create([
            'usuario_id' => $usuario->id,
            'razao_social' => 'Indústria Teste Ltda',
            'nome_fantasia' => 'Teste',
        ]);

        $this->browse(function (Browser $browser) use ($usuario, $empresa) {
            $this->preencherAteBloco4($browser->loginAs($usuario), $empresa)
                ->click('@quiz-Q17-sim')
                ->waitFor('@quiz-bloco-4-captacao')
                ->type('@quiz-Q18', '5000000')   // 50.000,00
                ->type('@quiz-Q19', '2000000')   // 20.000,00
                ->type('@quiz-Q20', '1000000')   // 10.000,00 (< Q09 = 100.000)
                // Uma única linha de sócio (CA-1) — botão remover desabilitado.
                ->assertDisabled('@quiz-socio-remover-0')
                ->type('@quiz-socio-0-nome', 'Ana Lima')
                ->type('@quiz-socio-0-cpf', '52998224725')
                ->pause(600)
                ->click('@quiz-calcular')
                ->waitFor('@diagnostico-mvp-badge');
        });

        $diagnostico = Diagnostico::withoutGlobalScopes()
            ->where('empresa_analisada_id', $empresa->id)
            ->firstOrFail();

        $this->assertSame(
            [['cpf' => '52998224725', 'nome' => 'Ana Lima']],
            (array) $diagnostico->quiz_payload['socios'],
        );
    }

    public function test_adiciona_tres_remove_um_e_submete(): void
    {
        $usuario = Usuario::factory()->create(['nome' => 'Roberto Souza']);
        $empresa = EmpresaAnalisada::factory()->create([
            'usuario_id' => $usuario->id,
            'razao_social' => 'Indústria Teste Ltda',
            'nome_fantasia' => 'Teste',
        ]);

        $this->browse(function (Browser $browser) use ($usuario, $empresa) {
            $this->preencherAteBloco4($browser->loginAs($usuario), $empresa)
                ->click('@quiz-Q17-sim')
                ->waitFor('@quiz-bloco-4-captacao')
                ->type('@quiz-Q18', '5000000')
                ->type('@quiz-Q19', '2000000')
                ->type('@quiz-Q20', '1000000')
                // Linha 0 + 3 adicionadas = 4 sócios.
                ->type('@quiz-socio-0-nome', 'Carla')->type('@quiz-socio-0-cpf', '52998224725')
                ->click('@quiz-socio-adicionar')->waitFor('@quiz-socio-1-cpf')
                ->type('@quiz-socio-1-nome', 'Ana')->type('@quiz-socio-1-cpf', '11144477735')
                ->click('@quiz-socio-adicionar')->waitFor('@quiz-socio-2-cpf')
                ->type('@quiz-socio-2-nome', 'Bruno')->type('@quiz-socio-2-cpf', '12345678909')
                ->click('@quiz-socio-adicionar')->waitFor('@quiz-socio-3-cpf')
                ->type('@quiz-socio-3-nome', 'Dudu')->type('@quiz-socio-3-cpf', '98765432100')
                ->pause(600)
                // Remove a 2ª linha (índice 1 = Ana) → confirma → sobram 3, renumeradas.
                ->click('@quiz-socio-remover-1')
                ->acceptDialog()
                ->waitUntilMissing('@quiz-socio-3-cpf')
                ->pause(400)
                ->click('@quiz-calcular')
                ->waitFor('@diagnostico-mvp-badge');
        });

        $diagnostico = Diagnostico::withoutGlobalScopes()
            ->where('empresa_analisada_id', $empresa->id)
            ->firstOrFail();

        // 3 sócios, ordenados alfabeticamente por CPF (CA-9); Ana (removida) ausente.
        $this->assertSame(
            [
                ['cpf' => '12345678909', 'nome' => 'Bruno'],
                ['cpf' => '52998224725', 'nome' => 'Carla'],
                ['cpf' => '98765432100', 'nome' => 'Dudu'],
            ],
            (array) $diagnostico->quiz_payload['socios'],
        );
    }

    /**
     * Preenche Blocos 1→3 com valores saudáveis e para no Bloco 4 (Q17 em branco).
     */
    private function preencherAteBloco4(Browser $browser, EmpresaAnalisada $empresa): Browser
    {
        return $browser
            ->visit(route('diagnosticos.novo', $empresa))
            ->resize(1280, 900)
            ->waitFor('@quiz-bloco-1')
            ->click('@quiz-proximo')
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
            ->pause(600)
            ->click('@quiz-proximo')
            ->waitFor('@quiz-bloco-3')
            ->type('@quiz-Q02', '5000000')    // 50.000,00
            ->type('@quiz-Q03', '2000000')    // 20.000,00
            ->type('@quiz-Q04', '1000000')    // 10.000,00
            ->type('@quiz-Q05', '30000000')   // 300.000,00
            ->type('@quiz-Q06', '4000000')    // 40.000,00
            ->type('@quiz-Q07', '8000000')    // 80.000,00
            ->pause(600)
            ->click('@quiz-proximo')
            ->waitFor('@quiz-bloco-4');
    }
}

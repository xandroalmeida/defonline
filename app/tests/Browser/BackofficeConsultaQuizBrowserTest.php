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
 * STORY-104/105/106 no navegador real: a equipe acha o cliente pelo CPF, abre a ficha, a empresa e
 * as respostas do quiz; edita o enunciado de uma pergunta e o cliente vê o texto novo no quiz.
 *
 * Escreve no banco (fora do grupo `smoke` por design).
 */
final class BackofficeConsultaQuizBrowserTest extends DuskTestCase
{
    use DatabaseTruncation;

    /** @var list<string> */
    protected array $tablesToTruncate = [
        'quiz_textos', 'quiz_rascunhos', 'diagnosticos', 'empresas_analisadas', 'usuarios',
        'audit_logs', 'business_metrics', 'term_acceptances', 'sessions', 'evento_produto', 'cache',
    ];

    public function test_consulta_respostas_e_edicao_de_texto(): void
    {
        $admin = Usuario::factory()->create(['nome' => 'Ana Admin']);
        $admin->forceFill(['permissoes_admin' => ['usuarios.ver', 'quiz.ver_respostas', 'quiz.editar_textos']])->save();
        $cliente = Usuario::factory()->create(['nome' => 'Roberto Cliente', 'cpf' => '52998224725', 'telefone' => '85987654321']);
        $empresa = EmpresaAnalisada::factory()->create([
            'usuario_id' => $cliente->id, 'razao_social' => 'Metalúrgica Fortaleza Ltda', 'nome_fantasia' => 'Metal Forte',
            'documento' => '11222333000181', 'telefone' => '8533334444',
        ]);
        $diag = Diagnostico::factory()->create([
            'usuario_id' => $cliente->id, 'empresa_analisada_id' => $empresa->id,
            'quiz_payload' => ['Q01' => 'industria', 'Q09' => '100000.00', 'Q13' => '2.50', 'Q17' => false],
        ]);

        $this->browse(function (Browser $browser) use ($admin, $cliente, $empresa, $diag) {
            $browser->loginAs($admin)->resize(1280, 900)
                ->visit(route('backoffice.usuarios'))
                ->type('@backoffice-busca', '529.982.247-25')
                ->click('@backoffice-buscar')
                ->waitForText('Roberto Cliente')
                ->click('@backoffice-ficha-'.$cliente->id)
                ->waitFor('@ficha-usuario-dados')
                ->assertSeeIn('@ficha-usuario-cpf', '529.982.247-25')
                ->assertAttribute('@ficha-usuario-whatsapp', 'href', 'https://wa.me/5585987654321')
                ->click('@ficha-usuario-empresa-'.$empresa->id)
                ->waitFor('@ficha-empresa-dados')
                ->assertSeeIn('@ficha-empresa-documento', '11.222.333/0001-81')
                ->click('@ficha-respostas-'.$diag->id)
                ->waitFor('@respostas-bloco-2')
                ->assertSeeIn('@resposta-Q09', 'R$ 100.000,00')
                ->assertSeeIn('@resposta-Q13', '2,50%');

            $browser->visit(route('backoffice.quiz.textos'))
                ->waitFor('@textos-lista')
                ->click('@texto-editar-Q09')
                ->waitFor('@texto-Q09')
                ->clear('@texto-rotulo')
                ->type('@texto-rotulo', 'Quanto você vende por mês?')
                // O rótulo sai em maiúsculas pelo CSS: compara sem diferenciar a caixa.
                ->waitUsing(5, 100, fn () => str_contains(mb_strtolower($browser->text('@texto-previa')), 'quanto você vende por mês?'))
                ->click('@texto-salvar')
                ->waitFor('@texto-editado-Q09');

            $browser->loginAs($cliente)
                ->visit(route('diagnosticos.novo', $empresa))
                ->waitFor('@quiz-bloco-1')
                ->click('@quiz-proximo')
                ->waitFor('@quiz-bloco-2')
                ->assertSeeIn('@quiz-bloco-2', 'QUANTO VOCÊ VENDE POR MÊS?');
        });
    }
}

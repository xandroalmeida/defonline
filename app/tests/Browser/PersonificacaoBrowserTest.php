<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Models\AuditLog;
use App\Models\EmpresaAnalisada;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * STORY-066 — E2E do "Logar como" e da tabela sem corte em 1280px.
 *
 * NÃO está no grupo `smoke` por design — faz writes.
 */
final class PersonificacaoBrowserTest extends DuskTestCase
{
    use DatabaseTruncation;

    /** @var list<string> */
    protected array $tablesToTruncate = ['empresas_analisadas', 'usuarios', 'audit_logs', 'sessions', 'cache'];

    public function test_equipe_assume_o_usuario_e_volta_com_tudo_auditado(): void
    {
        $equipe = Usuario::factory()->create(['nome' => 'Equipe EBP', 'email' => 'equipe@ebparcerias.com.br']);
        $alvo = Usuario::factory()->create([
            'nome' => 'Maria Pereira de Albuquerque Cavalcanti',
            'email' => 'maria.pereira.albuquerque@exemplo.com.br',
        ]);

        $empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $alvo->id]);

        $this->browse(function (Browser $browser) use ($equipe, $alvo, $empresa) {
            $browser->resize(1280, 800)
                ->loginAs($equipe)
                ->visit('/backoffice/usuarios')
                ->waitFor('@backoffice-usuarios');

            // A tabela cabe na área útil: sem rolagem, nenhuma coluna fica cortada.
            $sobra = $browser->script(
                "const c = document.querySelector('[dusk=backoffice-usuarios]').parentElement; return c.scrollWidth - c.clientWidth;",
            )[0];
            $this->assertLessThanOrEqual(0, $sobra, 'tabela não cabe em 1280px');

            $browser->assertMissing('@personificacao-aviso')
                ->press('@backoffice-logar-como-'.$alvo->id)
                ->waitForLocation('/home')
                ->waitFor('@personificacao-aviso')
                ->assertSeeIn('@personificacao-aviso', 'Maria Pereira')
                ->visit('/backoffice/usuarios')
                ->assertSee('404')
                ->visit('/empresas/'.$empresa->id)
                ->waitFor('@personificacao-aviso')
                ->assertMissing('@personificacao-marca')
                ->type('@empresa-show-observacoes', 'Ajuste feito pela equipe')
                ->press('@empresa-show-salvar-observacoes')
                ->waitFor('@empresa-show-observacoes-salvas')
                ->refresh()
                ->waitFor('@personificacao-marca')
                ->assertSeeIn('@personificacao-marca', 'Equipe EBP')
                ->press('@personificacao-voltar')
                ->waitForLocation('/backoffice/usuarios')
                ->waitFor('@backoffice-usuarios')
                ->assertMissing('@personificacao-aviso')
                ->assertSeeIn('@backoffice-usuario-'.$alvo->id, '1 via admin');
        });

        $this->assertSame($equipe->id, $empresa->fresh()?->personificado_por_id);

        $this->assertSame(1, AuditLog::where('action', 'personificacao.iniciada')->where('actor_id', $equipe->id)->count());
        $this->assertSame(1, AuditLog::where('action', 'personificacao.encerrada')->where('actor_id', $equipe->id)->count());
        $this->assertGreaterThanOrEqual(3, AuditLog::where('action', 'personificacao.requisicao')->where('usuario_id', $alvo->id)->count());
    }
}

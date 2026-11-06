<?php

declare(strict_types=1);

use App\Livewire\Empresa\AlertaRenovacaoDiagnostico;
use App\Livewire\Empresa\PrazoRenovacaoDiagnostico;
use App\Mail\RenovacaoDiagnosticoMessage;
use App\Models\AvisoRenovacaoDiagnostico;
use App\Models\Diagnostico;
use App\Models\EmpresaAnalisada;
use App\Models\Usuario;
use App\Services\Diagnostico\AvisarRenovacaoDeDiagnostico;
use App\Services\Diagnostico\RenovacaoDeDiagnostico;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

/** STORY-099 — aviso para renovar o diagnóstico na plataforma e por e-mail. */
beforeEach(fn () => Mail::fake());

function _empresaComDiagnostico(?Usuario $usuario = null, int $mesesAtras = 7, array $empresa = []): EmpresaAnalisada
{
    $usuario ??= Usuario::factory()->create(['email_confirmed_at' => now()]);
    $e = EmpresaAnalisada::factory()->create(['usuario_id' => $usuario->id, ...$empresa]);
    Diagnostico::factory()->paraEmpresa($e)->create(['gerado_em' => now()->subMonths($mesesAtras)]);

    return $e->fresh();
}

function _rodarAvisos(): array
{
    return app(AvisarRenovacaoDeDiagnostico::class)->executar();
}

describe('rotina de e-mails', function () {
    it('CA-1 e CA-2 — vale o prazo da empresa, com 6 meses como padrão, e empresa sem diagnóstico não recebe', function () {
        $padraoNoPrazo = _empresaComDiagnostico(mesesAtras: 5);
        $padraoVencida = _empresaComDiagnostico(mesesAtras: 7);
        $tresVencida = _empresaComDiagnostico(mesesAtras: 4, empresa: ['renovacao_diagnostico_meses' => 3]);
        $dozeNoPrazo = _empresaComDiagnostico(mesesAtras: 11, empresa: ['renovacao_diagnostico_meses' => 12]);
        EmpresaAnalisada::factory()->create(['usuario_id' => Usuario::factory()->create(['email_confirmed_at' => now()])->id]);

        expect(_rodarAvisos()['enviados'])->toBe(2);

        Mail::assertSent(RenovacaoDiagnosticoMessage::class, fn ($m) => $m->empresa === $padraoVencida->nomeExibicao());
        Mail::assertSent(RenovacaoDiagnosticoMessage::class, fn ($m) => $m->empresa === $tresVencida->nomeExibicao());
        expect($padraoNoPrazo->renovacao_diagnostico_meses)->toBe(6)
            ->and($dozeNoPrazo->renovacao_diagnostico_meses)->toBe(12);
    });

    it('CA-2 — conta do último diagnóstico, não do primeiro', function () {
        $e = _empresaComDiagnostico(mesesAtras: 20);
        Diagnostico::factory()->paraEmpresa($e)->create(['gerado_em' => now()->subMonth()]);

        _rodarAvisos();

        Mail::assertNothingSent();
    });

    it('CA-4 — envia o aviso e o lembrete 30 dias depois, e nada além disso', function () {
        _empresaComDiagnostico();

        _rodarAvisos();
        _rodarAvisos();
        Mail::assertSentCount(1);

        $this->travel(29)->days();
        _rodarAvisos();
        Mail::assertSentCount(1);

        $this->travel(2)->days();
        _rodarAvisos();
        _rodarAvisos();
        Mail::assertSentCount(2);
        Mail::assertSent(RenovacaoDiagnosticoMessage::class, fn ($m) => $m->lembrete);

        $this->travel(90)->days();
        _rodarAvisos();
        Mail::assertSentCount(2);
    });

    it('CA-4 — envio com falha devolve a vaga e a próxima rodada tenta de novo', function () {
        _empresaComDiagnostico();
        $fake = Mail::getFacadeRoot();
        $quebrado = Mockery::mock();
        $quebrado->shouldReceive('to')->once()->andThrow(new RuntimeException('smtp'));
        Mail::swap($quebrado);

        expect(_rodarAvisos())->toBe(['enviados' => 0, 'falhas' => 1])
            ->and(AvisoRenovacaoDiagnostico::query()->sole()->aviso_em)->toBeNull();

        Mail::swap($fake);

        expect(_rodarAvisos()['enviados'])->toBe(1);
    });

    it('CA-5 — dispensar o alerta não cancela o e-mail pendente', function () {
        $e = _empresaComDiagnostico();

        Livewire::actingAs(Usuario::query()->findOrFail($e->usuario_id))
            ->test(AlertaRenovacaoDiagnostico::class, ['empresa' => $e])
            ->call('dispensar');
        auth()->logout();

        expect(_rodarAvisos()['enviados'])->toBe(1);
    });

    it('CA-5 — novo diagnóstico cancela o lembrete e reinicia a contagem', function () {
        $e = _empresaComDiagnostico();
        _rodarAvisos();

        Diagnostico::factory()->paraEmpresa($e)->create(['gerado_em' => now()]);
        $this->travel(31)->days();
        _rodarAvisos();
        Mail::assertSentCount(1);

        $this->travel(6)->months();
        _rodarAvisos();
        Mail::assertSentCount(2);
        Mail::assertSent(RenovacaoDiagnosticoMessage::class, fn ($m) => ! $m->lembrete);
    });

    it('CA-7 — não envia a descadastrado, anonimizado, excluído nem a e-mail não confirmado', function (array $dados, bool $excluir) {
        $u = Usuario::factory()->create(['email_confirmed_at' => now()]);
        _empresaComDiagnostico($u);
        $u->forceFill($dados)->save();

        if ($excluir) {
            $u->delete();
        }

        _rodarAvisos();

        Mail::assertNothingSent();
    })->with([
        'descadastrado' => [['aviso_renovacao_descadastrado_em' => now()], false],
        'anonimizado' => [['anonimizado_at' => now()], false],
        'não confirmado' => [['email_confirmed_at' => null], false],
        'excluído' => [[], true],
    ]);

    it('CA-10 — quem já estava vencido no lançamento recebe um único aviso, sem lembrete', function () {
        _empresaComDiagnostico();
        $noPrazo = _empresaComDiagnostico(mesesAtras: 2);

        $migracao = require database_path('migrations/2026_11_06_000030_aviso_renovacao_diagnostico.php');
        $migracao->marcarVencidosNoLancamento();

        _rodarAvisos();
        $this->travel(31)->days();
        _rodarAvisos();
        Mail::assertSentCount(1);

        expect(AvisoRenovacaoDiagnostico::query()->where('sem_lembrete', true)->count())->toBe(1)
            ->and(AvisoRenovacaoDiagnostico::query()->where('diagnostico_id', $noPrazo->diagnosticos()->first()?->id)->exists())->toBeFalse();
    });

    it('CA-11 — cada empresa gera o próprio e-mail, sem agrupar', function () {
        $u = Usuario::factory()->create(['email_confirmed_at' => now()]);
        _empresaComDiagnostico($u);
        _empresaComDiagnostico($u);

        _rodarAvisos();

        Mail::assertSentCount(2);
    });

    it('o comando roda a rotina e está agendado', function () {
        _empresaComDiagnostico();

        expect(Artisan::call('diagnosticos:avisar-renovacao'))->toBe(0);
        Mail::assertSentCount(1);

        Artisan::call('schedule:list');
        expect(Artisan::output())->toContain('diagnosticos:avisar-renovacao');
    });
});

describe('conteúdo do e-mail e descadastro', function () {
    it('CA-6 — e-mail tem HTML, texto, a empresa, o assunto certo e o link de descadastro', function () {
        $m = new RenovacaoDiagnosticoMessage('Ana', 'Padaria Sol', '06/11/2025', 'https://app/novo', 'https://app/sair', false);

        $m->assertHasSubject('Hora de renovar o diagnóstico de Padaria Sol — DEFOnline');
        $m->assertSeeInHtml('Padaria Sol');
        $m->assertSeeInHtml('https://app/sair');
        $m->assertSeeInText('Padaria Sol');
        $m->assertSeeInText('https://app/sair');
        expect($m->headers()->text['List-Unsubscribe'])->toBe('<https://app/sair>')
            ->and((new RenovacaoDiagnosticoMessage('Ana', 'Padaria Sol', '06/11/2025', 'a', 'b', true))->envelope()->subject)->toStartWith('Lembrete:');
    });

    it('CA-6 — o link funciona sem login, abrir não descadastra e confirmar sim', function () {
        $u = Usuario::factory()->create();
        $link = URL::signedRoute('avisos.renovacao.descadastro', ['usuario' => $u->id]);

        $this->get($link)->assertOk()->assertSee('Parar de receber estes avisos?');
        expect($u->fresh()->aviso_renovacao_descadastrado_em)->toBeNull();

        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->post($link)->assertOk()->assertSee('O alerta continua aparecendo na plataforma.');
        expect($u->fresh()->aviso_renovacao_descadastrado_em)->not->toBeNull();
    });

    it('CA-6 — link sem assinatura válida não descadastra', function () {
        $u = Usuario::factory()->create();

        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->post(route('avisos.renovacao.descadastro', ['usuario' => $u->id]))->assertForbidden();

        expect($u->fresh()->aviso_renovacao_descadastrado_em)->toBeNull();
    });

    it('CA-8 — o Logar como não descadastra', function () {
        $u = Usuario::factory()->create();
        $link = URL::signedRoute('avisos.renovacao.descadastro', ['usuario' => $u->id]);

        $this->withSession(_sessaoDePersonificacao(_adminDaEquipe(), $u))->actingAs($u)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->post($link)->assertOk();

        expect($u->fresh()->aviso_renovacao_descadastrado_em)->toBeNull();
    });
});

describe('na plataforma', function () {
    it('CA-3 — mostra o alerta com a ação, só com o prazo vencido', function () {
        $vencida = _empresaComDiagnostico();
        $u = Usuario::query()->findOrFail($vencida->usuario_id);
        $noPrazo = _empresaComDiagnostico($u, 2);
        $semDiagnostico = EmpresaAnalisada::factory()->create(['usuario_id' => $u->id]);

        Livewire::actingAs($u)->test(AlertaRenovacaoDiagnostico::class, ['empresa' => $vencida])
            ->assertSee('Fazer novo diagnóstico')
            ->assertSeeHtml(route('diagnosticos.novo', $vencida->id));
        Livewire::actingAs($u)->test(AlertaRenovacaoDiagnostico::class, ['empresa' => $noPrazo])->assertDontSee('Fazer novo diagnóstico');
        Livewire::actingAs($u)->test(AlertaRenovacaoDiagnostico::class, ['empresa' => $semDiagnostico])->assertDontSee('Fazer novo diagnóstico');
    });

    it('CA-3 — o alerta aparece em Minhas Empresas e na página da empresa', function () {
        $e = _empresaComDiagnostico();
        $u = Usuario::query()->findOrFail($e->usuario_id);

        $this->actingAs($u)->get(route('home'))->assertOk()->assertSee('renovacao-alerta-'.$e->id, false);
        $this->actingAs($u)->get(route('empresas.show', $e->id))->assertOk()
            ->assertSee('renovacao-alerta-'.$e->id, false)
            ->assertSee('renovacao-meses', false);
    });

    it('a página da empresa leva ao novo diagnóstico, com o rótulo de cada caso', function () {
        $comDiagnostico = _empresaComDiagnostico();
        $u = Usuario::query()->findOrFail($comDiagnostico->usuario_id);
        $semDiagnostico = EmpresaAnalisada::factory()->create(['usuario_id' => $u->id]);

        $this->actingAs($u)->get(route('empresas.show', $comDiagnostico->id))->assertOk()
            ->assertSee('Refazer diagnóstico')
            ->assertSee('href="'.route('diagnosticos.novo', $comDiagnostico->id).'"', false);
        $this->actingAs($u)->get(route('empresas.show', $semDiagnostico->id))->assertOk()
            ->assertSee('Iniciar diagnóstico')
            ->assertSee('href="'.route('diagnosticos.novo', $semDiagnostico->id).'"', false);
    });

    it('CA-3 — dispensar some com o alerta, e novo diagnóstico também', function () {
        $e = _empresaComDiagnostico();
        $u = Usuario::query()->findOrFail($e->usuario_id);

        Livewire::actingAs($u)->test(AlertaRenovacaoDiagnostico::class, ['empresa' => $e])
            ->call('dispensar')
            ->assertDontSee('Fazer novo diagnóstico');

        $outra = _empresaComDiagnostico($u);
        Diagnostico::factory()->paraEmpresa($outra)->create(['gerado_em' => now()]);

        Livewire::actingAs($u)->test(AlertaRenovacaoDiagnostico::class, ['empresa' => $outra])->assertDontSee('Fazer novo diagnóstico');
    });

    it('CA-1 — escolher o prazo salva, atualiza o alerta e recomeça o ciclo', function () {
        $e = _empresaComDiagnostico();
        $u = Usuario::query()->findOrFail($e->usuario_id);
        _rodarAvisos();

        Livewire::actingAs($u)->test(PrazoRenovacaoDiagnostico::class, ['empresa' => $e])
            ->assertSet('meses', 6)
            ->set('meses', 12)
            ->assertHasNoErrors()
            ->assertSee('Prazo salvo.')
            ->assertDispatched('prazo-renovacao-alterado');

        expect($e->fresh()->renovacao_diagnostico_meses)->toBe(12)
            ->and(AvisoRenovacaoDiagnostico::query()->count())->toBe(0);
        Livewire::actingAs($u)->test(AlertaRenovacaoDiagnostico::class, ['empresa' => $e])->assertDontSee('Fazer novo diagnóstico');
    });

    it('CA-1 — recusa prazo fora da lista', function () {
        $e = _empresaComDiagnostico();

        Livewire::actingAs(Usuario::query()->findOrFail($e->usuario_id))->test(PrazoRenovacaoDiagnostico::class, ['empresa' => $e])
            ->set('meses', 5)
            ->assertHasErrors('meses');

        expect($e->fresh()->renovacao_diagnostico_meses)->toBe(6);
    });

    it('CA-8 — empresa de outra conta não abre', function () {
        $e = _empresaComDiagnostico();
        $outro = Usuario::factory()->create();

        Livewire::actingAs($outro)->test(AlertaRenovacaoDiagnostico::class, ['empresa' => $e->id])->assertStatus(404);
    });

    it('CA-8 — o Logar como não dispensa nem muda o prazo', function () {
        $e = _empresaComDiagnostico();
        $u = Usuario::query()->findOrFail($e->usuario_id);
        _personificar(_adminDaEquipe(), $u);

        $alerta = new AlertaRenovacaoDiagnostico;
        $alerta->empresaId = $e->id;
        $alerta->dispensar(app(RenovacaoDeDiagnostico::class));

        $prazo = new PrazoRenovacaoDiagnostico;
        $prazo->empresaId = $e->id;
        $prazo->meses = 12;
        $prazo->updatedMeses(app(RenovacaoDeDiagnostico::class));

        expect(AvisoRenovacaoDiagnostico::query()->count())->toBe(0)
            ->and($e->fresh()->renovacao_diagnostico_meses)->toBe(6)
            ->and($prazo->meses)->toBe(6);
    });
});

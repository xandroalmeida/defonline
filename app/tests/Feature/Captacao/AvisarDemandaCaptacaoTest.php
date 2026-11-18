<?php

declare(strict_types=1);

use App\Jobs\AvisarDemandaCaptacao;
use App\Mail\DemandaCaptacaoEquipeMessage;
use App\Mail\DemandaCaptacaoRecebidaMessage;
use App\Models\CaptacaoConfig;
use App\Models\DemandaCaptacao;
use App\Models\Diagnostico;
use App\Models\EmpresaAnalisada;
use App\Models\Usuario;
use App\Observabilidade\AlertaDaEquipe;
use Illuminate\Support\Facades\Mail;

/**
 * STORY-102 / PDR-021 — avisos da demanda de captação: e-mail da equipe só se houver um cadastrado,
 * confirmação ao cliente, alerta sem dado pessoal e nada repetido numa nova tentativa.
 */
beforeEach(function () {
    Mail::fake();
    $this->alerta = Mockery::mock(AlertaDaEquipe::class);
    app()->instance(AlertaDaEquipe::class, $this->alerta);

    $this->usuario = Usuario::factory()->create(['nome' => 'Roberto Silva', 'email' => 'roberto@exemplo.com.br', 'telefone' => '11987654321']);
    $this->empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $this->usuario->id, 'telefone' => '1133334444']);
    $diagnostico = Diagnostico::factory()->create([
        'usuario_id' => $this->usuario->id,
        'empresa_analisada_id' => $this->empresa->id,
        'quiz_payload' => ['Q17' => true, 'Q18' => '50000.00', 'Q19' => '20000.00', 'Q20' => '10000.00', 'socios' => [['cpf' => '52998224725', 'nome' => 'Ana Lima']]],
    ]);
    $this->demanda = DemandaCaptacao::query()->create([
        'diagnostico_id' => $diagnostico->id, 'usuario_id' => $this->usuario->id,
        'empresa_analisada_id' => $this->empresa->id, 'valor_captar' => '50000', 'autoriza_contato' => true,
    ]);
    $this->rodar = fn () => (new AvisarDemandaCaptacao($this->demanda->id))->handle();
});

it('com e-mail cadastrado, avisa a equipe com o contato e os valores, e confirma ao cliente', function () {
    CaptacaoConfig::atual()->update(['email' => 'captacao@exemplo.com.br']);
    $this->alerta->shouldReceive('enviar')->once()->withArgs(fn (string $titulo, string $msg, array $ctx) => $titulo === 'Nova demanda de captação'
        && $ctx === ['demanda_id' => $this->demanda->id]
        && ! str_contains($msg, 'Roberto') && ! str_contains($msg, 'roberto@'));

    ($this->rodar)();

    Mail::assertSent(DemandaCaptacaoEquipeMessage::class, function (DemandaCaptacaoEquipeMessage $m) {
        $html = $m->render();

        return $m->hasTo('captacao@exemplo.com.br')
            && $m->valorCaptar === 'R$ 50.000,00' && $m->endividamento === 'R$ 20.000,00' && $m->socios === ['Ana Lima']
            && str_contains($html, 'roberto@exemplo.com.br') && str_contains($html, '(11) 3333-4444')
            && ! str_contains($html, '52998224725');
    });
    Mail::assertSent(DemandaCaptacaoRecebidaMessage::class, fn (DemandaCaptacaoRecebidaMessage $m) => $m->hasTo('roberto@exemplo.com.br')
        && str_contains($m->render(), '5 dias úteis'));

    $this->demanda->refresh();
    expect($this->demanda->equipe_avisada_em)->not->toBeNull()
        ->and($this->demanda->cliente_avisado_em)->not->toBeNull();
});

it('sem e-mail cadastrado, a equipe não recebe e-mail, mas o alerta e a confirmação ao cliente saem', function (?string $email) {
    CaptacaoConfig::atual()->update(['email' => $email]);
    $this->alerta->shouldReceive('enviar')->once();

    ($this->rodar)();

    Mail::assertNotSent(DemandaCaptacaoEquipeMessage::class);
    Mail::assertSent(DemandaCaptacaoRecebidaMessage::class);
})->with(['nulo' => [null], 'só espaços' => ['   ']]);

it('sem a linha de configuração, trata como sem e-mail', function () {
    CaptacaoConfig::query()->delete();
    $this->alerta->shouldReceive('enviar')->once();

    ($this->rodar)();

    Mail::assertNotSent(DemandaCaptacaoEquipeMessage::class);
});

it('sem autorização de contato, não envia nada', function () {
    CaptacaoConfig::atual()->update(['email' => 'captacao@exemplo.com.br']);
    $this->demanda->update(['autoriza_contato' => false]);
    $this->alerta->shouldNotReceive('enviar');

    ($this->rodar)();

    Mail::assertNothingSent();
});

it('demanda que sumiu: não faz nada', function () {
    $this->alerta->shouldNotReceive('enviar');

    (new AvisarDemandaCaptacao('019a0000-0000-7000-8000-000000000000'))->handle();

    Mail::assertNothingSent();
});

it('numa nova tentativa, não repete o que já foi enviado', function () {
    CaptacaoConfig::atual()->update(['email' => 'captacao@exemplo.com.br']);
    $this->demanda->update(['equipe_avisada_em' => now()]);
    $this->alerta->shouldNotReceive('enviar');

    ($this->rodar)();
    ($this->rodar)();

    Mail::assertNotSent(DemandaCaptacaoEquipeMessage::class);
    Mail::assertSent(DemandaCaptacaoRecebidaMessage::class, 1);
});

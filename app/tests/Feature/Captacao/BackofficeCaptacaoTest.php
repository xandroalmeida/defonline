<?php

declare(strict_types=1);

use App\Livewire\Backoffice\Captacao;
use App\Models\AuditLog;
use App\Models\CaptacaoConfig;
use App\Models\DemandaCaptacao;
use App\Models\Diagnostico;
use App\Models\EmpresaAnalisada;
use App\Models\Usuario;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

/** STORY-102 / PDR-021 — backoffice de captação: e-mail da equipe e lista das demandas. */
beforeEach(function () {
    $this->admin = Usuario::factory()->create();
    $this->admin->forceFill(['permissoes_admin' => ['captacao.gerenciar']])->save();
    $this->tela = fn () => Livewire::actingAs($this->admin)->test(Captacao::class);
});

it('quem não tem a permissão recebe 404, na tela e nas ações', function () {
    $outro = Usuario::factory()->create();
    $outro->forceFill(['permissoes_admin' => ['glossario.editar']])->save();

    $this->actingAs($outro)->get('/backoffice/captacao')->assertNotFound();
    $this->actingAs($this->admin)->get('/backoffice/captacao')->assertOk()->assertSee('Captação');
});

it('o menu mostra Captação só para quem tem a permissão', function () {
    $this->actingAs($this->admin)->get('/backoffice/captacao')->assertSee(route('backoffice.captacao'));

    $outro = Usuario::factory()->create();
    $outro->forceFill(['permissoes_admin' => ['glossario.editar']])->save();
    $this->actingAs($outro)->get('/backoffice/glossario')->assertDontSee(route('backoffice.captacao'));
});

it('começa sem e-mail e avisa que nada é enviado', function () {
    ($this->tela)()->assertSet('email', '')->assertSee('Sem e-mail cadastrado');
});

it('salva o e-mail normalizado e audita antes e depois', function () {
    ($this->tela)()->set('email', '  Captacao@Exemplo.com.br ')->call('salvar')
        ->assertHasNoErrors()->assertSet('salvo', true)->assertSee('enviada para captacao@exemplo.com.br');

    expect(CaptacaoConfig::emailDaEquipe())->toBe('captacao@exemplo.com.br')
        ->and(CaptacaoConfig::atual()->alterado_por_usuario_id)->toBe($this->admin->id);

    $log = AuditLog::query()->where('action', 'captacao.email_alterado')->sole();
    expect($log->before->getArrayCopy())->toBe(['email' => null])->and($log->after->getArrayCopy())->toBe(['email' => 'captacao@exemplo.com.br']);
});

it('em branco, apaga o e-mail e para de enviar', function () {
    CaptacaoConfig::atual()->update(['email' => 'captacao@exemplo.com.br']);

    ($this->tela)()->assertSet('email', 'captacao@exemplo.com.br')->set('email', ' ')->call('salvar')->assertHasNoErrors();

    expect(CaptacaoConfig::emailDaEquipe())->toBeNull();
});

it('recusa e-mail inválido sem gravar', function (string $email) {
    ($this->tela)()->set('email', $email)->call('salvar')->assertHasErrors('email')->assertSet('salvo', false);

    expect(CaptacaoConfig::emailDaEquipe())->toBeNull();
})->with(['sem arroba' => ['captacao.exemplo.com.br'], 'longo demais' => [str_repeat('a', 250).'@x.com.br']]);

it('lista as demandas de todos os usuários, com o prazo só para as autorizadas', function () {
    $dono = Usuario::factory()->create(['nome' => 'Roberto Silva']);
    $empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $dono->id]);
    $d1 = Diagnostico::factory()->create(['usuario_id' => $dono->id, 'empresa_analisada_id' => $empresa->id]);
    $d2 = Diagnostico::factory()->create(['usuario_id' => $dono->id, 'empresa_analisada_id' => $empresa->id]);
    $base = ['usuario_id' => $dono->id, 'empresa_analisada_id' => $empresa->id];
    // Segunda, 16/11/2026, 12h em Brasília: retorno até a segunda seguinte.
    $this->travelTo(CarbonImmutable::parse('2026-11-16 15:00:00', 'UTC'));
    DemandaCaptacao::query()->create([...$base, 'diagnostico_id' => $d1->id, 'valor_captar' => '50000', 'autoriza_contato' => true]);
    DemandaCaptacao::query()->create([...$base, 'diagnostico_id' => $d2->id, 'valor_captar' => null, 'autoriza_contato' => false]);

    ($this->tela)()
        ->assertSee('Roberto Silva')
        ->assertSee($empresa->nomeExibicao())->assertSee($empresa->documentoFormatado())
        ->assertSee('R$ 50.000,00')
        ->assertSeeInOrder(['Sim', '23/11/2026'])
        ->assertSeeInOrder(['Não', '—']);
});

it('sem demandas, mostra a lista vazia', function () {
    ($this->tela)()->assertSee('Nenhuma demanda de captação ainda.');
});

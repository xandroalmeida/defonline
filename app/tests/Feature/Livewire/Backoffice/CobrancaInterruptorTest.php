<?php

declare(strict_types=1);

use App\Livewire\Backoffice\Planos;
use App\Models\AuditLog;
use App\Models\CobrancaConfig;
use App\Models\Usuario;
use Livewire\Livewire;

/**
 * STORY-073 CA-1 — o interruptor de cobrança: desligado por padrão, alterado na tela de planos
 * e auditado.
 */
beforeEach(function () {
    config(['backoffice.equipe_emails' => []]);
    $this->admin = Usuario::factory()->create();
    $this->admin->forceFill(['permissoes_admin' => ['planos.gerenciar']])->save();
    $this->tela = fn () => Livewire::actingAs($this->admin)->test(Planos::class);
});

it('nasce desligado e a tela mostra isso', function () {
    expect(CobrancaConfig::ligada())->toBeFalse();

    ($this->tela)()->assertSet('cobrancaAtiva', false)->assertSee('Cobrança desligada');
});

it('liga e desliga pela tela, com mensagem e auditoria de antes, depois e autor', function () {
    ($this->tela)()->set('cobrancaAtiva', true)->call('salvarCobranca')
        ->assertSet('mensagemCobranca', 'Interruptor salvo.')->assertSee('Cobrança ligada');

    expect(CobrancaConfig::ligada())->toBeTrue()
        ->and(CobrancaConfig::atual()->alterado_por_usuario_id)->toBe($this->admin->id);

    ($this->tela)()->assertSet('cobrancaAtiva', true)->set('cobrancaAtiva', false)->call('salvarCobranca');

    expect(CobrancaConfig::ligada())->toBeFalse();

    $auditorias = AuditLog::where('action', 'planos.cobranca_config_alterado')->get()
        // Não ordena por `occurred_at`: as duas auditorias podem cair no mesmo instante. A ordem lógica é
        // "ligou" (depois = true) e depois "desligou" (depois = false).
        ->sortByDesc(fn (AuditLog $a) => (int) $a->after['ativa'])->values();
    expect($auditorias)->toHaveCount(2)
        ->and($auditorias[0]->subject_type)->toBe('CobrancaConfig')
        ->and($auditorias[0]->before['ativa'])->toBeFalse()
        ->and($auditorias[0]->after['ativa'])->toBeTrue()
        ->and($auditorias[0]->actor_id)->toBe($this->admin->id)
        ->and($auditorias[1]->before['ativa'])->toBeTrue()
        ->and($auditorias[1]->after['ativa'])->toBeFalse();
});

it('salvar sem mudar nada não audita', function () {
    ($this->tela)()->call('salvarCobranca');

    expect(AuditLog::where('action', 'planos.cobranca_config_alterado')->count())->toBe(0);
});

it('quem não tem planos.gerenciar não consegue alterar', function () {
    $outro = Usuario::factory()->create();
    $outro->forceFill(['permissoes_admin' => ['glossario.editar']])->save();

    Livewire::actingAs($outro)->test(Planos::class)->assertNotFound();

    expect(CobrancaConfig::ligada())->toBeFalse();
});

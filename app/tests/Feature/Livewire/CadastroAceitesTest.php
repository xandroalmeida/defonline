<?php

declare(strict_types=1);

use App\Domain\TermoTipo;
use App\Livewire\Cadastro;
use App\Models\AuditLog;
use App\Models\TermAcceptance;
use App\Models\Usuario;
use App\Support\TermosVigentes;
use Illuminate\Support\Str;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * STORY-012 — Termo de Adesão + consentimento LGPD (CA-1 a CA-6).
 *
 * O fluxo principal de cadastro continua coberto em CadastroTest; aqui foca no
 * mecanismo de aceites: validação de obrigatórios, persistência em
 * `term_acceptances` (mesmo para marketing recusado), conteúdo do audit log
 * e ausência de ip/user-agent no log.
 */
const CPF_TERMO_A = '529.982.247-25';
const CPF_TERMO_B = '111.444.777-35';

function preencherCadastroBase(Testable $c): Testable
{
    return $c
        ->set('cpf', CPF_TERMO_A)
        ->set('nome', 'Roberto Souza')
        ->set('email', 'roberto.aceites@exemplo.com.br')
        ->set('senha', 'Senha1234')
        ->set('senha_confirmation', 'Senha1234')
        ->set('telefone', '11988887777');
}

it('bloqueia submit quando o Termo de Adesão não foi aceito (CA-2)', function () {
    $component = preencherCadastroBase(Livewire::test(Cadastro::class))
        ->set('aceite_termo_adesao', false)
        ->set('aceite_lgpd', true)
        ->call('submit')
        ->assertHasErrors(['aceite_termo_adesao'])
        ->assertNoRedirect();

    expect($component->errors()->get('aceite_termo_adesao')[0])
        ->toBe('Você precisa aceitar o Termo de Adesão para continuar.');
    expect(Usuario::count())->toBe(0);
    expect(TermAcceptance::count())->toBe(0);
});

it('bloqueia submit quando a Política de Privacidade/LGPD não foi aceita (CA-2)', function () {
    $component = preencherCadastroBase(Livewire::test(Cadastro::class))
        ->set('aceite_termo_adesao', true)
        ->set('aceite_lgpd', false)
        ->call('submit')
        ->assertHasErrors(['aceite_lgpd'])
        ->assertNoRedirect();

    expect($component->errors()->get('aceite_lgpd')[0])
        ->toBe('Você precisa aceitar a Política de Privacidade e LGPD para continuar.');
    expect(Usuario::count())->toBe(0);
});

it('cria Usuário e três registros em term_acceptances quando marketing é aceito (CA-2, CA-3)', function () {
    preencherCadastroBase(Livewire::test(Cadastro::class))
        ->set('aceite_termo_adesao', true)
        ->set('aceite_lgpd', true)
        ->set('aceite_marketing', true)
        ->call('submit')
        ->assertRedirect('/login');

    $usuario = Usuario::firstWhere('email', 'roberto.aceites@exemplo.com.br');
    expect($usuario)->not->toBeNull();

    $aceites = TermAcceptance::where('usuario_id', $usuario->id)->get();
    expect($aceites)->toHaveCount(3);

    $porTipo = $aceites->keyBy(fn (TermAcceptance $t) => $t->termo_tipo->value);
    expect($porTipo->get('termo_adesao')->aceito)->toBeTrue();
    expect($porTipo->get('lgpd')->aceito)->toBeTrue();
    expect($porTipo->get('marketing')->aceito)->toBeTrue();

    foreach ($porTipo as $aceite) {
        expect($aceite->versao)->toBe('v1-placeholder');
        expect($aceite->conteudo_hash)->toMatch('/^[a-f0-9]{64}$/');
        expect($aceite->ip)->not->toBeNull();
        expect($aceite->user_agent)->not->toBeNull();
    }
});

it('registra marketing recusado mesmo quando o opt-in fica desmarcado (CA-2, CA-3)', function () {
    preencherCadastroBase(Livewire::test(Cadastro::class))
        ->set('aceite_termo_adesao', true)
        ->set('aceite_lgpd', true)
        ->set('aceite_marketing', false)
        ->call('submit')
        ->assertRedirect('/login');

    $usuario = Usuario::firstWhere('email', 'roberto.aceites@exemplo.com.br');
    $marketing = TermAcceptance::where('usuario_id', $usuario->id)
        ->where('termo_tipo', 'marketing')
        ->first();

    expect($marketing)->not->toBeNull();
    expect($marketing->aceito)->toBeFalse();
    expect($marketing->versao)->toBe('v1-placeholder');
});

it('hash registrado bate com o conteúdo da view placeholder vigente (CA-3, CA-4)', function () {
    preencherCadastroBase(Livewire::test(Cadastro::class))
        ->set('aceite_termo_adesao', true)
        ->set('aceite_lgpd', true)
        ->call('submit')
        ->assertRedirect('/login');

    $usuario = Usuario::firstWhere('email', 'roberto.aceites@exemplo.com.br');

    $hashTermo = TermAcceptance::where('usuario_id', $usuario->id)
        ->where('termo_tipo', 'termo_adesao')->value('conteudo_hash');
    $hashLgpd = TermAcceptance::where('usuario_id', $usuario->id)
        ->where('termo_tipo', 'lgpd')->value('conteudo_hash');

    expect($hashTermo)->toBe(TermosVigentes::para(TermoTipo::TermoAdesao)->conteudoHash);
    expect($hashLgpd)->toBe(TermosVigentes::para(TermoTipo::Lgpd)->conteudoHash);
});

it('emite audit log "termo.aceito" para os obrigatórios e "termo.recusado" para marketing desmarcado (CA-6)', function () {
    preencherCadastroBase(Livewire::test(Cadastro::class))
        ->set('aceite_termo_adesao', true)
        ->set('aceite_lgpd', true)
        ->set('aceite_marketing', false)
        ->call('submit')
        ->assertRedirect('/login');

    $aceitos = AuditLog::where('action', 'termo.aceito')->get();
    $recusados = AuditLog::where('action', 'termo.recusado')->get();

    expect($aceitos)->toHaveCount(2);
    expect($recusados)->toHaveCount(1);

    $tiposAceitos = $aceitos->pluck('after.termo_tipo')->all();
    sort($tiposAceitos);
    expect($tiposAceitos)->toBe(['lgpd', 'termo_adesao']);
    expect($recusados->first()->after['termo_tipo'])->toBe('marketing');
});

it('NÃO grava ip ou user-agent em audit_logs dos aceites (CA-6 — PII fica só em term_acceptances)', function () {
    preencherCadastroBase(Livewire::test(Cadastro::class))
        ->set('aceite_termo_adesao', true)
        ->set('aceite_lgpd', true)
        ->call('submit')
        ->assertRedirect('/login');

    $aceitos = AuditLog::whereIn('action', ['termo.aceito', 'termo.recusado'])->get();
    foreach ($aceitos as $log) {
        $context = $log->context?->toArray() ?? [];
        expect(array_key_exists('ip', $context))->toBeFalse();
        expect(array_key_exists('user_agent', $context))->toBeFalse();
    }
});

it('term_acceptances bloqueia update e delete na aplicação (CA-3 — append-only)', function () {
    Usuario::factory()->create(['cpf' => '52998224725', 'email' => 'a@a.test']);
    $aceite = TermAcceptance::create([
        'id' => (string) Str::uuid7(),
        'usuario_id' => Usuario::first()->id,
        'termo_tipo' => 'termo_adesao',
        'aceito' => true,
        'versao' => 'v1-placeholder',
        'conteudo_hash' => str_repeat('a', 64),
        'ip' => '127.0.0.1',
        'user_agent' => 'phpunit',
    ]);

    expect(fn () => $aceite->update(['aceito' => false]))
        ->toThrow(RuntimeException::class, 'append-only');
    expect(fn () => $aceite->delete())
        ->toThrow(RuntimeException::class, 'append-only');
});

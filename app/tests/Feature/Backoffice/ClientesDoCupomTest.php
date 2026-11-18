<?php

declare(strict_types=1);

use App\Domain\Cobranca\TipoDesconto;
use App\Livewire\Backoffice\ClientesDoCupom;
use App\Models\AuditLog;
use App\Models\Cupom;
use App\Models\CupomConcessao;
use App\Models\CupomUso;
use App\Models\Diagnostico;
use App\Models\EmpresaAnalisada;
use App\Models\Plano;
use App\Models\Usuario;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;

/** Clientes que vieram por um cupom: relatório para acompanhar parceria e planilha para o parceiro. */
beforeEach(function () {
    $this->admin = Usuario::factory()->create();
    $this->admin->forceFill(['permissoes_admin' => ['cupons.gerenciar']])->save();

    $this->cupom = Cupom::query()->create([
        'codigo' => 'PARCEIRO30', 'tipo' => TipoDesconto::Percentual, 'valor' => 1000,
        'vigencia_inicio' => now()->subMonth()->toDateString(), 'vigencia_fim' => now()->addMonth()->toDateString(), 'ativo' => true,
    ]);

    // Ana usou o cupom, assinou e fez 2 diagnósticos.
    $this->ana = Usuario::factory()->create(['nome' => 'Ana Lima', 'email' => 'ana@exemplo.com.br', 'telefone' => '85987654321']);
    CupomUso::query()->create(['cupom_id' => $this->cupom->id, 'usuario_id' => $this->ana->id, 'desconto_centavos' => 4990, 'usado_em' => CarbonImmutable::parse('2026-11-10 15:00', 'UTC')]);
    DB::table('assinaturas')->insert([
        'id' => (string) Str::uuid7(), 'usuario_id' => $this->ana->id, 'plano_id' => Plano::query()->where('nome', 'Básico')->firstOrFail()->id,
        'trial' => false, 'status' => 'ativa', 'ciclo' => 'mensal', 'data_inicio' => now(), 'proximo_vencimento' => now()->addMonth(),
        'cota_snapshot' => 5, 'cota_restante_ciclo' => 3, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $this->ana->id]);
    Diagnostico::factory()->count(2)->create(['usuario_id' => $this->ana->id, 'empresa_analisada_id' => $empresa->id]);

    // Bruno recebeu por concessão e não usou.
    $this->bruno = Usuario::factory()->create(['nome' => 'Bruno Costa']);
    CupomConcessao::query()->create(['cupom_id' => $this->cupom->id, 'usuario_id' => $this->bruno->id, 'concedida_em' => CarbonImmutable::parse('2026-11-05 15:00', 'UTC')]);

    // Fora do cupom.
    Usuario::factory()->create(['nome' => 'Zélia Outra']);
});

it('lista quem usou e quem recebeu, com contato, plano e diagnósticos, e os totais', function () {
    $this->actingAs($this->admin)->get('/backoffice/cupons/'.$this->cupom->id.'/clientes')->assertOk()
        ->assertSee('Clientes do cupom PARCEIRO30')
        ->assertSeeInOrder(['Ana Lima', 'ana@exemplo.com.br', '(85) 98765-4321', 'Usou', '10/11/2026', 'R$ 49,90', 'Básico (ativa)'])
        ->assertSeeInOrder(['Bruno Costa', 'Recebeu, não usou', '05/11/2026', 'Sem plano'])
        ->assertDontSee('Zélia Outra')
        ->assertSee('dusk="cupom-total-clientes">2<', false)
        ->assertSee('dusk="cupom-total-usaram">1<', false)
        ->assertSee('dusk="cupom-total-plano">1<', false)
        ->assertSee('dusk="cupom-total-com-diagnostico">1<', false)
        ->assertSee('dusk="cupom-total-diagnosticos">2<', false);

    expect(AuditLog::query()->where('action', 'backoffice.cupom_clientes_vistos')->where('subject_id', $this->cupom->id)->count())->toBe(1);
});

it('quem usou e também recebeu aparece uma vez só, como Usou', function () {
    CupomConcessao::query()->create(['cupom_id' => $this->cupom->id, 'usuario_id' => $this->ana->id, 'concedida_em' => now()->subMonth()]);

    $html = $this->actingAs($this->admin)->get('/backoffice/cupons/'.$this->cupom->id.'/clientes')->getContent();
    expect(substr_count($html, 'dusk="cupom-cliente-'.$this->ana->id.'"'))->toBe(1);
});

it('usuário anonimizado aparece sem dados pessoais', function () {
    $this->ana->forceFill(['anonimizado_at' => now()])->save();

    $this->actingAs($this->admin)->get('/backoffice/cupons/'.$this->cupom->id.'/clientes')->assertOk()
        ->assertSee('Anonimizado')->assertDontSee('ana@exemplo.com.br');
});

it('cupom sem clientes mostra a lista vazia e não oferece a planilha', function () {
    $vazio = Cupom::query()->create(['codigo' => 'VAZIO', 'tipo' => TipoDesconto::Percentual, 'valor' => 100,
        'vigencia_inicio' => now()->toDateString(), 'vigencia_fim' => now()->addMonth()->toDateString(), 'ativo' => true]);

    Livewire::actingAs($this->admin)->test(ClientesDoCupom::class, ['cupom' => $vazio->id])
        ->assertSee('Ninguém recebeu nem usou este cupom ainda.')->assertDontSee('Baixar planilha');
});

it('exporta a planilha para o parceiro e audita', function () {
    $resposta = $this->actingAs($this->admin)->get('/backoffice/cupons/'.$this->cupom->id.'/clientes/exportar');

    $resposta->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    expect($resposta->headers->get('Content-Disposition'))->toContain('clientes-cupom-parceiro30-');

    $csv = $resposta->streamedContent();
    expect($csv)->toStartWith("\xEF\xBB\xBF")
        ->toContain('Nome;E-mail;WhatsApp;Situação;Desde')
        ->toContain('"Ana Lima";ana@exemplo.com.br;"(85) 98765-4321";Usou;10/11/2026;49,90;"Básico (ativa)";1;2;')
        ->toContain('"Bruno Costa"')
        ->not->toContain('Zélia');

    expect(AuditLog::query()->where('action', 'backoffice.cupom_clientes_exportados')->sole()->context['linhas'])->toBe(2);
});

it('a lista de cupons leva ao relatório', function () {
    $this->actingAs($this->admin)->get('/backoffice/cupons')->assertOk()->assertSee(route('backoffice.cupons.clientes', $this->cupom->id));
});

it('sem a permissão de cupons, relatório e planilha respondem 404', function () {
    $outro = Usuario::factory()->create();
    $outro->forceFill(['permissoes_admin' => ['usuarios.ver']])->save();

    $this->actingAs($outro)->get('/backoffice/cupons/'.$this->cupom->id.'/clientes')->assertNotFound();
    $this->actingAs($outro)->get('/backoffice/cupons/'.$this->cupom->id.'/clientes/exportar')->assertNotFound();
});

it('cupom inexistente responde 404', function () {
    $this->actingAs($this->admin)->get('/backoffice/cupons/019a0000-0000-7000-8000-000000000000/clientes')->assertNotFound();
});

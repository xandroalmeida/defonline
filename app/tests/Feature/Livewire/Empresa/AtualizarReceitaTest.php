<?php

declare(strict_types=1);

use App\Domain\FonteEnriquecimento;
use App\Domain\Rfb\RfbCnpjResult;
use App\Livewire\Empresa\AtualizarReceita;
use App\Livewire\Empresa\Cadastrar;
use App\Models\AuditLog;
use App\Models\EmpresaAnalisada;
use App\Models\Usuario;
use App\Services\Rfb\MockRfbCnpjClient;
use App\Services\Rfb\RfbCnpjClient;
use App\Services\Rfb\RfbConsultarCnpj;
use App\Support\DataExibicao;
use Database\Factories\EmpresaAnalisadaFactory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

/**
 * STORY-064 — "Atualizar dados da Receita" em /empresas/{id}. Provedor mock, sem rede.
 */
beforeEach(function () {
    Cache::flush();
    $this->usuario = Usuario::factory()->create();
    $this->actingAs($this->usuario);
});

function empresaLegada(Usuario $usuario, string $raiz = '45237000', array $extra = []): EmpresaAnalisada
{
    return EmpresaAnalisada::factory()->create([
        'usuario_id' => $usuario->id,
        'documento' => EmpresaAnalisadaFactory::cnpjComRaiz($raiz),
        'razao_social' => 'Digitada à mão LTDA',
        'nome_fantasia' => 'Digitada',
        'municipio' => 'Lugar Nenhum',
        'uf' => 'AC',
        'fonte_enriquecimento' => FonteEnriquecimento::Manual,
        'observacoes' => 'Minha anotação',
        'ficha_rfb' => null,
        ...$extra,
    ]);
}

/** Cliente que conta chamadas; troca o provedor para que o cache de 5 min valha. */
function clienteContador(): object
{
    $cliente = new class implements RfbCnpjClient
    {
        public int $chamadas = 0;

        public function consultarCnpj(string $cnpj): RfbCnpjResult
        {
            $this->chamadas++;

            return (new MockRfbCnpjClient)->consultarCnpj($cnpj);
        }
    };
    config(['services.rfb.provider' => 'cnpja']);
    app()->instance(RfbCnpjClient::class, $cliente);

    return $cliente;
}

it('mostra a ação e o aviso de legada na empresa manual com CNPJ (CA-1, CA-2)', function () {
    $empresa = empresaLegada($this->usuario);

    $this->get("/empresas/{$empresa->id}")
        ->assertOk()
        ->assertSee('Atualizar dados da Receita')
        ->assertSee('Estes dados não foram confirmados na Receita. Atualize para ver a ficha completa.')
        ->assertSee('Digitada à mão LTDA')
        ->assertSee('Lugar Nenhum / AC');
});

it('trata como legada a empresa rfb sem ficha completa e mostra a data da consulta (CA-1, CA-2)', function () {
    $empresa = empresaLegada($this->usuario, extra: [
        'fonte_enriquecimento' => FonteEnriquecimento::Rfb,
        'enriquecido_at' => '2026-03-04 10:00:00',
    ]);

    $this->get("/empresas/{$empresa->id}")
        ->assertSee('Estes dados não foram confirmados na Receita')
        ->assertSee('Dados da Receita de 04/03/2026');
});

it('não mostra aviso na empresa com ficha completa, mas mostra a data (CA-1)', function () {
    $empresa = empresaLegada($this->usuario);
    Livewire::test(AtualizarReceita::class, ['empresa' => $empresa])->call('atualizar');

    $this->get("/empresas/{$empresa->id}")
        ->assertSee('Atualizar dados da Receita')
        ->assertSee('Dados da Receita de '.DataExibicao::data(now()))
        ->assertDontSee('Estes dados não foram confirmados');
});

it('não mostra a ação para empresa com CPF (CA-1)', function () {
    $cpf = EmpresaAnalisada::factory()->comoCpf()->create(['usuario_id' => $this->usuario->id]);

    $this->get("/empresas/{$cpf->id}")->assertOk()->assertDontSee('Atualizar dados da Receita');

    Livewire::test(AtualizarReceita::class, ['empresa' => $cpf])
        ->call('atualizar')
        ->assertNoRedirect();
    expect($cpf->fresh()->ficha_rfb)->toBeNull();
});

it('substitui a ficha inteira, vira rfb, preserva observações e audita só nomes de campos (CA-3)', function () {
    $empresa = empresaLegada($this->usuario, extra: ['nome_fantasia_informado' => 'Meu Nome', 'email' => 'a@b.com']);
    $esperado = (new MockRfbCnpjClient)->consultarCnpj($empresa->documento);

    Livewire::test(AtualizarReceita::class, ['empresa' => $empresa])
        ->call('atualizar')
        ->assertRedirect(route('empresas.show', $empresa));

    $nova = $empresa->fresh();
    expect($nova->fonte_enriquecimento)->toBe(FonteEnriquecimento::Rfb)
        ->and($nova->razao_social)->toBe($esperado->razaoSocial)
        ->and($nova->municipio)->toBe($esperado->municipio)
        ->and($nova->enriquecido_at)->not->toBeNull()
        ->and($nova->ficha_rfb)->toEqual($esperado->ficha->toArray())
        ->and($nova->observacoes)->toBe('Minha anotação')
        ->and($nova->nome_fantasia_informado)->toBe('Meu Nome')
        ->and($nova->email)->toBe('a@b.com');

    $audit = AuditLog::query()->where('action', 'empresa.rfb_atualizada')->sole();
    expect($audit->subject_id)->toBe($empresa->id)
        ->and($audit->context['campos_alterados'])->toContain('razao_social', 'fonte_enriquecimento', 'socios', 'endereco', 'cnaes_secundarios')
        ->and(json_encode($audit->context))->not->toContain($esperado->razaoSocial)
        ->not->toContain($empresa->documento);
});

it('audita só os campos que mudaram numa segunda atualização (CA-3)', function () {
    $empresa = empresaLegada($this->usuario);
    Livewire::test(AtualizarReceita::class, ['empresa' => $empresa])->call('atualizar');
    $empresa->refresh()->forceFill(['razao_social' => 'Mudou', 'enriquecido_at' => now()->subMinutes(10)])->save();
    Cache::flush();
    $anteriores = DB::table('audit_logs')->where('action', 'empresa.rfb_atualizada')->pluck('id')->all();

    Livewire::test(AtualizarReceita::class, ['empresa' => $empresa])->call('atualizar');

    $audit = DB::table('audit_logs')->where('action', 'empresa.rfb_atualizada')->whereNotIn('id', $anteriores)->sole();
    expect(json_decode($audit->context, true)['campos_alterados'])->toBe(['razao_social']);
});

it('mantém a ficha anterior e mostra a mensagem do CA-7 da STORY-063 na falha (CA-4)', function (string $raiz, string $mensagem) {
    $empresa = empresaLegada($this->usuario, $raiz);

    Livewire::test(AtualizarReceita::class, ['empresa' => $empresa])
        ->call('atualizar')
        ->assertNoRedirect()
        ->assertSet('erro', $mensagem)
        ->assertSee($mensagem);

    $igual = $empresa->fresh();
    expect($igual->razao_social)->toBe('Digitada à mão LTDA')
        ->and($igual->fonte_enriquecimento)->toBe(FonteEnriquecimento::Manual)
        ->and($igual->ficha_rfb)->toBeNull()
        ->and(AuditLog::query()->where('action', 'empresa.rfb_atualizada')->count())->toBe(0);
})->with([
    'provedor fora' => ['99887766', Cadastrar::MSG_RECEITA_INDISPONIVEL],
    'cnpj inexistente' => ['00112233', Cadastrar::MSG_CNPJ_INEXISTENTE],
]);

it('usa o cache da consulta e avisa que já está atualizado (CA-5)', function () {
    $cliente = clienteContador();
    $empresa = empresaLegada($this->usuario);

    $primeira = Livewire::test(AtualizarReceita::class, ['empresa' => $empresa])->call('atualizar');
    $primeira->assertRedirect();
    $this->get("/empresas/{$empresa->id}")->assertSee('Dados da Receita atualizados.');

    Livewire::test(AtualizarReceita::class, ['empresa' => $empresa->fresh()])->call('atualizar')->assertRedirect();
    $this->get("/empresas/{$empresa->id}")->assertSee('Os dados da Receita já estão atualizados.');

    expect($cliente->chamadas)->toBe(1)
        ->and(AuditLog::query()->where('action', 'empresa.rfb_atualizada')->count())->toBe(1);
});

it('reaproveita a consulta do cadastro feita há pouco (CA-5)', function () {
    $cliente = clienteContador();
    $cnpj = EmpresaAnalisadaFactory::cnpjComRaiz('45237000');
    $consulta = app(RfbConsultarCnpj::class)->executar($cnpj);
    $empresa = EmpresaAnalisada::factory()->create([
        'usuario_id' => $this->usuario->id,
        'documento' => $cnpj,
        'fonte_enriquecimento' => FonteEnriquecimento::Rfb,
        'enriquecido_at' => $consulta->consultadoAt,
        'ficha_rfb' => $consulta->ficha->toArray(),
    ]);

    Livewire::test(AtualizarReceita::class, ['empresa' => $empresa])->call('atualizar');

    expect($cliente->chamadas)->toBe(1)
        ->and(AuditLog::query()->where('action', 'empresa.rfb_atualizada')->count())->toBe(0);
});

it('não aceita trocar o id da empresa por payload forjado e só enxerga empresas do próprio usuário', function () {
    $empresa = empresaLegada($this->usuario);
    $alheia = empresaLegada(Usuario::factory()->create(), '12345678');

    $teste = Livewire::test(AtualizarReceita::class, ['empresa' => $empresa]);
    expect(fn () => $teste->set('empresaId', $alheia->id))->toThrow(CannotUpdateLockedPropertyException::class);
    expect(fn () => $teste->set('legada', false))->toThrow(CannotUpdateLockedPropertyException::class);
    expect($alheia->fresh()->ficha_rfb)->toBeNull();
});

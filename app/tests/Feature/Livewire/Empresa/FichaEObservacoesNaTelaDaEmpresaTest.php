<?php

declare(strict_types=1);

use App\Domain\FonteEnriquecimento;
use App\Livewire\Empresa\Cadastrar;
use App\Livewire\Empresa\Observacoes;
use App\Models\AuditLog;
use App\Models\EmpresaAnalisada;
use App\Models\Usuario;
use App\Services\Rfb\MockRfbCnpjClient;
use Database\Factories\EmpresaAnalisadaFactory;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

/**
 * STORY-063 CA-3/CA-4/CA-5 — ficha RFB só-leitura e observações editáveis
 * em /empresas/{id}.
 */
beforeEach(function () {
    $this->usuario = Usuario::factory()->create();
    $this->actingAs($this->usuario);
});

function empresaComFicha(Usuario $usuario, string $raiz = '45237000'): EmpresaAnalisada
{
    $cnpj = EmpresaAnalisadaFactory::cnpjComRaiz($raiz);
    $r = (new MockRfbCnpjClient)->consultarCnpj($cnpj);

    return EmpresaAnalisada::factory()->create([
        'usuario_id' => $usuario->id,
        'documento' => $cnpj,
        'razao_social' => $r->razaoSocial,
        'fonte_enriquecimento' => FonteEnriquecimento::Rfb,
        'enriquecido_at' => now(),
        'ficha_rfb' => $r->ficha->toArray(),
        'observacoes' => 'Anotação inicial',
    ]);
}

it('mostra a ficha completa só-leitura e as observações (CA-3, CA-4, CA-5)', function () {
    $empresa = empresaComFicha($this->usuario);
    $ficha = $empresa->fichaRfb();

    $resposta = $this->get(route('empresas.show', $empresa))
        ->assertOk()
        ->assertSee('Dados da Receita de')
        ->assertSee('Regime tributário')
        ->assertSee('Sócios e administradores')
        ->assertSee('Anotação inicial')
        ->assertSee('Salvar observações');

    foreach ($ficha->socios as $socio) {
        $resposta->assertSee($socio->nome);
    }
    foreach ($ficha->cnaesSecundarios as $atividade) {
        $resposta->assertSee($atividade->codigoFormatado());
    }

    // O único campo editável da tela é o das observações (o CSRF do logout é hidden).
    expect(preg_match_all('/<input(?![^>]*type="hidden")/', $resposta->getContent()))->toBe(0);
    expect(substr_count($resposta->getContent(), '<textarea'))->toBe(1);
});

it('empresa legada sem ficha continua abrindo com os campos antigos', function () {
    $empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $this->usuario->id]);

    $this->get(route('empresas.show', $empresa))
        ->assertOk()
        ->assertSee($empresa->razao_social)
        ->assertSee('Município / UF')
        ->assertDontSee('Regime tributário')
        ->assertSee('Salvar observações');
});

it('salva observações com audit log sem o conteúdo do texto (CA-5)', function () {
    $empresa = empresaComFicha($this->usuario);
    $fichaAntes = $empresa->ficha_rfb;

    Livewire::test(Observacoes::class, ['empresa' => $empresa])
        ->assertSet('observacoes', 'Anotação inicial')
        ->set('observacoes', '  Sócio Roberto prefere contato por WhatsApp.  ')
        ->call('salvar')
        ->assertHasNoErrors()
        ->assertSet('salvo', true)
        ->assertSee('Observações salvas.');

    $empresa->refresh();
    expect($empresa->observacoes)->toBe('Sócio Roberto prefere contato por WhatsApp.');
    expect($empresa->ficha_rfb)->toEqual($fichaAntes);

    $audit = AuditLog::where('action', 'empresa.observacoes_alteradas')->sole();
    expect($audit->subject_id)->toBe($empresa->id);
    expect(json_encode($audit->getAttributes()))->not->toContain('WhatsApp');
});

it('apagar as observações grava null; salvar sem mudança não gera audit (CA-5)', function () {
    $empresa = empresaComFicha($this->usuario);

    Livewire::test(Observacoes::class, ['empresa' => $empresa])
        ->set('observacoes', 'Anotação inicial')
        ->call('salvar')
        ->set('observacoes', '   ')
        ->call('salvar');

    expect($empresa->refresh()->observacoes)->toBeNull();
    expect(AuditLog::where('action', 'empresa.observacoes_alteradas')->count())->toBe(1);
});

it('rejeita observações acima de 2.000 caracteres (CA-5)', function () {
    $empresa = empresaComFicha($this->usuario);

    Livewire::test(Observacoes::class, ['empresa' => $empresa])
        ->set('observacoes', str_repeat('a', Cadastrar::OBSERVACOES_MAX + 1))
        ->call('salvar')
        ->assertHasErrors(['observacoes'])
        ->assertSet('salvo', false);

    expect($empresa->refresh()->observacoes)->toBe('Anotação inicial');
});

it('não deixa trocar a empresa alvo nem alcançar empresa de outro usuário (CA-4)', function () {
    $minha = empresaComFicha($this->usuario);
    $alheia = empresaComFicha(Usuario::factory()->create(), '45315000');

    $c = Livewire::test(Observacoes::class, ['empresa' => $minha]);
    expect(fn () => $c->set('empresaId', $alheia->id))->toThrow(CannotUpdateLockedPropertyException::class);

    // Mesmo que o id chegasse, o Global Scope não resolve empresa alheia.
    $forjado = new Observacoes;
    $forjado->empresaId = $alheia->id;
    $forjado->observacoes = 'invasão';
    expect(fn () => $forjado->salvar())->toThrow(ModelNotFoundException::class);
    expect(EmpresaAnalisada::withoutGlobalScopes()->find($alheia->id)->observacoes)->toBe('Anotação inicial');
});

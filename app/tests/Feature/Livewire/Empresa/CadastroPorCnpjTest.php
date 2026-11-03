<?php

declare(strict_types=1);

use App\Domain\Cpf;
use App\Domain\FonteEnriquecimento;
use App\Domain\Rfb\RfbCnpjResult;
use App\Domain\SituacaoCadastral;
use App\Domain\TipoDocumento;
use App\Livewire\Empresa\Cadastrar;
use App\Models\AuditLog;
use App\Models\EmpresaAnalisada;
use App\Models\Scopes\BelongsToUsuarioScope;
use App\Models\Usuario;
use App\Services\Rfb\RfbCnpjClient;
use App\Services\Rfb\RfbCnpjResultSerializer;
use Database\Factories\EmpresaAnalisadaFactory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

/**
 * STORY-063 (PDR-010) — cadastro de empresa pelo CNPJ com ficha RFB
 * só-leitura. Provedor mock: o 3º dígito do CNPJ escolhe o regime, o 4º a
 * quantidade de CNAEs secundários e o 5º a de sócios (MockRfbCnpjClient).
 */
beforeEach(function () {
    $this->usuario = Usuario::factory()->create();
    $this->actingAs($this->usuario);
});

function consultasRfbRegistradas(): int
{
    return DB::table('business_metrics')->where('tipo', 'rfb_consulta')->count();
}

it('antes da consulta mostra só o CNPJ — nenhum dado da empresa (CA-1)', function () {
    Livewire::test(Cadastrar::class)
        ->assertSet('tipo_documento', TipoDocumento::Cnpj->value)
        ->assertSee('Consultar')
        ->assertDontSee('Razão social')
        ->assertDontSee('Confirmar e cadastrar');
});

it('CNPJ com DV inválido não consulta a Receita (CA-2)', function () {
    Livewire::test(Cadastrar::class)
        ->set('documento', '11.222.333/0001-82')
        ->call('consultarReceita')
        ->assertSet('mensagemErro', Cadastrar::MSG_CNPJ_INVALIDO)
        ->assertSet('resultadoRfb', null);

    expect(consultasRfbRegistradas())->toBe(0);
});

it('CNPJ já cadastrado pelo usuário não consulta e mostra link para a empresa (CA-2)', function () {
    $cnpj = EmpresaAnalisadaFactory::cnpjComRaiz('45237000');
    $existente = EmpresaAnalisada::factory()->create(['usuario_id' => $this->usuario->id, 'documento' => $cnpj]);

    Livewire::test(Cadastrar::class)
        ->set('documento', $cnpj)
        ->call('consultarReceita')
        ->assertSet('mensagemErro', Cadastrar::MSG_JA_CADASTRADA)
        ->assertSet('empresaExistenteId', $existente->id)
        ->assertSee(route('empresas.show', $existente->id))
        ->assertSet('resultadoRfb', null);

    expect(consultasRfbRegistradas())->toBe(0);
});

it('consulta bem-sucedida exibe a ficha completa só-leitura (CA-3)', function () {
    $cnpj = EmpresaAnalisadaFactory::cnpjComRaiz('45237000'); // Simples, 3 secundárias, 3 sócios

    $c = Livewire::test(Cadastrar::class)
        ->set('documento', $cnpj)
        ->call('consultarReceita')
        ->assertSet('statusConsultaRfb', 'sucesso')
        ->assertSet('cnpjConsultado', $cnpj);

    $r = RfbCnpjResultSerializer::fromArray($c->get('resultadoRfb'));

    $c->assertSee($r->razaoSocial)
        ->assertSee('Natureza jurídica')
        ->assertSee($r->ficha->capitalSocialFormatado())
        ->assertSee($r->ficha->cnaePrincipal->codigoFormatado())
        ->assertSee('Simples Nacional')
        ->assertSee('MEI (SIMEI)')
        ->assertSee($r->ficha->endereco->cepFormatado())
        ->assertSee('Confirmar e cadastrar');

    foreach ($r->ficha->cnaesSecundarios as $atividade) {
        $c->assertSee($atividade->codigoFormatado());
    }
    foreach ($r->ficha->socios as $socio) {
        $c->assertSee($socio->nome);
    }

    // CA-4: nenhum input para os dados oficiais.
    $html = $c->html();
    expect($html)->not->toContain('id="razao_social"');
    expect($html)->not->toContain('id="cnae"');
    expect($html)->not->toContain('id="municipio"');
});

it('campo ausente na resposta aparece como "Não informado pela Receita" (CA-3)', function () {
    app()->instance(RfbCnpjClient::class, new class implements RfbCnpjClient
    {
        public function consultarCnpj(string $cnpj): RfbCnpjResult
        {
            return new RfbCnpjResult('EMPRESA MÍNIMA LTDA', null, null, 'Curitiba', 'PR', SituacaoCadastral::Ativa, null, 'fake', Carbon::now());
        }
    });

    Livewire::test(Cadastrar::class)
        ->set('documento', EmpresaAnalisadaFactory::cnpjComRaiz('45237000'))
        ->call('consultarReceita')
        ->assertSee('EMPRESA MÍNIMA LTDA')
        ->assertSee('Não informado pela Receita')
        ->assertSee('Nenhuma atividade secundária na Receita')
        ->assertSee('Nenhum sócio ou administrador informado pela Receita');
});

it('o cliente não consegue forjar nem alterar a ficha da Receita (CA-4)', function (string $propriedade, mixed $valor) {
    $c = Livewire::test(Cadastrar::class)
        ->set('documento', EmpresaAnalisadaFactory::cnpjComRaiz('45237000'))
        ->call('consultarReceita');

    expect(fn () => $c->set($propriedade, $valor))->toThrow(CannotUpdateLockedPropertyException::class);
})->with([
    'ficha' => ['resultadoRfb', ['razao_social' => 'FORJADA']],
    'cnpj' => ['cnpjConsultado', '11222333000181'],
    'status' => ['statusConsultaRfb', 'sucesso'],
]);

it('confirmar sem consulta não grava nada, mesmo com campos manuais preenchidos (CA-4)', function () {
    Livewire::test(Cadastrar::class)
        ->set('documento', EmpresaAnalisadaFactory::cnpjComRaiz('45237000'))
        ->set('razao_social', 'FORJADA LTDA')
        ->set('municipio', 'X')
        ->set('uf', 'SP')
        ->call('confirmar')
        ->assertNoRedirect()
        ->assertSet('mensagemErro', 'Consulte o CNPJ na Receita antes de confirmar.');

    // submit() em modo CNPJ também não aceita o formulário manual.
    Livewire::test(Cadastrar::class)
        ->set('documento', EmpresaAnalisadaFactory::cnpjComRaiz('45237000'))
        ->set('razao_social', 'FORJADA LTDA')
        ->set('municipio', 'X')
        ->set('uf', 'SP')
        ->call('submit')
        ->assertNoRedirect();

    expect(EmpresaAnalisada::count())->toBe(0);
});

it('campos manuais setados junto com a consulta são ignorados na gravação (CA-4)', function () {
    $cnpj = EmpresaAnalisadaFactory::cnpjComRaiz('45237000');

    $c = Livewire::test(Cadastrar::class)
        ->set('documento', $cnpj)
        ->call('consultarReceita');
    $r = RfbCnpjResultSerializer::fromArray($c->get('resultadoRfb'));

    $c->set('razao_social', 'FORJADA LTDA')
        ->set('uf', 'AC')
        ->set('cnae', '9999999')
        ->call('confirmar')
        ->assertRedirect();

    $empresa = EmpresaAnalisada::firstWhere('documento', $cnpj);
    expect($empresa->razao_social)->toBe($r->razaoSocial);
    expect($empresa->uf)->toBe($r->uf);
    expect($empresa->cnae)->toBe($r->cnae);
});

it('confirmar grava a ficha completa, fonte rfb e observações (CA-5, CA-6)', function () {
    $cnpj = EmpresaAnalisadaFactory::cnpjComRaiz('41100000'); // MEI

    $c = Livewire::test(Cadastrar::class)
        ->set('documento', $cnpj)
        ->call('consultarReceita');
    $r = RfbCnpjResultSerializer::fromArray($c->get('resultadoRfb'));

    $c->set('observacoes', '  Cliente do escritório desde 2020.  ')
        ->call('confirmar')
        ->assertHasNoErrors();

    $empresa = EmpresaAnalisada::firstWhere('documento', $cnpj);
    $c->assertRedirect("/empresas/{$empresa->id}");

    expect($empresa->usuario_id)->toBe($this->usuario->id);
    expect($empresa->tipo_documento)->toBe(TipoDocumento::Cnpj);
    expect($empresa->fonte_enriquecimento)->toBe(FonteEnriquecimento::Rfb);
    expect($empresa->enriquecido_at)->not->toBeNull();
    expect($empresa->razao_social)->toBe($r->razaoSocial);
    expect($empresa->cnae)->toBe($r->cnae);
    expect($empresa->uf)->toBe($r->uf);
    expect($empresa->situacao_cadastral)->toBe($r->situacaoCadastral);
    expect($empresa->observacoes)->toBe('Cliente do escritório desde 2020.');
    expect($empresa->fichaRfb())->toEqual($r->ficha);
    expect($empresa->fichaRfb()->simei->optante)->toBeTrue();

    $audit = AuditLog::where('action', 'empresa.cadastrada')->firstOrFail();
    expect(json_encode($audit->getAttributes()))->not->toContain($cnpj);
});

it('observação vazia vira null e acima de 2.000 caracteres é rejeitada (CA-5)', function () {
    $cnpj = EmpresaAnalisadaFactory::cnpjComRaiz('45237000');

    Livewire::test(Cadastrar::class)
        ->set('documento', $cnpj)
        ->call('consultarReceita')
        ->set('observacoes', str_repeat('a', 2001))
        ->call('confirmar')
        ->assertHasErrors(['observacoes'])
        ->set('observacoes', '   ')
        ->call('confirmar')
        ->assertRedirect();

    expect(EmpresaAnalisada::firstWhere('documento', $cnpj)->observacoes)->toBeNull();
});

it('falha da Receita bloqueia o cadastro sem liberar preenchimento manual (CA-7)', function (string $raiz, string $status, string $mensagem) {
    Livewire::test(Cadastrar::class)
        ->set('documento', EmpresaAnalisadaFactory::cnpjComRaiz($raiz))
        ->call('consultarReceita')
        ->assertSet('statusConsultaRfb', $status)
        ->assertSet('mensagemErro', $mensagem)
        ->assertSet('resultadoRfb', null)
        ->assertSee($mensagem)
        ->assertDontSee('Razão social')
        ->assertDontSee('Confirmar e cadastrar')
        ->call('confirmar')
        ->assertNoRedirect();

    expect(EmpresaAnalisada::count())->toBe(0);
    expect(consultasRfbRegistradas())->toBe(1); // métrica continua sendo emitida
})->with([
    'inexistente' => ['00112233', 'cnpj_inexistente', Cadastrar::MSG_CNPJ_INEXISTENTE],
    'timeout' => ['99887766', 'timeout', Cadastrar::MSG_RECEITA_INDISPONIVEL],
    'erro 5xx' => ['88776655', 'erro_5xx', Cadastrar::MSG_RECEITA_INDISPONIVEL],
    'erro de rede' => ['77665544', 'erro_rede', Cadastrar::MSG_RECEITA_INDISPONIVEL],
]);

it('empresa não ativa pode ser cadastrada, com aviso destacado da situação (CA-8)', function () {
    $cnpj = EmpresaAnalisadaFactory::cnpjComRaiz('42900000');

    Livewire::test(Cadastrar::class)
        ->set('documento', $cnpj)
        ->call('consultarReceita')
        ->assertSee('empresa BAIXADA na Receita desde 30/06/2025')
        ->call('confirmar')
        ->assertRedirect();

    expect(EmpresaAnalisada::firstWhere('documento', $cnpj)->situacao_cadastral)->toBe(SituacaoCadastral::Baixada);
});

it('editar o CNPJ depois da consulta descarta a ficha (CA-4)', function () {
    Livewire::test(Cadastrar::class)
        ->set('documento', EmpresaAnalisadaFactory::cnpjComRaiz('45237000'))
        ->call('consultarReceita')
        ->assertNotSet('resultadoRfb', null)
        ->set('documento', EmpresaAnalisadaFactory::cnpjComRaiz('45315000'))
        ->assertSet('resultadoRfb', null)
        ->assertSet('cnpjConsultado', null)
        ->call('confirmar')
        ->assertNoRedirect();
});

it('trocar para CPF descarta a consulta e o tipo inválido volta para CNPJ (CA-9)', function () {
    Livewire::test(Cadastrar::class)
        ->set('documento', EmpresaAnalisadaFactory::cnpjComRaiz('45237000'))
        ->call('consultarReceita')
        ->call('usarCpf')
        ->assertSet('resultadoRfb', null)
        ->assertSet('documento', '')
        ->set('tipo_documento', 'passaporte')
        ->assertSet('tipo_documento', TipoDocumento::Cnpj->value);
});

it('CPF válido no campo único segue para o cadastro manual com o CPF preenchido', function () {
    $cpf = EmpresaAnalisadaFactory::gerarCpfValido();

    Livewire::test(Cadastrar::class)
        ->set('documento', $cpf)
        ->call('consultarReceita')
        ->assertSet('tipo_documento', TipoDocumento::Cpf->value)
        ->assertSet('documento', Cpf::formatar($cpf))
        ->assertSet('mensagemErro', null)
        ->assertSee('Razão social')
        ->set('razao_social', 'Joana Autônoma')
        ->set('municipio', 'Belo Horizonte')
        ->set('uf', 'MG')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertRedirect();

    expect(EmpresaAnalisada::firstWhere('documento', $cpf)->tipo_documento)->toBe(TipoDocumento::Cpf);
    expect(consultasRfbRegistradas())->toBe(0);
});

it('CPF inválido ou documento com tamanho errado no campo único é rejeitado', function (string $doc) {
    Livewire::test(Cadastrar::class)
        ->set('documento', $doc)
        ->call('consultarReceita')
        ->assertSet('tipo_documento', TipoDocumento::Cnpj->value)
        ->assertSet('mensagemErro', Cadastrar::MSG_CNPJ_INVALIDO);

    expect(consultasRfbRegistradas())->toBe(0);
})->with([
    'CPF com DV errado' => ['123.456.789-00'],
    'CPF sequência trivial' => ['111.111.111-11'],
    'tamanho intermediário' => ['1234567890123'],
]);

it('CPF já cadastrado pelo usuário no campo único mostra link para a empresa', function () {
    $cpf = EmpresaAnalisadaFactory::gerarCpfValido();
    $existente = EmpresaAnalisada::factory()->create([
        'usuario_id' => $this->usuario->id, 'documento' => $cpf, 'tipo_documento' => TipoDocumento::Cpf->value,
    ]);

    Livewire::test(Cadastrar::class)
        ->set('documento', $cpf)
        ->call('consultarReceita')
        ->assertSet('tipo_documento', TipoDocumento::Cnpj->value)
        ->assertSet('mensagemErro', Cadastrar::MSG_JA_CADASTRADA)
        ->assertSet('empresaExistenteId', $existente->id);
});

it('consulta em modo CPF é no-op', function () {
    Livewire::test(Cadastrar::class)
        ->call('usarCpf')
        ->set('documento', EmpresaAnalisadaFactory::gerarCpfValido())
        ->call('consultarReceita')
        ->assertSet('statusConsultaRfb', null);

    expect(consultasRfbRegistradas())->toBe(0);
});

it('outro usuário pode cadastrar o mesmo CNPJ; o próprio pode recadastrar após soft delete', function () {
    $cnpj = EmpresaAnalisadaFactory::cnpjComRaiz('45237000');
    $original = EmpresaAnalisada::factory()->create(['usuario_id' => $this->usuario->id, 'documento' => $cnpj]);

    $this->actingAs(Usuario::factory()->create());
    Livewire::test(Cadastrar::class)
        ->set('documento', $cnpj)->call('consultarReceita')->call('confirmar')->assertRedirect();

    $this->actingAs($this->usuario);
    $original->delete();
    Livewire::test(Cadastrar::class)
        ->set('documento', $cnpj)->call('consultarReceita')->call('confirmar')->assertRedirect();

    expect(EmpresaAnalisada::withoutGlobalScope(BelongsToUsuarioScope::class)
        ->where('documento', $cnpj)->whereNull('deleted_at')->count())->toBe(2);
});

it('cadastro concorrente do mesmo CNPJ entre consulta e confirmação é barrado (CA-2)', function () {
    $cnpj = EmpresaAnalisadaFactory::cnpjComRaiz('45237000');

    $c = Livewire::test(Cadastrar::class)
        ->set('documento', $cnpj)
        ->call('consultarReceita');

    EmpresaAnalisada::factory()->create(['usuario_id' => $this->usuario->id, 'documento' => $cnpj]);

    $c->call('confirmar')
        ->assertNoRedirect()
        ->assertSet('mensagemErro', Cadastrar::MSG_JA_CADASTRADA);

    expect(EmpresaAnalisada::where('documento', $cnpj)->count())->toBe(1);
});

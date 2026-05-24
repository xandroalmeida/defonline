<?php

declare(strict_types=1);

use App\Domain\FonteEnriquecimento;
use App\Domain\SituacaoCadastral;
use App\Domain\TipoDocumento;
use App\Livewire\Empresa\Cadastrar;
use App\Models\EmpresaAnalisada;
use App\Models\EventoProduto;
use App\Models\Usuario;
use Database\Factories\EmpresaAnalisadaFactory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

/**
 * STORY-016 CA-3 / CA-4 — emissão de eventos de produto.
 *
 * - `usuario_cadastrado` emitido após o Usuário confirmar o email (ADR-004 §2.2).
 * - `empresa_cadastrada` emitido na mesma transação do save da Empresa.
 * - Schema sem PII (cobertura adicional do PiiEmEventoException já existente em EventLoggerTest).
 */
function linkAssinadoConfirmar(Usuario $u): string
{
    return URL::temporarySignedRoute(
        'email.confirmar',
        Carbon::now()->addMinutes(60),
        ['usuario' => $u->id],
    );
}

it('NÃO emite usuario_cadastrado no submit do cadastro — só após confirmar o email', function () {
    /** @var Usuario $usuario */
    $usuario = Usuario::factory()->unconfirmed()->create();

    expect(EventoProduto::where('nome_evento', 'usuario_cadastrado')->count())->toBe(0);

    $this->get(linkAssinadoConfirmar($usuario))->assertRedirect(route('email.confirmado'));

    expect(EventoProduto::where('nome_evento', 'usuario_cadastrado')->count())->toBe(1);
});

it('grava usuario_cadastrado com schema correto e sem PII após confirmação do email (CA-3)', function () {
    $usuario = Usuario::factory()->unconfirmed()->create([
        'email' => 'roberto.evento@exemplo.com.br',
        'nome' => 'Roberto Souza',
    ]);

    $this->get(linkAssinadoConfirmar($usuario))->assertRedirect();

    /** @var EventoProduto|null $evento */
    $evento = EventoProduto::where('nome_evento', 'usuario_cadastrado')
        ->where('usuario_id', $usuario->id)
        ->first();

    expect($evento)->not->toBeNull();
    expect($evento->empresa_id)->toBeNull();
    expect($evento->request_id)->not->toBeEmpty();

    $propriedades = (array) $evento->propriedades;
    expect($propriedades)->toHaveKey('plano_inicial', 'basico_beta');

    // Defesa explícita — schema NÃO carrega PII.
    $serializado = json_encode($propriedades);
    expect($serializado)->not->toContain('roberto.evento@exemplo.com.br');
    expect($serializado)->not->toContain($usuario->cpf);
    expect($serializado)->not->toContain($usuario->telefone);
    expect($serializado)->not->toContain('Roberto Souza');
});

it('NÃO emite usuario_cadastrado se o email já estava confirmado (idempotência relativa)', function () {
    $usuario = Usuario::factory()->create(); // factory: confirmed by default

    $this->get(linkAssinadoConfirmar($usuario)); // controller redireciona com motivo "ja_confirmado"

    expect(EventoProduto::where('nome_evento', 'usuario_cadastrado')->count())->toBe(0);
});

it('grava empresa_cadastrada com schema correto e sem PII no cadastro manual de CNPJ (CA-4)', function () {
    $usuario = Usuario::factory()->create();
    $this->actingAs($usuario);

    $cnpj = EmpresaAnalisadaFactory::gerarCnpjValido();

    Livewire::test(Cadastrar::class)
        ->set('tipo_documento', TipoDocumento::Cnpj->value)
        ->set('documento', $cnpj)
        ->set('razao_social', 'Marcenaria Roberto LTDA')
        ->set('cnae', '3101200')
        ->set('municipio', 'Curitiba')
        ->set('uf', 'PR')
        ->set('situacao_cadastral', SituacaoCadastral::Ativa->value)
        ->call('submit')
        ->assertRedirect();

    /** @var EmpresaAnalisada $empresa */
    $empresa = EmpresaAnalisada::firstWhere('documento', $cnpj);

    /** @var EventoProduto|null $evento */
    $evento = EventoProduto::where('nome_evento', 'empresa_cadastrada')
        ->where('empresa_id', $empresa->id)
        ->first();

    expect($evento)->not->toBeNull();
    expect($evento->usuario_id)->toBe($usuario->id);
    expect($evento->request_id)->not->toBeEmpty();

    $propriedades = (array) $evento->propriedades;
    expect($propriedades)->toMatchArray([
        'empresa_id' => $empresa->id,
        'tipo_documento' => 'cnpj',
        'fonte_enriquecimento' => 'manual',
        'uf' => 'PR',
        'cnae_2digitos' => '31',
    ]);

    // PII ausente do payload.
    $serializado = json_encode($propriedades);
    expect($serializado)->not->toContain($cnpj);
    expect($serializado)->not->toContain($empresa->razao_social);
});

it('cnae_2digitos vira null quando a empresa não tem CNAE informado (CPF autônomo)', function () {
    $usuario = Usuario::factory()->create();
    $this->actingAs($usuario);

    $cpf = EmpresaAnalisadaFactory::gerarCpfValido();

    Livewire::test(Cadastrar::class)
        ->set('tipo_documento', TipoDocumento::Cpf->value)
        ->set('documento', $cpf)
        ->set('razao_social', 'Joana — Costureira Autônoma')
        ->set('municipio', 'Belo Horizonte')
        ->set('uf', 'MG')
        ->set('situacao_cadastral', SituacaoCadastral::NaoInformada->value)
        ->call('submit')
        ->assertRedirect();

    $empresa = EmpresaAnalisada::firstWhere('documento', $cpf);

    /** @var EventoProduto $evento */
    $evento = EventoProduto::where('empresa_id', $empresa->id)->firstOrFail();
    $propriedades = (array) $evento->propriedades;

    expect($propriedades['tipo_documento'])->toBe('cpf');
    expect($propriedades['fonte_enriquecimento'])->toBe('manual');
    expect($propriedades['cnae_2digitos'])->toBeNull();
});

it('emite empresa_cadastrada com fonte_enriquecimento=rfb quando consulta foi bem-sucedida', function () {
    $usuario = Usuario::factory()->create();
    $cnpj = EmpresaAnalisadaFactory::cnpjComRaiz('12345678');

    Livewire::actingAs($usuario)
        ->test(Cadastrar::class)
        ->set('tipo_documento', TipoDocumento::Cnpj->value)
        ->set('documento', $cnpj)
        ->call('consultarReceita')
        ->call('submit')
        ->assertRedirect();

    $empresa = EmpresaAnalisada::firstWhere('documento', $cnpj);
    expect($empresa->fonte_enriquecimento)->toBe(FonteEnriquecimento::Rfb);

    $evento = EventoProduto::where('empresa_id', $empresa->id)->firstOrFail();
    $propriedades = (array) $evento->propriedades;
    expect($propriedades['fonte_enriquecimento'])->toBe('rfb');
});

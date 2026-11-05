<?php

declare(strict_types=1);

use App\Actions\CalcularDiagnostico;
use App\Domain\Cpf;
use App\Domain\FonteEnriquecimento;
use App\Livewire\Empresa\AtualizarReceita;
use App\Livewire\Empresa\NomeFantasia;
use App\Models\Diagnostico;
use App\Models\EmpresaAnalisada;
use App\Models\Usuario;
use App\Services\Rfb\MockRfbCnpjClient;
use App\Support\Relatorio\PdfDiagnostico;
use Database\Factories\EmpresaAnalisadaFactory;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/**
 * STORY-064 CA-6/CA-8 — o relatório emitido guarda os dados da Receita da emissão.
 * Só o nome informado pelo usuário (PDR-017) continua lido ao vivo.
 */
const QUIZ_SNAPSHOT = [
    'Q01' => 1, 'Q02' => '50000', 'Q03' => '20000', 'Q04' => '10000',
    'Q05' => '300000', 'Q06' => '40000', 'Q07' => '80000', 'Q08' => '50000',
    'Q09' => '100000', 'Q10' => 90, 'Q11' => 15, 'Q12' => 15, 'Q13' => '2',
    'Q14' => '20000', 'Q15' => '8000', 'Q16' => '2000',
];

beforeEach(function () {
    Cache::flush();
    $this->usuario = Usuario::factory()->create();
    $this->actingAs($this->usuario);
    $this->empresa = EmpresaAnalisada::factory()->create([
        'usuario_id' => $this->usuario->id,
        'documento' => EmpresaAnalisadaFactory::cnpjComRaiz('45237000'),
        'razao_social' => 'Antiga Razão LTDA',
        'nome_fantasia' => 'Antiga Fantasia',
        'municipio' => 'Cidade Antiga',
        'uf' => 'AC',
        'fonte_enriquecimento' => FonteEnriquecimento::Manual,
    ]);
});

it('congela na emissão razão social, fantasia da Receita, documento e município/UF', function () {
    $diag = app(CalcularDiagnostico::class)->execute($this->empresa, QUIZ_SNAPSHOT);

    expect($diag->fresh()->empresa_snapshot)->toEqual([
        'tipo_documento' => 'cnpj',
        'documento' => $this->empresa->documento,
        'razao_social' => 'Antiga Razão LTDA',
        'nome_fantasia' => 'Antiga Fantasia',
        'municipio' => 'Cidade Antiga',
        'uf' => 'AC',
    ]);
});

it('atualizar a Receita não muda tela nem PDF emitidos, mas o diagnóstico novo usa os dados novos', function () {
    $antigo = app(CalcularDiagnostico::class)->execute($this->empresa, QUIZ_SNAPSHOT);
    $novos = (new MockRfbCnpjClient)->consultarCnpj($this->empresa->documento);

    Livewire::test(AtualizarReceita::class, ['empresa' => $this->empresa])->call('atualizar');
    expect($this->empresa->fresh()->razao_social)->toBe($novos->razaoSocial);

    $this->get("/diagnosticos/{$antigo->id}")
        ->assertOk()
        ->assertSee('Antiga Fantasia')
        ->assertSee('Antiga Razão LTDA')
        ->assertDontSee($novos->razaoSocial);

    $html = PdfDiagnostico::padrao()->html($antigo->fresh());
    expect($html)->toContain('Antiga Fantasia', 'Antiga Razão LTDA', 'Cidade Antiga / AC')
        ->not->toContain($novos->razaoSocial)
        ->not->toContain($novos->municipio);
    expect(PdfDiagnostico::padrao()->nomeArquivo($antigo->fresh()))->toContain('antiga-fantasia');

    $novo = app(CalcularDiagnostico::class)->execute($this->empresa->fresh(), [...QUIZ_SNAPSHOT, 'Q02' => '51000']);
    expect($novo->empresa_snapshot['razao_social'])->toBe($novos->razaoSocial)
        ->and($novo->empresa_snapshot['municipio'])->toBe($novos->municipio);
    expect(PdfDiagnostico::padrao()->html($novo->fresh()))->toContain($novos->razaoSocial);
});

it('editar o nome informado continua mudando os diagnósticos emitidos (PDR-017)', function () {
    $diag = app(CalcularDiagnostico::class)->execute($this->empresa, QUIZ_SNAPSHOT);

    Livewire::test(NomeFantasia::class, ['empresa' => $this->empresa])
        ->set('nomeFantasia', 'Nome Novo do Usuário')
        ->call('salvar');

    $this->get("/diagnosticos/{$diag->id}")->assertSee('Nome Novo do Usuário')->assertSee('Antiga Razão LTDA');
    expect(PdfDiagnostico::padrao()->html($diag->fresh()))->toContain('Nome Novo do Usuário');
    expect($diag->fresh()->nomeExibicao())->toBe('Nome Novo do Usuário');
});

it('segue a ordem da PDR-017: informado, fantasia congelada, razão social congelada', function () {
    $diag = app(CalcularDiagnostico::class)->execute($this->empresa, QUIZ_SNAPSHOT);
    expect($diag->nomeExibicao())->toBe('Antiga Fantasia')
        ->and($diag->razaoSocialComplementar())->toBe('Antiga Razão LTDA');

    $diag->forceFill(['empresa_snapshot' => [...$diag->empresa_snapshot, 'nome_fantasia' => null]]);
    expect($diag->nomeExibicao())->toBe('Antiga Razão LTDA')
        ->and($diag->razaoSocialComplementar())->toBeNull();
});

it('em CPF congela o documento mas lê o nome comercial ao vivo', function () {
    $cpf = EmpresaAnalisada::factory()->comoCpf()->create([
        'usuario_id' => $this->usuario->id, 'razao_social' => 'Fulano de Tal', 'nome_fantasia' => 'Fulano Reformas',
    ]);
    $diag = app(CalcularDiagnostico::class)->execute($cpf, QUIZ_SNAPSHOT);

    expect($diag->rotuloDocumento())->toBe('CPF')
        ->and($diag->documentoFormatado())->toBe(Cpf::formatar($cpf->documento))
        ->and($diag->nomeExibicao())->toBe('Fulano Reformas');

    $cpf->update(['nome_fantasia' => 'Fulano Pinturas']);
    expect($diag->fresh()->nomeExibicao())->toBe('Fulano Pinturas');
});

it('migration: preenche os emitidos, neutraliza dono anonimizado e empresa inexistente, e impede nulo', function () {
    $migration = require database_path('migrations/2026_11_05_200020_add_empresa_snapshot_to_diagnosticos.php');
    $normal = app(CalcularDiagnostico::class)->execute($this->empresa, QUIZ_SNAPSHOT);
    $excluida = EmpresaAnalisada::factory()->create(['usuario_id' => $this->usuario->id, 'razao_social' => 'Excluída SA']);
    $dEx = Diagnostico::factory()->paraEmpresa($excluida)->create();
    $excluida->delete();
    $anon = Usuario::factory()->create(['anonimizado_at' => now()]);
    $empAnon = EmpresaAnalisada::factory()->create(['usuario_id' => $anon->id, 'razao_social' => 'Segredo Anonimizado']);
    $dAnon = Diagnostico::factory()->paraEmpresa($empAnon)->create();

    $migration->down();
    $migration->up();

    $lido = fn (Diagnostico $d) => json_decode((string) DB::table('diagnosticos')->where('id', $d->id)->value('empresa_snapshot'), true);
    expect($lido($normal)['razao_social'])->toBe('Antiga Razão LTDA')
        ->and($lido($normal)['municipio'])->toBe('Cidade Antiga')
        ->and($lido($dEx)['razao_social'])->toBe('Excluída SA')
        ->and($lido($dAnon)['razao_social'])->toBe('Empresa removida')
        ->and($lido($dAnon)['documento'])->toBe('')
        ->and(json_encode($lido($dAnon)))->not->toContain('Segredo');

    $diagAnon = Diagnostico::withoutGlobalScopes()->find($dAnon->id);
    expect($diagAnon->documentoFormatado())->toBe('—')->and($diagAnon->localizacao())->toBe('—');

    expect(fn () => DB::table('diagnosticos')->where('id', $normal->id)->update(['empresa_snapshot' => null]))
        ->toThrow(QueryException::class);
});

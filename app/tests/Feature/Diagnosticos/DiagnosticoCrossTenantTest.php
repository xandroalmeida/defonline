<?php

declare(strict_types=1);

use App\Actions\CalcularDiagnostico;
use App\Models\Diagnostico;
use App\Models\EmpresaAnalisada;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;

/*
| ----------------------------------------------------------------------
| Cross-tenant em Diagnostico — IDR-009 (404 silente via Global Scope).
|
| O motor V1 ainda não tem controller (STORY-029 entrega `/diagnosticos/{id}`).
| Este teste exerce a camada de **modelo + Global Scope** que é a fonte da
| verdade do 404 silente — `BelongsToUsuarioScope` filtra `WHERE usuario_id =
| Auth::id()`, então qualquer `findOrFail` cross-tenant levanta
| `ModelNotFoundException` → Laravel converte em 404. Quando STORY-029 abrir
| o endpoint, o teste E2E HTTP é trivial (esse comportamento já está provado
| aqui no nível do modelo).
| ----------------------------------------------------------------------
*/

function gerarDiagnosticoDe(Usuario $dono): Diagnostico
{
    Auth::login($dono);
    $empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $dono->id]);
    $diag = app(CalcularDiagnostico::class)->execute($empresa, [
        'Q01' => 1, 'Q02' => '50000', 'Q03' => '80000', 'Q04' => '60000',
        'Q05' => '300000', 'Q06' => '40000', 'Q07' => '30000', 'Q08' => '50000',
        'Q09' => '100000', 'Q10' => 45, 'Q11' => 30, 'Q12' => 30, 'Q13' => '2',
        'Q14' => '20000', 'Q15' => '8000', 'Q16' => '2000',
    ]);
    Auth::logout();

    return $diag;
}

it('Diagnostico::find devolve o registro do próprio usuário', function () {
    $roberto = Usuario::factory()->create();
    $diag = gerarDiagnosticoDe($roberto);

    Auth::login($roberto);
    expect(Diagnostico::find($diag->id))->not->toBeNull()
        ->and(Diagnostico::find($diag->id)->id)->toBe($diag->id);
});

it('Diagnostico::find devolve null para diagnóstico de outro usuário (404 silente)', function () {
    $roberto = Usuario::factory()->create();
    $marcos = Usuario::factory()->create();

    $diagDoRoberto = gerarDiagnosticoDe($roberto);

    Auth::login($marcos);
    expect(Diagnostico::find($diagDoRoberto->id))->toBeNull();
});

it('Diagnostico::findOrFail levanta ModelNotFoundException cross-tenant (Laravel converte em 404)', function () {
    $roberto = Usuario::factory()->create();
    $marcos = Usuario::factory()->create();

    $diagDoRoberto = gerarDiagnosticoDe($roberto);

    Auth::login($marcos);
    expect(fn () => Diagnostico::findOrFail($diagDoRoberto->id))
        ->toThrow(ModelNotFoundException::class);
});

it('listagem (Diagnostico::query()->get()) não vaza registros entre tenants', function () {
    $roberto = Usuario::factory()->create();
    $marcos = Usuario::factory()->create();

    gerarDiagnosticoDe($roberto);
    gerarDiagnosticoDe($roberto);
    gerarDiagnosticoDe($marcos);

    Auth::login($roberto);
    expect(Diagnostico::query()->count())->toBe(2);

    Auth::login($marcos);
    expect(Diagnostico::query()->count())->toBe(1);
});

it('withoutGlobalScopes expõe todos os registros (caminho de admin/queue/seed apenas)', function () {
    $roberto = Usuario::factory()->create();
    $marcos = Usuario::factory()->create();
    gerarDiagnosticoDe($roberto);
    gerarDiagnosticoDe($marcos);

    expect(Diagnostico::withoutGlobalScopes()->count())->toBe(2);
});

<?php

declare(strict_types=1);

use App\Models\DrmLancamento;
use App\Models\EmpresaAnalisada;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

/**
 * STORY-050 — duas retificações simultâneas da mesma competência, a partir da mesma versão base:
 * processos PHP separados, conexões separadas, COMMIT de verdade (é aqui que a FK adiada do
 * IDR-016 é conferida). O `SELECT … FOR UPDATE` e a concorrência otimista deixam passar uma só.
 */
// A base (ConcorrenciaTestCase + RefreshDatabase) é declarada para a pasta em CarteiraConcorrenciaTest.

/** @param  list<string>  $usuarioIds */
function _limparRetificacao(array $usuarioIds): void
{
    config(['database.connections.pgsql_limpeza_drm' => [
        ...config('database.connections.pgsql'),
        'username' => env('DB_SUPERUSER'),
        'password' => env('DB_SUPERUSER_PASSWORD'),
    ]]);
    $banco = DB::connection('pgsql_limpeza_drm');
    $banco->statement('set session_replication_role = replica');

    foreach (['audit_logs', 'drm_lancamentos', 'drm_rascunhos', 'drm_preferencias', 'empresas_analisadas'] as $tabela) {
        $banco->table($tabela)->whereIn('usuario_id', $usuarioIds)->delete();
    }
    $banco->table('usuarios')->whereIn('id', $usuarioIds)->delete();
    DB::purge('pgsql_limpeza_drm');
}

beforeEach(function () {
    $this->usuario = Usuario::factory()->create();
    $this->empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $this->usuario->id]);
    DrmLancamento::factory()->create(['empresa_analisada_id' => $this->empresa->id, 'competencia' => '2026-09-01']);
});

afterEach(function () {
    _limparRetificacao([$this->usuario->id]);
});

it('duas retificações simultâneas da versão 1: uma vira a versão 2, a outra recebe o conflito', function () {
    $inicio = microtime(true) + 4.0;
    $processos = array_map(function (string $d02) use ($inicio): Process {
        $p = new Process([PHP_BINARY, dirname(__DIR__).'/Support/retificacao_concorrente.php', $this->empresa->id, '2026-09', '1', $d02, (string) $inicio], null, ['APP_ENV' => 'testing']);
        $p->start();

        return $p;
    }, ['6.000,00', '7.000,00']);

    $resultados = array_map(function (Process $p): array {
        $p->wait();
        $saida = json_decode(trim($p->getOutput()), true);
        expect($saida)->toBeArray("o filho não devolveu JSON: {$p->getOutput()} {$p->getErrorOutput()}");

        return $saida;
    }, $processos);

    $porResultado = array_count_values(array_column($resultados, 'resultado'));
    ksort($porResultado);
    $versoes = DrmLancamento::query()->where('empresa_analisada_id', $this->empresa->id)->orderBy('versao')->get();

    expect($porResultado)->toBe(['conflito' => 1, 'ok' => 1], json_encode($resultados))
        ->and($versoes->pluck('versao')->all())->toBe([1, 2])
        ->and($versoes->whereNull('substituido_por_id')->count())->toBe(1)
        ->and($versoes[0]->substituido_por_id)->toBe($versoes[1]->id);
});

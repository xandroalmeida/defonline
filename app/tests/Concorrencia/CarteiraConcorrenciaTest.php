<?php

declare(strict_types=1);

use App\Domain\Cobranca\Carteira;
use App\Models\Assinatura;
use App\Models\CobrancaConfig;
use App\Models\Diagnostico;
use App\Models\EmpresaAnalisada;
use App\Models\LoteCredito;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\ConcorrenciaTestCase;

/**
 * STORY-073 CA-6 e CA-10 — concorrência de verdade: processos PHP separados, conexões separadas,
 * Postgres real. Uma transação de teste não serviria: os filhos não a enxergariam.
 */
pest()->extend(ConcorrenciaTestCase::class)->use(RefreshDatabase::class)->in(__DIR__);

/**
 * Apaga o que o teste efetivou. `evento_produto` é append-only para o papel da aplicação
 * (ADR-003), então a limpeza usa o superusuário do ambiente de teste — sem isso, os eventos
 * `diagnostico_concluido` dos filhos vazariam para os testes que contam eventos.
 *
 * @param  list<string>  $usuarioIds
 */
function _limparConcorrencia(array $usuarioIds): void
{
    config(['database.connections.pgsql_limpeza' => [
        ...config('database.connections.pgsql'),
        'username' => env('DB_SUPERUSER'),
        'password' => env('DB_SUPERUSER_PASSWORD'),
    ]]);
    $banco = DB::connection('pgsql_limpeza');
    // `term_acceptances` também é append-only e `usuarios` aponta para ela; sem os gatilhos de FK
    // (só o superusuário pode desligá-los) o DELETE do Usuário esbarra no REVOKE.
    $banco->statement('set session_replication_role = replica');

    foreach (['consumos_analise', 'diagnosticos', 'lotes_credito', 'assinaturas', 'quiz_rascunhos', 'evento_produto', 'empresas_analisadas'] as $tabela) {
        $banco->table($tabela)->whereIn('usuario_id', $usuarioIds)->delete();
    }

    $banco->table('usuarios')->whereIn('id', $usuarioIds)->delete();
    $banco->table('cobranca_config')->update(['ativa' => false]);
    DB::purge('pgsql_limpeza');
}

/**
 * Solta N processos filhos ao mesmo tempo (todos esperam o mesmo instante) e devolve os resultados.
 *
 * @param  list<string|null>  $quizIds  um por filho; null = sem quiz
 * @return list<array<string, mixed>>
 */
function _soltarFilhos(Usuario $usuario, EmpresaAnalisada $empresa, array $quizIds, float $esperaSegundos = 4.0): array
{
    $inicio = microtime(true) + $esperaSegundos;
    $processos = array_map(function (?string $quizId) use ($usuario, $empresa, $inicio): Process {
        $p = new Process([PHP_BINARY, dirname(__DIR__).'/Support/carteira_concorrente.php', $usuario->id, $empresa->id, $quizId ?? '-', (string) $inicio], null, ['APP_ENV' => 'testing']);
        $p->start();

        return $p;
    }, $quizIds);

    return _colherFilhos($processos);
}

/**
 * @param  list<Process>  $processos
 * @return list<array<string, mixed>>
 */
function _colherFilhos(array $processos): array
{
    return array_map(function (Process $p): array {
        $p->wait();
        $saida = json_decode(trim($p->getOutput()), true);
        expect($saida)->toBeArray("o filho não devolveu JSON: {$p->getOutput()} {$p->getErrorOutput()}");

        return $saida;
    }, $processos);
}

/** @param  list<array<string, mixed>>  $resultados */
function _contar(array $resultados, string $resultado): int
{
    return count(array_filter($resultados, fn (array $r): bool => $r['resultado'] === $resultado));
}

beforeEach(function () {
    $this->usuario = Usuario::factory()->create();
    $this->empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $this->usuario->id]);
    CobrancaConfig::atual()->update(['ativa' => true]);
});

afterEach(function () {
    _limparConcorrencia([$this->usuario->id]);
});

it('seis conclusões simultâneas com saldo 1 geram uma análise só e o saldo nunca fica negativo', function () {
    $assinatura = app(Carteira::class)->concederCota($this->usuario->id, 1, now()->subDay(), now()->addMonth());

    $resultados = _soltarFilhos($this->usuario, $this->empresa, array_map(fn () => (string) Str::uuid7(), range(1, 6)));

    expect(_contar($resultados, 'ok'))->toBe(1)
        ->and(_contar($resultados, 'sem_saldo'))->toBe(5)
        ->and($assinatura->fresh()->cota_restante_ciclo)->toBe(0)
        ->and(Diagnostico::withoutGlobalScopes()->where('usuario_id', $this->usuario->id)->count())->toBe(1)
        ->and(DB::table('consumos_analise')->where('usuario_id', $this->usuario->id)->count())->toBe(1);
});

it('a mesma conclusão disparada várias vezes é cobrada uma vez, mesmo com saldo de sobra', function () {
    $assinatura = app(Carteira::class)->concederCota($this->usuario->id, 5, now()->subDay(), now()->addMonth());
    $quizId = (string) Str::uuid7();

    $resultados = _soltarFilhos($this->usuario, $this->empresa, [$quizId, $quizId, $quizId, $quizId]);

    expect(_contar($resultados, 'ok'))->toBe(1)
        ->and(_contar($resultados, 'ja_concluida'))->toBe(3)
        ->and($assinatura->fresh()->cota_restante_ciclo)->toBe(4)
        ->and(Diagnostico::withoutGlobalScopes()->where('usuario_id', $this->usuario->id)->count())->toBe(1);

    $diagnosticoOk = collect($resultados)->firstWhere('resultado', 'ok')['diagnostico_id'];
    expect(collect($resultados)->where('resultado', 'ja_concluida')->pluck('diagnostico_id')->unique()->all())->toBe([$diagnosticoOk]);
});

it('cinco conclusões com um lote de 3 créditos consomem exatamente 3 e esgotam o lote', function () {
    $lote = app(Carteira::class)->concederCreditos($this->usuario->id, 3, now()->addMonths(6));

    $resultados = _soltarFilhos($this->usuario, $this->empresa, array_map(fn () => (string) Str::uuid7(), range(1, 5)));

    $lote = LoteCredito::query()->findOrFail($lote->id);
    expect(_contar($resultados, 'ok'))->toBe(3)
        ->and(_contar($resultados, 'sem_saldo'))->toBe(2)
        ->and($lote->quantidade_restante)->toBe(0)
        ->and($lote->status->value)->toBe('esgotado')
        ->and(DB::table('consumos_analise')->where('lote_credito_id', $lote->id)->count())->toBe(3);
});

it('cota e créditos juntos: a cota sai primeiro e o total consumido é a soma dos dois', function () {
    $cota = app(Carteira::class)->concederCota($this->usuario->id, 2, now()->subDay(), now()->addMonth());
    $lote = app(Carteira::class)->concederCreditos($this->usuario->id, 1, now()->addMonths(6));

    $resultados = _soltarFilhos($this->usuario, $this->empresa, array_map(fn () => (string) Str::uuid7(), range(1, 5)));

    expect(_contar($resultados, 'ok'))->toBe(3)
        ->and(_contar($resultados, 'sem_saldo'))->toBe(2)
        ->and(Assinatura::query()->findOrFail($cota->id)->cota_restante_ciclo)->toBe(0)
        ->and(LoteCredito::query()->findOrFail($lote->id)->quantidade_restante)->toBe(0)
        ->and(DB::table('consumos_analise')->where('origem', 'cota')->where('usuario_id', $this->usuario->id)->count())->toBe(2)
        ->and(DB::table('consumos_analise')->where('origem', 'credito')->where('usuario_id', $this->usuario->id)->count())->toBe(1);
});

it('quem segura o lock faz os outros esperarem e enxergarem o saldo já debitado', function () {
    $assinatura = app(Carteira::class)->concederCota($this->usuario->id, 1, now()->subDay(), now()->addMonth());
    $filhos = [];

    // Os filhos largam enquanto este processo ainda não efetivou o débito. Sem o lock, leriam
    // cota 1 e também passariam; com ele, esperam e encontram 0.
    app(Carteira::class)->consumir($this->usuario->id, $this->empresa->id, null, function () use (&$filhos): Diagnostico {
        $inicio = microtime(true);
        foreach (range(1, 3) as $_) {
            $p = new Process([PHP_BINARY, dirname(__DIR__).'/Support/carteira_concorrente.php', $this->usuario->id, $this->empresa->id, '-', (string) $inicio], null, ['APP_ENV' => 'testing']);
            $p->start();
            $filhos[] = $p;
        }

        // Tempo para os filhos subirem, chegarem ao lock e ficarem parados nele.
        sleep(5);
        expect(collect($filhos)->every(fn (Process $p) => $p->isRunning()))->toBeTrue('os filhos deveriam estar esperando o lock');

        return Diagnostico::factory()->create(['usuario_id' => $this->usuario->id, 'empresa_analisada_id' => $this->empresa->id]);
    });

    $resultados = _colherFilhos($filhos);

    expect(_contar($resultados, 'sem_saldo'))->toBe(3)
        ->and($assinatura->fresh()->cota_restante_ciclo)->toBe(0)
        ->and(DB::table('consumos_analise')->where('usuario_id', $this->usuario->id)->count())->toBe(1);
});

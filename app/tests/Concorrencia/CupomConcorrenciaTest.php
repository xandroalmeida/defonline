<?php

declare(strict_types=1);

use App\Domain\Cobranca\TipoDesconto;
use App\Models\Assinatura;
use App\Models\CobrancaConfig;
use App\Models\Cupom;
use App\Models\Invoice;
use App\Models\PacoteCredito;
use App\Models\Plano;
use App\Models\Usuario;
use App\Services\Cobranca\CupomDoCheckout;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

/**
 * STORY-083 CA-4 — concorrência de verdade no limite de usos do cupom: processos PHP separados, conexões
 * separadas, Postgres real. Uma transação de teste não serviria: os filhos não a enxergariam.
 */
// A pasta toda já usa ConcorrenciaTestCase + RefreshDatabase (declarado em CarteiraConcorrenciaTest.php).

/**
 * Apaga o que o teste efetivou (o superusuário do ambiente de teste, como em `_limparConcorrencia`).
 *
 * @param  list<string>  $usuarioIds
 */
function _limparCupons(array $usuarioIds, string $cupomId): void
{
    config(['database.connections.pgsql_limpeza' => [
        ...config('database.connections.pgsql'),
        'username' => env('DB_SUPERUSER'),
        'password' => env('DB_SUPERUSER_PASSWORD'),
    ]]);
    $banco = DB::connection('pgsql_limpeza');
    $banco->statement('set session_replication_role = replica');
    $banco->table('cupom_usos')->where('cupom_id', $cupomId)->delete();

    foreach (['lotes_credito', 'invoices', 'assinaturas', 'term_acceptances', 'evento_produto'] as $tabela) {
        $banco->table($tabela)->whereIn('usuario_id', $usuarioIds)->delete();
    }

    $banco->table('cupom_itens')->where('cupom_id', $cupomId)->delete();
    $banco->table('cupons')->where('id', $cupomId)->delete();
    $banco->table('usuarios')->whereIn('id', $usuarioIds)->delete();
    $banco->table('cobranca_config')->update(['ativa' => false]);
    DB::purge('pgsql_limpeza');
}

/**
 * Solta um filho por usuário ao mesmo tempo e devolve os resultados.
 *
 * @param  list<string>  $usuarioIds
 * @return list<array<string, mixed>>
 */
function _soltarContratacoes(array $usuarioIds, string $tipo, string $codigo): array
{
    $inicio = microtime(true) + 4.0;
    $processos = array_map(function (string $id) use ($tipo, $codigo, $inicio): Process {
        $p = new Process([PHP_BINARY, dirname(__DIR__).'/Support/cupom_concorrente.php', $id, $tipo, $codigo, (string) $inicio], null, ['APP_ENV' => 'testing']);
        $p->start();

        return $p;
    }, $usuarioIds);

    return array_map(function (Process $p): array {
        $p->wait();
        $saida = json_decode(trim($p->getOutput()), true);
        expect($saida)->toBeArray("o filho não devolveu JSON: {$p->getOutput()} {$p->getErrorOutput()}");

        return $saida;
    }, $processos);
}

function _cupomConcorrente(string $codigo, int $limite, int $valor, ?PacoteCredito $pacote, ?Plano $plano): Cupom
{
    $cupom = Cupom::query()->create([
        'codigo' => $codigo, 'tipo' => TipoDesconto::Percentual, 'valor' => $valor,
        'vigencia_inicio' => now()->subDay()->toDateString(), 'vigencia_fim' => now()->addMonth()->toDateString(),
        'ativo' => true, 'limite_usos' => $limite, 'ciclos_duracao' => 3,
    ]);
    $pacote !== null && $cupom->itens()->create(['pacote_credito_id' => $pacote->id]);
    $plano !== null && $cupom->itens()->create(['plano_id' => $plano->id, 'ciclo' => 'mensal']);

    return $cupom;
}

beforeEach(function () {
    CobrancaConfig::atual()->update(['ativa' => true]);
    $this->usuarios = collect(range(1, 6))->map(fn () => Usuario::factory()->create(['email_confirmed_at' => now()]));
});

afterEach(function () {
    _limparCupons($this->usuarios->pluck('id')->all(), $this->cupom->id);
});

it('seis checkouts pagos disputam 2 vagas: só 2 levam o desconto e as reservas nunca passam do limite', function () {
    $this->cupom = _cupomConcorrente('DISPUTA', 2, 200, PacoteCredito::query()->where('codigo', 'plus')->firstOrFail(), null);

    $resultados = _soltarContratacoes($this->usuarios->pluck('id')->all(), 'creditos', 'DISPUTA');

    $ok = collect($resultados)->where('resultado', 'ok');
    $recusados = collect($resultados)->where('resultado', 'recusado');
    expect($ok)->toHaveCount(2)
        ->and($recusados)->toHaveCount(4)
        ->and($recusados->pluck('motivo')->unique()->all())->toBe(['limite_esgotado'])
        ->and(Invoice::query()->where('cupom_id', $this->cupom->id)->count())->toBe(2);

    // Os dois pagam: o uso vira registro e o limite fica exato.
    foreach (Invoice::query()->where('cupom_id', $this->cupom->id)->get() as $invoice) {
        app(CupomDoCheckout::class)->registrarUso($invoice);
    }

    expect(DB::table('cupom_usos')->where('cupom_id', $this->cupom->id)->count())->toBe(2);
});

it('seis ativações sem cobrança disputam a última vaga: uma assinatura, um uso, cinco recusadas', function () {
    $this->cupom = _cupomConcorrente('ULTIMA', 1, 1000, null, Plano::query()->where('nome', 'Básico')->firstOrFail());

    $resultados = _soltarContratacoes($this->usuarios->pluck('id')->all(), 'assinatura', 'ULTIMA');

    expect(collect($resultados)->where('resultado', 'ok'))->toHaveCount(1)
        ->and(collect($resultados)->where('resultado', 'recusado'))->toHaveCount(5)
        ->and(collect($resultados)->where('resultado', 'erro'))->toHaveCount(0)
        ->and(DB::table('cupom_usos')->where('cupom_id', $this->cupom->id)->count())->toBe(1)
        ->and(Assinatura::query()->whereIn('usuario_id', $this->usuarios->pluck('id'))->count())->toBe(1)
        ->and(Invoice::query()->where('cupom_id', $this->cupom->id)->where('status', 'paga')->count())->toBe(1);
});

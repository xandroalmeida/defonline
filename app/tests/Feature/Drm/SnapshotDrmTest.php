<?php

declare(strict_types=1);

use App\Actions\Drm\FecharLancamento;
use App\Domain\Drm\Competencia;
use App\Domain\Drm\Dinheiro;
use App\Domain\Drm\Grupo;
use App\Domain\Drm\Lancamento;
use App\Domain\Drm\Modo;
use App\Domain\Drm\MotorDrm;
use App\Domain\Drm\Setor;
use App\Models\DrmLancamento;
use App\Models\EmpresaAnalisada;
use App\Models\Usuario;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/*
|-------------------------------------------------------------------------
| STORY-048 / ADR-007 — o fechamento grava o snapshot, e um mês fechado sem ele
| não pode existir: nem pelo fechamento, nem por INSERT direto, nem herdado da
| STORY-047 (backfill).
|-------------------------------------------------------------------------
*/

beforeEach(function () {
    // O compose de desenvolvimento sobe o Postgres em America/Sao_Paulo e o app roda em UTC; o Laravel grava
    // o horário de parede sem offset, então `timestamptz` ficaria deslocado. Fixar a sessão em UTC isola o
    // teste desse descompasso do ambiente (não é do motor; `LOCAL` morre com o rollback do teste).
    DB::statement("SET LOCAL TIME ZONE 'UTC'");
    $this->usuario = Usuario::factory()->create();
    $this->actingAs($this->usuario);
    $this->empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $this->usuario->id]);
});

function snapshot_lancamento(): Lancamento
{
    $modos = array_fill_keys(array_map(fn (Grupo $g) => $g->value, Grupo::cases()), Modo::Detalhado);

    return Lancamento::deTexto(Setor::Comercio, $modos, [
        'D01' => '100.000,00', 'D02' => '6.000,00', 'D04' => '30.000,00', 'D09' => '2.000,00', 'D16' => '10.000,00',
        'D38' => '900,00', 'D40' => '8.000,00', 'D41' => '200,00', 'D42' => '50,00',
    ]);
}

describe('fechamento', function () {
    it('grava a demonstração dos dois modos na mesma linha, com a versão do motor e o carimbo recebidos pelo motor', function () {
        CarbonImmutable::setTestNow('2026-10-03 14:20:00');

        $registro = app(FecharLancamento::class)->execute($this->empresa, Competencia::deTexto('2026-09'), snapshot_lancamento(), []);
        $l = DrmLancamento::query()->findOrFail($registro->id);

        expect($l->demonstracao['linhas']['fora'])->not->toBeEmpty()
            ->and($l->demonstracao['linhas']['dentro'])->not->toBeEmpty()
            ->and($l->demonstracao['motor_drm_version'])->toBe('1.0.0')->toBe($l->motor_drm_version)
            ->and($l->demonstracao['competencia'])->toBe('2026-09')
            ->and($l->demonstracao['setor'])->toBe($l->setor)
            ->and(CarbonImmutable::parse($l->demonstracao['gerado_em'])->equalTo($l->gerado_em))->toBeTrue();

        CarbonImmutable::setTestNow();
    });

    it('o snapshot é exatamente o que o motor devolve para o payload e o carimbo gravados (reproduzível)', function () {
        $registro = app(FecharLancamento::class)->execute($this->empresa, Competencia::deTexto('2026-09'), snapshot_lancamento(), []);
        $l = DrmLancamento::query()->findOrFail($registro->id);

        $recalculado = (new MotorDrm)->calcular($l->lancamento_payload->getArrayCopy(), Setor::from($l->setor), Competencia::deData($l->competencia), $l->gerado_em);

        expect(json_decode(json_encode($l->demonstracao), true))->toEqual($recalculado);
    });

    it('o invariante vale no dado persistido: Lucro Líquido idêntico nos dois arrays', function () {
        $registro = app(FecharLancamento::class)->execute($this->empresa, Competencia::deTexto('2026-09'), snapshot_lancamento(), []);
        $l = DrmLancamento::query()->findOrFail($registro->id);
        $lucro = fn (string $modo): string => collect($l->demonstracao['linhas'][$modo])->firstWhere('codigo', 'L11')['nominal'];

        expect($lucro('dentro'))->toBe($lucro('fora'))
            ->and(Dinheiro::deDecimal($lucro('fora')))->toBe(snapshot_lancamento()->lucroLiquido());
    });

    it('lançamento fechado nunca é recalculado: subir a versão do motor não toca no que já foi gravado', function () {
        $registro = app(FecharLancamento::class)->execute($this->empresa, Competencia::deTexto('2026-09'), snapshot_lancamento(), []);
        $antes = DrmLancamento::query()->findOrFail($registro->id)->getRawOriginal('demonstracao');

        config(['drm.motor_drm_version' => '2.0.0']);
        $depois = DrmLancamento::query()->findOrFail($registro->id);

        expect($depois->getRawOriginal('demonstracao'))->toBe($antes)->and($depois->motor_drm_version)->toBe('1.0.0');
    });

    it('a fábrica produz o mesmo tipo de snapshot que o fechamento', function () {
        $l = DrmLancamento::factory()->create(['empresa_analisada_id' => $this->empresa->id]);

        expect($l->demonstracao['linhas']['fora'][0]['codigo'])->toBe('L1')
            ->and($l->motor_drm_version)->toBe($l->demonstracao['motor_drm_version']);
    });
});

describe('o banco recusa mês sem snapshot', function () {
    it('demonstração vazia', function () {
        expect(fn () => DrmLancamento::factory()->create(['empresa_analisada_id' => $this->empresa->id, 'demonstracao' => [], 'motor_drm_version' => '1.0.0']))
            ->toThrow(QueryException::class, 'drm_lancamentos_demonstracao_check');
    });

    it('demonstração sem um dos modos', function (string $modo) {
        $demo = DrmLancamento::factory()->make(['empresa_analisada_id' => $this->empresa->id])->demonstracao->getArrayCopy();
        unset($demo['linhas'][$modo]);

        expect(fn () => DrmLancamento::factory()->create(['empresa_analisada_id' => $this->empresa->id, 'demonstracao' => $demo]))
            ->toThrow(QueryException::class, 'drm_lancamentos_demonstracao_check');
    })->with(['fora', 'dentro']);

    it('versão da coluna diferente da versão do snapshot', function () {
        expect(fn () => DrmLancamento::factory()->create(['empresa_analisada_id' => $this->empresa->id, 'motor_drm_version' => '0.0.0']))
            ->toThrow(QueryException::class, 'drm_lancamentos_demonstracao_check');
    });
});

describe('backfill dos meses fechados na STORY-047', function () {
    function snapshot_migration(): object
    {
        return require base_path('database/migrations/2026_11_05_100020_exige_demonstracao_no_snapshot_do_drm.php');
    }

    it('calcula o snapshot de quem ficou com `[]`, com o carimbo original, e deixa os demais intactos', function () {
        $migration = snapshot_migration();
        $migration->down(); // estado anterior: sem CHECK

        $velho = DrmLancamento::factory()->create(['empresa_analisada_id' => $this->empresa->id, 'competencia' => '2026-08-01']);
        $intacto = DrmLancamento::factory()->create(['empresa_analisada_id' => $this->empresa->id, 'competencia' => '2026-09-01']);
        DB::table('drm_lancamentos')->where('id', $velho->id)->update(['demonstracao' => '[]', 'motor_drm_version' => '0.0.0']);
        $hashAntes = DB::table('drm_lancamentos')->where('id', $velho->id)->value('payload_hash');
        $intactoAntes = DB::table('drm_lancamentos')->where('id', $intacto->id)->value('demonstracao');

        DB::statement('SET CONSTRAINTS ALL IMMEDIATE'); // a FK adiável tem eventos pendentes dos inserts do teste
        $migration->up();

        $l = DrmLancamento::query()->findOrFail($velho->id);
        $esperado = (new MotorDrm)->calcular($l->lancamento_payload->getArrayCopy(), Setor::from($l->setor), Competencia::deData($l->competencia), $l->gerado_em);

        expect(json_decode(json_encode($l->demonstracao), true))->toEqual($esperado)
            ->and($l->motor_drm_version)->toBe('1.0.0')
            ->and(DB::table('drm_lancamentos')->where('id', $velho->id)->value('payload_hash'))->toBe($hashAntes)
            ->and(DB::table('drm_lancamentos')->where('id', $intacto->id)->value('demonstracao'))->toBe($intactoAntes);
    });

    it('instala o CHECK ao final e o down o remove', function () {
        $migration = snapshot_migration();
        $existe = fn (): bool => DB::scalar("SELECT count(*) FROM pg_constraint WHERE conname = 'drm_lancamentos_demonstracao_check'") === 1;

        expect($existe())->toBeTrue();
        $migration->down();
        expect($existe())->toBeFalse();
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
        $migration->up();
        expect($existe())->toBeTrue();
    });

    it('sem nenhum mês a corrigir, não faz nada além do CHECK', function () {
        $migration = snapshot_migration();
        $migration->down();

        expect(fn () => $migration->up())->not->toThrow(Throwable::class);
    });
});

<?php

declare(strict_types=1);

use App\Domain\Drm\Grupo;
use App\Domain\Drm\Modo;
use App\Models\DrmLancamento;
use App\Models\DrmPreferencia;
use App\Models\DrmRascunho;
use App\Models\EmpresaAnalisada;
use App\Models\Usuario;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
|-------------------------------------------------------------------------
| STORY-047 CA-10 / ADR-007 — invariantes de persistência verificáveis em SQL.
|-------------------------------------------------------------------------
*/

beforeEach(function () {
    $this->usuario = Usuario::factory()->create();
    $this->actingAs($this->usuario);
    $this->empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $this->usuario->id]);
});

/**
 * O caminho da retificação (STORY-050): aponta a vigente para a nova versão e só então insere a nova.
 * Inserir primeiro violaria o índice único parcial; a FK adiada permite esta ordem.
 */
function trocarVersao(DrmLancamento $atual, int $versao, array $payload): DrmLancamento
{
    return DB::transaction(function () use ($atual, $versao, $payload) {
        $id = (string) Str::uuid7();
        $atual->update(['substituido_por_id' => $id]);

        return DrmLancamento::factory()->create([
            'id' => $id, 'empresa_analisada_id' => $atual->empresa_analisada_id, 'competencia' => $atual->competencia,
            'versao' => $versao, 'lancamento_payload' => $payload,
        ]);
    });
}

describe('drm_lancamentos', function () {
    it('o banco recusa duas versões vigentes da mesma competência (regra B1)', function () {
        DrmLancamento::factory()->create(['empresa_analisada_id' => $this->empresa->id]);

        expect(fn () => DB::transaction(fn () => DrmLancamento::factory()->create(['empresa_analisada_id' => $this->empresa->id])))
            ->toThrow(UniqueConstraintViolationException::class);
    });

    it('competências diferentes e empresas diferentes convivem', function () {
        $outra = EmpresaAnalisada::factory()->create(['usuario_id' => $this->usuario->id]);
        DrmLancamento::factory()->create(['empresa_analisada_id' => $this->empresa->id, 'competencia' => '2026-08-01']);
        DrmLancamento::factory()->create(['empresa_analisada_id' => $this->empresa->id, 'competencia' => '2026-09-01']);
        DrmLancamento::factory()->create(['empresa_analisada_id' => $outra->id, 'competencia' => '2026-09-01']);

        expect(DrmLancamento::query()->count())->toBe(3);
    });

    it('permite relançar a competência depois do soft delete (índice parcial)', function () {
        $primeiro = DrmLancamento::factory()->create(['empresa_analisada_id' => $this->empresa->id]);
        $primeiro->delete();

        DrmLancamento::factory()->create(['empresa_analisada_id' => $this->empresa->id]);

        expect(DrmLancamento::query()->count())->toBe(1);
    });

    it('a cadeia de versões: v1 apontada para a v2 deixa de ser vigente', function () {
        $v1 = DrmLancamento::factory()->create(['empresa_analisada_id' => $this->empresa->id]);
        $v2 = trocarVersao($v1, 2, ['x' => '1.00']);

        expect(DrmLancamento::vigenteDaCompetencia($this->empresa, '2026-09-01')?->id)->toBe($v2->id)
            ->and(DrmLancamento::query()->vigente()->count())->toBe(1);
    });

    it('recusa versão repetida, versão < 1, dia diferente de 01, setor e hash inválidos', function (array $campos) {
        expect(fn () => DB::transaction(fn () => DrmLancamento::factory()->create([
            'empresa_analisada_id' => $this->empresa->id, ...$campos,
        ])))->toThrow(QueryException::class);
    })->with([
        'versão zero' => [['versao' => 0]],
        'dia 15' => [['competencia' => '2026-09-15']],
        'setor' => [['setor' => 'agro']],
        'hash curto' => [['payload_hash' => 'abc']],
        'motor sem semver' => [['motor_drm_version' => 'v1']],
    ]);

    it('imutabilidade: nenhuma coluna de conteúdo pode ser alterada depois do insert', function (string $coluna, mixed $valor) {
        $l = DrmLancamento::factory()->create(['empresa_analisada_id' => $this->empresa->id]);

        expect(fn () => $l->update([$coluna => $valor]))->toThrow(LogicException::class, 'imutável');
    })->with([
        ['competencia', '2026-01-01'],
        ['versao', 9],
        ['setor', 'industria'],
        ['lancamento_payload', ['D01' => '1.00']],
        ['payload_hash', str_repeat('a', 64)],
        ['demonstracao', ['x' => 1]],
        ['alertas_aceitos', [['regra' => 'A1']]],
        ['motor_drm_version', '9.9.9'],
    ]);

    it('o ponteiro de vigência vai de NULL para um id e nunca é reescrito', function () {
        $v1 = DrmLancamento::factory()->create(['empresa_analisada_id' => $this->empresa->id]);
        $v2 = trocarVersao($v1, 2, ['x' => '1.00']);
        $v3 = trocarVersao($v2, 3, ['x' => '2.00']);

        expect(fn () => $v1->update(['substituido_por_id' => $v3->id]))->toThrow(LogicException::class, 'ponteiro')
            ->and(fn () => $v1->update(['substituido_por_id' => null]))->toThrow(LogicException::class);
    });

    it('isola por usuário: outro usuário não enxerga o lançamento', function () {
        DrmLancamento::factory()->create(['empresa_analisada_id' => $this->empresa->id]);

        $this->actingAs(Usuario::factory()->create());

        expect(DrmLancamento::query()->count())->toBe(0);
    });

    it('não toca nas tabelas do Diagnóstico: sem FK entre elas', function () {
        $fks = DB::select(<<<'SQL'
            SELECT conrelid::regclass::text AS tabela, confrelid::regclass::text AS alvo
            FROM pg_constraint
            WHERE contype = 'f'
              AND (conrelid::regclass::text LIKE 'drm\_%' OR confrelid::regclass::text LIKE 'drm\_%')
        SQL);

        $envolvidas = collect($fks)->flatMap(fn ($f) => [$f->tabela, $f->alvo])->unique()->all();
        expect($envolvidas)->not->toContain('diagnosticos')->not->toContain('quiz_rascunhos');
    });
});

describe('drm_rascunhos', function () {
    function rascunho(array $extra = []): array
    {
        return [
            'usuario_id' => test()->usuario->id,
            'empresa_analisada_id' => test()->empresa->id,
            'competencia' => '2026-09-01',
            'origem' => 'inicial',
            'rascunho_payload' => ['valores' => []],
            'expires_at' => now()->addDays(90),
            ...$extra,
        ];
    }

    it('um rascunho ativo por (usuário, empresa, competência)', function () {
        DrmRascunho::query()->create(rascunho());

        expect(fn () => DB::transaction(fn () => DrmRascunho::query()->create(rascunho())))->toThrow(UniqueConstraintViolationException::class);

        DrmRascunho::query()->create(rascunho(['competencia' => '2026-10-01']));
        expect(DrmRascunho::query()->count())->toBe(2);
    });

    it('origem e base_versao andam juntas: retificação exige a versão-base', function () {
        expect(fn () => DB::transaction(fn () => DrmRascunho::query()->create(rascunho(['origem' => 'retificacao']))))->toThrow(QueryException::class);
        expect(fn () => DB::transaction(fn () => DrmRascunho::query()->create(rascunho(['base_versao' => 1]))))->toThrow(QueryException::class);

        DrmRascunho::query()->create(rascunho(['origem' => 'retificacao', 'base_versao' => 1]));
        expect(DrmRascunho::query()->count())->toBe(1);
    });

    it('ativos ignora expirados e maisRecente devolve o último salvo', function () {
        $antigo = DrmRascunho::query()->create(rascunho(['competencia' => '2026-08-01', 'expires_at' => now()->subDay()]));
        $a = DrmRascunho::query()->create(rascunho(['competencia' => '2026-09-01']));
        $b = DrmRascunho::query()->create(rascunho(['competencia' => '2026-10-01']));
        $a->touch();
        $a->forceFill(['updated_at' => now()->addMinute()])->save();

        expect(DrmRascunho::paraCompetencia($this->empresa, '2026-08-01'))->toBeNull()
            ->and(DrmRascunho::maisRecente($this->empresa)?->id)->toBe($a->id)
            ->and($antigo->fresh())->not->toBeNull();
    });
});

describe('drm_preferencias', function () {
    it('sem registro devolve os defaults (detalhado, pró-labore fora) sem gravar nada', function () {
        $p = DrmPreferencia::daEmpresa($this->empresa);

        expect($p->posicao_prolabore)->toBe('fora')
            ->and($p->modos())->toBe(array_fill_keys(['custo', 'vendas', 'administrativas', 'financeiras'], Modo::Detalhado))
            ->and(DrmPreferencia::query()->count())->toBe(0);
    });

    it('gravarModos cria na primeira vez e atualiza depois, uma linha por empresa', function () {
        DrmPreferencia::gravarModos($this->empresa, ['custo' => Modo::Agregado]);
        DrmPreferencia::gravarModos($this->empresa, ['custo' => Modo::Agregado, 'vendas' => Modo::Agregado]);

        $p = DrmPreferencia::daEmpresa($this->empresa);
        expect(DrmPreferencia::query()->count())->toBe(1)
            ->and($p->modo(Grupo::Custo))->toBe(Modo::Agregado)
            ->and($p->modo(Grupo::Vendas))->toBe(Modo::Agregado)
            ->and($p->modo(Grupo::Administrativas))->toBe(Modo::Detalhado);
    });

    it('o banco só aceita modos e posição conhecidos', function () {
        expect(fn () => DB::transaction(fn () => DB::table('drm_preferencias')->insert([
            'id' => (string) Str::uuid7(), 'usuario_id' => $this->usuario->id,
            'empresa_analisada_id' => $this->empresa->id, 'modo_custo' => 'meio-termo',
            'created_at' => now(), 'updated_at' => now(),
        ])))->toThrow(QueryException::class);
    });
});

describe('GET /empresas/{empresa}/drm/{competencia}', function () {
    it('mostra o mês fechado para o dono', function () {
        DrmLancamento::factory()->create(['empresa_analisada_id' => $this->empresa->id]);

        $this->get(route('drm.mes', [$this->empresa, '2026-09']))
            ->assertOk()
            ->assertSee('Setembro/2026');
    });

    it('404 silente: competência sem lançamento', function () {
        $this->get(route('drm.mes', [$this->empresa, '2026-09']))->assertNotFound();
    });

    it('404 silente: lançamento de outro usuário (IDR-009)', function () {
        $outro = Usuario::factory()->create();
        $alheia = EmpresaAnalisada::factory()->create(['usuario_id' => $outro->id]);
        DrmLancamento::factory()->create(['empresa_analisada_id' => $alheia->id]);

        $this->get(route('drm.mes', [$alheia, '2026-09']))->assertNotFound();
    });

    it('competência fora do formato nem casa com a rota', function () {
        $this->get('/empresas/'.$this->empresa->id.'/drm/2026-13')->assertNotFound();
    });
});
